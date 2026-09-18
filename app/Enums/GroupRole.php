<?php

namespace App\Enums;

enum GroupRole: string
{
    case Admin = 'admin';
    case Organizer = 'organizer';
    case Member = 'member';
}