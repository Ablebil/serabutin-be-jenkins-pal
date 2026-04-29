<?php

namespace App\Http\Resources\Api\V1\Jobs;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client' => $this->whenLoaded('client', fn() => [
                'id' => $this->client->id,
                'full_name' => $this->client->full_name,
                'role' => $this->client->role,
                'created_at' => $this->client->created_at,
                'updated_at' => $this->client->updated_at,
            ]),
            'category' => $this->whenLoaded('category', fn() => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
                'is_active' => $this->category->is_active,
                'created_at' => $this->category->created_at,
                'updated_at' => $this->category->updated_at,
            ]),
            'title' => $this->title,
            'description' => $this->description,
            'budget_min' => $this->budget_min,
            'budget_max' => $this->budget_max,
            'workers_needed' => $this->workers_needed,
            'location_district' => $this->location_district,
            'location_city' => $this->location_city,
            'status' => $this->status,
            'start_at' => $this->start_at,
            'deadline_at' => $this->deadline_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
