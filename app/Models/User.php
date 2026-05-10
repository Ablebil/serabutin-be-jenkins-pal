<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory;
    use HasUuids;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'password_hash',
        'full_name',
        'role',
        'is_verified',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password_hash',
    ];

    /**
     * Indicates if the model should be auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the primary key.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'is_verified' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Return the hashed password column used for authentication.
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    /**
     * Get the profile associated with the user.
     */
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    /**
     * Get all jobs posted by this client.
     */
    public function postedJobs(): HasMany
    {
        return $this->hasMany(Job::class, 'client_id');
    }

    /**
     * Get all bids placed by this worker.
     */
    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class, 'worker_id');
    }

    /**
     * Get all job assignments for this worker.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(JobAssignment::class, 'worker_id');
    }

    /**
     * Get all reviews received by this user (as reviewee).
     */
    public function receivedReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewee_id');
    }

    /**
     * Get all refresh token sessions for the user.
     */
    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    /**
     * Get all notifications sent by this user.
     */
    public function sentNotifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'actor_id');
    }
}
