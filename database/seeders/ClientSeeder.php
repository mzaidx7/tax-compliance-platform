<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Firm;
use App\Models\User;
use Illuminate\Database\Seeder;
use LogicException;

final class ClientSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new LogicException('Synthetic client data cannot be seeded in production.');
        }

        $firm = Firm::query()
            ->where('slug', 'synthetic-compliance-practice')
            ->firstOrFail();
        $administrator = User::query()
            ->where('email', 'administrator@example.test')
            ->firstOrFail();

        Client::factory()->createForFirm($firm, [
            'internal_code' => 'CL-0001',
            'internal_code_normalized' => 'CL-0001',
            'legal_name' => 'Synthetic Horizon Trading LLC',
            'trade_name' => 'Synthetic Horizon',
            'entity_type' => 'Limited liability company',
            'created_by' => $administrator->id,
        ]);

        Client::factory()->createForFirm($firm, [
            'internal_code' => 'CL-0002',
            'internal_code_normalized' => 'CL-0002',
            'legal_name' => 'Synthetic Dune Advisory FZ-LLC',
            'trade_name' => null,
            'entity_type' => 'Free zone company',
            'created_by' => $administrator->id,
        ]);
    }
}
