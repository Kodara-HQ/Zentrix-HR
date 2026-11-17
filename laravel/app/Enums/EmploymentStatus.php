<?php

namespace App\Enums;

enum EmploymentStatus: string
{
    case PROBATION = 'PROBATION';
    case ACTIVE = 'ACTIVE';
    case NOTICE = 'NOTICE';
    case SUSPENDED = 'SUSPENDED';
    case INACTIVE = 'INACTIVE';
    case RESIGNED = 'RESIGNED';
    case RETIRED = 'RETIRED';
    case TERMINATED = 'TERMINATED';
}
