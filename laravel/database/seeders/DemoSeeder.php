<?php

namespace Database\Seeders;

use App\Enums\EventType;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\EmployeeEducation;
use App\Models\EmployeeEvent;
use App\Models\EmployeePayItem;
use App\Models\EmployeeSkill;
use App\Models\EmploymentType;
use App\Models\JobTitle;
use App\Models\PayrollComponent;
use App\Models\User;
use App\Models\WorkLocation;
use App\Services\EmployeeService;
use App\Support\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Sample company with 18 staff, one per role, for trying the system out. Every account signs in with
 * the password Hris@2026. Ported from the employee part of server/src/seed.ts; later modules add their
 * own sample data (leave requests, payroll runs, ...).
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'Hris@2026';

    public function run(EmployeeService $employees): void
    {
        $this->call(ReferenceDataSeeder::class);

        // The demo staff go into the first company.
        Tenant::run(Company::orderBy('id')->value('id'), fn () => $this->fill($employees));
    }

    private function fill(EmployeeService $employees): void
    {
        // A test administrator that is not tied to a staff record (sign in as "test").
        User::firstOrCreate(['username' => 'test'], [
            'email' => 'test@zentrix.test', 'password_hash' => Hash::make(self::PASSWORD), 'role' => 'SUPER_ADMIN',
            'first_name' => 'Test', 'last_name' => 'Administrator', 'is_active' => true, 'must_change_password' => false,
        ]);

        if (Employee::exists()) {
            $this->command?->warn('Employees already exist; the demo data was not added.');

            return;
        }

        $today = CarbonImmutable::today();
        $birthday = fn (int $daysAhead, int $year) => $today->addDays($daysAhead)->setYear($year)->toDateString();

        Tenant::company()->update([
            'name' => 'Zentrix HR', 'legal_name' => 'Zentrix HR', 'tin' => 'C0012345678', 'ssnit_employer_no' => 'ER-ML-20418',
            'email' => 'hr@zentrix.gh', 'phone' => '+233 30 200 0100', 'address' => '14 Liberation Road, Airport City', 'city' => 'Accra', 'next_employee_no' => 19,
        ]);

        $departments = [];
        foreach (['HR' => 'Human Resources', 'FIN' => 'Finance', 'OPS' => 'Operations', 'IT' => 'Information Technology', 'COM' => 'Commercial', 'WH' => 'Warehouse'] as $code => $name) {
            $departments[$code] = Department::create(['code' => $code, 'name' => $name, 'description' => "{$name} department"]);
        }

        $titles = [];
        foreach ([
            ['HR Director', 'HR', 'M3'], ['HR Manager', 'HR', 'M2'], ['HR Officer', 'HR', 'P2'], ['Recruitment Officer', 'HR', 'P2'],
            ['Finance Manager', 'FIN', 'M2'], ['Payroll Officer', 'FIN', 'P2'], ['Accountant', 'FIN', 'P2'],
            ['Operations Manager', 'OPS', 'M2'], ['Operations Supervisor', 'OPS', 'P3'], ['Operations Officer', 'OPS', 'P1'],
            ['IT Manager', 'IT', 'M2'], ['IT Support Officer', 'IT', 'P1'], ['Commercial Manager', 'COM', 'M2'], ['Sales Executive', 'COM', 'P2'],
            ['Warehouse Supervisor', 'WH', 'P3'], ['Warehouse Assistant', 'WH', 'P1'], ['Driver', 'OPS', 'P1'],
        ] as [$name, $code, $grade]) {
            $titles[$name] = JobTitle::create(['name' => $name, 'grade' => $grade, 'department_id' => $departments[$code]->id]);
        }

        $fullTime = EmploymentType::where('name', 'Full-time')->first();
        $casual = EmploymentType::where('name', 'Casual')->first();

        $locations = [];
        foreach ([
            ['Airport City', 'Head office', 'Accra', 'Greater Accra', '14 Liberation Road, Airport City'],
            ['Tema Port', 'Tema branch', 'Tema', 'Greater Accra', 'Harbour Area, Tema'],
            ['Kumasi Depot', 'Kumasi branch', 'Kumasi', 'Ashanti', 'Asafo Depot, Kumasi'],
        ] as [$name, $branch, $city, $region, $address]) {
            $locations[$name] = WorkLocation::create(compact('name', 'branch', 'city', 'region', 'address'));
        }

        // no, first, last, gender, role, dept, title, location, manager, salary, status, dob, hired, probation, casual
        $people = [
            ['EMP-0001', 'Efua', 'Boateng', 'FEMALE', 'SUPER_ADMIN', 'HR', 'HR Director', 'Airport City', null, 18500, 'ACTIVE', $birthday(40, 1982), '2016-03-01'],
            ['EMP-0002', 'Kojo', 'Mensah', 'MALE', 'HR_MANAGER', 'HR', 'HR Manager', 'Airport City', 'EMP-0001', 12800, 'ACTIVE', $birthday(18, 1986), '2018-07-15'],
            ['EMP-0003', 'Akosua', 'Darko', 'FEMALE', 'HR_ADMIN', 'HR', 'HR Officer', 'Airport City', 'EMP-0002', 7200, 'ACTIVE', $birthday(3, 1993), '2021-02-01'],
            ['EMP-0004', 'Abena', 'Sarpong', 'FEMALE', 'RECRUITMENT_OFFICER', 'HR', 'Recruitment Officer', 'Airport City', 'EMP-0002', 6900, 'ACTIVE', $birthday(55, 1994), '2022-05-09'],
            ['EMP-0005', 'Adwoa', 'Frimpong', 'FEMALE', 'DEPARTMENT_MANAGER', 'FIN', 'Finance Manager', 'Airport City', null, 14200, 'ACTIVE', $birthday(70, 1984), '2017-01-10'],
            ['EMP-0006', 'Yaw', 'Owusu', 'MALE', 'PAYROLL_OFFICER', 'FIN', 'Payroll Officer', 'Airport City', 'EMP-0005', 8100, 'ACTIVE', $birthday(22, 1990), '2019-09-02'],
            ['EMP-0007', 'Nana', 'Yeboah', 'MALE', 'EMPLOYEE', 'FIN', 'Accountant', 'Airport City', 'EMP-0005', 7600, 'ACTIVE', $birthday(90, 1991), '2020-04-06'],
            ['EMP-0008', 'Kofi', 'Asante', 'MALE', 'DEPARTMENT_MANAGER', 'OPS', 'Operations Manager', 'Airport City', null, 13600, 'ACTIVE', $birthday(8, 1983), '2015-11-02'],
            ['EMP-0009', 'Ama', 'Adjei', 'FEMALE', 'SUPERVISOR', 'OPS', 'Operations Supervisor', 'Airport City', 'EMP-0008', 8400, 'ACTIVE', $birthday(10, 1990), '2019-03-18'],
            ['EMP-0010', 'Kwame', 'Boateng', 'MALE', 'EMPLOYEE', 'OPS', 'Operations Officer', 'Airport City', 'EMP-0009', 5200, 'ACTIVE', $birthday(3, 1996), '2023-01-09'],
            ['EMP-0011', 'Esi', 'Quaye', 'FEMALE', 'EMPLOYEE', 'OPS', 'Operations Officer', 'Kumasi Depot', 'EMP-0009', 5000, 'ACTIVE', $birthday(10, 1997), '2023-06-01'],
            ['EMP-0012', 'Kojo', 'Appiah', 'MALE', 'DEPARTMENT_MANAGER', 'IT', 'IT Manager', 'Airport City', null, 13100, 'ACTIVE', $birthday(100, 1988), '2018-08-20'],
            ['EMP-0013', 'Linda', 'Osei', 'FEMALE', 'EMPLOYEE', 'IT', 'IT Support Officer', 'Airport City', 'EMP-0012', 4800, 'PROBATION', $birthday(14, 1999), $today->subDays(70)->toDateString(), $today->addDays(12)->toDateString()],
            ['EMP-0014', 'Samuel', 'Tetteh', 'MALE', 'DEPARTMENT_MANAGER', 'COM', 'Commercial Manager', 'Airport City', null, 12500, 'ACTIVE', $birthday(120, 1985), '2017-05-02'],
            ['EMP-0015', 'Priscilla', 'Aidoo', 'FEMALE', 'EMPLOYEE', 'COM', 'Sales Executive', 'Kumasi Depot', 'EMP-0014', 6100, 'ACTIVE', $birthday(25, 1995), '2024-02-12'],
            ['EMP-0016', 'Ibrahim', 'Fuseini', 'MALE', 'SUPERVISOR', 'WH', 'Warehouse Supervisor', 'Tema Port', 'EMP-0008', 7800, 'ACTIVE', $birthday(45, 1989), '2019-10-01'],
            ['EMP-0017', 'Rashid', 'Mensah', 'MALE', 'EMPLOYEE', 'WH', 'Warehouse Assistant', 'Tema Port', 'EMP-0016', 3200, 'ACTIVE', $birthday(6, 2000), '2024-08-05', null, true],
            ['EMP-0018', 'Yaw', 'Darko', 'MALE', 'EMPLOYEE', 'OPS', 'Driver', 'Tema Port', 'EMP-0008', 3400, 'RESIGNED', $birthday(200, 1987), '2018-01-15'],
        ];

        $passwordHash = Hash::make(self::PASSWORD);
        $byNo = [];
        foreach ($people as $person) {
            [$no, $first, $last, $gender, $role, $dept, $title, $location, , $salary, $status, $dob, $hired] = $person;
            $probation = $person[13] ?? null;
            $isCasual = $person[14] ?? false;
            $phone = '+2332441000'.substr($no, -2);
            $city = match ($location) { 'Tema Port' => 'Tema', 'Kumasi Depot' => 'Kumasi', default => 'Accra' };

            $employee = Employee::create([
                'employee_no' => $no, 'first_name' => $first, 'last_name' => $last, 'gender' => $gender, 'date_of_birth' => $dob,
                'email' => strtolower("{$first}.{$last}@zentrix.gh"), 'phone' => $phone,
                'address' => match ($location) { 'Tema Port' => 'Community 5, Tema', 'Kumasi Depot' => 'Asafo, Kumasi', default => 'East Legon, Accra' },
                'city' => $city, 'region' => $location === 'Kumasi Depot' ? 'Ashanti' : 'Greater Accra',
                'emergency_name' => "{$last} family", 'emergency_phone' => str_replace('100', '200', $phone), 'emergency_relation' => 'Sibling',
                'next_of_kin_name' => "{$first} {$last} Snr", 'next_of_kin_phone' => str_replace('100', '300', $phone), 'next_of_kin_relation' => 'Parent',
                'department_id' => $departments[$dept]->id, 'job_title_id' => $titles[$title]->id,
                'employment_type_id' => ($isCasual ? $casual : $fullTime)->id, 'location_id' => $locations[$location]->id,
                'status' => $status, 'date_of_employment' => $hired, 'probation_end_date' => $probation, 'basic_salary' => $salary,
                'ssnit_number' => 'C'.substr($no, -4).substr($dob, 0, 4), 'tin_number' => 'P00'.substr($no, -4).'392',
            ]);
            $byNo[$no] = $employee;

            User::create([
                'email' => $employee->email, 'password_hash' => $passwordHash, 'role' => $role, 'first_name' => $first, 'last_name' => $last,
                'employee_id' => $employee->id, 'department_id' => $employee->department_id, 'location_id' => $employee->location_id,
                'is_active' => $status !== 'RESIGNED', 'must_change_password' => false,
            ]);
            EmployeeEvent::create(['employee_id' => $employee->id, 'type' => EventType::HIRE, 'title' => 'Joined Zentrix HR', 'effective_date' => $hired, 'to_value' => ['employeeNo' => $no, 'title' => $title]]);
            EmployeeEducation::create([
                'employee_id' => $employee->id,
                'institution' => $dept === 'IT' ? 'Kwame Nkrumah University of Science and Technology' : 'University of Ghana',
                'qualification' => $salary > 10000 ? 'MBA' : "Bachelor's degree",
                'field' => match ($dept) { 'FIN' => 'Accounting', 'IT' => 'Computer Science', default => 'Business Administration' },
                'end_year' => (int) substr($dob, 0, 4) + 23,
            ]);
            EmployeeSkill::create(['employee_id' => $employee->id, 'name' => $dept === 'IT' ? 'Systems support' : 'Customer operations', 'level' => 'Proficient', 'years' => 4]);
            EmployeeBankAccount::create([
                'employee_id' => $employee->id, 'bank_name' => str_ends_with($no, '6') || str_ends_with($no, '7') ? 'GCB Bank' : 'Ecobank Ghana',
                'branch' => 'Airport City', 'account_name' => "{$first} {$last}", 'account_number' => '144100'.substr($no, -4), 'is_primary' => true,
            ]);
            $end = match ($no) { 'EMP-0015' => $today->addDays(20), 'EMP-0016' => $today->addDays(45), 'EMP-0017' => $today->addDays(200), default => null };
            $kind = $end ? 'Fixed-term contract' : 'Employment contract';
            Contract::create([
                'employee_id' => $employee->id, 'type' => $kind, 'title' => "{$kind} — {$first} {$last}", 'start_date' => $hired,
                'end_date' => $end?->toDateString(), 'status' => $status === 'RESIGNED' ? 'ENDED' : 'ACTIVE',
            ]);
            $employees->grantLeaveBalances($employee);
        }

        foreach ($people as $person) {
            if ($person[8]) {
                $byNo[$person[0]]->update(['manager_id' => $byNo[$person[8]]->id]);
            }
        }
        $departments['HR']->update(['head_id' => $byNo['EMP-0001']->id]);
        $departments['FIN']->update(['head_id' => $byNo['EMP-0005']->id]);
        $departments['OPS']->update(['head_id' => $byNo['EMP-0008']->id]);

        $transport = PayrollComponent::where('code', 'TRANSPORT')->firstOrFail(); // created by ReferenceDataSeeder
        $rent = PayrollComponent::where('code', 'RENT')->firstOrFail();
        foreach ($people as $person) {
            if ($person[10] === 'RESIGNED') {
                continue;
            }
            $employee = $byNo[$person[0]];
            EmployeePayItem::create(['employee_id' => $employee->id, 'component_id' => $transport->id, 'amount' => $person[9] > 10000 ? 800 : 400]);
            if ($person[9] > 10000) {
                EmployeePayItem::create(['employee_id' => $employee->id, 'component_id' => $rent->id, 'amount' => 1200]);
            }
        }

        $this->call(DemoAttendanceSeeder::class);
        $this->command?->info('Added Zentrix HR with '.count($people).' employees. Every demo account signs in with Hris@2026.');
    }
}
