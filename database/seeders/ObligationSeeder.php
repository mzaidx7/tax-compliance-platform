<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Obligation;
use App\Models\User;
use Illuminate\Database\Seeder;
use LogicException;

final class ObligationSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new LogicException('Synthetic obligation data cannot be seeded in production.');
        }

        $administrator = User::query()
            ->where('email', 'administrator@example.test')
            ->firstOrFail();
        $clients = Client::withoutGlobalScopes()
            ->with('firm')
            ->whereIn('internal_code', ['CL-0001', 'CL-0002'])
            ->orderBy('internal_code')
            ->get();

        foreach ($clients as $index => $client) {
            Obligation::factory()->createForFirm($client->firm, $client, [
                'obligation_type' => $index === 0
                    ? 'VAT return'
                    : 'Trade licence renewal',
                'period_label' => $index === 0 ? 'Apr to Jun 2026' : null,
                'statutory_due_date' => $index === 0 ? '2026-09-28' : '2026-10-15',
                'internal_target_date' => $index === 0 ? '2026-09-21' : '2026-10-08',
                'source_reference' => 'Demo record for local testing only.',
                'last_verified_on' => '2026-07-27',
                'verified_by' => $administrator->id,
                'created_by' => $administrator->id,
            ]);
        }
    }
}
