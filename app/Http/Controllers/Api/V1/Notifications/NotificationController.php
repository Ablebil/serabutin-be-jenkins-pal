<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->success(
            __('notification.index.success'),
            NotificationResource::collection(collect())
        );
    }

    public function read(Request $request, string $id): JsonResponse
    {
        return $this->success(__('notification.read.success'));
    }

    public function readAll(Request $request): JsonResponse
    {
        return $this->success(__('notification.read_all.success'));
    }
}
