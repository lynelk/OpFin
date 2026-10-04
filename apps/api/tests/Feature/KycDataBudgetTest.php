<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KycDataBudgetTest extends TestCase
{
    use RefreshDatabase;


    private function fakePng(string $name, int $kilobytes): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZQmcAAAAASUVORK5CYII=',
            true
        );
        $this->assertNotFalse($png);
        $targetBytes = $kilobytes * 1024;
        $content = $png.str_repeat("\0", max(0, $targetBytes - strlen($png)));

        return UploadedFile::fake()->createWithContent($name, $content);
    }

    public function test_mobile_kyc_rejects_three_image_package_above_data_budget(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'phone_verified_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $response = $this->post('/api/kyc/cases', [
            'national_id' => 'CM123456789012',
            'capture_channel' => 'app',
            'national_id_front' => $this->fakePng('front.png', 600),
            'national_id_back' => $this->fakePng('back.png', 600),
            'selfie_with_id' => $this->fakePng('selfie.png', 600),
        ], ['Accept' => 'application/json']);

        $response->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Mobile identity evidence exceeds the 1.5 MB low-data upload budget. Retake or compress the largest photo.'
            );
    }
}
