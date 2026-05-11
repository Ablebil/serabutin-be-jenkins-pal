<?php

namespace App\Http\Controllers\Api\V1\Reviews;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Reviews\StoreReviewRequest;
use App\Http\Resources\Api\V1\Reviews\ReviewResource;
use App\Models\Job;
use App\Models\JobAssignment;
use App\Models\Review;
use App\Models\UserProfile;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    public function store(StoreReviewRequest $request, string $id): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        $job = Job::query()->find($id);

        if (is_null($job) || !is_null($job->deleted_at)) {
            return $this->error(__('reviews.store.job_not_found'), 404);
        }

        if ($job->status !== 'completed') {
            return $this->error(__('reviews.store.job_not_completed'), 403);
        }

        $payload = $request->validated();
        $assignmentId = $payload['assignment_id'];

        if ($user->role === 'client') {
            if ($job->client_id !== $user->id) {
                return $this->error(__('auth.jwt.forbidden'), 403);
            }

            $assignment = JobAssignment::query()
                ->where('id', $assignmentId)
                ->where('job_id', $job->id)
                ->first();

            if (is_null($assignment)) {
                return $this->error(__('reviews.store.assignment_not_found'), 404);
            }

            $reviewerId = $user->id;
            $revieweeId = $assignment->worker_id;
        } elseif ($user->role === 'worker') {
            $assignment = JobAssignment::query()
                ->where('id', $assignmentId)
                ->where('job_id', $job->id)
                ->where('worker_id', $user->id)
                ->first();

            if (is_null($assignment)) {
                return $this->error(__('reviews.store.assignment_not_found'), 404);
            }

            $reviewerId = $user->id;
            $revieweeId = $job->client_id;
        } else {
            return $this->error(__('auth.jwt.forbidden'), 403);
        }

        $alreadyReviewed = Review::query()
            ->where('assignment_id', $assignmentId)
            ->where('reviewer_id', $reviewerId)
            ->exists();

        if ($alreadyReviewed) {
            return $this->error(__('reviews.store.already_reviewed'), 409);
        }

        try {
            $review = DB::transaction(function () use ($payload, $assignmentId, $reviewerId, $revieweeId) {
                $review = Review::query()->create([
                    'assignment_id' => $assignmentId,
                    'reviewer_id' => $reviewerId,
                    'reviewee_id' => $revieweeId,
                    'rating' => $payload['rating'],
                    'comment' => $payload['comment'] ?? null,
                ]);

                $review->refresh();

                $avgRating = Review::query()
                    ->where('reviewee_id', $revieweeId)
                    ->avg('rating');

                UserProfile::query()
                    ->where('user_id', $revieweeId)
                    ->update(['avg_rating' => $avgRating]);

                return $review;
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505') {
                return $this->error(__('reviews.store.already_reviewed'), 409);
            }

            throw $exception;
        }

        $review->load(['reviewer', 'reviewee', 'assignment.job.category']);

        return $this->success(
            __('reviews.store.success'),
            new ReviewResource($review),
            201
        );
    }
}
