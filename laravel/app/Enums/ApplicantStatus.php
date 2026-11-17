<?php

namespace App\Enums;

enum ApplicantStatus: string
{
    case APPLIED = 'APPLIED';
    case SCREENED = 'SCREENED';
    case SHORTLISTED = 'SHORTLISTED';
    case INTERVIEW = 'INTERVIEW';
    case OFFERED = 'OFFERED';
    case HIRED = 'HIRED';
    case REJECTED = 'REJECTED';
    case WITHDRAWN = 'WITHDRAWN';
}
