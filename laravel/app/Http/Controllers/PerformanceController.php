<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Appraisal;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ImprovementPlan;
use App\Models\Objective;
use App\Models\PerformanceCycle;
use App\Models\User;
use App\Services\Notifier;
use App\Support\Access;
use App\Support\Audit;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Cycles, objectives, appraisals and improvement plans. Ported from the performance part of server/src/routes/talent.ts.
 * Review flow: opened (DRAFT) → employee self-assesses (SELF_SUBMITTED) → manager rates (REVIEWED) → employee acknowledges.
 */
class PerformanceController extends Controller
{
    public const REVIEWERS = ['HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER', 'SUPERVISOR'];

    public function __construct(private Notifier $notifier) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $visible = Access::scopeEmployees(Employee::query(), $user)->select('id');
        $reviewer = Access::hasRole($user, self::REVIEWERS);

        return view('performance.index', [
            'reviewer' => $reviewer,
            'hr' => Access::isHr($user),
            'own' => $user->role === Role::EMPLOYEE,
            'cycles' => PerformanceCycle::withCount('appraisals', 'objectives')->orderByDesc('start_date')->get(),
            'objectives' => Objective::with('employee', 'department', 'cycle')
                ->where(fn ($q) => $q->whereIn('employee_id', $visible)
                    ->orWhere(fn ($d) => $d->where('level', 'DEPARTMENT')->when(! Access::isHr($user), fn ($x) => $x->where('department_id', $user->department_id))))
                ->latest()->get(),
            'appraisals' => Appraisal::with('employee', 'cycle', 'plans')->whereIn('employee_id', $visible)->latest('updated_at')->get(),
            'people' => $reviewer ? Access::scopeEmployees(Employee::query(), $user)->whereIn('status', ['ACTIVE', 'PROBATION', 'NOTICE'])->orderBy('first_name')->get()
                ->reject(fn ($e) => $e->id === $user->employee_id)->mapWithKeys(fn ($e) => [$e->id => $e->employee_no.' · '.Format::fullName($e)])->all() : [],
            'departments' => Department::orderBy('name')->pluck('name', 'id')->all(),
            'user' => $user,
        ]);
    }

    public function storeCycle(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:191'],
            'period_type' => ['required', Rule::in(['QUARTERLY', 'HALF_YEARLY', 'ANNUAL'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'status' => ['required', Rule::in(['DRAFT', 'OPEN', 'CLOSED'])],
        ]);
        PerformanceCycle::create($data);

        return back()->with('status', 'Cycle created.');
    }

    public function updateCycle(Request $request, PerformanceCycle $cycle)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['DRAFT', 'OPEN', 'CLOSED'])]]);
        $cycle->update($data);

        return back()->with('status', "{$cycle->name} is now ".strtolower($data['status']).'.');
    }

    public function storeObjective(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'cycle_id' => ['required', 'exists:performance_cycles,id'],
            'level' => ['required', Rule::in(['INDIVIDUAL', 'DEPARTMENT'])],
            'employee_id' => ['nullable', 'required_if:level,INDIVIDUAL', 'exists:employees,id'],
            'department_id' => ['nullable', 'required_if:level,DEPARTMENT', 'exists:departments,id'],
            'title' => ['required', 'string', 'min:2', 'max:191'],
            'kpi' => ['required', 'string', 'min:2', 'max:500'],
            'target' => ['required', 'string', 'max:500'],
            'weight' => ['required', 'numeric', 'gt:0', 'max:100'],
        ]);
        if ($data['level'] === 'INDIVIDUAL') {
            $this->assertCanReview($user, Employee::findOrFail($data['employee_id']));
            $data['department_id'] = null;
        } else {
            abort_unless(Access::isHr($user) || $user->department_id === $data['department_id'], 403, 'You can only set objectives for your own department.');
            $data['employee_id'] = null;
        }
        Objective::create($data);

        return back()->with('status', 'Objective added.');
    }

    public function scoreObjective(Request $request, Objective $objective)
    {
        $data = $request->validate(['actual' => ['nullable', 'string', 'max:500'], 'score' => ['nullable', 'numeric', 'between:0,100']]);
        if ($objective->employee_id) {
            $this->assertCanReview($request->user(), Employee::findOrFail($objective->employee_id));
        }
        $objective->update($data);

        return back()->with('status', 'Result recorded.');
    }

    public function storeAppraisal(Request $request)
    {
        $data = $request->validate([
            'cycle_id' => ['required', Rule::exists('performance_cycles', 'id')->whereNot('status', 'CLOSED')],
            'employee_id' => ['required', 'exists:employees,id', Rule::unique('appraisals')->where('cycle_id', $request->input('cycle_id'))],
        ], ['employee_id.unique' => 'This employee already has a review in that cycle.', 'cycle_id.exists' => 'Choose a cycle that is not closed.']);
        $employee = Employee::findOrFail($data['employee_id']);
        $this->assertCanReview($request->user(), $employee);

        $appraisal = Appraisal::create([...$data, 'reviewer_id' => $request->user()->id, 'status' => 'DRAFT']);
        $this->notifier->notify([User::where('employee_id', $employee->id)->value('id')], 'Performance review opened', 'Complete your self-assessment.', 'APPRAISAL', '/performance');
        Audit::log('CREATE', 'Appraisal', $appraisal->id, 'Opened review for '.Format::fullName($employee));

        return back()->with('status', 'Review opened. '.Format::fullName($employee).' has been asked for a self-assessment.');
    }

    /** The employee's own step: self-assessment, then acknowledging the manager's rating. */
    public function selfAssess(Request $request, Appraisal $appraisal)
    {
        abort_unless($request->user()->employee_id === $appraisal->employee_id, 403, 'This is not your review.');

        if ($request->input('step') === 'acknowledge') {
            abort_unless($appraisal->status->value === 'REVIEWED', 422, 'Your manager has not rated this review yet.');
            $appraisal->update(['status' => 'ACKNOWLEDGED']);

            return back()->with('status', 'Review acknowledged.');
        }

        abort_unless($appraisal->status->value === 'DRAFT', 422, 'Your self-assessment has already been submitted.');
        $data = $request->validate(['self_rating' => ['required', 'numeric', 'between:1,5'], 'self_comments' => ['required', 'string', 'min:5', 'max:3000']]);
        $appraisal->update([...$data, 'status' => 'SELF_SUBMITTED']);
        $this->notifier->notify([$appraisal->reviewer_id], 'Self-assessment submitted', Format::fullName($appraisal->employee).' submitted their self-assessment.', 'APPRAISAL', '/performance');

        return back()->with('status', 'Self-assessment submitted.');
    }

    public function review(Request $request, Appraisal $appraisal)
    {
        $this->assertCanReview($request->user(), $appraisal->employee);
        abort_if(in_array($appraisal->status->value, ['REVIEWED', 'ACKNOWLEDGED'], true), 422, 'This review is already complete.');
        $data = $request->validate([
            'supervisor_rating' => ['required', 'numeric', 'between:1,5'],
            'final_rating' => ['required', 'numeric', 'between:1,5'],
            'supervisor_comments' => ['required', 'string', 'min:5', 'max:3000'],
            'recommendation' => ['required', Rule::in(['Retain', 'Promote', 'Improvement plan', 'Confirm appointment', 'Extend probation'])],
        ]);
        $appraisal->update([...$data, 'reviewer_id' => $request->user()->id, 'status' => 'REVIEWED']);
        $this->notifier->notify([User::where('employee_id', $appraisal->employee_id)->value('id')], 'Performance review completed', 'Your manager has rated your review. Please read and acknowledge it.', 'APPRAISAL', '/performance');
        Audit::log('UPDATE', 'Appraisal', $appraisal->id, 'Rated review for '.Format::fullName($appraisal->employee));

        return back()->with('status', 'Review saved.');
    }

    public function storePlan(Request $request, Appraisal $appraisal)
    {
        $this->assertCanReview($request->user(), $appraisal->employee);
        $data = $request->validate([
            'goals' => ['required', 'string', 'min:5', 'max:3000'],
            'support' => ['nullable', 'string', 'max:3000'],
            'review_date' => ['nullable', 'date', 'after:today'],
        ]);
        ImprovementPlan::create([...$data, 'appraisal_id' => $appraisal->id, 'employee_id' => $appraisal->employee_id, 'status' => 'OPEN']);

        return back()->with('status', 'Improvement plan added.');
    }

    private function assertCanReview(User $user, Employee $employee): void
    {
        abort_unless(Access::hasRole($user, self::REVIEWERS) && $user->employee_id !== $employee->id && (Access::isHr($user) || Access::canApproveAsManager($user, $employee)),
            403, 'You can only manage reviews for your own team.');
    }
}
