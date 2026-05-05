<?php

namespace App\Http\Controllers\Api\V1\Jobs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Jobs\StoreJobRequest;
use App\Http\Requests\Api\V1\Jobs\UpdateJobRequest;
use App\Http\Requests\Api\V1\Jobs\UpdateJobStatusRequest;
use App\Http\Resources\Api\V1\Jobs\JobResource;
use App\Http\Resources\Api\V1\Users\PublicUserResource;
use App\Models\Job;
use App\Models\Review;
use App\Services\Users\ProfileSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JobController extends Controller
{
    public function __construct(
        private readonly ProfileSummaryService $profileSummary,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('limit', 10);

        $query = Job::query()
            ->where('status', 'open')
            ->with(['client', 'category']);

        if ($request->filled('category_slug')) {
            $query->whereHas('category', fn($q) => $q->where('slug', $request->input('category_slug')));
        }

        if ($request->filled('city')) {
            $query->where('location_city', $request->input('city'));
        }

        if ($request->filled('budget_min')) {
            $query->where('budget_max', '>=', $request->input('budget_min'));
        }

        if ($request->filled('budget_max')) {
            $query->where('budget_min', '<=', $request->input('budget_max'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        if ($request->filled('q')) {
            $search = $request->input('q');
            $query->whereRaw("to_tsvector('simple', title) @@ websearch_to_tsquery('simple', ?)", [$search]);
        }

        $paginator = $query->cursorPaginate(perPage: $perPage);

        return $this->cursor(
            __('jobs.index.success'),
            JobResource::collection($paginator->items()),
            $paginator
        );
    }

    public function store(StoreJobRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $job = DB::transaction(function () use ($payload, $request) {
            $job = Job::create([
                'client_id' => $request->attributes->get('auth_user')->id,
                'category_id' => $payload['category_id'],
                'title' => $payload['title'],
                'description' => $payload['description'],
                'budget_min' => $payload['budget_min'],
                'budget_max' => $payload['budget_max'],
                'workers_needed' => $payload['workers_needed'],
                'location_district' => $payload['location_district'],
                'location_city' => $payload['location_city'],
                'status' => 'open',
                'start_at' => $payload['start_at'],
                'deadline_at' => $payload['deadline_at'],
            ]);

            $this->profileSummary->refreshJobCounts($request->attributes->get('auth_user'));

            return $job;
        });

        $job->load(['client', 'category']);

        return $this->success(
            __('jobs.store.success'),
            new JobResource($job),
            201
        );
    }

    public function show(string $id): JsonResponse
    {
        $job = Job::with(['client', 'category'])
            ->where('id', $id)
            ->first();

        if (is_null($job) || !is_null($job->deleted_at)) {
            return $this->error(__('jobs.show.not_found'), 404);
        }

        return $this->success(
            __('jobs.show.success'),
            new JobResource($job)
        );
    }

    public function update(UpdateJobRequest $request, string $id): JsonResponse
    {
        $job = Job::find($id);

        if (is_null($job) || !is_null($job->deleted_at)) {
            return $this->error(__('jobs.show.not_found'), 404);
        }

        if ($job->client_id !== $request->attributes->get('auth_user')->id) {
            return $this->error(__('auth.jwt.forbidden'), 403);
        }

        if ($job->status !== 'open') {
            return $this->error(__('jobs.update.not_open'), 403);
        }

        $payload = $request->validated();

        $job->update($payload);
        $job->load(['client', 'category']);

        return $this->success(
            __('jobs.update.success'),
            new JobResource($job)
        );
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $job = Job::find($id);

        if (is_null($job) || !is_null($job->deleted_at)) {
            return $this->error(__('jobs.show.not_found'), 404);
        }

        if ($job->client_id !== $request->attributes->get('auth_user')->id) {
            return $this->error(__('auth.jwt.forbidden'), 403);
        }

        if ($job->status !== 'open') {
            return $this->error(__('jobs.destroy.not_open'), 403);
        }

        DB::transaction(function () use ($job, $request) {
            $job->delete();
            $this->profileSummary->refreshJobCounts($request->attributes->get('auth_user'));
        });

        return $this->success(__('jobs.destroy.success'));
    }

    public function updateStatus(UpdateJobStatusRequest $request, string $id): JsonResponse
    {
        $job = Job::with(['assignments.worker.profile'])->find($id);

        if (is_null($job) || !is_null($job->deleted_at)) {
            return $this->error(__('jobs.show.not_found'), 404);
        }

        if ($job->client_id !== $request->attributes->get('auth_user')->id) {
            return $this->error(__('auth.jwt.forbidden'), 403);
        }

        if ($job->status !== 'in_progress') {
            return $this->error(__('jobs.status.not_in_progress'), 403);
        }

        $newStatus = $request->validated()['status'];

        if ($newStatus === 'completed') {
            DB::transaction(function () use ($job) {
                $job->update(['status' => 'completed']);

                foreach ($job->assignments as $assignment) {
                    $this->profileSummary->refreshJobCounts($assignment->worker);
                }
            });
        } else {
            $job->update(['status' => $newStatus]);
        }

        $job->load(['client', 'category']);

        return $this->success(
            __('jobs.status.success'),
            new JobResource($job)
        );
    }

    public function getWorkers(Request $request, string $id): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        $job = Job::with('assignments.worker')->find($id);

        if (is_null($job) || !is_null($job->deleted_at)) {
            return $this->error(__('jobs.show.not_found'), 404);
        }

        $isOwner = $job->client_id === $user->id;
        $isAssignedWorker = $job->assignments()->where('worker_id', $user->id)->exists();

        if (!$isOwner && !$isAssignedWorker) {
            return $this->error(__('auth.jwt.forbidden'), 403);
        }

        $assignments = $job->assignments()->with('worker')->get();

        $response = $assignments->map(function ($assignment) use ($user) {
            $alreadyReviewed = Review::where('reviewer_id', $user->id)
                ->where('assignment_id', $assignment->id)
                ->exists();

            return [
                'assignment_id' => $assignment->id,
                'worker' => PublicUserResource::make($assignment->worker),
                'already_reviewed' => $alreadyReviewed,
            ];
        });

        return $this->success(__('jobs.workers.success'), $response);
    }
}
