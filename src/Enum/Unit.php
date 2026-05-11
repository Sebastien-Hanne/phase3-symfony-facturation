<?php

namespace App\Enum;

enum Unit: string
{
    case piece = 'pièce';
    case hour = 'heure';
    case day = 'jour';
    case month = 'mois';
    case year = 'année';
}