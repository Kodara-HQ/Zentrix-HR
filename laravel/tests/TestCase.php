<?php

namespace Tests;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\JobTitle;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function makeUser(Role $role = Role::HR_ADMIN, array $attributes = []): User
    {
        return User::create($attributes + [
            'email' => Str::lower(Str::random(8)).'@zentrix.test',
            'password_hash' => Hash::make('Secret123'),
            'role' => $role,
            'first_name' => 'Ama',
            'last_name' => 'Mensah',
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    protected function makeEmployee(array $attributes = []): Employee
    {
        $department = Department::firstOrCreate(['code' => 'OPS'], ['name' => 'Operations']);

        return Employee::create($attributes + [
            'employee_no' => 'EMP'.Str::random(5),
            'first_name' => 'Kofi',
            'last_name' => 'Owusu',
            'gender' => 'MALE',
            'date_of_birth' => '1990-04-12',
            'email' => Str::lower(Str::random(8)).'@zentrix.test',
            'phone' => '0240000000',
            'department_id' => $department->id,
            'job_title_id' => JobTitle::firstOrCreate(['name' => 'Officer', 'department_id' => $department->id])->id,
            'employment_type_id' => EmploymentType::firstOrCreate(['name' => 'Permanent'])->id,
            'location_id' => WorkLocation::firstOrCreate(['name' => 'Head office', 'branch' => 'Accra'], ['city' => 'Accra', 'region' => 'Greater Accra'])->id,
            'status' => 'ACTIVE',
            'date_of_employment' => '2024-01-02',
            'basic_salary' => 4500,
        ]);
    }
}
