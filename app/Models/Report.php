<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    protected $table = 'rapports';

    protected $fillable = ['internship_id', 'titre', 'statut', 'progression'];

    protected $casts = ['progression' => 'integer'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function internship(): BelongsTo
    {
        return $this->belongsTo(Internship::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ReportSection::class, 'rapport_id')->orderBy('ordre')->orderBy('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'rapport_id');
    }

    public function webographies(): HasMany
    {
        return $this->hasMany(Webography::class, 'rapport_id');
    }
}
