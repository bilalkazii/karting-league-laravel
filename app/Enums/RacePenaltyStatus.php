<?php

namespace App\Enums;

enum RacePenaltyStatus: string
{
    case Issued = 'issued';
    case UnderReview = 'under_review';
    case Cancelled = 'cancelled';
}