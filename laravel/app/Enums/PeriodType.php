<?php

namespace App\Enums;

enum PeriodType: string
{
    case QUARTERLY = 'QUARTERLY';
    case HALF_YEARLY = 'HALF_YEARLY';
    case ANNUAL = 'ANNUAL';
}
