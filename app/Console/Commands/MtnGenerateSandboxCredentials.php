<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class MtnGenerateSandboxCredentials extends Command
{
    protected $signature = 'mtn:generate-sandbox {type=collection : collection|disbursement}';
    protected $description = 'Generate API user and API key for MTN MoMo Sandbox (Collection or Disbursement)';

    public function handle()
    {
        $type = $this->argument('type');
        $uuid = (string) Str::uuid();
        $baseUrl = config('services.mtn.base_url');
        $subKey = $type === 'disbursement'
            ? config('services.mtn.disbursement_sub_key')
            : config('services.mtn.collection_sub_key');

        $this->info("Creating new {$type} API user with Reference ID: {$uuid}");

        // 1️⃣ Create API User
        $response = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $subKey,
            'X-Reference-Id' => $uuid,
        ])->post("{$baseUrl}/v1_0/apiuser", [
            'providerCallbackHost' => 'https://app.opfin.co/mtn-callback', // replace with your dev callback domain
        ]);

        if (!$response->successful()) {
            $this->error('Failed to create API user:');
            $this->line(json_encode($response->json(), JSON_PRETTY_PRINT));
            return;
        }

        $this->info('✅ API User created successfully!');

        // 2️⃣ Generate API Key
        $response = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $subKey,
        ])->post("{$baseUrl}/v1_0/apiuser/{$uuid}/apikey");

        if (!$response->successful()) {
            $this->error('Failed to generate API key:');
            $this->line(json_encode($response->json(), JSON_PRETTY_PRINT));
            return;
        }

        $data = $response->json();
        $apiKey = $data['apiKey'] ?? null;

        $this->info('✅ API Key generated successfully!');
        $this->line("User ID: {$uuid}");
        $this->line("API Key: {$apiKey}");
    }
}
