<?php

namespace App\Enums;

enum WelfareStatus: string
{
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case FULFILLED = 'FULFILLED';
}
