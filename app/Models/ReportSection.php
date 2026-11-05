<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportSection extends Model
{
    protected $fillable = ['rapport_id', 'parent_id', 'titre', 'type', 'contenu', 'ordre'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'rapport_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('ordre')->orderBy('id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(SectionVersion::class, 'report_section_id')->latest('id');
    }
}
