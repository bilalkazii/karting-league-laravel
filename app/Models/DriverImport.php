<?php

namespace App\Models;

use Database\Factories\DriverImportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DriverImport extends Model
{
    /** @use HasFactory<DriverImportFactory> */
    use HasFactory;

    public const PREVIEW = 'preview';

    public const APPLIED = 'applied';

    public const DISCARDED = 'discarded';

    protected $fillable = [
        'group_id',
        'created_by',
        'original_filename',
        'status',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(DriverImportRow::class)->orderBy('row_number');
    }

    public function isPending(): bool
    {
        return $this->status === self::PREVIEW;
    }
}
