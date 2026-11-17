<?php

namespace App\Enums;

enum LeaveCountMode: string
{
    case WORKING_DAYS = 'WORKING_DAYS';
    case CALENDAR_DAYS = 'CALENDAR_DAYS';
}
