<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('etudiant')->after('password')->index();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 50)->unique();
            $table->text('description')->nullable();
            $table->decimal('prix', 10, 2)->default(0);
            $table->unsignedInteger('tokens_inclus')->default(0);
            $table->unsignedInteger('duree_jours')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('internships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('titre');
            $table->string('entreprise');
            $table->string('adresse_entreprise')->nullable();
            $table->string('poste')->nullable();
            $table->string('encadrant_nom')->nullable();
            $table->string('encadrant_email')->nullable();
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->text('theme')->nullable();
            $table->text('problematique')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'date_debut']);
        });

        Schema::create('rapports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('internship_id')->nullable()->constrained('internships')->nullOnDelete();
            $table->string('titre');
            $table->string('statut')->default('brouillon');
            $table->unsignedTinyInteger('progression')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('report_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rapport_id')->constrained('rapports')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('report_sections')->cascadeOnDelete();
            $table->string('titre');
            $table->string('type')->default('section');
            $table->longText('contenu')->nullable();
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();
            $table->index(['rapport_id', 'ordre']);
        });

        Schema::create('section_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_section_id')->constrained('report_sections')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->longText('contenu');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('generation_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rapport_id')->nullable()->constrained('rapports')->nullOnDelete();
            $table->string('type_operation', 30);
            $table->string('modele', 100)->nullable();
            $table->string('statut', 20)->default('en_cours');
            $table->unsignedInteger('tokens_consommes')->default(0);
            $table->longText('prompt')->nullable();
            $table->longText('reponse')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('dialogues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rapport_id')->nullable()->constrained('rapports')->nullOnDelete();
            $table->foreignId('generation_session_id')->nullable()->constrained('generation_sessions')->nullOnDelete();
            $table->text('question');
            $table->longText('reponse');
            $table->timestamps();
            $table->index(['user_id', 'rapport_id', 'created_at']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rapport_id')->nullable()->constrained('rapports')->nullOnDelete();
            $table->string('nom_original');
            $table->string('chemin');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('taille_bytes');
            $table->longText('texte_extrait')->nullable();
            $table->timestamps();
        });

        Schema::create('webographies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rapport_id')->constrained('rapports')->cascadeOnDelete();
            $table->string('titre');
            $table->text('url');
            $table->text('resume')->nullable();
            $table->boolean('lien_verifie')->default(false);
            $table->timestamp('verifie_le')->nullable();
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('subscription_plans');
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->unsignedInteger('tokens_alloues')->default(0);
            $table->decimal('prix_paye', 10, 2)->default(0);
            $table->string('statut', 20)->default('en_attente');
            $table->string('mode_paiement', 50)->nullable();
            $table->string('transaction_id', 100)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'statut']);
        });

        Schema::create('token_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->foreignId('rapport_id')->nullable()->constrained('rapports')->nullOnDelete();
            $table->foreignId('generation_session_id')->nullable()->constrained('generation_sessions')->nullOnDelete();
            $table->integer('quantite');
            $table->string('type_operation', 30);
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_usages');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('webographies');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('dialogues');
        Schema::dropIfExists('generation_sessions');
        Schema::dropIfExists('section_versions');
        Schema::dropIfExists('report_sections');
        Schema::dropIfExists('rapports');
        Schema::dropIfExists('internships');
        Schema::dropIfExists('subscription_plans');
        Schema::dropIfExists('personal_access_tokens');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
