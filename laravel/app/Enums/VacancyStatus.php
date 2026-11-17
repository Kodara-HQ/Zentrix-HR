<?php

namespace App\Enums;

enum VacancyStatus: string
{
    case DRAFT = 'DRAFT';
    case OPEN = 'OPEN';
    case ON_HOLD = 'ON_HOLD';
    case CLOSED = 'CLOSED';
    case FILLED = 'FILLED';
}
