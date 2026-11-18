<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Certification;
use App\Models\Employee;
use App\Models\TrainingEnrollment;
use App\Models\TrainingNeed;
use App\Models\TrainingProgram;
use App\Models\TrainingProvider;
use App\Models\User;
use App\Services\Notifier;
use App\Support\Access;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Programmes, enrolments, training needs and certificates. Ported from the training part of server/src/routes/talent.ts. */
class TrainingController extends Controller
{
    public const MANAGERS = ['HR_ADMIN', 'HR_MANAGER'];

    public function __construct(private Notifier $notifier) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $visible = Access::scopeEmployees(Employee::query(), $user)->select('id');
        $people = Access::scopeEmployees(Employee::query(), $user)->whereIn('status', ['ACTIVE', 'PROBATION', 'NOTICE'])->orderBy('first_name')->get();

        return view('training.index', [
            'hr' => Access::hasRole($user, self::MANAGERS),
            'enroller' => Access::hasRole($user, [...self::MANAGERS, 'DEPARTMENT_MANAGER']),
            'programs' => TrainingProgram::with(['provider', 'enrollments' => fn ($q) => $q->whereIn('employee_id', $visible)->with('employee')])->orderByDesc('start_date')->get(),
            'needs' => TrainingNeed::whereIn('employee_id', $visible)->latest()->get(),
            'certs' => Certification::with('employee')->whereIn('employee_id', $visible)->orderByRaw('expires_on is null')->orderBy('expires_on')->get(),
            'people' => $people->mapWithKeys(fn ($e) => [$e->id => $e->employee_no.' · '.Format::fullName($e)])->all(),
            'names' => Employee::whereIn('id', $visible)->get()->mapWithKeys(fn ($e) => [$e->id => Format::fullName($e)])->all(),
            'providers' => TrainingProvider::orderBy('name')->pluck('name', 'id')->all(),
            'spend' => TrainingProgram::whereNot('status', 'CANCELLED')->sum('cost'),
            'user' => $user,
        ]);
    }

    public function storeProgram(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:191'],
            'provider_name' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:3000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:191'],
            'capacity' => ['nullable', 'integer', 'min:1'],
        ]);
        $provider = ! empty($data['provider_name']) ? TrainingProvider::firstOrCreate(['name' => trim($data['provider_name'])]) : null;
        unset($data['provider_name']);
        TrainingProgram::create([...$data, 'provider_id' => $provider?->id, 'cost' => $data['cost'] ?? 0, 'status' => 'PLANNED']);

        return back()->with('status', 'Programme added.');
    }

    public function updateProgram(Request $request, TrainingProgram $program)
    {
        $program->update($request->validate(['status' => ['required', Rule::in(['PLANNED', 'ONGOING', 'COMPLETED', 'CANCELLED'])]]));

        return back()->with('status', "{$program->title} updated.");
    }

    public function enrol(Request $request, TrainingProgram $program)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id', Rule::unique('training_enrollments')->where('program_id', $program->id)],
        ], ['employee_id.unique' => 'That employee is already enrolled.']);
        $employee = Employee::findOrFail($data['employee_id']);
        Access::assertEmployeeVisible($request->user(), $employee);
        abort_if(in_array($program->status->value, ['COMPLETED', 'CANCELLED'], true), 422, 'This programme is closed.');
        abort_if($program->capacity && $program->enrollments()->count() >= $program->capacity, 422, 'This programme is full.');

        $program->enrollments()->create(['employee_id' => $employee->id, 'status' => 'ENROLLED']);
        $this->notifier->notify([User::where('employee_id', $employee->id)->value('id')], 'Training enrolment', "You are enrolled on {$program->title}, starting ".Format::date($program->start_date).'.', 'TRAINING', '/training');

        return back()->with('status', Format::fullName($employee).' enrolled.');
    }

    public function updateEnrollment(Request $request, TrainingEnrollment $enrollment)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['ENROLLED', 'ATTENDED', 'ABSENT', 'COMPLETED'])],
            'score' => ['nullable', 'numeric', 'between:0,100'],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ]);
        $enrollment->update([...$data, 'attended' => in_array($data['status'], ['ATTENDED', 'COMPLETED'], true)]);

        return back()->with('status', 'Attendance recorded.');
    }

    public function storeNeed(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'employee_id' => ['nullable', 'exists:employees,id'],
            'skill' => ['required', 'string', 'min:2', 'max:191'],
            'priority' => ['required', Rule::in(['Low', 'Medium', 'High'])],
            'justification' => ['nullable', 'string', 'max:2000'],
        ]);
        $employeeId = $user->role === Role::EMPLOYEE ? $user->employee_id : ($data['employee_id'] ?? $user->employee_id);
        abort_unless($employeeId, 422, 'Choose an employee.');
        Access::assertEmployeeVisible($user, Employee::findOrFail($employeeId));
        TrainingNeed::create([...$data, 'employee_id' => $employeeId, 'status' => 'OPEN']);

        return back()->with('status', 'Training need recorded.');
    }

    public function updateNeed(Request $request, TrainingNeed $need)
    {
        $need->update($request->validate(['status' => ['required', Rule::in(['OPEN', 'PLANNED', 'MET', 'DECLINED'])]]));

        return back()->with('status', 'Training need updated.');
    }

    public function storeCertificate(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'employee_id' => ['nullable', 'exists:employees,id'],
            'name' => ['required', 'string', 'min:2', 'max:191'],
            'issuer' => ['required', 'string', 'min:2', 'max:191'],
            'credential_no' => ['nullable', 'string', 'max:191'],
            'issued_on' => ['nullable', 'date', 'before_or_equal:today'],
            'expires_on' => ['nullable', 'date', 'after:issued_on'],
        ]);
        $employeeId = Access::isHr($user) ? ($data['employee_id'] ?? $user->employee_id) : $user->employee_id;
        abort_unless($employeeId, 422, 'Choose an employee.');
        Certification::create([...$data, 'employee_id' => $employeeId]);

        return back()->with('status', 'Certificate added. A reminder goes out 30 days before it expires.');
    }

    public function destroyCertificate(Certification $certification)
    {
        $certification->delete();

        return back()->with('status', 'Certificate removed.');
    }
}
