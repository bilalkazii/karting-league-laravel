<?php

namespace App\Enums;

enum AwardType: string
{
    case Winner = 'winner';
    case Podium = 'podium';
    case Pole = 'pole';
    case DriverOfRace = 'driver_of_race';
    case MostImproved = 'most_improved';
    case CleanestDriver = 'cleanest_driver';
    case ChampionshipWinner = 'championship_winner';
    case TeamChampion = 'team_champion';
    case MostWins = 'most_wins';
    case MostPoles = 'most_poles';
    case MostPodiums = 'most_podiums';
    case CleanestSeason = 'cleanest_season';
    case Participation = 'participation';
}