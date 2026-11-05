<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GenerationSession extends Model
{
    protected $fillable = [
        'user_id', 'rapport_id', 'type_operation', 'modele', 'statut',
        'tokens_consommes', 'prompt', 'reponse', 'error_message',
    ];

    protected $casts = ['tokens_consommes' => 'integer'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'rapport_id');
    }
}
