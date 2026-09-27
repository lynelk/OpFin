<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MobileHomeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileHomeController extends Controller
{
    public function __construct(
        private readonly MobileHomeService $home,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'currency' => ['sometimes', 'string', 'size:3'],
        ]);

        $snapshot = $this->home->snapshot(
            $request->user(),
            strtoupper((string) ($validated['currency'] ?? 'UGX'))
        );

        $etag = hash(
            'sha256',
            json_encode(
                $snapshot,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            )
        );

        $headers = [
            'ETag' => '"'.$etag.'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ];

        $clientEtag = (string) $request->header('If-None-Match', '');
        if ($clientEtag !== '' && str_contains($clientEtag, $etag)) {
            return response()->json(null, 304, $headers);
        }

        return response()->json([
            'data' => $snapshot,
        ], 200, $headers);
    }
}
