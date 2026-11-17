<?php

namespace App\Enums;

enum OfferStatus: string
{
    case DRAFT = 'DRAFT';
    case SENT = 'SENT';
    case ACCEPTED = 'ACCEPTED';
    case DECLINED = 'DECLINED';
    case WITHDRAWN = 'WITHDRAWN';
}
