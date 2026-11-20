<?php

namespace App\Services;

use App\Enums\EmploymentStatus;
use App\Enums\EventType;
use App\Enums\LetterType;
use App\Enums\Role;
use App\Models\Company;
use App\Support\Tenant;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\EmployeeEvent;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use App\Support\Audit;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Hiring and employment changes. Ported from server/src/routes/employees.ts and lib/employees.ts. */
class EmployeeService
{
    public function __construct(private Letters $letters, private Notifier $notifier) {}

    /** EMP-0001, EMP-0002, ... The company row is locked so two hires never get the same number. */
    public function nextEmployeeNo(): string
    {
        return DB::transaction(function () {
            $company = Company::whereKey(Tenant::id())->lockForUpdate()->first();
            abort_unless($company, 500, 'No company is selected.');
            $number = 'EMP-'.str_pad((string) $company->next_employee_no, 4, '0', STR_PAD_LEFT);
            $company->increment('next_employee_no');

            return $number;
        });
    }

    /** Opens a year's leave balances (this year by default) for every leave type that applies to the employee's gender. */
    public function grantLeaveBalances(Employee $employee, ?int $year = null): void
    {
        $year ??= (int) now()->format('Y');
        $gender = $employee->gender?->value ?? $employee->gender;
        foreach (LeaveType::all() as $type) {
            $typeGender = $type->gender?->value ?? $type->gender;
            if ($typeGender && $typeGender !== $gender) {
                continue;
            }
            LeaveBalance::firstOrCreate(
                ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year],
                ['entitled' => $type->affects_balance ? round((float) $type->days_per_year) : 0, 'used' => 0, 'pending' => 0],
            );
        }
    }

    public function recordEvent(Employee $employee, EventType $type, string $title, string $effectiveDate, ?string $description = null, ?array $from = null, ?array $to = null): void
    {
        EmployeeEvent::create([
            'employee_id' => $employee->id,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'from_value' => $from,
            'to_value' => $to,
            'effective_date' => $effectiveDate,
            'created_by_id' => auth()->id(),
        ]);
    }

    /**
     * Creates the employee with their leave balances, hire event, employment contract and appointment letter,
     * and optionally an ESS sign-in. Returns the employee and the one-time temporary password.
     *
     * @return array{0: Employee, 1: ?string}
     */
    public function hire(array $data, bool $createLogin): array
    {
        $employeeNo = $this->nextEmployeeNo();
        $hireDate = $data['date_of_employment'];
        $probationEnd = $data['probation_end_date'] ?? null;
        $status = $probationEnd && CarbonImmutable::parse($probationEnd)->isAfter(today()) ? EmploymentStatus::PROBATION : EmploymentStatus::ACTIVE;
        $contractEnd = $data['contract_end_date'] ?? null;
        unset($data['contract_end_date']);

        $employee = Employee::create([...$data, 'employee_no' => $employeeNo, 'email' => strtolower($data['email']), 'status' => $status]);
        $employee->load('department', 'jobTitle', 'location');

        $this->grantLeaveBalances($employee);
        $this->recordEvent($employee, EventType::HIRE, 'Employee hired', $hireDate, to: ['employeeNo' => $employeeNo, 'status' => $status->value, 'jobTitleId' => $employee->job_title_id]);
        Contract::create([
            'employee_id' => $employee->id,
            'type' => 'Employment contract',
            'title' => 'Employment contract — '.Format::fullName($employee),
            'start_date' => $hireDate,
            'end_date' => $contractEnd,
            'status' => 'ACTIVE',
        ]);

        $company = Tenant::name();
        $this->letters->issue($employee, LetterType::APPOINTMENT, 'Appointment letter', $hireDate, [
            "I am pleased to confirm your appointment with {$company}. The particulars of this appointment are set out below.",
            "You will serve as {$employee->jobTitle->name} in the {$employee->department->name} department, based at {$employee->location->name}, {$employee->location->city}. Your employment begins on ".CarbonImmutable::parse($hireDate)->format('j F Y').", and your employee number is {$employeeNo}.",
            'Please contact Human Resources if you need anything before your first day. We look forward to working with you.',
        ]);

        $temporaryPassword = null;
        if ($createLogin) {
            $temporaryPassword = 'Welcome@'.substr($employeeNo, -4);
            User::create([
                'email' => $employee->email,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'role' => Role::EMPLOYEE,
                'password_hash' => Hash::make($temporaryPassword),
                'employee_id' => $employee->id,
                'department_id' => $employee->department_id,
                'location_id' => $employee->location_id,
                'is_active' => true,
                'must_change_password' => true,
            ]);
        }

        Audit::log('CREATE', 'Employee', $employee->id, 'Hired '.Format::fullName($employee));

        return [$employee, $temporaryPassword];
    }

    /** Promote, transfer, suspend, reinstate or deactivate. */
    public function applyAction(Employee $employee, string $type, array $data): void
    {
        $employee->load('jobTitle', 'department', 'location');
        $date = $data['effective_date'];
        $notes = $data['notes'] ?? null;

        if ($type === 'PROMOTE') {
            $from = ['jobTitle' => $employee->jobTitle->name, 'basicSalary' => (float) $employee->basic_salary];
            $employee->update(['job_title_id' => $data['job_title_id'], 'basic_salary' => $data['basic_salary']]);
            $employee->load('jobTitle');
            $this->recordEvent($employee, EventType::PROMOTION, "Promoted to {$employee->jobTitle->name}", $date, $notes, $from, ['jobTitle' => $employee->jobTitle->name, 'basicSalary' => (float) $data['basic_salary']]);
            $this->letters->issue($employee, LetterType::PROMOTION, 'Promotion letter', $date, [
                "You have been promoted from {$from['jobTitle']} to {$employee->jobTitle->name}, effective {$date}.",
                $notes ?: 'Please contact Human Resources if you have questions about this change.',
            ]);
            Audit::log('UPDATE', 'Employee', $employee->id, 'Promoted '.Format::fullName($employee));

            return;
        }

        if ($type === 'TRANSFER') {
            $from = ['departmentId' => $employee->department_id, 'locationId' => $employee->location_id];
            $employee->update([
                'department_id' => $data['department_id'] ?? null ?: $employee->department_id,
                'location_id' => $data['location_id'] ?? null ?: $employee->location_id,
                'manager_id' => array_key_exists('manager_id', $data) ? ($data['manager_id'] ?: null) : $employee->manager_id,
            ]);
            $employee->load('department', 'location');
            $this->recordEvent($employee, EventType::TRANSFER, "Transferred to {$employee->department->name}", $date, $notes, $from, ['departmentId' => $employee->department_id, 'locationId' => $employee->location_id]);
            $this->letters->issue($employee, LetterType::TRANSFER, 'Transfer letter', $date, [
                "Your duty station changes to {$employee->location->name}, {$employee->location->city}, in {$employee->department->name}, effective {$date}.",
            ]);
            User::where('employee_id', $employee->id)->update(['department_id' => $employee->department_id, 'location_id' => $employee->location_id]);
            Audit::log('UPDATE', 'Employee', $employee->id, 'Transferred '.Format::fullName($employee));

            return;
        }

        [$status, $event, $title] = match ($type) {
            'SUSPEND' => [EmploymentStatus::SUSPENDED, EventType::SUSPENSION, 'Suspended'],
            'REINSTATE' => [EmploymentStatus::ACTIVE, EventType::REINSTATEMENT, 'Reinstated'],
            default => [EmploymentStatus::INACTIVE, EventType::STATUS_CHANGE, 'Deactivated'],
        };
        $employee->update(['status' => $status]);
        $this->recordEvent($employee, $event, $title, $date, $notes, to: ['status' => $status->value]);

        $account = User::where('employee_id', $employee->id)->first();
        if ($status === EmploymentStatus::INACTIVE) {
            $account?->update(['is_active' => false]);
        } elseif ($status === EmploymentStatus::ACTIVE && $type === 'REINSTATE') {
            $account?->update(['is_active' => true]);
        }
        $this->notifier->notify([$account?->id], 'Employment status updated', Format::fullName($employee).' is now '.strtolower($status->value).'.', 'EMPLOYEE_STATUS', "/employees/{$employee->id}");
        Audit::log('UPDATE', 'Employee', $employee->id, $title.' '.Format::fullName($employee));
    }
}
