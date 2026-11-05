<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Internship extends Model
{
    protected $fillable = [
        'titre', 'entreprise', 'adresse_entreprise', 'poste', 'encadrant_nom',
        'encadrant_email', 'date_debut', 'date_fin', 'theme', 'problematique',
    ];

    protected $casts = ['date_debut' => 'date', 'date_fin' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'internship_id');
    }
}
