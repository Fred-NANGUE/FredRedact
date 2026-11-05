<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Webography extends Model
{
    protected $fillable = ['user_id', 'rapport_id', 'titre', 'url', 'resume', 'lien_verifie', 'verifie_le'];

    protected $casts = ['lien_verifie' => 'boolean', 'verifie_le' => 'datetime'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'rapport_id');
    }
}
