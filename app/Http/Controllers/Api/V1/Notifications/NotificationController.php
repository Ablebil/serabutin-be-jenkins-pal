<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        $query = Notification::query()
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->latest();

        if ($request->filled('is_read')) {
            $isRead = filter_var($request->input('is_read'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if (!is_null($isRead)) {
                $query->when(
                    $isRead,
                    fn ($builder) => $builder->whereNotNull('read_at'),
                    fn ($builder) => $builder->whereNull('read_at')
                );
            }
        }

        $paginator = $query->paginate(
            perPage: (int) $request->input('limit', 10),
            page: (int) $request->input('page', 1)
        );

        $unreadCount = Notification::query()
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->count();

        $response = $this->paginated(
            __('notification.index.success'),
            NotificationResource::collection($paginator),
            $paginator
        );

        $payload = $response->getData(true);
        $payload['meta']['unread_count'] = $unreadCount;

        return $response->setData($payload);
    }

    public function read(Request $request, string $id): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        $notification = Notification::query()->where('id', $id)->first();

        if (is_null($notification)) {
            return $this->error(__('notification.read.not_found'), 404);
        }

        if ($notification->notifiable_type !== User::class || $notification->notifiable_id !== $user->id) {
            return $this->error(__('notification.read.forbidden'), 403);
        }

        $notification->markAsRead();

        return $this->success(
            __('notification.read.success'),
            new NotificationResource($notification)
        );
    }

    public function readAll(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        Notification::markAllAsReadFor($user);

        return $this->success(__('notification.read_all.success'));
    }
}
