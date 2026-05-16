<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->data ?? [];

        return [
            'id' => $this->id,
            'type' => $this->type,
            'job_id' => $data['job_id'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'bid_id' => $data['bid_id'] ?? null,
            'is_read' => !is_null($this->read_at),
            'created_at' => $this->created_at,
        ];
    }
}
