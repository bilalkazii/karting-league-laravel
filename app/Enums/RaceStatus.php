<?php

namespace App\Enums;

enum RaceStatus: string
{
    case Draft = 'draft';
    case Lobby = 'lobby';
    case Qualifying = 'qualifying';
    case Grid = 'grid';
    case Racing = 'racing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}