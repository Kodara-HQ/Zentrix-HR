<?php

namespace App\Enums;

enum EventType: string
{
    case HIRE = 'HIRE';
    case TRANSFER = 'TRANSFER';
    case PROMOTION = 'PROMOTION';
    case SUSPENSION = 'SUSPENSION';
    case REINSTATEMENT = 'REINSTATEMENT';
    case STATUS_CHANGE = 'STATUS_CHANGE';
    case SALARY_CHANGE = 'SALARY_CHANGE';
    case CONFIRMATION = 'CONFIRMATION';
}
