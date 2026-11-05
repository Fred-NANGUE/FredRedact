<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TokenUsage extends Model
{
    // La table token_usages n'a pas de colonne updated_at
    public const UPDATED_AT = null;

    // Types qui représentent une vraie consommation par l'IA
    public const CONSUMPTION_TYPES = ['generation', 'humanisation', 'dialogue', 'webographie', 'plan'];

    protected $table = 'token_usages';

    protected $fillable = [
        'user_id', 'subscription_id', 'rapport_id', 'generation_session_id',
        'quantite', 'type_operation', 'description',
    ];

    protected $casts = ['quantite' => 'integer'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
