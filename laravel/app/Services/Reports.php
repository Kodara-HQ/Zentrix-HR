<?php

namespace App\Services;

use App\Models\Appraisal;
use App\Models\AttendanceRecord;
use App\Models\Contract;
use App\Models\DisciplinaryCase;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\ManpowerBudget;
use App\Models\OvertimeRequest;
use App\Models\Payslip;
use App\Models\Separation;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\Vacancy;
use App\Support\Access;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The report catalogue, ported from buildReport in server/src/routes/insights.ts.
 * Unlike the Node version, every report is limited to the employees the person may see.
 */
class Reports
{
    public const CATALOGUE = [
        'employees' => 'Employee master list', 'headcount' => 'Headcount', 'demographics' => 'Demographics',
        'attendance' => 'Attendance', 'absenteeism' => 'Absenteeism', 'leave' => 'Leave utilisation', 'overtime' => 'Overtime',
        'payroll' => 'Payroll', 'cost' => 'Employee cost', 'turnover' => 'Turnover', 'recruitment' => 'Recruitment',
        'training' => 'Training expenditure', 'performance' => 'Appraisals', 'contracts' => 'Contracts',
        'discipline' => 'Discipline', 'workforce' => 'Workforce plan',
    ];

    private const ACTIVE = ['PROBATION', 'ACTIVE', 'NOTICE', 'SUSPENDED'];

    /** Reports this person may run. */
    public static function available(User $user): array
    {
        return array_filter(self::CATALOGUE, fn ($_, $name) => self::allowed($user, $name), ARRAY_FILTER_USE_BOTH);
    }

    public static function allowed(User $user, string $name): bool
    {
        return match ($name) {
            'payroll', 'cost' => Access::canSeeCompensation($user),
            'discipline' => Access::canSeeDiscipline($user),
            'recruitment', 'training', 'workforce' => Access::hasRole($user, ['HR_ADMIN', 'HR_MANAGER', 'RECRUITMENT_OFFICER', 'DEPARTMENT_MANAGER']),
            default => array_key_exists($name, self::CATALOGUE),
        };
    }

    /** @return array{title: string, columns: array<string, string>, rows: array<int, array<string, mixed>>} */
    public function build(User $user, string $name, array $filters): array
    {
        abort_unless(self::allowed($user, $name), 403, 'This report is restricted.');
        $from = CarbonImmutable::parse($filters['from'] ?? today()->subYear())->startOfDay();
        $to = CarbonImmutable::parse($filters['to'] ?? today())->endOfDay();
        $dept = $filters['department_id'] ?? null;

        // Everyone in reach, narrowed by the department filter.
        $people = fn () => Access::scopeEmployees(Employee::query(), $user)->when($dept, fn ($q) => $q->where('department_id', $dept));
        $ids = fn () => $people()->select('id');
        $d = fn ($v) => $v ? CarbonImmutable::parse($v)->toDateString() : '';
        $n = fn ($v) => $v === null ? '' : (float) $v;

        return match ($name) {
            'employees', 'demographics' => [
                'title' => $name === 'demographics' ? 'Employee demographics' : 'Employee master list',
                'columns' => ['no' => 'Employee no', 'name' => 'Name', 'gender' => 'Gender', 'age' => 'Age', 'department' => 'Department', 'title' => 'Job title', 'location' => 'Location', 'status' => 'Status', 'hired' => 'Hired'],
                'rows' => $people()->with('department', 'jobTitle', 'location')->orderBy('last_name')->get()->map(fn ($e) => [
                    'no' => $e->employee_no, 'name' => Format::fullName($e), 'gender' => Format::title($e->gender), 'age' => $e->date_of_birth->age,
                    'department' => $e->department?->name, 'title' => $e->jobTitle?->name, 'location' => $e->location?->name,
                    'status' => Format::title($e->status), 'hired' => $d($e->date_of_employment),
                ])->all(),
            ],
            'headcount' => [
                'title' => 'Employee headcount',
                'columns' => ['department' => 'Department', 'headcount' => 'Headcount'],
                'rows' => $people()->whereIn('status', self::ACTIVE)->with('department')->get()->groupBy(fn ($e) => $e->department?->name ?? '—')
                    ->map(fn ($rows, $department) => ['department' => $department, 'headcount' => $rows->count()])->sortBy('department')->values()->all(),
            ],
            'attendance', 'absenteeism' => [
                'title' => $name === 'absenteeism' ? 'Absenteeism report' : 'Attendance report',
                'columns' => ['date' => 'Date', 'employee' => 'Employee', 'department' => 'Department', 'status' => 'Status', 'in' => 'Clock in', 'out' => 'Clock out', 'late' => 'Late minutes'],
                'rows' => AttendanceRecord::with('employee.department')->whereIn('employee_id', $ids())
                    ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                    ->when($name === 'absenteeism', fn ($q) => $q->where('status', 'ABSENT'))
                    ->orderByDesc('date')->limit(5000)->get()->map(fn ($r) => [
                        'date' => $d($r->date), 'employee' => Format::fullName($r->employee), 'department' => $r->employee?->department?->name,
                        'status' => Format::title($r->status), 'in' => Format::time($r->clock_in), 'out' => Format::time($r->clock_out), 'late' => $r->late_minutes,
                    ])->all(),
            ],
            'leave' => [
                'title' => 'Leave utilisation',
                'columns' => ['employee' => 'Employee', 'type' => 'Type', 'start' => 'Start', 'end' => 'End', 'days' => 'Days', 'status' => 'Status'],
                'rows' => LeaveRequest::with('employee', 'leaveType')->whereIn('employee_id', $ids())
                    ->where('start_date', '<=', $to->toDateString())->where('end_date', '>=', $from->toDateString())->orderByDesc('start_date')->get()
                    ->map(fn ($r) => ['employee' => Format::fullName($r->employee), 'type' => $r->leaveType?->name, 'start' => $d($r->start_date), 'end' => $d($r->end_date), 'days' => $n($r->days), 'status' => Format::title($r->status)])->all(),
            ],
            'overtime' => [
                'title' => 'Overtime report',
                'columns' => ['employee' => 'Employee', 'date' => 'Date', 'hours' => 'Hours', 'multiplier' => 'Rate', 'status' => 'Status'],
                'rows' => OvertimeRequest::with('employee')->whereIn('employee_id', $ids())->whereBetween('date', [$from->toDateString(), $to->toDateString()])->orderByDesc('date')->get()
                    ->map(fn ($r) => ['employee' => Format::fullName($r->employee), 'date' => $d($r->date), 'hours' => $n($r->hours), 'multiplier' => $n($r->multiplier), 'status' => Format::title($r->status)])->all(),
            ],
            'payroll', 'cost' => [
                'title' => $name === 'cost' ? 'Employee cost analysis' : 'Payroll report',
                'columns' => ['period' => 'Period', 'employee' => 'Employee', 'department' => 'Department', 'gross' => 'Gross', 'paye' => 'PAYE', 'net' => 'Net', 'employer' => 'Employer SSNIT', 'cost' => 'Cost to company'],
                'rows' => Payslip::with('employee.department', 'payrollRun')->whereIn('employee_id', $ids())
                    ->whereHas('payrollRun', fn (Builder $q) => $q->where('status', 'APPROVED')
                        ->whereRaw('(year * 100 + month) between ? and ?', [$from->year * 100 + $from->month, $to->year * 100 + $to->month]))
                    ->get()->sortByDesc(fn ($s) => $s->payrollRun->year * 100 + $s->payrollRun->month)->values()
                    ->map(fn ($s) => [
                        'period' => sprintf('%d-%02d', $s->payrollRun->year, $s->payrollRun->month), 'employee' => Format::fullName($s->employee), 'department' => $s->employee?->department?->name,
                        'gross' => $n($s->gross_pay), 'paye' => $n($s->paye), 'net' => $n($s->net_pay), 'employer' => $n($s->employer_ssnit), 'cost' => round((float) $s->gross_pay + (float) $s->employer_ssnit, 2),
                    ])->all(),
            ],
            'turnover' => [
                'title' => 'Employee turnover',
                'columns' => ['employee' => 'Employee', 'department' => 'Department', 'type' => 'Type', 'last' => 'Last day', 'status' => 'Status'],
                'rows' => Separation::with('employee.department')->whereIn('employee_id', $ids())->whereBetween('last_working_day', [$from->toDateString(), $to->toDateString()])->orderByDesc('last_working_day')->get()
                    ->map(fn ($s) => ['employee' => Format::fullName($s->employee), 'department' => $s->employee?->department?->name, 'type' => Format::title($s->type), 'last' => $d($s->last_working_day), 'status' => Format::title($s->status)])->all(),
            ],
            'recruitment' => [
                'title' => 'Recruitment report',
                'columns' => ['title' => 'Vacancy', 'department' => 'Department', 'status' => 'Status', 'applicants' => 'Applicants', 'filled' => 'Filled', 'cost' => 'Cost'],
                'rows' => Vacancy::with('department')->withCount('applicants')->when($dept, fn ($q) => $q->where('department_id', $dept))->whereBetween('created_at', [$from, $to])->latest()->get()
                    ->map(fn ($v) => ['title' => $v->title, 'department' => $v->department?->name, 'status' => Format::title($v->status), 'applicants' => $v->applicants_count, 'filled' => $v->filled.'/'.$v->openings, 'cost' => round((float) $v->advertising_cost + (float) $v->agency_cost, 2)])->all(),
            ],
            'training' => [
                'title' => 'Training expenditure',
                'columns' => ['title' => 'Programme', 'provider' => 'Provider', 'dates' => 'Dates', 'cost' => 'Cost', 'enrolled' => 'Enrolled', 'status' => 'Status'],
                'rows' => TrainingProgram::with('provider')->withCount('enrollments')->where('start_date', '<=', $to->toDateString())->where('end_date', '>=', $from->toDateString())->orderByDesc('start_date')->get()
                    ->map(fn ($p) => ['title' => $p->title, 'provider' => $p->provider?->name, 'dates' => $d($p->start_date).' – '.$d($p->end_date), 'cost' => $n($p->cost), 'enrolled' => $p->enrollments_count, 'status' => Format::title($p->status)])->all(),
            ],
            'performance' => [
                'title' => 'Performance appraisals',
                'columns' => ['employee' => 'Employee', 'cycle' => 'Cycle', 'self' => 'Self', 'manager' => 'Manager', 'final' => 'Final', 'recommendation' => 'Recommendation', 'status' => 'Status'],
                'rows' => Appraisal::with('employee', 'cycle')->whereIn('employee_id', $ids())->latest()->get()
                    ->map(fn ($a) => ['employee' => Format::fullName($a->employee), 'cycle' => $a->cycle?->name, 'self' => $n($a->self_rating), 'manager' => $n($a->supervisor_rating), 'final' => $n($a->final_rating), 'recommendation' => $a->recommendation, 'status' => Format::title($a->status)])->all(),
            ],
            'contracts' => [
                'title' => 'Contract expiration',
                'columns' => ['employee' => 'Employee', 'title' => 'Contract', 'start' => 'Start', 'end' => 'End', 'status' => 'Status'],
                'rows' => Contract::with('employee')->whereIn('employee_id', $ids())->orderByRaw('end_date is null')->orderBy('end_date')->get()
                    ->map(fn ($c) => ['employee' => Format::fullName($c->employee), 'title' => $c->title, 'start' => $d($c->start_date), 'end' => $d($c->end_date), 'status' => Format::title($c->status)])->all(),
            ],
            'discipline' => [
                'title' => 'Disciplinary report',
                'columns' => ['case' => 'Case', 'employee' => 'Employee', 'date' => 'Incident', 'status' => 'Status', 'outcome' => 'Outcome'],
                'rows' => DisciplinaryCase::with('employee')->whereIn('employee_id', $ids())->whereBetween('incident_date', [$from->toDateString(), $to->toDateString()])->orderByDesc('incident_date')->get()
                    ->map(fn ($c) => ['case' => $c->case_no, 'employee' => Format::fullName($c->employee), 'date' => $d($c->incident_date), 'status' => Format::title($c->status), 'outcome' => $c->outcome])->all(),
            ],
            'workforce' => [
                'title' => 'Workforce plan '.$to->year,
                'columns' => ['department' => 'Department', 'approved' => 'Approved', 'actual' => 'Actual', 'gap' => 'Gap', 'cost' => 'Estimated annual cost'],
                'rows' => ManpowerBudget::with('department')->where('year', $to->year)->when($dept, fn ($q) => $q->where('department_id', $dept))->get()
                    ->groupBy('department_id')->map(function ($budgets) {
                        $actual = Employee::where('department_id', $budgets->first()->department_id)->whereIn('status', self::ACTIVE)->count();
                        $approved = $budgets->sum('approved_headcount');

                        return ['department' => $budgets->first()->department?->name, 'approved' => $approved, 'actual' => $actual, 'gap' => $approved - $actual, 'cost' => round($budgets->sum(fn ($b) => (float) $b->estimated_cost), 2)];
                    })->sortBy('department')->values()->all(),
            ],
            default => abort(404),
        };
    }
}
