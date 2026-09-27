<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'email', 'password', 'phone', 'profile_photo', 'city', 'address'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const RoleAdmin = 'admin';

    public const RoleUser = 'user';

    protected $attributes = [
        'role' => self::RoleUser,
    ];

    public function isAdmin(): bool
    {
        return $this->role === self::RoleAdmin;
    }

    public function profilePhotoUrl(): ?string
    {
        return $this->profile_photo ? Storage::disk('public')->url($this->profile_photo) : null;
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function rentalBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'renter_user_id');
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(UserPaymentMethod::class)->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
