<?php

namespace App\Enums;

enum Gender: string
{
    case FEMALE = 'FEMALE';
    case MALE = 'MALE';
    case OTHER = 'OTHER';
    case UNDISCLOSED = 'UNDISCLOSED';
}
