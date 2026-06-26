<?php

namespace App\Enum;

enum Status: string
{
    case Draft           = 'draft';
    case Pending_payment = 'pending';
    case Paid            = 'paid';

    public function getLabel(): string
    {
        return match($this) {
            Status::Draft           => 'Brouillon',
            Status::Pending_payment => 'En attente',
            Status::Paid            => 'Payées',
        };
    }
}