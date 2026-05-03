<?php

namespace App\Services\RAG;

use OpenAI;

class EmbeddingService
{
    public function embed(string $text): array
    {
        $client = OpenAI::client(config('services.openai_api_key'));
        $response = $client->embeddings()->create([
            'model' => 'text-embedding-3-large',
            'input' => $text,
        ]);

        return $response['data'][0]['embedding'];
    }
}
