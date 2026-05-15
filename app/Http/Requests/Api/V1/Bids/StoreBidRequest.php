<?php

namespace App\Http\Requests\Api\V1\Bids;

use App\Models\Job;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'proposed_price' => ['required', 'numeric', 'min:0'],
            'message' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'proposed_price.required' => 'Harga penawaran wajib diisi.',
            'proposed_price.numeric' => 'Harga penawaran harus berupa angka.',
            'proposed_price.min' => 'Harga penawaran tidak boleh negatif.',
            'message.string' => 'Pesan harus berupa teks.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $jobId = $this->route('id');

            if (!$jobId) {
                return;
            }

            $job = Job::query()->find($jobId);

            if (is_null($job) || !is_null($job->deleted_at)) {
                return;
            }

            $price = $this->input('proposed_price');

            if ($price === null) {
                return;
            }

            $priceValue = (float) $price;
            $budgetMin = (float) $job->budget_min;
            $budgetMax = (float) $job->budget_max;

            if ($priceValue < $budgetMin || $priceValue > $budgetMax) {
                $validator->errors()->add('proposed_price', __('bids.store.price_out_of_range'));
            }
        });
    }
}
