<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case ENROLLED = 'ENROLLED';
    case ATTENDED = 'ATTENDED';
    case ABSENT = 'ABSENT';
    case COMPLETED = 'COMPLETED';
}
