<?php

namespace App\Enums;

enum ApprovalStatus: string
{
    case PENDING_MANAGER = 'PENDING_MANAGER';
    case PENDING_HR = 'PENDING_HR';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case CANCELLED = 'CANCELLED';
}
