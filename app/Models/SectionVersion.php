<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SectionVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['report_section_id', 'user_id', 'contenu'];

    public function section(): BelongsTo
    {
        return $this->belongsTo(ReportSection::class, 'report_section_id');
    }
}
