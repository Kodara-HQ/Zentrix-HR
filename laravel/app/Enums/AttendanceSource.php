<?php

namespace App\Enums;

enum AttendanceSource: string
{
    case MANUAL = 'MANUAL';
    case CLOCK = 'CLOCK';
    case BIOMETRIC = 'BIOMETRIC';
}
