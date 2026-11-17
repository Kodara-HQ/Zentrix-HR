<?php

namespace App\Enums;

enum CaseStatus: string
{
    case OPEN = 'OPEN';
    case INVESTIGATING = 'INVESTIGATING';
    case HEARING = 'HEARING';
    case CLOSED = 'CLOSED';
}
