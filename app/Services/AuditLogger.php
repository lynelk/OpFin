<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function record(
        string $event,
        ?Model $actor = null,
        ?Model $subject = null,
        array $metadata = [],
        ?Request $request = null
    ): AuditLog {
        $requestMetadata = [];

        if ($request) {
            $requestMetadata = [
                'method' => $request->method(),
                'path' => '/' . ltrim($request->path(), '/'),
            ];
        }

        return AuditLog::create([
            'event' => $event,
            'actor_type' => $actor ? $actor::class : null,
            'actor_id' => $actor?->getKey(),
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => array_merge($requestMetadata, $metadata),
        ]);
    }
}
