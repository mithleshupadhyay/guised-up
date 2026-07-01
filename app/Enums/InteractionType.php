<?php

namespace App\Enums;

enum InteractionType: string
{
    case View = 'view';
    case Reply = 'reply';
    case Reaction = 'reaction';

    public function weight(): float
    {
        return match ($this) {
            self::View => 0.20,
            self::Reply => 1.00,
            self::Reaction => 0.60,
        };
    }
}
