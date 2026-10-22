<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialSpace;
use App\Support\PeopleSpaceAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PeopleBiometricEventController extends Controller
{
    public function __construct(private readonly PeopleSpaceAccess $access) {}

    public function configureBinding(Request $request, FinancialSpace $space, int $connector): JsonResponse
    {
        $this->access->hr($request, $space);
        $secret = $this->secret();
        $device = $this->device($space, $connector);
        abort_unless($device->status === 'active', 409, 'Only approved biometric devices can bind workers.');

        $data = $request->validate([
            'engagement_id' => ['required', 'integer', 'min:1'],
            'external_subject_id' => ['required', 'string', 'min:1', 'max:160'],
            'lawful_basis_reference' => ['required', 'string', 'min:12', 'max:160'],
            'privacy_notice_reference' => ['required', 'string', 'min:12', 'max:160'],
        ]);
        $engagement = $this->access->engagement($request, $space, (int) $data['engagement_id']);
        abort_unless($engagement->status === 'active', 409);
        $subjectHash = $this->subjectHash($secret, $space->id, $connector, $data['external_subject_id']);

        $id = DB::table('people_biometric_bindings')->insertGetId([
            'business_space_id' => $space->id,
            'connector_id' => $connector,
            'engagement_id' => $data['engagement_id'],
            'external_subject_hash' => $subjectHash,
            'lawful_basis_reference' => $data['lawful_basis_reference'],
            'privacy_notice_reference' => $data['privacy_notice_reference'],
            'status' => 'pending_review',
            'registered_by_user_id' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->access->audit($request, $space, 'biometric.binding.submitted', 'biometric_binding', $id);

        return response()->json(['data' => [
            'binding_id' => $id, 'status' => 'pending_review',
            'subject_hash_only' => true,
        ]], 201);
    }

    public function approveBinding(Request $request, FinancialSpace $space, int $connector, int $binding): JsonResponse
    {
        $this->access->hr($request, $space);
        $this->device($space, $connector);
        $query = DB::table('people_biometric_bindings')
            ->where('business_space_id', $space->id)
            ->where('connector_id', $connector)
            ->where('id', $binding);
        $row = $query->first();
        abort_unless($row, 404);
        abort_unless($row->status === 'pending_review', 409);
        abort_unless((int) $row->registered_by_user_id !== (int) $request->user()->id, 403);
        $query->update([
            'status' => 'approved',
            'approved_by_user_id' => $request->user()->id,
            'approved_at' => now(),
            'updated_at' => now(),
        ]);
        $this->access->audit($request, $space, 'biometric.binding.approved', 'biometric_binding', $binding);

        return response()->json(['data' => ['binding_id' => $binding, 'status' => 'approved']]);
    }

    public function approveConnector(Request $request, FinancialSpace $space, int $connector): JsonResponse
    {
        abort_unless($space->type === 'business' && $space->status === 'active', 404);
        abort_unless((bool) config('services.opfin_people.biometric_event_enabled', false), 503, 'Vendor integration not enabled.');
        $this->secret();
        $data = $request->validate([
            'processing_approval_reference' => ['required', 'string', 'min:12', 'max:160'],
            'privacy_notice_reference' => ['required', 'string', 'min:12', 'max:160'],
            'vendor_security_review_reference' => ['required', 'string', 'min:12', 'max:160'],
        ]);
        $query = DB::table('people_biometric_connectors')
            ->where('business_space_id', $space->id)
            ->where('id', $connector);
        $device = $query->first();
        abort_unless($device, 404);
        abort_unless($device->status === 'pending_review', 409);
        abort_unless((int) $device->configured_by_user_id !== (int) $request->user()->id, 403);

        $query->update([
            ...$data,
            'status' => 'active',
            'reviewed_by_user_id' => $request->user()->id,
            'processing_approved_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['data' => [
            'connector_id' => $connector,
            'status' => 'active',
            'regulatory_certification' => 'external_evidence_required',
            'template_storage_enabled' => false,
        ]]);
    }

    /**
     * Approved vendor device/event adapter. Raw biometric features/templates
     * are rejected. Device-side subject matching must already have happened.
     */
    public function ingest(Request $request, FinancialSpace $space, int $connector): JsonResponse
    {
        abort_unless((bool) config('services.opfin_people.biometric_event_enabled', false), 503);
        $secret = $this->secret();
        $timestamp = $request->header('X-People-Device-Timestamp');
        $nonce = $request->header('X-People-Device-Nonce');
        $signature = $request->header('X-People-Device-Signature');
        abort_unless(
            is_string($timestamp) && ctype_digit($timestamp)
            && abs(time() - (int) $timestamp) <= 60
            && is_string($nonce) && preg_match('/^[a-f0-9]{32}$/D', $nonce) === 1
            && is_string($signature) && preg_match('/^[a-f0-9]{64}$/D', $signature) === 1,
            401
        );
        $raw = $request->getContent();
        abort_unless(strlen($raw) <= 4096, 413);
        $message = $timestamp."\n".$nonce."\n".$raw;
        abort_unless(hash_equals(hash_hmac('sha256', $message, $secret), $signature), 401);
        abort_unless(
            Cache::add('people:biometric:nonce:'.hash('sha256', $space->id.':'.$connector.':'.$nonce), 1, 120),
            401,
            'Replay rejected.'
        );
        $device = $this->device($space, $connector);
        abort_unless($device->status === 'active', 403, 'Device is not approved.');

        $input = json_decode($raw, true);
        abort_unless(is_array($input) && array_is_list($input) === false, 422);
        $allowed = [
            'schema_version', 'vendor_event_id', 'external_subject_id',
            'direction', 'occurred_at', 'verification_method',
        ];
        abort_unless(array_diff(array_keys($input), $allowed) === [], 422, 'Raw or unsupported biometric data is forbidden.');
        $data = validator($input, [
            'schema_version' => ['required', 'integer', 'in:1'],
            'vendor_event_id' => ['required', 'string', 'min:8', 'max:160'],
            'external_subject_id' => ['required', 'string', 'min:1', 'max:160'],
            'direction' => ['required', Rule::in(['in', 'out'])],
            'occurred_at' => ['required', 'date'],
            'verification_method' => ['required', Rule::in(['device_verified'])],
        ])->validate();

        $occurred = CarbonImmutable::parse($data['occurred_at'])->utc();
        abort_unless(
            $occurred->lessThanOrEqualTo(now()->addMinutes(5))
            && $occurred->greaterThanOrEqualTo(now()->subDays(31)),
            422,
            'Device clock outside accepted capture window.'
        );
        $subjectHash = $this->subjectHash(
            $secret, $space->id, $connector, $data['external_subject_id']
        );
        $binding = DB::table('people_biometric_bindings')
            ->where('business_space_id', $space->id)
            ->where('connector_id', $connector)
            ->where('external_subject_hash', $subjectHash)
            ->where('status', 'approved')->first();
        abort_unless($binding, 403, 'Device subject has no approved, privacy-notified engagement.');
        abort_unless(
            DB::table('people_engagements')
                ->where('business_space_id', $space->id)
                ->where('id', $binding->engagement_id)
                ->where('status', 'active')->exists(),
            403
        );

        $hash = hash_hmac('sha256', $data['vendor_event_id'], $secret);
        $receipt = DB::transaction(function () use ($space, $device, $connector, $binding, $data, $hash, $occurred, $raw) {
            $existing = DB::table('people_biometric_event_receipts')
                ->where('connector_id', $connector)
                ->where('vendor_event_hash', $hash)->lockForUpdate()->first();
            if ($existing) {
                abort_unless(
                    (int) $existing->engagement_id === (int) $binding->engagement_id,
                    409,
                    'A reused vendor event ID must not be reassigned to another worker.'
                );

                return $existing;
            }
            $event = DB::table('people_attendance_events')->insertGetId([
                'business_space_id' => $space->id,
                'engagement_id' => $binding->engagement_id,
                'direction' => $data['direction'],
                'occurred_at' => $occurred->format('Y-m-d H:i:s'),
                'source' => 'biometric_device',
                'idempotency_key' => 'device:'.$connector.':'.$hash,
                'status' => 'submitted',
                'recorded_by_user_id' => $device->configured_by_user_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $id = DB::table('people_biometric_event_receipts')->insertGetId([
                'business_space_id' => $space->id,
                'connector_id' => $connector,
                'engagement_id' => $binding->engagement_id,
                'attendance_event_id' => $event,
                'vendor_event_hash' => $hash,
                'signed_payload_hash' => hash('sha256', $raw),
                'ingestion_status' => 'accepted_for_review',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return DB::table('people_biometric_event_receipts')->find($id);
        }, 3);

        return response()->json(['data' => [
            'receipt_id' => $receipt->id,
            'status' => 'accepted_for_review',
            'attendance_status' => 'submitted',
            'device_identity_result' => 'asserted_by_approved_vendor_not_reverified_by_opfin',
        ]]);
    }

    private function secret(): string
    {
        $secret = config('services.opfin_people.biometric_event_secret');
        abort_unless(is_string($secret) && strlen($secret) >= 32, 503, 'Biometric device signing is unconfigured.');

        return $secret;
    }

    private function device(FinancialSpace $space, int $connector): object
    {
        abort_unless($space->type === 'business' && $space->status === 'active' && $space->deleted_at === null, 404);

        return DB::table('people_biometric_connectors')
            ->where('business_space_id', $space->id)
            ->where('id', $connector)->first() ?? abort(404);
    }

    private function subjectHash(string $secret, int $spaceId, int $connectorId, string $subjectId): string
    {
        return hash_hmac('sha256', $spaceId.':'.$connectorId.':'.$subjectId, $secret);
    }
}
