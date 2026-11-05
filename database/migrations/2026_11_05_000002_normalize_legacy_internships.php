<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'titre' => fn (Blueprint $table) => $table->string('titre', 180)->nullable(),
            'poste' => fn (Blueprint $table) => $table->string('poste', 180)->nullable(),
            'encadrant_nom' => fn (Blueprint $table) => $table->string('encadrant_nom', 180)->nullable(),
            'encadrant_email' => fn (Blueprint $table) => $table->string('encadrant_email')->nullable(),
            'date_debut' => fn (Blueprint $table) => $table->date('date_debut')->nullable(),
            'date_fin' => fn (Blueprint $table) => $table->date('date_fin')->nullable(),
            'theme' => fn (Blueprint $table) => $table->text('theme')->nullable(),
            'problematique' => fn (Blueprint $table) => $table->text('problematique')->nullable(),
        ];

        foreach ($columns as $name => $definition) {
            if (! Schema::hasColumn('internships', $name)) {
                Schema::table('internships', $definition);
            }
        }

        if (Schema::hasColumn('internships', 'theme_stage')) {
            Schema::table('internships', function (Blueprint $table) {
                $table->string('theme_stage', 255)->nullable()->change();
            });
        }

        if (Schema::hasColumn('internships', 'theme_stage')) {
            DB::table('internships')->whereNull('theme')->update(['theme' => DB::raw('theme_stage')]);
        }
        if (Schema::hasColumn('internships', 'poste_occupe')) {
            DB::table('internships')->whereNull('poste')->update(['poste' => DB::raw('poste_occupe')]);
        }
        if (Schema::hasColumn('internships', 'tuteur_entreprise')) {
            DB::table('internships')->whereNull('encadrant_nom')->update(['encadrant_nom' => DB::raw('tuteur_entreprise')]);
        }
        if (Schema::hasColumn('internships', 'periode_debut')) {
            DB::table('internships')->whereNull('date_debut')->update(['date_debut' => DB::raw('periode_debut')]);
        }
        if (Schema::hasColumn('internships', 'periode_fin')) {
            DB::table('internships')->whereNull('date_fin')->update(['date_fin' => DB::raw('periode_fin')]);
        }

        DB::table('internships')
            ->whereNull('titre')
            ->select(['id', 'entreprise'])
            ->orderBy('id')
            ->chunkById(500, function ($internships) {
                foreach ($internships as $internship) {
                    DB::table('internships')->where('id', $internship->id)->update([
                        'titre' => 'Stage - '.$internship->entreprise,
                    ]);
                }
            });

        Schema::table('internships', function (Blueprint $table) {
            $table->string('titre', 180)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        // Keep normalized internship data; these columns may contain migrated legacy values.
    }
};
