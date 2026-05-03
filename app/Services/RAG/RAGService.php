<?php

namespace App\Services\RAG;

class RAGService
{
    public function retrieve(string $question): array
    {
        $embedding = app(EmbeddingService::class)->embed($question);

        return app(VectorSearchService::class)
            ->search($embedding);
    }
}
