<?php

namespace App\Enum;

enum Status: string
{
    case Draft = 'Brouillon';
    case Pending_payment = 'En attente';
    case Paid = 'Payées';
}