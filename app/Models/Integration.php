<?php

namespace App\Models;

use App\Enums\IntegrationStatus;
use Database\Factories\IntegrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A team-level connection to a third-party app, brokered by the configured integration provider.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $connected_by
 * @property string $provider
 * @property string $provider_app_id
 * @property string $provider_connection_id
 * @property string $app_slug
 * @property string $name
 * @property string|null $logo
 * @property IntegrationStatus $status
 * @property string|null $status_reason
 * @property Carbon|null $connected_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User|null $connector
 */
#[Fillable([
    'team_id',
    'connected_by',
    'provider',
    'provider_app_id',
    'provider_connection_id',
    'app_slug',
    'name',
    'logo',
    'status',
    'status_reason',
    'connected_at',
])]
class Integration extends Model
{
    /** @use HasFactory<IntegrationFactory> */
    use HasFactory;

    /**
     * Get the team that owns the integration.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who connected the integration.
     *
     * @return BelongsTo<User, $this>
     */
    public function connector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    /**
     * Determine if the integration is ready to be used.
     */
    public function isConnected(): bool
    {
        return $this->status === IntegrationStatus::Active;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => IntegrationStatus::class,
            'connected_at' => 'datetime',
        ];
    }
}
