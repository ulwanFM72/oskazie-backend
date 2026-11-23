<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Error bisnis yang aman ditampilkan ke pengguna. Dirender sebagai JSON {message, code}.
 */
class ApiException extends RuntimeException
{
    public function __construct(
        string $message,
        private int $status = 422,
        private string $errorCode = 'error',
    ) {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
        ], $this->status);
    }

    // Error bisnis tidak perlu masuk log aplikasi.
    public function report(): void
    {
    }
}
