<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

// Generated from the Prisma schema of the Node version; edit freely.
class Employee extends Model
{
    use BelongsToCompany, HasUlids;

    protected $table = 'employees';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'gender' => \App\Enums\Gender::class,
            'date_of_birth' => 'date',
            'status' => \App\Enums\EmploymentStatus::class,
            'date_of_employment' => 'date',
            'probation_end_date' => 'date',
            'basic_salary' => 'decimal:2',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class, 'job_title_id');
    }

    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class, 'employment_type_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class, 'location_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'employee_id');
    }

    public function education(): HasMany
    {
        return $this->hasMany(EmployeeEducation::class, 'employee_id');
    }

    public function qualifications(): HasMany
    {
        return $this->hasMany(EmployeeQualification::class, 'employee_id');
    }

    public function skills(): HasMany
    {
        return $this->hasMany(EmployeeSkill::class, 'employee_id');
    }

    public function experience(): HasMany
    {
        return $this->hasMany(EmployeeExperience::class, 'employee_id');
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(EmployeeBankAccount::class, 'employee_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(EmployeeEvent::class, 'employee_id');
    }

    public function payItems(): HasMany
    {
        return $this->hasMany(EmployeePayItem::class, 'employee_id');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class, 'employee_id');
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class, 'employee_id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'employee_id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'employee_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class, 'employee_id');
    }

    public function overtime(): HasMany
    {
        return $this->hasMany(OvertimeRequest::class, 'employee_id');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class, 'employee_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'employee_id');
    }

    public function letters(): HasMany
    {
        return $this->hasMany(HrLetter::class, 'employee_id');
    }

    public function objectives(): HasMany
    {
        return $this->hasMany(Objective::class, 'employee_id');
    }

    public function appraisals(): HasMany
    {
        return $this->hasMany(Appraisal::class, 'employee_id');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(ImprovementPlan::class, 'employee_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(TrainingEnrollment::class, 'employee_id');
    }

    public function certifications(): HasMany
    {
        return $this->hasMany(Certification::class, 'employee_id');
    }

    public function developmentPlans(): HasMany
    {
        return $this->hasMany(DevelopmentPlan::class, 'employee_id');
    }

    public function grievances(): HasMany
    {
        return $this->hasMany(Grievance::class, 'employee_id');
    }

    public function disciplinaryCases(): HasMany
    {
        return $this->hasMany(DisciplinaryCase::class, 'employee_id');
    }

    public function warnings(): HasMany
    {
        return $this->hasMany(Warning::class, 'employee_id');
    }

    public function welfareRequests(): HasMany
    {
        return $this->hasMany(WelfareRequest::class, 'employee_id');
    }

    public function recognitions(): HasMany
    {
        return $this->hasMany(Recognition::class, 'employee_id');
    }

    public function separations(): HasMany
    {
        return $this->hasMany(Separation::class, 'employee_id');
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class, 'employee_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(PayAdjustment::class, 'employee_id');
    }

    public function successionAsIncumbent(): HasMany
    {
        return $this->hasMany(SuccessionPlan::class, 'incumbent_id');
    }

    public function successionAsSuccessor(): HasMany
    {
        return $this->hasMany(SuccessionPlan::class, 'successor_id');
    }
}
