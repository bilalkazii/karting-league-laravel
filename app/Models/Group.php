<?php

namespace App\Models;

use App\Enums\GroupPrivacy;
use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'logo_initials',
        'logo_color',
        'logo_text_color',
        'cover_color',
        'privacy',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'privacy' => GroupPrivacy::class,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Driver::class, 'group_members')
            ->withPivot('role', 'availability', 'joined_at');
    }

    public function memberDrivers(): BelongsToMany
    {
        return $this->members();
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function races(): HasMany
    {
        return $this->hasMany(Race::class);
    }

    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }
}
