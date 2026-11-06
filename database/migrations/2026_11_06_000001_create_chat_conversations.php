<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rapport_id')->nullable()->constrained('rapports')->nullOnDelete();
            $table->string('titre', 100)->default('Nouvelle discussion');
            $table->timestamps();
            $table->index(['user_id', 'rapport_id', 'updated_at']);
        });

        Schema::table('dialogues', function (Blueprint $table) {
            $table->foreignId('chat_conversation_id')->nullable()->constrained('chat_conversations')->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('attachment_name')->nullable();
        });

        $groups = DB::table('dialogues')
            ->select('user_id', 'rapport_id')
            ->groupBy('user_id', 'rapport_id')
            ->get();

        foreach ($groups as $group) {
            $firstMessage = DB::table('dialogues')
                ->where('user_id', $group->user_id)
                ->when(
                    $group->rapport_id === null,
                    fn ($query) => $query->whereNull('rapport_id'),
                    fn ($query) => $query->where('rapport_id', $group->rapport_id)
                )
                ->oldest('id')
                ->first(['question']);

            $reportTitle = $group->rapport_id
                ? DB::table('rapports')->where('id', $group->rapport_id)->value('titre')
                : null;
            $title = $reportTitle ?: mb_substr(trim(strip_tags($firstMessage->question ?? 'Discussion générale')), 0, 100);
            $now = now();
            $conversationId = DB::table('chat_conversations')->insertGetId([
                'user_id' => $group->user_id,
                'rapport_id' => $group->rapport_id,
                'titre' => $title !== '' ? $title : 'Discussion générale',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('dialogues')
                ->where('user_id', $group->user_id)
                ->when(
                    $group->rapport_id === null,
                    fn ($query) => $query->whereNull('rapport_id'),
                    fn ($query) => $query->where('rapport_id', $group->rapport_id)
                )
                ->update(['chat_conversation_id' => $conversationId]);
        }
    }

    public function down(): void
    {
        Schema::table('dialogues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('chat_conversation_id');
            $table->dropConstrainedForeignId('document_id');
            $table->dropColumn('attachment_name');
        });

        Schema::dropIfExists('chat_conversations');
    }
};
