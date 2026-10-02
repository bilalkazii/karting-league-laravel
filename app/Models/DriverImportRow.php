<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverImportRow extends Model
{
    protected $fillable = [
        'driver_import_id',
        'row_number',
        'raw_name',
        'raw_email',
        'raw_phone',
        'raw_event_label',
        'raw_finish_position',
        'raw_points_displayed',
        'match_status',
        'matched_driver_id',
        'match_score',
        'issues',
        'created_driver_id',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'match_score' => 'float',
            'issues' => 'array',
            'applied_at' => 'datetime',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(DriverImport::class, 'driver_import_id');
    }

    public function matchedDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'matched_driver_id');
    }

    public function createdDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'created_driver_id');
    }

    /** True when a person still has to decide how this row is handled. */
    public function needsReview(): bool
    {
        return in_array($this->match_status, ['ambiguous', 'uncertain'], true);
    }

    public function isApplied(): bool
    {
        return $this->applied_at !== null;
    }
}
