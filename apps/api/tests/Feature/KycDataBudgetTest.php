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
            'national_id_front' => UploadedFile::fake()
                ->image('front.jpg', 800, 600)
                ->size(600),
            'national_id_back' => UploadedFile::fake()
                ->image('back.jpg', 800, 600)
                ->size(600),
            'selfie_with_id' => UploadedFile::fake()
                ->image('selfie.jpg', 800, 600)
                ->size(600),
        ], ['Accept' => 'application/json']);

        $response->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Mobile identity evidence exceeds the 1.5 MB low-data upload budget. Retake or compress the largest photo.'
            );
    }
}
