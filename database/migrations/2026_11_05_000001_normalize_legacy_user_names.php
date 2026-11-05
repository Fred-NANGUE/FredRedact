<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasName = Schema::hasColumn('users', 'name');
        $hasNom = Schema::hasColumn('users', 'nom');
        $hasPrenom = Schema::hasColumn('users', 'prenom');

        if (! $hasName && ! $hasNom && ! $hasPrenom) {
            throw new RuntimeException('La table users doit contenir name ou les anciennes colonnes nom/prenom.');
        }

        if (! $hasName) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('name')->nullable();
            });

            $columns = array_filter(['id', $hasNom ? 'nom' : null, $hasPrenom ? 'prenom' : null]);
            DB::table('users')->select($columns)->orderBy('id')->chunkById(500, function ($users) use ($hasNom, $hasPrenom) {
                foreach ($users as $user) {
                    $parts = [];
                    if ($hasNom && trim((string) $user->nom) !== '') {
                        $parts[] = trim((string) $user->nom);
                    }
                    if ($hasPrenom && trim((string) $user->prenom) !== '') {
                        $parts[] = trim((string) $user->prenom);
                    }

                    DB::table('users')->where('id', $user->id)->update(['name' => implode(' ', $parts)]);
                }
            });
        }

        if ($hasNom) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('nom')->nullable()->change();
            });
        }

        if ($hasPrenom) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('prenom')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Keep the normalized name column instead of deleting existing user data.
    }
};
