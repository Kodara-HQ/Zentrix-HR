<?php

namespace App\Enums;

enum AppraisalStatus: string
{
    case DRAFT = 'DRAFT';
    case SELF_SUBMITTED = 'SELF_SUBMITTED';
    case REVIEWED = 'REVIEWED';
    case ACKNOWLEDGED = 'ACKNOWLEDGED';
}
