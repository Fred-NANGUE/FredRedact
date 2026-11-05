<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    protected $fillable = [
        'user_id', 'rapport_id', 'nom_original', 'chemin', 'mime_type', 'taille_bytes', 'texte_extrait',
    ];

    protected $casts = ['taille_bytes' => 'integer'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'rapport_id');
    }
}
