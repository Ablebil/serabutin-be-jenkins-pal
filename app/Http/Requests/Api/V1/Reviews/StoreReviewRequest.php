<?php

namespace App\Http\Requests\Api\V1\Reviews;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assignment_id' => ['required', 'uuid', 'exists:job_assignments,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'assignment_id.required' => 'Assignment wajib diisi.',
            'assignment_id.uuid' => 'Format assignment tidak valid.',
            'assignment_id.exists' => 'Assignment tidak ditemukan.',
            'rating.required' => 'Rating wajib diisi.',
            'rating.integer' => 'Rating harus berupa angka.',
            'rating.min' => 'Rating minimal 1.',
            'rating.max' => 'Rating maksimal 5.',
            'comment.string' => 'Komentar harus berupa teks.',
            'comment.max' => 'Komentar maksimal 1000 karakter.',
        ];
    }
}
