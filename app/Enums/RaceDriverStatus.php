<?php

namespace App\Enums;

enum RaceDriverStatus: string
{
    case Invited = 'invited';
    case Confirmed = 'confirmed';
    case Declined = 'declined';
    case Ready = 'ready';
    case Racing = 'racing';
    case Finished = 'finished';
    case Dnf = 'dnf';
    case Dns = 'dns';
    case Disqualified = 'disqualified';
    case Retired = 'retired';
    case Withdrawn = 'withdrawn';
}
