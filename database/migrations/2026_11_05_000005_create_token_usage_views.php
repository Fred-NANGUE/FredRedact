<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE VIEW v_user_token_balance AS
            SELECT user_id, COALESCE(SUM(quantite), 0) AS tokens_restants
            FROM token_usages
            GROUP BY user_id');

        DB::statement("CREATE VIEW v_report_token_usage AS
            SELECT t.user_id, r.id AS rapport_id, r.titre,
                -SUM(t.quantite) AS tokens_utilises
            FROM token_usages AS t
            INNER JOIN rapports AS r ON r.id = t.rapport_id
            WHERE t.quantite < 0
                AND t.type_operation IN ('generation', 'humanisation', 'dialogue', 'webographie', 'plan')
            GROUP BY t.user_id, r.id, r.titre");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_report_token_usage');
        DB::statement('DROP VIEW IF EXISTS v_user_token_balance');
    }
};
