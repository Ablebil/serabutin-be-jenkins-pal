<?php

namespace App\Http\Requests\Api\V1\Users;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['sometimes', 'string', 'max:100'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:500'],
            'location_district' => ['sometimes', 'nullable', 'string', 'max:100'],
            'location_city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'avatar_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.string' => __('users.validation.full_name_string'),
            'full_name.max' => __('users.validation.full_name_max'),
            'bio.string' => __('users.validation.bio_string'),
            'bio.max' => __('users.validation.bio_max'),
            'location_district.string' => __('users.validation.location_district_string'),
            'location_district.max' => __('users.validation.location_district_max'),
            'location_city.string' => __('users.validation.location_city_string'),
            'location_city.max' => __('users.validation.location_city_max'),
            'avatar_url.url' => __('users.validation.avatar_url_url'),
            'avatar_url.max' => __('users.validation.avatar_url_max'),
            'phone.string' => __('users.validation.phone_string'),
            'phone.max' => __('users.validation.phone_max'),
        ];
    }
}
