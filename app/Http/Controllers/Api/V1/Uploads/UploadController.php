<?php

namespace App\Http\Controllers\Api\V1\Uploads;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make(
            $request->all(),
            [
                'file' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png'],
            ],
            [
                'file.required' => __('uploads.validation.file_required'),
                'file.file' => __('uploads.validation.file_invalid'),
                'file.max' => __('uploads.validation.file_too_large'),
                'file.mimes' => __('uploads.validation.file_unsupported'),
            ]
        );

        $validator->validate();

        $file = $request->file('file');

        $extension = $file->extension();
        $filename = (string) Str::uuid() . '.' . $extension;
        $path = 'avatars/' . $filename;

        $diskName = config('filesystems.default');
        $disk = Storage::disk($diskName);

        $disk->putFileAs('avatars', $file, $filename);

        return $this->success(
            __('uploads.upload.success'),
            ['url' => Storage::url($path)],
            201
        );
    }
}
