<?php

namespace App\Enums;

enum DriverAvailability: string
{
    case Available = 'available';
    case Maybe = 'maybe';
    case NotAvailable = 'not-available';
}