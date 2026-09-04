<?php

namespace Database\Factories;

use App\Enums\IntegrationStatus;
use App\Models\Integration;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Integration>
 */
class IntegrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $app = fake()->randomElement(['github', 'slack', 'gmail', 'notion', 'linear']);

        return [
            'team_id' => Team::factory(),
            'connected_by' => null,
            'provider' => 'composio',
            'provider_app_id' => 'ac_'.Str::random(12),
            'provider_connection_id' => 'ca_'.Str::random(12),
            'app_slug' => $app,
            'name' => Str::headline($app),
            'logo' => "https://logos.composio.dev/api/{$app}",
            'status' => IntegrationStatus::Active,
            'status_reason' => null,
            'connected_at' => now(),
        ];
    }

    /**
     * Indicate that the user has not finished authorizing the app yet.
     */
    public function initiated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => IntegrationStatus::Initiated,
            'connected_at' => null,
        ]);
    }

    /**
     * Indicate that the connection's credentials have expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => IntegrationStatus::Expired,
            'status_reason' => 'The access token could not be refreshed.',
        ]);
    }

    /**
     * Indicate that authorizing the app failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => IntegrationStatus::Failed,
            'status_reason' => 'The provider rejected the authorization.',
            'connected_at' => null,
        ]);
    }
}
