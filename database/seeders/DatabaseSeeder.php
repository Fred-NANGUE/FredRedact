<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach ([
            ['nom' => 'Free', 'description' => 'Pour découvrir la rédaction assistée.', 'prix' => 0, 'tokens_inclus' => 5000, 'duree_jours' => null],
            ['nom' => 'Pro', 'description' => 'Pour un rapport de stage complet.', 'prix' => 5000, 'tokens_inclus' => 50000, 'duree_jours' => 30],
            ['nom' => 'Premium', 'description' => 'Pour les projets intensifs et plusieurs rapports.', 'prix' => 15000, 'tokens_inclus' => 150000, 'duree_jours' => 30],
        ] as $plan) {
            SubscriptionPlan::updateOrCreate(['nom' => $plan['nom']], $plan + ['actif' => true]);
        }
    }
}
