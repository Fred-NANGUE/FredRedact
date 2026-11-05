<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('subscription_plans')->where('nom', 'Pro')->update(['prix' => 5000]);
        DB::table('subscription_plans')->where('nom', 'Premium')->update(['prix' => 15000]);
    }

    public function down(): void
    {
        DB::table('subscription_plans')->where('nom', 'Pro')->update(['prix' => 9.99]);
        DB::table('subscription_plans')->where('nom', 'Premium')->update(['prix' => 24.99]);
    }
};
