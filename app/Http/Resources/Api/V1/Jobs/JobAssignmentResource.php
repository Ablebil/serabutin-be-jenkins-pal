<?php

namespace App\Http\Resources\Api\V1\Jobs;

use App\Http\Resources\Api\V1\Users\PublicUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->attributes->get('auth_user');

        return [
            'assignment_id' => $this->id,
            'job' => $this->whenLoaded('job', fn() => new JobResource($this->job)),
            'worker' => $this->whenLoaded('worker', fn() => new PublicUserResource($this->worker)),
            'has_reviewed' => $this->when(
                !is_null($user),
                fn() => $this->hasReviewedBy($user),
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
