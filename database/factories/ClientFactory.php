<?php

namespace Database\Factories;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\Firm;
use App\Models\User;
use App\Tenancy\FirmContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $internalCode = strtoupper(fake()->unique()->bothify('CL-####'));

        return [
            'internal_code' => $internalCode,
            'internal_code_normalized' => $internalCode,
            'legal_name' => fake()->company(),
            'trade_name' => fake()->optional()->company(),
            'entity_type' => fake()->randomElement([
                'Limited liability company',
                'Free zone company',
                'Sole establishment',
            ]),
            'status' => ClientStatus::Active,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Persist one synthetic client through a trusted firm context.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createForFirm(Firm $firm, array $attributes = []): Client
    {
        return app(FirmContext::class)->runForFirm($firm, function () use ($attributes): Client {
            $client = $this
                ->count(null)
                ->create($attributes);

            if (! $client instanceof Client) {
                throw new LogicException('The client factory did not create one client.');
            }

            return $client;
        });
    }
}
