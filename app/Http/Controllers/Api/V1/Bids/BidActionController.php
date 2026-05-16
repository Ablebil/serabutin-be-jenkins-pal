<?php

namespace App\Http\Controllers\Api\V1\Bids;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Bids\AcceptBidRequest;
use App\Http\Resources\Api\V1\Bids\BidResource;
use App\Models\Bid;
use App\Models\Job;
use App\Models\JobAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BidActionController extends Controller
{
    public function accept(AcceptBidRequest $request, string $id): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        $result = DB::transaction(function () use ($id, $user) {
            $bid = Bid::query()->where('id', $id)->lockForUpdate()->first();

            if (is_null($bid)) {
                return ['error' => 'not_found'];
            }

            $job = Job::query()->where('id', $bid->job_id)->lockForUpdate()->first();

            if (is_null($job) || !is_null($job->deleted_at)) {
                return ['error' => 'job_not_found'];
            }

            if ($job->client_id !== $user->id) {
                return ['error' => 'forbidden'];
            }

            if (!in_array($job->status, ['open', 'in_progress'], true)) {
                return ['error' => 'job_not_open'];
            }

            if ($bid->status !== 'pending') {
                return ['error' => 'not_pending'];
            }

            $acceptedCount = Bid::query()
                ->where('job_id', $job->id)
                ->where('status', 'accepted')
                ->count();

            if ($acceptedCount >= $job->workers_needed) {
                return ['error' => 'slots_full'];
            }

            $bid->update(['status' => 'accepted']);

            JobAssignment::query()->create([
                'job_id' => $job->id,
                'bid_id' => $bid->id,
                'worker_id' => $bid->worker_id,
                'client_id' => $job->client_id,
            ]);

            if ($job->status === 'open') {
                $job->update(['status' => 'in_progress']);
            }

            $acceptedCount++;

            if ($acceptedCount >= $job->workers_needed) {
                Bid::query()
                    ->where('job_id', $job->id)
                    ->where('status', 'pending')
                    ->where('id', '!=', $bid->id)
                    ->update(['status' => 'rejected']);
            }

            return ['bid' => $bid->fresh()->load('worker')];
        });

        if (isset($result['error'])) {
            return match ($result['error']) {
                'not_found' => $this->error(__('bids.accept.not_found'), 404),
                'job_not_found' => $this->error(__('bids.accept.job_not_found'), 404),
                'forbidden' => $this->error(__('auth.jwt.forbidden'), 403),
                'job_not_open' => $this->error(__('bids.accept.job_not_open'), 403),
                'not_pending' => $this->error(__('bids.accept.not_pending'), 403),
                'slots_full' => $this->error(__('bids.accept.slots_full'), 409),
                default => $this->error(__('general.server_error'), 500),
            };
        }

        return $this->success(
            __('bids.accept.success'),
            new BidResource($result['bid'])
        );
    }
}
