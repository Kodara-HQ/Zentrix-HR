<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case PRESENT = 'PRESENT';
    case ABSENT = 'ABSENT';
    case LATE = 'LATE';
    case LEAVE = 'LEAVE';
    case HOLIDAY = 'HOLIDAY';
    case OFF = 'OFF';
    case REMOTE = 'REMOTE';
}
