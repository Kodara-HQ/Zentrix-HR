<?php

namespace App\Enums;

enum Role: string
{
    /** Runs the whole system: sets up companies and can open any of them. Belongs to no company. */
    case SYSTEM_ADMIN = 'SYSTEM_ADMIN';
    case SUPER_ADMIN = 'SUPER_ADMIN';
    case HR_ADMIN = 'HR_ADMIN';
    case HR_MANAGER = 'HR_MANAGER';
    case DEPARTMENT_MANAGER = 'DEPARTMENT_MANAGER';
    case SUPERVISOR = 'SUPERVISOR';
    case PAYROLL_OFFICER = 'PAYROLL_OFFICER';
    case EMPLOYEE = 'EMPLOYEE';
    case RECRUITMENT_OFFICER = 'RECRUITMENT_OFFICER';
}
