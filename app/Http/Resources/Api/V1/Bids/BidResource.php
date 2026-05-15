<?php

namespace App\Http\Resources\Api\V1\Bids;

use App\Http\Resources\Api\V1\Users\PublicUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BidResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'worker' => $this->whenLoaded('worker', fn() => new PublicUserResource($this->worker)),
            'proposed_price' => $this->proposed_price,
            'message' => $this->message,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
