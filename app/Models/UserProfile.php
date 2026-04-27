<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'bio',
        'location_district',
        'location_city',
        'avatar_url',
        'phone',
        'avg_rating',
        'total_jobs_posted',
        'total_jobs_completed',
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
            'avg_rating' => 'float',
            'total_jobs_posted' => 'integer',
            'total_jobs_completed' => 'integer',
        ];
    }

    /**
     * Get the owner user for this profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
