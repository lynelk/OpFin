<?php

namespace App\Console\Commands;

use App\Services\RAG\EmbeddingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class IndexKnowledgeUrl extends Command
{
    protected $signature = 'knowledge:index-url {url} {--name=Unknown}';
    protected $description = 'Fetch a single URL, embed its content, and upsert to Pinecone for RAG';

    public function handle()
    {
        $url = $this->argument('url');
        $name = $this->option('name');

        $this->info("Fetching URL: $url");

        try {
            $response = Http::get($url);

            if (!$response->ok()) {
                $this->error("Failed to fetch URL");
                return 1;
            }

            $text = strip_tags($response->body());
            $chunks = explode("\n\n", $text);
            $chunks = array_filter($chunks, fn($c) => trim($c) !== '');

            $this->info("Found " . count($chunks) . " chunks. Indexing...");

            foreach ($chunks as $i => $chunk) {
                $embedding = app(EmbeddingService::class)->embed($chunk);

                Http::withHeaders([
                    'Api-Key' => config('services.pinecone.key'),
                    'Content-Type' => 'application/json',
                ])->post(config('services.pinecone.url') . '/vectors/upsert', [
                    'vectors' => [
                        [
                            'id' => 'manual-' . $i,
                            'values' => $embedding,
                            'metadata' => [
                                'text' => $chunk,
                                'source' => $name,
                                'url' => $url,
                            ],
                        ]
                    ]
                ]);
            }

            $this->info("✅ Successfully indexed: $url");
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
        }

        return 0;
    }
}
