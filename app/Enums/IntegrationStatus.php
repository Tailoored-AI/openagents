<?php

namespace App\Enums;

enum IntegrationStatus: string
{
    case Initiated = 'initiated';
    case Active = 'active';
    case Expired = 'expired';
    case Failed = 'failed';
    case Inactive = 'inactive';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Initiated => 'Pending',
            self::Active => 'Connected',
            self::Expired => 'Expired',
            self::Failed => 'Failed',
            self::Inactive => 'Inactive',
        };
    }
}
