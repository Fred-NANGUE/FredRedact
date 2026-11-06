<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatConversation extends Model
{
    protected $fillable = ['user_id', 'rapport_id', 'titre'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'rapport_id');
    }

    public function dialogues(): HasMany
    {
        return $this->hasMany(Dialogue::class, 'chat_conversation_id');
    }
}
