<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobAssignment extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'job_id',
        'bid_id',
        'worker_id',
        'client_id',
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
     * Get the worker assigned to this job.
     */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'worker_id');
    }

    /**
     * Get the client who owns this assignment.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * Get the job this assignment belongs to.
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /**
     * Get the reviews for this assignment.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'assignment_id');
    }

    /**
     * Check whether the given reviewer has already reviewed this assignment.
     */
    public function hasReviewedBy(?User $reviewer): bool
    {
        if (is_null($reviewer)) {
            return false;
        }

        if ($this->relationLoaded('reviews')) {
            return $this->reviews->contains('reviewer_id', $reviewer->id);
        }

        return $this->reviews()
            ->where('reviewer_id', $reviewer->id)
            ->exists();
    }
}
