<?php

namespace App\Enums;

enum TrainingStatus: string
{
    case PLANNED = 'PLANNED';
    case ONGOING = 'ONGOING';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';
}
