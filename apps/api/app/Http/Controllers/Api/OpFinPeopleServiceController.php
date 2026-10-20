<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Scoped first-party service verification for OpFin People.
 * No browser token, financial details or employment data crosses this boundary.
 */
class OpFinPeopleServiceController extends Controller
{
    public function verify(Request $request): JsonResponse
    {
        $secret = config('services.opfin_people.verification_secret');
        abort_unless(is_string($secret) && strlen($secret) >= 32, 503, 'Service integration unavailable.');

        $timestamp = $request->header('X-OpFin-People-Timestamp');
        $nonce = $request->header('X-OpFin-People-Nonce');
        $signature = $request->header('X-OpFin-People-Signature');
        abort_unless(
            is_string($timestamp) && ctype_digit($timestamp)
            && abs(time() - (int) $timestamp) <= 60
            && is_string($nonce) && preg_match('/^[a-f0-9]{32}$/', $nonce)
            && is_string($signature) && preg_match('/^[a-f0-9]{64}$/', $signature),
            401
        );

        $message = $timestamp."\n".$nonce."\n".$request->getContent();
        $expected = hash_hmac('sha256', $message, $secret);
        abort_unless(hash_equals($expected, $signature), 401);
        abort_unless(Cache::add('opfin_people:nonce:'.hash('sha256', $nonce), 1, 120), 401);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
            'space_public_id' => ['required', 'uuid'],
        ]);
        $member = DB::table('financial_space_memberships as m')
            ->join('financial_spaces as s', 's.id', '=', 'm.financial_space_id')
            ->where('m.user_id', $data['user_id'])
            ->where('m.status', 'active')
            ->where('s.status', 'active')
            ->where('s.type', 'business')
            ->where('s.public_id', $data['space_public_id'])
            ->whereNull('m.deleted_at')
            ->whereNull('s.deleted_at')
            ->select('m.role')
            ->first();

        return response()->json(['data' => [
            'verified' => $member !== null,
            'can_manage_people' => $member !== null && in_array($member->role, ['owner', 'administrator', 'director'], true),
            'role' => $member?->role,
        ]])->header('Cache-Control', 'no-store');
    }
}
