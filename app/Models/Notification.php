<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Notifications\DatabaseNotification;

class Notification extends DatabaseNotification
{
    use HasUuids;

    /**
     * Get the user who triggered this notification.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Mark this notification as read if it is unread.
     */
    public function markAsRead(): void
    {
        if (is_null($this->read_at)) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }

    /**
     * Mark all notifications as read for the given user.
     */
    public static function markAllAsReadFor(User $user): int
    {
        return static::query()
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}

