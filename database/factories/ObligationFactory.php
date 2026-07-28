<?php

namespace Database\Factories;

use App\Enums\ObligationOrigin;
use App\Enums\ObligationStatus;
use App\Models\Client;
use App\Models\Firm;
use App\Models\Obligation;
use App\Models\User;
use App\Tenancy\FirmContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;

/**
 * @extends Factory<Obligation>
 */
class ObligationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => null,
            'obligation_type' => fake()->randomElement([
                'Manual VAT review',
                'Manual Corporate Tax review',
                'Trade licence renewal review',
            ]),
            'period_label' => fake()->optional()->bothify('Period ####-##'),
            'statutory_due_date' => fake()->dateTimeBetween('+30 days', '+180 days'),
            'effective_due_date' => null,
            'internal_target_date' => fake()->dateTimeBetween('+7 days', '+29 days'),
            'origin' => ObligationOrigin::Manual,
            'status' => ObligationStatus::Open,
            'source_reference' => 'Synthetic manual fixture, not regulatory guidance.',
            'last_verified_on' => now()->toDateString(),
            'verified_by' => User::factory(),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Persist one synthetic obligation through a trusted firm context.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createForFirm(Firm $firm, Client $client, array $attributes = []): Obligation
    {
        if ($client->firm_id !== $firm->id) {
            throw new LogicException('The obligation client must belong to the selected firm.');
        }

        return app(FirmContext::class)->runForFirm(
            $firm,
            function () use ($client, $attributes): Obligation {
                $obligation = $this
                    ->count(null)
                    ->state(['client_id' => $client->id])
                    ->create($attributes);

                if (! $obligation instanceof Obligation) {
                    throw new LogicException('The obligation factory did not create one obligation.');
                }

                return $obligation;
            },
        );
    }
}
