<?php

namespace App\Enums;

enum CycleStatus: string
{
    case DRAFT = 'DRAFT';
    case OPEN = 'OPEN';
    case CLOSED = 'CLOSED';
}
