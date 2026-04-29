<?php

namespace App\Http\Resources\Api\V1\Reviews;

use App\Http\Resources\Api\V1\Users\PublicUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assignment_id' => $this->assignment_id,
            'reviewer' => $this->whenLoaded('reviewer', fn() => new PublicUserResource($this->reviewer)),
            'reviewee' => $this->whenLoaded('reviewee', fn() => new PublicUserResource($this->reviewee)),
            'rating' => $this->rating,
            'comment' => $this->comment,
            'created_at' => $this->created_at,
        ];
    }
}
