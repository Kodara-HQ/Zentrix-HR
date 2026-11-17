<?php

namespace App\Enums;

enum LetterType: string
{
    case APPOINTMENT = 'APPOINTMENT';
    case PROMOTION = 'PROMOTION';
    case TRANSFER = 'TRANSFER';
    case WARNING = 'WARNING';
    case CONFIRMATION = 'CONFIRMATION';
    case TERMINATION = 'TERMINATION';
    case OFFER = 'OFFER';
}
