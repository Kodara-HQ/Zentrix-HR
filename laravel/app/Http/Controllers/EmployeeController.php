<?php

namespace App\Http\Controllers;

use App\Enums\EventType;
use App\Models\Employee;
use App\Models\EmployeeEducation;
use App\Models\EmployeePayItem;
use App\Models\EmployeeSkill;
use App\Models\FileAsset;
use App\Models\Payslip;
use App\Models\User;
use App\Services\EmployeeService;
use App\Support\Access;
use App\Support\Audit;
use App\Support\Format;
use App\Support\Lookups;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Employee records. Ported from server/src/routes/employees.ts and the Employees / EmployeeProfile pages. */
class EmployeeController extends Controller
{
    /** Contact details an employee may change on their own record. */
    private const SELF_FIELDS = ['phone', 'alt_phone', 'address', 'city', 'region', 'emergency_name', 'emergency_phone', 'emergency_relation', 'next_of_kin_name', 'next_of_kin_phone', 'next_of_kin_relation'];

    public function __construct(private EmployeeService $service) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $search = trim((string) $request->query('search'));
        $sort = in_array($request->query('sort'), ['employee_no', 'date_of_employment', 'basic_salary'], true) ? $request->query('sort') : 'last_name';
        $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';

        $employees = Access::scopeEmployees(Employee::query(), $user)
            ->with('department', 'jobTitle', 'location')
            ->when($request->query('department_id'), fn ($q, $id) => $q->where('department_id', $id))
            ->when($request->query('location_id'), fn ($q, $id) => $q->where('location_id', $id))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('employee_no', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy($sort, $dir)
            ->paginate(12)
            ->withQueryString();

        return view('employees.index', [
            'employees' => $employees,
            'writer' => Access::hasRole($user, ['HR_ADMIN', 'HR_MANAGER']),
            'departments' => Lookups::departments(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, creating: true);
        $createLogin = $request->boolean('create_login');
        unset($data['create_login']);

        [$employee, $password] = $this->service->hire($data, $createLogin);

        return redirect()->route('employees.show', $employee)->with('status', $password
            ? "Record created. Temporary password: {$password} — it is shown only once."
            : 'Record created.');
    }

    public function show(Request $request, Employee $employee)
    {
        $user = $request->user();
        Access::assertEmployeeVisible($user, $employee);

        $employee->load([
            'department', 'jobTitle', 'employmentType', 'location', 'manager',
            'education', 'qualifications', 'skills', 'experience', 'certifications',
            'events' => fn ($q) => $q->orderByDesc('effective_date')->orderByDesc('created_at'),
            'contracts' => fn ($q) => $q->orderByDesc('start_date'),
            'letters' => fn ($q) => $q->orderByDesc('issued_on'),
            'leaveBalances' => fn ($q) => $q->where('year', now()->year)->with('leaveType'),
        ]);
        $compensation = Access::canSeeCompensation($user, $employee->id);
        if ($compensation) {
            $employee->load('bankAccounts', 'payItems.component');
        }

        $writer = Access::hasRole($user, ['HR_ADMIN', 'HR_MANAGER']);
        $files = FileAsset::where('owner_type', 'EMPLOYEE')->where('owner_id', $employee->id)
            ->where('id', '!=', $employee->photo_file_id ?? '')
            ->whereNotIn('id', $employee->letters->pluck('file_asset_id')->filter())
            ->latest()->get()
            ->filter(fn ($file) => ! $file->confidential || Access::canSeeDiscipline($user) || $user->employee_id === $employee->id);

        return view('employees.show', [
            'employee' => $employee,
            'compensation' => $compensation,
            'writer' => $writer,
            'payroll' => Access::hasRole($user, ['HR_ADMIN', 'HR_MANAGER', 'PAYROLL_OFFICER']),
            'own' => $user->employee_id === $employee->id,
            'files' => $files,
            'tab' => $request->query('tab', 'overview'),
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        $user = $request->user();
        Access::assertEmployeeVisible($user, $employee);
        $hr = Access::isHr($user);
        abort_unless($hr || $user->employee_id === $employee->id, 403, 'You cannot edit this record.');

        $data = $this->validated($request, creating: false, employee: $employee);
        if (! $hr) {
            $data = array_intersect_key($data, array_flip(self::SELF_FIELDS));
        }
        unset($data['contract_end_date'], $data['create_login']);
        if (isset($data['email'])) {
            $data['email'] = strtolower($data['email']);
        }
        $employee->update($data);
        Audit::log('UPDATE', 'Employee', $employee->id, 'Updated '.Format::fullName($employee));

        return redirect()->route('employees.show', $employee)->with('status', 'Details saved.');
    }

    public function destroy(Request $request, Employee $employee)
    {
        // Employees with payroll history are never removed, only deactivated (as in the Node version).
        if ($request->query('mode') === 'hard' && ! Payslip::where('employee_id', $employee->id)->exists()) {
            $name = Format::fullName($employee);
            $employee->delete();
            Audit::log('DELETE', 'Employee', $employee->id, "Deleted {$name}");

            return redirect()->route('employees.index')->with('status', "{$name} was deleted.");
        }

        $this->service->applyAction($employee, 'DEACTIVATE', ['effective_date' => today()->toDateString()]);

        return redirect()->route('employees.show', $employee)->with('status', 'Employee deactivated.');
    }

    public function action(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['PROMOTE', 'TRANSFER', 'SUSPEND', 'REINSTATE', 'DEACTIVATE'])],
            'job_title_id' => ['nullable', 'required_if:type,PROMOTE', 'exists:job_titles,id'],
            'basic_salary' => ['nullable', 'required_if:type,PROMOTE', 'numeric', 'min:0'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'location_id' => ['nullable', 'exists:work_locations,id'],
            'manager_id' => ['nullable', 'exists:employees,id'],
            'effective_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['job_title_id.required_if' => 'A promotion needs a job title and salary.', 'basic_salary.required_if' => 'A promotion needs a job title and salary.']);
        $data['effective_date'] ??= today()->toDateString();

        $this->service->applyAction($employee, $data['type'], $data);

        return redirect()->route('employees.show', ['employee' => $employee, 'tab' => 'history'])->with('status', Format::title($data['type']).' recorded.');
    }

    public function addEducation(Request $request, Employee $employee)
    {
        $this->assertCanEditRecord($request->user(), $employee);
        $data = $request->validate([
            'institution' => ['required', 'string', 'min:2', 'max:191'],
            'qualification' => ['required', 'string', 'min:2', 'max:191'],
            'field' => ['nullable', 'string', 'max:191'],
            'end_year' => ['nullable', 'integer', 'between:1950,2100'],
        ]);
        $employee->education()->create($data);

        return redirect()->route('employees.show', ['employee' => $employee, 'tab' => 'qualifications'])->with('status', 'Education added.');
    }

    public function addSkill(Request $request, Employee $employee)
    {
        $this->assertCanEditRecord($request->user(), $employee);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:191', Rule::unique('employee_skills')->where('employee_id', $employee->id)],
            'level' => ['required', 'string', 'min:2', 'max:191'],
        ], ['name.unique' => 'That skill is already on the record.']);
        $employee->skills()->create($data);

        return redirect()->route('employees.show', ['employee' => $employee, 'tab' => 'qualifications'])->with('status', 'Skill added.');
    }

    public function removeEducation(Employee $employee, EmployeeEducation $item)
    {
        abort_unless($item->employee_id === $employee->id, 404);
        $item->delete();

        return redirect()->route('employees.show', ['employee' => $employee, 'tab' => 'qualifications']);
    }

    public function removeSkill(Employee $employee, EmployeeSkill $item)
    {
        abort_unless($item->employee_id === $employee->id, 404);
        $item->delete();

        return redirect()->route('employees.show', ['employee' => $employee, 'tab' => 'qualifications']);
    }

    public function savePayItem(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'component_id' => ['required', 'exists:payroll_components,id'],
            'amount' => ['required', 'numeric'],
        ]);
        EmployeePayItem::updateOrCreate(['employee_id' => $employee->id, 'component_id' => $data['component_id']], ['amount' => $data['amount']]);

        return redirect()->route('employees.show', ['employee' => $employee, 'tab' => 'compensation'])->with('status', 'Pay item saved.');
    }

    public function removePayItem(Employee $employee, EmployeePayItem $item)
    {
        abort_unless($item->employee_id === $employee->id, 404);
        $item->delete();

        return redirect()->route('employees.show', ['employee' => $employee, 'tab' => 'compensation']);
    }

    /** HR, or the employee on their own record (education and skills). */
    private function assertCanEditRecord(User $user, Employee $employee): void
    {
        abort_unless(Access::isHr($user) || $user->employee_id === $employee->id, 403, 'You cannot edit this record.');
    }

    private function validated(Request $request, bool $creating, ?Employee $employee = null): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'first_name' => [$required, 'string', 'min:2', 'max:191'],
            'middle_name' => ['nullable', 'string', 'max:191'],
            'last_name' => [$required, 'string', 'min:2', 'max:191'],
            'gender' => [$required, Rule::in(Lookups::GENDERS)],
            'date_of_birth' => [$required, 'date', 'before:today'],
            'email' => [$required, 'email', 'max:191', Rule::unique('employees', 'email')->ignore($employee?->id)],
            'phone' => [$required, 'string', 'min:7', 'max:40'],
            'alt_phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:191'],
            'region' => ['nullable', 'string', 'max:191'],
            'emergency_name' => ['nullable', 'string', 'max:191'],
            'emergency_phone' => ['nullable', 'string', 'max:40'],
            'emergency_relation' => ['nullable', 'string', 'max:191'],
            'next_of_kin_name' => ['nullable', 'string', 'max:191'],
            'next_of_kin_phone' => ['nullable', 'string', 'max:40'],
            'next_of_kin_relation' => ['nullable', 'string', 'max:191'],
            'department_id' => [$required, 'exists:departments,id'],
            'job_title_id' => [$required, 'exists:job_titles,id'],
            'employment_type_id' => [$required, 'exists:employment_types,id'],
            'location_id' => [$required, 'exists:work_locations,id'],
            'manager_id' => ['nullable', 'exists:employees,id', Rule::notIn(array_filter([$employee?->id]))],
            'date_of_employment' => [$required, 'date'],
            'probation_end_date' => ['nullable', 'date', 'after_or_equal:date_of_employment'],
            'basic_salary' => [$required, 'numeric', 'min:0'],
            'ssnit_number' => ['nullable', 'string', 'max:40'],
            'tin_number' => ['nullable', 'string', 'max:40'],
            'contract_end_date' => ['nullable', 'date', 'after:date_of_employment'],
            'create_login' => ['nullable', 'boolean'],
        ], [
            'department_id.exists' => 'That department is no longer in System settings. Choose it again, then save.',
            'job_title_id.exists' => 'That job title is no longer in System settings. Choose it again, then save.',
            'employment_type_id.exists' => 'That employment type is no longer in System settings. Choose it again, then save.',
            'location_id.exists' => 'That work location is no longer in System settings. Choose it again, then save.',
            'manager_id.exists' => 'That reporting manager is no longer on file. Clear the field and save again.',
            'email.unique' => 'Another employee already uses that email.',
        ]);
    }
}
