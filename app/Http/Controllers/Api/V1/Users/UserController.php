<?php

namespace App\Http\Controllers\Api\V1\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Users\UpdateProfileRequest;
use App\Http\Resources\Api\V1\Bids\BidResource;
use App\Http\Resources\Api\V1\Jobs\JobResource;
use App\Http\Resources\Api\V1\Reviews\ReviewResource;
use App\Http\Resources\Api\V1\Users\PublicUserResource;
use App\Http\Resources\Api\V1\Users\PublicUserProfileResource;
use App\Http\Resources\Api\V1\Users\UserProfileResource;
use App\Http\Resources\Api\V1\Users\UserResource;
use App\Models\JobAssignment;
use App\Models\User;
use App\Services\Users\ProfileSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly ProfileSummaryService $profileSummary,
    ) {
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        $user->loadMissing('profile');

        $categoryRatings = $user->role === 'worker'
            ? $this->profileSummary->getCategoryRatings($user)
            : null;

        return $this->success(
            __('users.me.success'),
            [
                'user' => new UserResource($user),
                'profile' => new UserProfileResource($user->profile, $categoryRatings),
            ]
        );
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        $payload = $request->validated();

        if (array_key_exists('full_name', $payload)) {
            $user->full_name = $payload['full_name'];
            $user->save();
        }

        $allowedProfileFields = [
            'bio',
            'location_district',
            'location_city',
            'avatar_url',
        ];

        if ($user->role === 'worker') {
            $allowedProfileFields[] = 'phone';
        }

        $profileFields = array_intersect_key($payload, array_flip($allowedProfileFields));

        if (!empty($profileFields)) {
            $user->profile()->update($profileFields);
        }

        $user->loadMissing('profile');
        $user->profile->refresh();

        $categoryRatings = $user->role === 'worker'
            ? $this->profileSummary->getCategoryRatings($user)
            : null;

        return $this->success(
            __('users.update.success'),
            [
                'user' => new UserResource($user),
                'profile' => new UserProfileResource($user->profile, $categoryRatings),
            ]
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $target = User::query()
            ->where('id', $id)
            ->where('is_active', true)
            ->with('profile')
            ->first();

        if (is_null($target)) {
            return $this->error(__('users.show.not_found'), 404);
        }

        $showPhone = false;

        if ($target->role === 'worker') {
            $viewer = $request->attributes->get('auth_user');

            if (!is_null($viewer) && $viewer->role === 'client') {
                $showPhone = JobAssignment::query()
                    ->where('worker_id', $target->id)
                    ->where('client_id', $viewer->id)
                    ->exists();
            }
        }

        $categoryRatings = $target->role === 'worker'
            ? $this->profileSummary->getCategoryRatings($target)
            : null;

        return $this->success(
            __('users.show.success'),
            [
                'user' => new PublicUserResource($target),
                'profile' => new PublicUserProfileResource($target->profile, $showPhone, $categoryRatings),
            ]
        );
    }

    public function postedJobs(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        $query = $user->postedJobs()
            ->with(['client', 'category'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('category_slug')) {
            $query->whereHas('category', fn($q) => $q->where('slug', $request->input('category_slug')));
        }

        $paginator = $query->paginate(
            perPage: (int) $request->input('limit', 10)
        );

        return $this->paginated(
            __('users.posted_jobs.success'),
            JobResource::collection($paginator),
            $paginator
        );
    }

    public function bidHistory(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        $query = $user->bids()
            ->with(['worker', 'job.category', 'job.client'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $paginator = $query->paginate(
            perPage: (int) $request->input('limit', 10)
        );

        $items = $paginator->getCollection()->map(fn($bid) => [
            'bid' => new BidResource($bid),
            'job' => new JobResource($bid->job),
        ]);

        return $this->paginated(
            __('users.bid_history.success'),
            $items,
            $paginator
        );
    }

    public function assignments(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        $query = $user->assignments()
            ->with(['job.category', 'job.client'])
            ->latest();

        if ($request->filled('status')) {
            $query->whereHas('job', fn($q) => $q->where('status', $request->input('status')));
        }

        if ($request->filled('category_slug')) {
            $query->whereHas('job.category', fn($q) => $q->where('slug', $request->input('category_slug')));
        }

        $paginator = $query->paginate(
            perPage: (int) $request->input('limit', 10)
        );

        $jobs = $paginator->getCollection()->map(fn($assignment) => $assignment->job);

        return $this->paginated(
            __('users.assignments.success'),
            JobResource::collection($jobs),
            $paginator
        );
    }

    public function reviews(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        $query = $user->receivedReviews()
            ->with(['reviewer', 'reviewee'])
            ->latest();

        $paginator = $query->paginate(
            perPage: (int) $request->input('limit', 10)
        );

        return $this->paginated(
            __('users.reviews.success'),
            ReviewResource::collection($paginator),
            $paginator
        );
    }

    public function publicJobs(Request $request, string $id): JsonResponse
    {
        $target = User::query()->where('id', $id)->where('is_active', true)->first();

        if (is_null($target)) {
            return $this->error(__('users.show.not_found'), 404);
        }

        if ($target->role !== 'client') {
            return $this->error(__('users.public_jobs.not_a_client'), 403);
        }

        $query = $target->postedJobs()
            ->with(['client', 'category'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('category_slug')) {
            $query->whereHas('category', fn($q) => $q->where('slug', $request->input('category_slug')));
        }

        $paginator = $query->paginate(
            perPage: (int) $request->input('limit', 10)
        );

        return $this->paginated(
            __('users.public_jobs.success'),
            JobResource::collection($paginator),
            $paginator
        );
    }

    public function publicAssignments(Request $request, string $id): JsonResponse
    {
        $target = User::query()->where('id', $id)->where('is_active', true)->first();

        if (is_null($target)) {
            return $this->error(__('users.show.not_found'), 404);
        }

        if ($target->role !== 'worker') {
            return $this->error(__('users.public_assignments.not_a_worker'), 403);
        }

        $query = $target->assignments()
            ->with(['job.category', 'job.client'])
            ->latest();

        if ($request->filled('status')) {
            $query->whereHas('job', fn($q) => $q->where('status', $request->input('status')));
        }

        if ($request->filled('category_slug')) {
            $query->whereHas('job.category', fn($q) => $q->where('slug', $request->input('category_slug')));
        }

        $paginator = $query->paginate(
            perPage: (int) $request->input('limit', 10)
        );

        $jobs = $paginator->getCollection()->map(fn($assignment) => $assignment->job);

        return $this->paginated(
            __('users.public_assignments.success'),
            JobResource::collection($jobs),
            $paginator
        );
    }

    public function publicReviews(Request $request, string $id): JsonResponse
    {
        $target = User::query()->where('id', $id)->where('is_active', true)->first();

        if (is_null($target)) {
            return $this->error(__('users.show.not_found'), 404);
        }

        $query = $target->receivedReviews()
            ->with(['reviewer', 'reviewee'])
            ->latest();

        $paginator = $query->paginate(
            perPage: (int) $request->input('limit', 10)
        );

        return $this->paginated(
            __('users.public_reviews.success'),
            ReviewResource::collection($paginator),
            $paginator
        );
    }
}