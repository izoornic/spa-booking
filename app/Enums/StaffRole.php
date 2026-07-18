<?php

namespace App\Enums;

enum StaffRole: string
{
    case Manager = 'manager';
    case Attendant = 'attendant';

    /**
     * Human-readable label (Serbian).
     */
    public function label(): string
    {
        return match ($this) {
            self::Manager => 'Upravnik',
            self::Attendant => 'Domar',
        };
    }
}
