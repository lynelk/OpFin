<?php

namespace App\Services;

use App\Models\ConsentRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProgrammeProviderAdapterService
{
    public const ADAPTER_TYPES = [
        'gnugrid_crb',
        'mno',
        'employer',
        'vsla',
        'stolets',
        'other',
    ];

    public const PURPOSES = [
        'programme_measurement',
        'financial_capability',
        'affordability',
        'credit_assessment',
    ];

    public function adapters(?int $programmeId = null): array
    {
        return [
            'adapters' => DB::table('programme_provider_adapters')
                ->when($programmeId, fn ($query) => $query->where('programme_id', $programmeId))
                ->orderBy('name')
                ->get()
                ->map(fn ($row) => $this->payload($row))
                ->values()
                ->all(),
            'adapter_types' => self::ADAPTER_TYPES,
            'purposes' => self::PURPOSES,
            'activation_rule' => 'Adapters remain disabled until credentials/configuration and legal basis are explicitly confirmed. No external credentials are stored by this registry.',
        ];
    }

    public function configure(array $data, User $actor): array
    {
        $code = strtoupper(trim($data['code']));
        $existing = DB::table('programme_provider_adapters')->whereRaw('UPPER(code) = ?', [$code])->first();

        if ($existing && (! isset($data['id']) || (int) $existing->id !== (int) $data['id'])) {
            throw new InvalidArgumentException('A provider adapter with this code already exists.');
        }

        $allowed = array_values(array_unique(array_map('strval', $data['allowed_signal_keys'] ?? [])));
        $mapping = $data['signal_mapping'] ?? [];

        if ($allowed === []) {
            throw new InvalidArgumentException('Provider adapter requires an explicit allowed-signal allow-list.');
        }

        foreach (array_keys($mapping) as $sourceKey) {
            if (! in_array($sourceKey, $allowed, true)) {
                throw new InvalidArgumentException('Provider signal mapping references a source key outside the adapter allow-list.');
            }
        }

        $values = [
            'programme_id' => $data['programme_id'] ?? null,
            'partner_id' => $data['partner_id'] ?? null,
            'code' => $code,
            'name' => trim($data['name']),
            'adapter_type' => $data['adapter_type'],
            'status' => $data['status'] ?? 'draft',
            'purpose' => $data['purpose'] ?? 'programme_measurement',
            'allowed_signal_keys' => json_encode($allowed),
            'signal_mapping' => json_encode($mapping),
            'requires_credit_processing_consent' => (bool) ($data['requires_credit_processing_consent'] ?? false),
            'credentials_configured' => (bool) ($data['credentials_configured'] ?? false),
            'legal_basis_confirmed' => (bool) ($data['legal_basis_confirmed'] ?? false),
            'activation_notes' => $data['activation_notes'] ?? null,
            'updated_by' => $actor->id,
            'updated_at' => now(),
        ];

        if (($values['status'] ?? 'draft') === 'active' && (! $values['credentials_configured'] || ! $values['legal_basis_confirmed'])) {
            throw new InvalidArgumentException('A provider adapter cannot be activated before credentials/configuration and legal basis are explicitly confirmed.');
        }

        if ($existing) {
            DB::table('programme_provider_adapters')->where('id', $existing->id)->update($values);
            $id = $existing->id;
        } else {
            $values['created_by'] = $actor->id;
            $values['created_at'] = now();
            $id = DB::table('programme_provider_adapters')->insertGetId($values);
        }

        return $this->payload(DB::table('programme_provider_adapters')->where('id', $id)->first());
    }

    public function ingest(int $adapterId, User $subject, array $data, User $actor): array
    {
        $adapter = DB::table('programme_provider_adapters')->where('id', $adapterId)->first();
        if (! $adapter || $adapter->status !== 'active') {
            throw new InvalidArgumentException('Provider adapter is not active.');
        }

        if (! $adapter->credentials_configured || ! $adapter->legal_basis_confirmed) {
            throw new InvalidArgumentException('Provider adapter activation evidence is incomplete.');
        }

        if ($adapter->requires_credit_processing_consent) {
            $consent = ConsentRecord::query()
                ->where('user_id', $subject->id)
                ->where('purpose', ConsentRecord::PURPOSE_CREDIT_PROCESSING)
                ->where('status', ConsentRecord::STATUS_GRANTED)
                ->exists();
            if (! $consent) {
                throw new InvalidArgumentException('Active credit-processing consent is required for this provider adapter.');
            }
        }

        if (($adapter->purpose ?? 'programme_measurement') === 'programme_measurement') {
            $profile = DB::table('inclusive_finance_profiles')->where('user_id', $subject->id)->first();
            if (! $profile || ! $profile->programme_measurement_consent) {
                throw new InvalidArgumentException('Programme measurement consent is required for this provider adapter.');
            }

            if ($adapter->programme_id && ! DB::table('inclusive_finance_enrolments')
                ->where('programme_id', $adapter->programme_id)
                ->where('user_id', $subject->id)
                ->where('status', 'enrolled')
                ->exists()) {
                throw new InvalidArgumentException('Active programme enrolment is required for programme-measurement provider evidence.');
            }
        }

        $signals = $data['signals'] ?? [];
        $allowed = $this->json($adapter->allowed_signal_keys);
        $mapping = $this->json($adapter->signal_mapping);

        foreach (array_keys($signals) as $key) {
            if (! in_array($key, $allowed, true)) {
                throw new InvalidArgumentException('Provider payload contains a signal outside the configured allow-list.');
            }
        }

        $payloadHash = hash('sha256', json_encode($signals, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use (
            $adapterId,
            $adapter,
            $subject,
            $data,
            $signals,
            $allowed,
            $mapping,
            $payloadHash,
        ) {
            $duplicate = DB::table('programme_provider_ingestions')
                ->where('adapter_id', $adapterId)
                ->where('provider_reference', $data['provider_reference'])
                ->lockForUpdate()
                ->first();

            if ($duplicate) {
                if (! hash_equals($duplicate->payload_hash, $payloadHash)) {
                    throw new InvalidArgumentException('Provider reference has already been used with different signal data.');
                }

                return [
                    'ingestion_id' => (int) $duplicate->id,
                    'duplicate' => true,
                    'status' => $duplicate->status,
                    'risk_eligible' => false,
                ];
            }

            $sourceType = match ($adapter->adapter_type) {
                'gnugrid_crb' => 'crb',
                'mno' => 'mno',
                'employer' => 'employer',
                'stolets' => 'transactional',
                default => 'partner',
            };

            $mapped = [];
            foreach ($signals as $sourceKey => $value) {
                if (! in_array($sourceKey, $allowed, true)) {
                    throw new InvalidArgumentException('Provider payload contains a signal outside the configured allow-list.');
                }

                $target = $mapping[$sourceKey] ?? $sourceKey;

                DB::table('alternative_data_signals')->insert([
                    'user_id' => $subject->id,
                    'financial_space_id' => null,
                    'source_type' => $sourceType,
                    'signal_key' => $target,
                    'signal_value' => json_encode($value),
                    'purpose' => $adapter->purpose,
                    'consent_record_id' => null,
                    'risk_eligible' => false,
                    'verified' => true,
                    'provider_reference' => $data['provider_reference'].':'.$sourceKey,
                    'provenance' => json_encode([
                        'adapter_code' => $adapter->code,
                        'adapter_type' => $adapter->adapter_type,
                        'source_key' => $sourceKey,
                        'programme_id' => $adapter->programme_id,
                    ]),
                    'observed_at' => $data['observed_at'] ?? now(),
                    'expires_at' => $data['expires_at'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $mapped[$sourceKey] = $target;
            }

            $id = DB::table('programme_provider_ingestions')->insertGetId([
                'adapter_id' => $adapterId,
                'user_id' => $subject->id,
                'provider_reference' => $data['provider_reference'],
                'payload_hash' => $payloadHash,
                'mapped_signals' => json_encode($mapped),
                'status' => 'processed',
                'received_at' => now(),
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'ingestion_id' => $id,
                'adapter_id' => $adapterId,
                'user_id' => $subject->id,
                'mapped_signals' => $mapped,
                'risk_eligible' => false,
                'verified' => true,
                'boundary' => 'Provider ingestion verifies provenance only. It does not add the signal to underwriting or change a score, price or limit.',
            ];
        });
    }

    private function payload(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'programme_id' => $row->programme_id ? (int) $row->programme_id : null,
            'partner_id' => $row->partner_id ? (int) $row->partner_id : null,
            'code' => $row->code,
            'name' => $row->name,
            'adapter_type' => $row->adapter_type,
            'status' => $row->status,
            'purpose' => $row->purpose,
            'allowed_signal_keys' => $this->json($row->allowed_signal_keys),
            'signal_mapping' => $this->json($row->signal_mapping),
            'requires_credit_processing_consent' => (bool) $row->requires_credit_processing_consent,
            'credentials_configured' => (bool) $row->credentials_configured,
            'legal_basis_confirmed' => (bool) $row->legal_basis_confirmed,
            'activation_notes' => $row->activation_notes,
            'external_credentials_stored_here' => false,
        ];
    }

    private function json(?string $value): array
    {
        if (! $value) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
