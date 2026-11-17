<?php

namespace App\Enums;

enum SeparationType: string
{
    case RESIGNATION = 'RESIGNATION';
    case RETIREMENT = 'RETIREMENT';
    case TERMINATION = 'TERMINATION';
    case END_OF_CONTRACT = 'END_OF_CONTRACT';
}
