<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * First-party, user-scoped HR discovery. Not a service-to-service identity assertion.
 * No HR data or financial records are returned.
 */
class OpFinPeopleMembershipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $spaces = $this->memberships($request)
            ->select('s.public_id', 's.name', 'm.role')
            ->orderBy('s.name')
            ->get();

        return response()->json(['data' => [
            'integration_available' => true,
            'spaces' => $spaces,
        ]])->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, string $publicId): JsonResponse
    {
        $membership = $this->memberships($request)
            ->where('s.public_id', $publicId)
            ->select('s.public_id', 's.name', 'm.role')
            ->first();

        abort_unless($membership, 404);

        return response()->json(['data' => [
            'space' => $membership,
            'can_manage_people' => in_array($membership->role, ['owner', 'administrator', 'director'], true),
        ]])->header('Cache-Control', 'no-store');
    }

    private function memberships(Request $request): \Illuminate\Database\Query\Builder
    {
        return DB::table('financial_space_memberships as m')
            ->join('financial_spaces as s', 's.id', '=', 'm.financial_space_id')
            ->where('m.user_id', $request->user()->id)
            ->where('m.status', 'active')
            ->where('s.status', 'active')
            ->where('s.type', 'business')
            ->whereNull('m.deleted_at')
            ->whereNull('s.deleted_at');
    }
}
