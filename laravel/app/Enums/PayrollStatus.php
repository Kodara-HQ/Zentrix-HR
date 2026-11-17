<?php

namespace App\Enums;

enum PayrollStatus: string
{
    case DRAFT = 'DRAFT';
    case PENDING_APPROVAL = 'PENDING_APPROVAL';
    case APPROVED = 'APPROVED';
    case CANCELLED = 'CANCELLED';
}
