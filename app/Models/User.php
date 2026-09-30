<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\DriverProfileVisibility;
use App\Enums\NotificationType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function driver(): HasOneThrough
    {
        return $this->hasOneThrough(Driver::class, Profile::class, 'user_id', 'profile_id', 'id', 'id');
    }

    public function preferences(): HasMany
    {
        return $this->hasMany(UserPreference::class);
    }

    public function preferenceValue(string $key, ?string $default = null): ?string
    {
        $value = $this->preferences()->where('key', $key)->value('value');

        return $value ?? $default;
    }

    public function setPreference(string $key, string $value): void
    {
        UserPreference::query()->updateOrCreate(
            ['user_id' => $this->getKey(), 'key' => $key],
            ['value' => $value],
        );
    }

    public function notificationEnabled(NotificationType $type): bool
    {
        return $this->preferenceValue($type->preferenceKey(), '1') === '1';
    }

    public function driverProfileVisibility(): DriverProfileVisibility
    {
        $value = $this->preferenceValue(DriverProfileVisibility::preferenceKey());

        return $value !== null
            ? (DriverProfileVisibility::tryFrom($value) ?? DriverProfileVisibility::Public)
            : DriverProfileVisibility::Public;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
