<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\ManpowerBudget;
use App\Models\SuccessionPlan;
use App\Models\Vacancy;
use App\Support\Access;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Headcount budgets against actual staff, and succession plans. Ported from server/src/routes/workplace.ts.
 * Department managers see their own department only; open vacancies are counted separately from the budget gap.
 */
class WorkforceController extends Controller
{
    private const ACTIVE = ['ACTIVE', 'PROBATION', 'NOTICE', 'SUSPENDED'];

    public function index(Request $request)
    {
        $user = $request->user();
        $hr = Access::isHr($user);
        $year = (int) $request->query('year', now()->year);
        $departments = Department::when(! $hr, fn ($q) => $q->where('id', $user->department_id ?? '-'))->orderBy('name')->get();

        $rows = $departments->map(function ($department) use ($year) {
            $staff = Employee::where('department_id', $department->id)->whereIn('status', self::ACTIVE)->get(['basic_salary']);
            $approved = (int) ManpowerBudget::where(['year' => $year, 'department_id' => $department->id])->sum('approved_headcount');

            return [
                'department' => $department,
                'current' => $staff->count(),
                'approved' => $approved,
                'gap' => $approved ? $approved - $staff->count() : null,
                'open' => (int) Vacancy::where('department_id', $department->id)->where('status', 'OPEN')->get()->sum(fn ($v) => max(0, $v->openings - $v->filled)),
                'monthly' => (float) $staff->sum('basic_salary'),
                'budget' => (float) ManpowerBudget::where(['year' => $year, 'department_id' => $department->id])->sum('estimated_cost'),
            ];
        });

        return view('workforce.index', [
            'hr' => $hr,
            'year' => $year,
            'rows' => $rows,
            'budgets' => ManpowerBudget::with('department', 'jobTitle')->where('year', $year)->whereIn('department_id', $departments->pluck('id'))->get(),
            'plans' => SuccessionPlan::with('incumbent', 'successor')->when(! $hr, fn ($q) => $q->where('department_id', $user->department_id ?? '-'))->get(),
            'departments' => Department::orderBy('name')->pluck('name', 'id')->all(),
            'jobTitles' => JobTitle::orderBy('name')->pluck('name', 'id')->all(),
            'people' => Employee::whereIn('status', self::ACTIVE)->orderBy('first_name')->get()->mapWithKeys(fn ($e) => [$e->id => $e->employee_no.' · '.Format::fullName($e)])->all(),
        ]);
    }

    /** One budget line per year, department and (optional) job title; saving again updates it. */
    public function saveBudget(Request $request)
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'between:2020,2100'],
            'department_id' => ['required', 'exists:departments,id'],
            'job_title_id' => ['nullable', 'exists:job_titles,id'],
            'approved_headcount' => ['required', 'integer', 'min:0', 'max:10000'],
            'estimated_cost' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        ManpowerBudget::updateOrCreate(
            ['year' => $data['year'], 'department_id' => $data['department_id'], 'job_title_id' => $data['job_title_id'] ?? null],
            ['approved_headcount' => $data['approved_headcount'], 'estimated_cost' => $data['estimated_cost'], 'notes' => $data['notes'] ?? null],
        );

        return redirect()->route('workforce.index', ['year' => $data['year']])->with('status', 'Budget saved.');
    }

    public function storePlan(Request $request)
    {
        $data = $request->validate([
            'position_title' => ['required', 'string', 'min:2', 'max:191'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'incumbent_id' => ['nullable', 'exists:employees,id'],
            'successor_id' => ['nullable', 'exists:employees,id', 'different:incumbent_id'],
            'readiness' => ['required', Rule::in(['Ready now', '1–2 years', '3+ years'])],
            'critical' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        SuccessionPlan::create([...$data, 'critical' => $request->boolean('critical')]);

        return back()->with('status', 'Succession plan added.');
    }

    public function destroyPlan(SuccessionPlan $plan)
    {
        $plan->delete();

        return back()->with('status', 'Succession plan removed.');
    }
}
