<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dialogue extends Model
{
    protected $fillable = [
        'user_id', 'rapport_id', 'generation_session_id', 'chat_conversation_id',
        'document_id', 'attachment_name', 'question', 'reponse',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'rapport_id');
    }

    public function generationSession(): BelongsTo
    {
        return $this->belongsTo(GenerationSession::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
