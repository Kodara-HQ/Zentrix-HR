<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
use App\Enums\Role;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\BiometricDevice;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\ShiftAssignment;
use App\Models\ShiftPattern;
use App\Models\WorkingCalendar;
use App\Services\AttendanceService;
use App\Support\Access;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Attendance for HR, managers and ESS, plus the fingerprint clock endpoint. Ported from server/src/routes/time.ts. */
class AttendanceController extends Controller
{
    public const DESK = ['HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER', 'SUPERVISOR'];

    public function __construct(private AttendanceService $attendance) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $own = $user->role === Role::EMPLOYEE;
        $visible = Access::scopeEmployees(Employee::query(), $user)->select('id');
        $from = $request->query('from');
        $to = $request->query('to');

        $records = AttendanceRecord::with('employee')->whereIn('employee_id', $visible)
            ->when($request->query('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($from, fn ($q) => $q->where('date', '>=', $from))
            ->when($to, fn ($q) => $q->where('date', '<=', $to))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('date')->orderBy('employee_id')
            ->paginate(25)->withQueryString();

        $pendingFirst = fn ($q) => $q->orderByRaw("status in ('PENDING_MANAGER','PENDING_HR') desc")->latest();
        $people = $own ? collect() : Access::scopeEmployees(Employee::query(), $user)
            ->whereNotIn('status', ['INACTIVE', 'RESIGNED', 'RETIRED', 'TERMINATED'])->orderBy('first_name')->get();

        return view('attendance.index', [
            'own' => $own,
            'desk' => Access::hasRole($user, self::DESK),
            'admin' => $user->role === Role::SUPER_ADMIN,
            'records' => $records,
            'corrections' => AttendanceCorrection::with('employee')->whereIn('employee_id', $visible)->tap($pendingFirst)->take(30)->get(),
            'overtime' => OvertimeRequest::with('employee')->whereIn('employee_id', $visible)->tap($pendingFirst)->take(30)->get(),
            'shifts' => $own ? collect() : ShiftAssignment::with('shift', 'employee')->whereIn('employee_id', $visible)->orderByDesc('effective_from')->take(8)->get(),
            'patterns' => ShiftPattern::orderBy('name')->get(),
            'calendar' => WorkingCalendar::where('is_active', true)->first(),
            'people' => $people,
            'peopleOptions' => $people->mapWithKeys(fn ($e) => [$e->id => $e->employee_no.' · '.Format::fullName($e)])->all(),
            'service' => $this->attendance,
            'clockUrls' => $user->role === Role::SUPER_ADMIN ? $this->clockUrls($request) : [],
        ]);
    }

    public function storeManual(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::in(['PRESENT', 'ABSENT', 'LATE', 'LEAVE', 'HOLIDAY', 'OFF', 'REMOTE'])],
            'clock_in' => ['nullable', 'date_format:H:i'],
            'clock_out' => ['nullable', 'date_format:H:i', 'after:clock_in'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['clock_out.after' => 'Clock-out must be after clock-in.', 'date.before_or_equal' => 'Attendance cannot be recorded for a future day.']);
        $this->attendance->manual($request->user(), Employee::findOrFail($data['employee_id']), $data['date'], $data['status'], $data['clock_in'] ?? null, $data['clock_out'] ?? null, $data['notes'] ?? null);

        return back()->with('status', 'Attendance saved.');
    }

    public function storeCorrection(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'employee_id' => ['nullable', 'exists:employees,id'],
            'date' => ['required', 'date'],
            'clock_in' => ['nullable', 'date_format:H:i'],
            'clock_out' => ['nullable', 'date_format:H:i', 'after:clock_in'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ], ['clock_out.after' => 'Clock-out must be after clock-in.']);
        $employeeId = $user->role === Role::EMPLOYEE ? $user->employee_id : ($data['employee_id'] ?? $user->employee_id);
        abort_unless($employeeId, 422, 'Choose an employee.');
        $this->attendance->requestCorrection($user, Employee::findOrFail($employeeId), $data['date'], $data['reason'], $data['clock_in'] ?? null, $data['clock_out'] ?? null);

        return back()->with('status', 'Correction requested.');
    }

    public function decideCorrection(Request $request, AttendanceCorrection $correction)
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['APPROVED', 'REJECTED'])], 'note' => ['nullable', 'string', 'max:2000']]);
        $this->attendance->decideCorrection($request->user(), $correction, $data['decision'], $data['note'] ?? null);

        return back()->with('status', $data['decision'] === 'APPROVED' ? 'Correction approved and attendance updated.' : 'Correction rejected.');
    }

    public function storeOvertime(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'employee_id' => ['nullable', 'exists:employees,id'],
            'date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'gt:0', 'max:24'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $employeeId = $user->role === Role::EMPLOYEE ? $user->employee_id : ($data['employee_id'] ?? $user->employee_id);
        abort_unless($employeeId, 422, 'Choose an employee.');
        $this->attendance->requestOvertime($user, Employee::findOrFail($employeeId), $data['date'], (float) $data['hours'], $data['reason']);

        return back()->with('status', 'Overtime requested.');
    }

    public function decideOvertime(Request $request, OvertimeRequest $overtime)
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['APPROVED', 'REJECTED'])], 'note' => ['nullable', 'string', 'max:2000']]);
        $this->attendance->decideOvertime($request->user(), $overtime, $data['decision'], $data['note'] ?? null);

        return back()->with('status', $data['decision'] === 'APPROVED' ? 'Overtime approved. It is paid in that month’s payroll.' : 'Overtime rejected.');
    }

    public function storeShift(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'shift_id' => ['required', 'exists:shift_patterns,id'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);
        Access::assertEmployeeVisible($request->user(), Employee::findOrFail($data['employee_id']));
        ShiftAssignment::create($data);

        return back()->with('status', 'Shift assigned.');
    }

    /** POST /api/time/biometric/punch from the fingerprint clock: {deviceKey, employeeNo, timestamp}. */
    public function biometric(Request $request)
    {
        // Devices may not ask for JSON, so answer in JSON explicitly rather than redirecting on bad input.
        $validator = validator($request->all(), [
            'deviceKey' => ['required', 'string', 'min:4'],
            'employeeNo' => ['required', 'string'],
            'timestamp' => ['required', 'date'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }
        $data = $validator->validated();
        $hash = hash('sha256', $data['deviceKey']);
        $device = BiometricDevice::where('api_key_hash', $hash)->where('is_active', true)->first();
        $envKey = (string) config('services.biometric.key');
        if (! $device && ($envKey === '' || ! hash_equals(hash('sha256', $envKey), $hash))) {
            return response()->json(['message' => 'Unrecognised attendance device.'], 401);
        }
        $employee = Employee::where('employee_no', $data['employeeNo'])->first();
        if (! $employee) {
            return response()->json(['message' => 'Employee number not found.'], 404);
        }

        $record = $this->attendance->punch($employee, CarbonImmutable::parse($data['timestamp']), 'BIOMETRIC', $device?->name, $request->ip());

        return response()->json($record->only(['id', 'date', 'clock_in', 'clock_out', 'status', 'late_minutes']));
    }

    /** Addresses this server answers on, for configuring the fingerprint clock (super administrator only). */
    private function clockUrls(Request $request): array
    {
        $hosts = array_unique(array_filter([
            ...((array) gethostbynamel(gethostname()) ?: []),
            $request->getHost(),
        ], fn ($ip) => $ip && ! str_starts_with($ip, '127.') && ! str_starts_with($ip, '169.254.')));
        $port = $request->getPort();

        return array_map(fn ($host) => "http://{$host}".(in_array($port, [80, 443], true) ? '' : ":{$port}").'/api/time/biometric/punch', array_values($hosts));
    }
}
