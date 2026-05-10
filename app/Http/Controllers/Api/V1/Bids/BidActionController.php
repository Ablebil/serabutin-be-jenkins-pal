<?php

namespace App\Http\Controllers\Api\V1\Bids;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Bids\AcceptBidRequest;
use App\Http\Requests\Api\V1\Bids\RejectBidRequest;
use App\Http\Resources\Api\V1\Bids\BidResource;
use App\Models\Bid;
use Illuminate\Http\JsonResponse;

class BidActionController extends Controller
{
    public function accept(AcceptBidRequest $request, string $id): JsonResponse
    {
        return $this->error('Not implemented.', 501);
    }

    public function reject(RejectBidRequest $request, string $id): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        $bid = Bid::query()->with('job')->find($id);

        if (is_null($bid)) {
            return $this->error(__('bids.reject.not_found'), 404);
        }

        if (is_null($bid->job) || !is_null($bid->job->deleted_at)) {
            return $this->error(__('bids.reject.job_not_found'), 404);
        }

        if ($bid->job->client_id !== $user->id) {
            return $this->error(__('auth.jwt.forbidden'), 403);
        }

        if ($bid->status !== 'pending') {
            return $this->error(__('bids.reject.not_pending'), 403);
        }

        $bid->update(['status' => 'rejected']);
        $bid->load('worker');

        return $this->success(
            __('bids.reject.success'),
            new BidResource($bid)
        );
    }
}
