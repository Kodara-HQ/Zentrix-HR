<?php

namespace App\Enums;

enum LoanStatus: string
{
    case ACTIVE = 'ACTIVE';
    case CLOSED = 'CLOSED';
}
