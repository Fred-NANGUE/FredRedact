<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('devise', 3)->nullable()->after('prix_paye');
        });

        DB::table('subscriptions')->whereNull('devise')->update(['devise' => 'EUR']);

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('devise', 3)->default('XAF')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('devise');
        });
    }
};
