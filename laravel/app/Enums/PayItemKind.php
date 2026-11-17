<?php

namespace App\Enums;

enum PayItemKind: string
{
    case ALLOWANCE = 'ALLOWANCE';
    case DEDUCTION = 'DEDUCTION';
    case RELIEF = 'RELIEF';
}
