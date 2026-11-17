<?php

namespace App\Enums;

enum AdjustmentKind: string
{
    case BONUS = 'BONUS';
    case COMMISSION = 'COMMISSION';
    case ALLOWANCE = 'ALLOWANCE';
    case DEDUCTION = 'DEDUCTION';
}
