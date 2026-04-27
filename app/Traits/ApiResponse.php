<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success(
        string $message,
        mixed $data = null,
        int $statusCode = 200
    ): JsonResponse {
        $response = [
            'status' => 'success',
            'message' => $message,
        ];

        if (!is_null($data)) {
            $response['data'] = $data;
        }

        return response()->json($response, $statusCode);
    }

    protected function error(
        string $message,
        int $statusCode,
        array $errors = []
    ): JsonResponse {
        $response = [
            'status' => 'error',
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    protected function paginated(
        string $message,
        mixed $data,
        mixed $paginator
    ): JsonResponse {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    protected function cursor(
        string $message,
        mixed $data,
        mixed $paginator
    ): JsonResponse {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'meta' => [
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'per_page' => $paginator->perPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ]);
    }
}