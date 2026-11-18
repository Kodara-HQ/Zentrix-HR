<?php

namespace App\Http\Controllers;

use App\Enums\EventType;
use App\Enums\LetterType;
use App\Enums\Role;
use App\Models\DisciplinaryCase;
use App\Models\Employee;
use App\Models\Grievance;
use App\Models\User;
use App\Models\Warning;
use App\Services\EmployeeService;
use App\Services\Letters;
use App\Services\Notifier;
use App\Support\Audit;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Grievances, disciplinary cases and warnings (HR only). Ported from server/src/routes/workplace.ts. */
class RelationsController extends Controller
{
    public const STATUSES = ['OPEN', 'INVESTIGATING', 'HEARING', 'CLOSED'];

    public function __construct(private Notifier $notifier) {}

    public function index()
    {
        $people = Employee::whereIn('status', ['ACTIVE', 'PROBATION', 'NOTICE', 'SUSPENDED'])->orderBy('first_name')->get();

        return view('relations.index', [
            'grievances' => Grievance::with('employee')->latest()->get(),
            'cases' => DisciplinaryCase::with('employee', 'warnings')->latest()->get(),
            'warnings' => Warning::with('employee', 'case')->orderByDesc('issued_on')->get(),
            'people' => $people->mapWithKeys(fn ($e) => [$e->id => $e->employee_no.' · '.Format::fullName($e)])->all(),
            'names' => Employee::all()->mapWithKeys(fn ($e) => [$e->id => Format::fullName($e)])->all(),
        ]);
    }

    public function storeGrievance(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'against_employee_id' => ['nullable', 'exists:employees,id', 'different:employee_id'],
            'category' => ['required', 'string', 'min:2', 'max:191'],
            'description' => ['required', 'string', 'min:5', 'max:5000'],
        ]);
        $grievance = Grievance::create([...$data, 'status' => 'OPEN', 'confidential' => true]);
        $this->notifier->notify($this->hrIds(), 'Grievance reported', $data['category'], 'GRIEVANCE', '/relations');
        Audit::log('CREATE', 'Grievance', $grievance->id, 'Grievance recorded');

        return back()->with('status', 'Grievance recorded.');
    }

    public function updateGrievance(Request $request, Grievance $grievance)
    {
        $grievance->update($request->validate(['status' => ['required', Rule::in(self::STATUSES)], 'resolution' => ['nullable', 'string', 'max:5000']]));

        return back()->with('status', 'Grievance updated.');
    }

    public function storeCase(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'incident_date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'min:5', 'max:5000'],
            'hearing_at' => ['nullable', 'date', 'after:now'],
            'confidential_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $case = DB::transaction(function () use ($data) {
            // Next case number from the highest one so far, locked so two cases never share a number.
            $last = DisciplinaryCase::lockForUpdate()->pluck('case_no')->map(fn ($n) => (int) substr($n, 3))->max() ?? 0;

            return DisciplinaryCase::create([...$data, 'case_no' => 'DC-'.str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT), 'status' => 'OPEN']);
        });
        Audit::log('CREATE', 'DisciplinaryCase', $case->id, "Opened case {$case->case_no}");

        return back()->with('status', "Case {$case->case_no} opened.");
    }

    public function updateCase(Request $request, DisciplinaryCase $case, EmployeeService $employees)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'hearing_at' => ['nullable', 'date'],
            'outcome' => ['nullable', 'string', 'max:5000'],
            'confidential_notes' => ['nullable', 'string', 'max:5000'],
            'suspend' => ['nullable', 'boolean'],
        ]);
        $case->update(collect($data)->except('suspend')->all());
        if ($request->boolean('suspend') && $case->employee->status->value !== 'SUSPENDED') {
            $employees->applyAction($case->employee, 'SUSPEND', ['effective_date' => today()->toDateString(), 'notes' => "Suspended under case {$case->case_no}"]);
        }
        Audit::log('UPDATE', 'DisciplinaryCase', $case->id, "Updated case {$case->case_no}");

        return back()->with('status', "Case {$case->case_no} updated.");
    }

    public function storeWarning(Request $request, Letters $letters)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'case_id' => ['nullable', Rule::exists('disciplinary_cases', 'id')->where('employee_id', $request->input('employee_id'))],
            'type' => ['required', Rule::in(['VERBAL', 'WRITTEN', 'FINAL'])],
            'issued_on' => ['required', 'date', 'before_or_equal:today'],
            'content' => ['required', 'string', 'min:5', 'max:5000'],
        ], ['case_id.exists' => 'That case belongs to someone else.']);
        $warning = Warning::create([...$data, 'issued_by_id' => $request->user()->id]);
        $employee = Employee::findOrFail($data['employee_id']);
        $letters->issue($employee, LetterType::WARNING, Format::title($data['type']).' warning', $data['issued_on'], [$data['content']]);
        $this->notifier->notify([User::where('employee_id', $employee->id)->value('id')], 'Warning issued', 'A '.strtolower($data['type']).' warning has been placed on your file.', 'DISCIPLINE', "/employees/{$employee->id}?tab=documents");
        Audit::log('CREATE', 'Warning', $warning->id, Format::title($data['type']).' warning for '.Format::fullName($employee));

        return back()->with('status', 'Warning issued. The letter is on the employee’s file as a confidential document.');
    }

    private function hrIds(): array
    {
        return User::where('is_active', true)->whereIn('role', [Role::SUPER_ADMIN, Role::HR_ADMIN, Role::HR_MANAGER])->pluck('id')->all();
    }
}
