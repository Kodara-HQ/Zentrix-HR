<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\ManpowerRequisition;
use App\Models\OvertimeRequest;
use App\Models\PayrollRun;
use App\Models\Separation;
use App\Models\WelfareRequest;
use App\Services\LeaveService;
use App\Support\Access;
use App\Support\Format;
use App\Support\WorkCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** Dashboard for HR and managers, home page for ESS. Ported from GET /dashboard in server/src/routes/insights.ts. */
class DashboardController extends Controller
{
    private const ACTIVE = ['PROBATION', 'ACTIVE', 'NOTICE', 'SUSPENDED'];

    private const EXITED = ['INACTIVE', 'RESIGNED', 'RETIRED', 'TERMINATED'];

    private const PRESENT = ['PRESENT', 'LATE', 'REMOTE'];

    public function __invoke(Request $request, LeaveService $leave)
    {
        $user = $request->user();
        if ($user->role === Role::EMPLOYEE) {
            return view('dashboard.ess', [
                'user' => $user,
                'employee' => $user->employee_id ? Employee::with('department', 'jobTitle', 'location')->find($user->employee_id) : null,
            ]);
        }

        $today = CarbonImmutable::today();
        $from = rescue(fn () => $request->query('from') ? CarbonImmutable::parse($request->query('from')) : null, null, false) ?? $today->subYear();
        $to = rescue(fn () => $request->query('to') ? CarbonImmutable::parse($request->query('to')) : null, null, false) ?? $today;
        $departmentId = $request->query('department_id');

        $employees = Access::scopeEmployees(Employee::query(), $user)
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->with('department')
            ->get();
        $ids = $employees->pluck('id');
        $active = $employees->filter(fn ($e) => in_array($e->status->value, self::ACTIVE, true));

        $exits = Separation::whereIn('employee_id', $ids)->whereIn('status', ['APPROVED', 'COMPLETED'])
            ->whereBetween('last_working_day', [$from->toDateString(), $to->toDateString()])->count();
        $averageHeadcount = max(1, ($active->count() + $exits) / 2);

        // Attendance over the last 30 days against the working days each active employee was expected in.
        $windowStart = $today->subDays(30);
        $expected = WorkCalendar::countDays($windowStart, $today->subDay(), 'WORKING_DAYS') * $active->count();
        $attendance = AttendanceRecord::whereIn('employee_id', $ids)
            ->where('date', '>=', $windowStart->toDateString())->where('date', '<', $today->toDateString())
            ->get(['date', 'status']);
        $isPresent = fn ($r) => in_array($r->status->value, self::PRESENT, true);
        $present = $attendance->filter($isPresent)->count();
        $absent = $attendance->filter(fn ($r) => $r->status->value === 'ABSENT')->count();
        $trend = $attendance->groupBy(fn ($r) => $r->date->toDateString())->sortKeys()
            ->map(fn ($rows, $date) => ['date' => $date, 'rate' => (int) round($rows->filter($isPresent)->count() / max(1, $rows->count()) * 100)])
            ->values();

        $contracts = Contract::with('employee')->whereIn('employee_id', $ids)->where('status', 'ACTIVE')
            ->whereBetween('end_date', [$today->toDateString(), $today->addDays(60)->toDateString()])
            ->orderBy('end_date')->get();

        $birthdays = $active->map(function ($e) use ($today) {
            $next = CarbonImmutable::parse($e->date_of_birth)->setYear($today->year);
            if ($next->lt($today)) {
                $next = $next->addYear();
            }

            return ['employee' => $e, 'date' => $next, 'days' => (int) $today->diffInDays($next)];
        })->filter(fn ($b) => $b['days'] <= 30)->sortBy('days')->take(6)->values();

        $pending = fn ($model) => $model::whereIn('employee_id', $ids)->whereIn('status', ['PENDING_MANAGER', 'PENDING_HR'])->count();
        $leaveWaiting = LeaveRequest::with('employee')->whereIn('employee_id', $ids)->whereIn('status', ['PENDING_MANAGER', 'PENDING_HR'])->get()
            ->filter(fn ($r) => $leave->canDecide($user, $r))->count();

        return view('dashboard.index', [
            'departments' => Department::orderBy('name')->pluck('name', 'id')->all(),
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'department_id' => $departmentId],
            'totals' => [
                'employees' => $employees->count(),
                'active' => $employees->filter(fn ($e) => $e->status->value === 'ACTIVE')->count(),
                'inactive' => $employees->filter(fn ($e) => in_array($e->status->value, self::EXITED, true))->count(),
                'new_hires' => $employees->filter(fn ($e) => $e->date_of_employment->between($from, $to))->count(),
                'on_leave' => LeaveRequest::whereIn('employee_id', $ids)->where('status', 'APPROVED')
                    ->where('start_date', '<=', $today->toDateString())->where('end_date', '>=', $today->toDateString())->count(),
                'probation' => $employees->filter(fn ($e) => $e->status->value === 'PROBATION')->count(),
                'contracts_expiring' => $contracts->count(),
                'turnover' => round($exits / $averageHeadcount * 100, 1),
                'attendance_rate' => $attendance->isNotEmpty() && $expected ? round($present / $expected * 100, 1) : null,
                'absenteeism_rate' => $attendance->isNotEmpty() && $expected ? round($absent / $expected * 100, 1) : null,
            ],
            'byDepartment' => $active->groupBy(fn ($e) => $e->department->name)->map->count()->sortDesc()->all(),
            'byGender' => $active->groupBy(fn ($e) => Format::title($e->gender))->map->count()->sortDesc()->all(),
            'trend' => $trend,
            'birthdays' => $birthdays,
            'contracts' => $contracts->take(8),
            'pending' => array_values(array_filter([
                ['Leave requests', $leaveWaiting, route('leave.index', ['status' => 'PENDING'])],
                Access::hasRole($user, ['HR_ADMIN', 'HR_MANAGER', 'PAYROLL_OFFICER']) ? ['Payroll runs', PayrollRun::where('status', 'PENDING_APPROVAL')->count(), route('payroll.index')] : null,
                Access::isHr($user) ? ['Recruitment requests', ManpowerRequisition::where('status', 'PENDING')->count(), url('/recruitment')] : null,
                ['Attendance corrections', $pending(AttendanceCorrection::class), url('/attendance')],
                ['Overtime', $pending(OvertimeRequest::class), url('/attendance')],
                ['Separations', Separation::whereIn('employee_id', $ids)->where('status', 'PENDING')->count(), url('/exit')],
                ['Welfare', WelfareRequest::whereIn('employee_id', $ids)->where('status', 'PENDING')->count(), url('/welfare')],
            ])),
        ]);
    }
}
