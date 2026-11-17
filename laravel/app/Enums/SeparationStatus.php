<?php

namespace App\Enums;

enum SeparationStatus: string
{
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case COMPLETED = 'COMPLETED';
    case REJECTED = 'REJECTED';
}
