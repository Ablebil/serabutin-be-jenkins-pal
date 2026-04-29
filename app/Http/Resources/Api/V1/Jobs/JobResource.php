<?php

namespace App\Http\Resources\Api\V1\Jobs;

use App\Http\Resources\Api\V1\Categories\CategoryResource;
use App\Http\Resources\Api\V1\Users\PublicUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client' => $this->whenLoaded('client', fn() => new PublicUserResource($this->client)),
            'category' => $this->whenLoaded('category', fn() => new CategoryResource($this->category)),
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
