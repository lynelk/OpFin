<?php

namespace App\Services\RAG;

use Illuminate\Support\Facades\Http;

class VectorSearchService
{
    public function search(array $embedding, int $limit = 5): array
    {
        $response = Http::withHeaders([
            'Api-Key' => config('services.pinecone.key'),
        ])->post(
            config('services.pinecone.url') . '/query',
            [
                'vector' => $embedding,
                'topK' => $limit,
                'includeMetadata' => true,
            ]
        );

        return collect($response->json('matches'))
            ->map(fn($m) => [
                'text' => $m['metadata']['text'],
                'source' => $m['metadata']['source'] ?? 'Unknown',
                'url' => $m['metadata']['url'] ?? null,
            ])
            ->toArray();
    }
}
