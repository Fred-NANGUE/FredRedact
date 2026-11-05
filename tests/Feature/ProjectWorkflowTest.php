<?php

namespace Tests\Feature;

use App\Models\Internship;
use App\Models\SubscriptionPlan;
use App\Models\TokenUsage;
use App\Models\User;
use App\Services\AIService;
use App\Services\SubscriptionService;
use App\Services\TokenService;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_user_name_columns_are_migrated_without_losing_names(): void
    {
        User::query()->delete();
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('name', 'nom');
            $table->string('prenom')->nullable();
        });
        DB::table('users')->insert([
            'nom' => 'NANGUE FEULEFACK',
            'prenom' => 'Fred Miguel',
            'email' => 'legacy@example.test',
            'password' => bcrypt('password'),
            'role' => 'etudiant',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require base_path('database/migrations/2026_11_05_000001_normalize_legacy_user_names.php');
        $migration->up();

        $this->assertSame('NANGUE FEULEFACK Fred Miguel', User::where('email', 'legacy@example.test')->value('name'));
        $this->assertTrue(Schema::hasColumn('users', 'nom'));
        $this->assertTrue(Schema::hasColumn('users', 'prenom'));
        $newUser = User::create([
            'name' => 'Nouvel utilisateur',
            'email' => 'new-user@example.test',
            'password' => 'password',
            'role' => 'etudiant',
        ]);
        $this->assertTrue($newUser->exists);
    }

    public function test_legacy_internship_columns_are_migrated_and_existing_values_preserved(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::drop('internships');
        Schema::create('internships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('entreprise', 200);
            $table->string('secteur_activite', 150)->nullable();
            $table->text('adresse_entreprise')->nullable();
            $table->string('tuteur_entreprise', 150)->nullable();
            $table->string('poste_occupe', 150)->nullable();
            $table->string('theme_stage', 255);
            $table->date('periode_debut')->nullable();
            $table->date('periode_fin')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::enableForeignKeyConstraints();

        $user = User::factory()->create();
        DB::table('internships')->insert([
            'user_id' => $user->id,
            'entreprise' => 'AFRICA GOLDEN BANK',
            'tuteur_entreprise' => 'KUATE Godwin',
            'poste_occupe' => 'Assistant DBA',
            'theme_stage' => 'Monitoring d’une base de données',
            'periode_debut' => '2025-06-03',
            'periode_fin' => '2025-08-03',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require base_path('database/migrations/2026_11_05_000002_normalize_legacy_internships.php');
        $migration->up();

        $internship = Internship::firstOrFail();
        $this->assertSame('Stage - AFRICA GOLDEN BANK', $internship->titre);
        $this->assertSame('Assistant DBA', $internship->poste);
        $this->assertSame('KUATE Godwin', $internship->encadrant_nom);
        $this->assertSame('Monitoring d’une base de données', $internship->theme);
        $this->assertSame('2025-06-03', $internship->date_debut->toDateString());
        $this->assertSame('2025-08-03', $internship->date_fin->toDateString());

        $newInternship = $user->internships()->create([
            'titre' => 'Nouveau stage',
            'entreprise' => 'Entreprise test',
        ]);
        $this->assertSame('Nouveau stage', $newInternship->fresh()->titre);
    }

    public function test_student_can_register_and_receives_free_tokens(): void
    {
        SubscriptionPlan::create([
            'nom' => 'Free',
            'prix' => 0,
            'tokens_inclus' => 5000,
            'duree_jours' => null,
            'actif' => true,
        ]);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Étudiant Exemple',
            'email' => 'student@example.test',
            'password' => 'password-123',
            'password_confirmation' => 'password-123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.role', 'etudiant')
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);
        $this->assertDatabaseHas('subscriptions', ['user_id' => $response->json('user.id'), 'statut' => 'actif']);
        $this->assertDatabaseHas('token_usages', ['user_id' => $response->json('user.id'), 'quantite' => 5000]);
    }

    public function test_login_logout_and_password_reset_revoke_access_tokens(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.test']);
        $login = $this->postJson('/api/auth/login', [
            'email' => 'reset@example.test',
            'password' => 'password',
        ])->assertOk();
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $user->id]);

        $this->withToken($login->json('token'))->postJson('/api/auth/logout')->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);

        $this->postJson('/api/auth/password/forgot', ['email' => $user->email])->assertOk();
        $resetToken = null;
        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use (&$resetToken) {
            $resetToken = $notification->token;

            return true;
        });
        $this->postJson('/api/auth/password/reset', [
            'email' => $user->email,
            'token' => $resetToken,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
    }

    public function test_google_sign_in_verifies_the_identity_and_creates_a_student_account(): void
    {
        config(['services.google.client_id' => 'fredredact-google-client']);
        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'aud' => 'fredredact-google-client',
                'iss' => 'https://accounts.google.com',
                'exp' => now()->addHour()->timestamp,
                'email_verified' => 'true',
                'sub' => 'google-user-123',
                'email' => 'Google.User@example.test',
                'name' => 'Google User',
                'picture' => 'https://lh3.googleusercontent.com/a/google-avatar',
            ]),
        ]);

        $response = $this->postJson('/api/auth/google', ['credential' => 'signed-google-id-token'])
            ->assertOk()
            ->assertJsonPath('user.email', 'google.user@example.test')
            ->assertJsonPath('user.role', 'etudiant')
            ->assertJsonStructure(['token']);

        $user = User::findOrFail($response->json('user.id'));
        $this->assertSame('google-user-123', $user->google_id);
        $this->assertSame('https://lh3.googleusercontent.com/a/google-avatar', $user->google_avatar_url);
        $this->assertNotNull($user->email_verified_at);
        $this->withToken($response->json('token'))
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('google_avatar_url', 'https://lh3.googleusercontent.com/a/google-avatar');
    }

    public function test_cinetpay_checkout_stays_pending_until_a_verified_callback_and_is_idempotent(): void
    {
        config([
            'services.cinetpay.api_key' => 'test-api-key',
            'services.cinetpay.site_id' => 'test-site-id',
            'services.cinetpay.base_url' => 'https://api-checkout.cinetpay.com',
        ]);
        Http::fake([
            'https://api-checkout.cinetpay.com/v2/payment' => Http::response([
                'code' => '201',
                'data' => ['payment_url' => 'https://checkout.example.test/pay'],
            ]),
            'https://api-checkout.cinetpay.com/v2/payment/check' => function ($request) {
                return Http::response([
                    'code' => '00',
                    'data' => [
                        'status' => 'ACCEPTED',
                        'amount' => 5000,
                        'currency' => 'XAF',
                        'transaction_id' => $request->data()['transaction_id'],
                    ],
                ]);
            },
        ]);

        $user = User::factory()->create();
        $plan = SubscriptionPlan::create([
            'nom' => 'Pro',
            'prix' => 5000,
            'tokens_inclus' => 50000,
            'duree_jours' => 30,
            'actif' => true,
        ]);
        Sanctum::actingAs($user);

        $checkout = $this->postJson('/api/billing/checkout', [
            'plan_id' => $plan->id,
            'mode_paiement' => 'orange_money',
            'customer_phone' => '+237600000000',
        ])->assertCreated()
            ->assertJsonPath('checkout_url', 'https://checkout.example.test/pay');

        $transactionId = $checkout->json('subscription.transaction_id');
        $this->assertSame('en_attente', $checkout->json('subscription.statut'));
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'transaction_id' => $transactionId,
            'statut' => 'en_attente',
            'prix_paye' => 0,
            'devise' => 'XAF',
        ]);

        $notification = $this->postJson('/api/billing/cinetpay/notify', ['cpm_trans_id' => $transactionId]);
        $notification->assertOk();
        $this->postJson('/api/billing/cinetpay/notify', ['cpm_trans_id' => $transactionId])->assertOk();
        $this->assertDatabaseHas('subscriptions', [
            'transaction_id' => $transactionId,
            'statut' => 'actif',
            'prix_paye' => 5000,
        ]);
        $this->assertDatabaseCount('token_usages', 1);
    }

    public function test_student_can_manage_owned_stage_report_and_section_versions(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $internshipResponse = $this->postJson('/api/internships', [
            'titre' => 'Stage de développement',
            'entreprise' => 'Exemple SARL',
            'theme' => 'Automatisation des rapports',
            'date_debut' => '2025-01-01',
        ])->assertCreated();
        $internshipId = $internshipResponse->json('id');
        $this->putJson("/api/internships/{$internshipId}", ['date_fin' => '2024-12-31'])->assertUnprocessable();

        $reportResponse = $this->postJson('/api/reports', [
            'titre' => 'Rapport de stage',
            'internship_id' => $internshipId,
        ])->assertCreated();
        $reportId = $reportResponse->json('id');

        $sectionResponse = $this->postJson("/api/reports/{$reportId}/sections", [
            'titre' => 'Introduction',
            'type' => 'introduction',
        ])->assertCreated();
        $sectionId = $sectionResponse->json('id');

        $this->putJson("/api/reports/{$reportId}/sections/{$sectionId}", [
            'titre' => 'Introduction',
            'contenu' => 'Première version.',
        ])->assertOk();
        $this->putJson("/api/reports/{$reportId}/sections/{$sectionId}", [
            'titre' => 'Introduction',
            'contenu' => 'Version finale.',
        ])->assertOk();

        $this->getJson("/api/reports/{$reportId}")
            ->assertOk()
            ->assertJsonPath('progression', 100)
            ->assertJsonPath('sections.0.contenu', 'Version finale.');
        $this->getJson("/api/reports/{$reportId}/sections/{$sectionId}/versions")
            ->assertOk()
            ->assertJsonPath('data.0.contenu', 'Première version.');
    }

    public function test_student_can_export_report_as_pdf_and_docx(): void
    {
        $user = User::factory()->create();
        $report = $user->reports()->create(['titre' => 'Rapport à exporter']);
        Sanctum::actingAs($user);
        Storage::fake('local');
        config([
            'services.ai_service.url' => 'http://127.0.0.1:5001',
            'services.ai_service.key' => 'test-key',
        ]);
        Http::fake(['*/api/export' => Http::response('Generated report content', 200)]);

        foreach (['pdf', 'docx'] as $format) {
            $response = $this->postJson("/api/reports/{$report->id}/export", ['format' => $format])
                ->assertOk()
                ->assertHeader('content-disposition');

            $this->assertStringContainsString('.'.$format, $response->headers->get('content-disposition'));
            $this->assertCount($format === 'pdf' ? 1 : 2, Storage::disk('local')->allFiles('exports/'.$user->id));
        }

        Http::assertSentCount(2);
    }

    public function test_student_cannot_read_another_users_report(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $report = $owner->reports()->create(['titre' => 'Privé']);
        Sanctum::actingAs($other);

        $this->getJson("/api/reports/{$report->id}")->assertNotFound();
    }

    public function test_student_cannot_attach_another_users_internship(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $internship = $owner->internships()->create([
            'titre' => 'Stage privé',
            'entreprise' => 'Société privée',
        ]);
        Sanctum::actingAs($other);

        $this->postJson('/api/reports', [
            'titre' => 'Rapport',
            'internship_id' => $internship->id,
        ])->assertUnprocessable();
    }

    public function test_admin_user_management_is_role_protected(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);
        $this->getJson('/api/admin/users')->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/users')->assertOk();
        $this->putJson("/api/admin/users/{$admin->id}", ['role' => 'etudiant'])->assertUnprocessable();
    }

    public function test_billing_administration_uses_the_current_user_schema(): void
    {
        SubscriptionPlan::create([
            'nom' => 'Free',
            'prix' => 0,
            'tokens_inclus' => 5000,
            'duree_jours' => null,
            'actif' => true,
        ]);
        $student = User::factory()->create(['name' => 'Étudiant Test']);
        app(SubscriptionService::class)->ensureFreeSubscription($student);
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/billing/subscriptions')
            ->assertOk()
            ->assertJsonPath('data.0.user.name', 'Étudiant Test');
        $this->getJson("/api/admin/billing/users/{$student->id}/tokens")
            ->assertOk()
            ->assertJsonPath('user.name', 'Étudiant Test');
        $this->getJson('/api/admin/billing/stats')->assertOk();
    }

    public function test_token_views_match_the_ledger_and_report_usage_endpoint(): void
    {
        $user = User::factory()->create();
        $report = $user->reports()->create(['titre' => 'Rapport suivi']);
        TokenUsage::create([
            'user_id' => $user->id,
            'rapport_id' => $report->id,
            'quantite' => -240,
            'type_operation' => 'generation',
        ]);
        TokenUsage::create([
            'user_id' => $user->id,
            'quantite' => 1000,
            'type_operation' => 'recharge',
        ]);

        $this->assertSame(760, app(TokenService::class)->balance($user));
        Sanctum::actingAs($user);
        $this->getJson('/api/billing/tokens/by-report')
            ->assertOk()
            ->assertJsonPath('0.rapport_id', $report->id)
            ->assertJsonPath('0.tokens_utilises', 240);
    }

    public function test_document_upload_extracts_text_and_keeps_the_file_private(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->mock(AIService::class)
            ->shouldReceive('extract')
            ->once()
            ->andReturn(['text' => 'Texte extrait de l’image.']);

        $response = $this->post('/api/documents', [
            'file' => UploadedFile::fake()->image('preuve.png'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $document = $response->json();
        $this->assertSame('Texte extrait de l’image.', $document['texte_extrait']);
        Storage::disk('local')->assertExists($document['chemin']);
        $this->getJson("/api/documents/{$document['id']}/download")->assertOk();
        $this->deleteJson("/api/documents/{$document['id']}")->assertOk();
        Storage::disk('local')->assertMissing($document['chemin']);
    }

    public function test_ai_chat_records_dialogue_and_charges_the_token_ledger(): void
    {
        SubscriptionPlan::create([
            'nom' => 'Free',
            'prix' => 0,
            'tokens_inclus' => 5000,
            'duree_jours' => null,
            'actif' => true,
        ]);
        $user = User::factory()->create();
        app(SubscriptionService::class)->ensureFreeSubscription($user);
        Sanctum::actingAs($user);

        $this->mock(AIService::class)
            ->shouldReceive('invoke')
            ->once()
            ->with('/api/dialogue', \Mockery::type('array'))
            ->andReturn(['text' => 'Organisez votre rapport en chapitres.']);

        $this->postJson('/api/ai/chat', ['question' => 'Comment commencer ?'])
            ->assertOk()
            ->assertJsonPath('dialogue.reponse', 'Organisez votre rapport en chapitres.');

        $this->assertDatabaseHas('dialogues', ['user_id' => $user->id, 'question' => 'Comment commencer ?']);
        $this->assertDatabaseHas('token_usages', [
            'user_id' => $user->id,
            'subscription_id' => $user->subscriptions()->first()->id,
            'type_operation' => 'dialogue',
        ]);
        $movements = (int) TokenUsage::where('user_id', $user->id)->sum('quantite');
        $this->assertLessThan(5000, app(TokenService::class)->balance($user));
        $this->assertSame($movements, app(TokenService::class)->balance($user));
    }
}
