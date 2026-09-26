<?php

namespace App\Services\ClubAccounting;

use App\Models\ClubBook;
use App\Models\ClubClientRequest;
use App\Models\ClubInstruction;
use App\Models\ClubStatement;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/** Durable UI recovery is separate from approval and never executes a provider. */
class ClubClientRequests
{
    public function __construct(private readonly ClubAccess $access, private readonly ClubInstructions $instructions,
        private readonly ClubReports $reports, private readonly AuditLogger $audit) {}

    public function current(ClubBook $book, User $user, string $purpose): ?ClubClientRequest
    {
        $this->authorise($book, $user, $purpose);
        return ClubClientRequest::where('active_slot', $this->slot($book, $user, $purpose))->first();
    }

    public function inspect(ClubBook $book, User $user, string $reference, string $hash): array
    {
        return DB::transaction(function () use ($book, $user, $reference, $hash): array {
            $record = $this->owned($book, $user, $reference, $hash);
            $this->authorise($book, $user, $record->purpose, $record->envelope);
            return ['envelope' => $record->envelope, 'reference' => $record->reference,
                'content_hash' => $record->content_hash, 'status' => $record->status];
        });
    }

    public function prepare(ClubBook $book, User $user, string $purpose, array $envelope): ClubClientRequest
    {
        $this->authorise($book, $user, $purpose, $envelope);
        if (strlen(ClubLedger::canonical($envelope)) > 200000) {
            throw new InvalidArgumentException('Split this request into smaller reviewed records.');
        }
        $rules = $purpose === 'instruction'
            ? ['type' => ['required', 'in:'.implode(',', ClubInstructions::TYPES)],
                'business_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'payload' => ['required', 'array']]
            : ['period_start' => ['required', 'date_format:Y-m-d'],
                'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start', 'before_or_equal:today'],
                'member_user_id' => ['nullable', 'integer', 'min:1']];
        $rules['idempotency_key'] = ['required', 'string', 'min:8', 'max:160', 'regex:/^[A-Za-z0-9:._-]+$/D'];
        if (array_diff(array_keys($envelope), array_keys($rules)) !== []) {
            throw new InvalidArgumentException('The request contains unsupported fields.');
        }
        $clean = Validator::make($envelope, $rules)->validate();
        if ($purpose === 'instruction') {
            $clean['payload'] = $this->instructions->validatePayload($clean['type'], $clean['payload']);
        } elseif (isset($clean['member_user_id'])) {
            $clean['member_user_id'] = (int) $clean['member_user_id'];
        }
        $hash = hash('sha256', ClubLedger::canonical(['book_id' => $book->id, 'user_id' => $user->id,
            'purpose' => $purpose, 'envelope' => $clean]));

        return DB::transaction(function () use ($book, $user, $purpose, $clean, $hash): ClubClientRequest {
            $locked = ClubBook::lockForUpdate()->findOrFail($book->id);
            $this->authorise($locked, $user, $purpose, $clean);
            $existing = ClubClientRequest::where('book_id', $book->id)->where('user_id', $user->id)
                ->where('purpose', $purpose)->where('idempotency_key', $clean['idempotency_key'])->first();
            if ($existing) {
                if (! hash_equals($existing->content_hash, $hash)) {
                    throw new InvalidArgumentException('The saved request key already has a different meaning.');
                }
                if ($existing->status === 'cancelled') {
                    throw new InvalidArgumentException('This unsubmitted request was cancelled. Use a new reviewed request.');
                }
                return $existing;
            }
            if (ClubClientRequest::where('active_slot', $this->slot($book, $user, $purpose))->exists()) {
                throw new InvalidArgumentException('Resolve and acknowledge the saved '.$purpose.' request before starting another.');
            }
            $record = ClubClientRequest::create(['reference' => (string) Str::uuid(), 'book_id' => $book->id,
                'user_id' => $user->id, 'purpose' => $purpose, 'active_slot' => $this->slot($book, $user, $purpose),
                'idempotency_key' => $clean['idempotency_key'], 'envelope' => $clean,
                'content_hash' => $hash, 'status' => 'prepared']);
            $this->audit->record('club.client_request.prepared', $user, $record,
                ['book_id' => $book->id, 'purpose' => $purpose, 'content_hash' => $hash]);
            return $record;
        }, 3);
    }

    public function submit(ClubBook $book, User $user, string $reference, string $hash): array
    {
        return DB::transaction(function () use ($book, $user, $reference, $hash): array {
            $locked = ClubBook::lockForUpdate()->findOrFail($book->id);
            $record = $this->owned($locked, $user, $reference, $hash);
            $this->authorise($locked, $user, $record->purpose, $record->envelope);
            if ($record->status === 'cancelled') {
                throw new InvalidArgumentException('The saved unsubmitted request was cancelled.');
            }
            if ($record->status === 'prepared') {
                $resource = $record->purpose === 'instruction'
                    ? $this->instructions->submit($locked, $user, $record->envelope)
                    : $this->reports->issue($locked, $user, $record->envelope);
                $record->update(['status' => 'submitted', 'resource_id' => $resource->id]);
                $this->audit->record('club.client_request.submitted', $user, $record,
                    ['resource_id' => $resource->id, 'purpose' => $record->purpose]);
            }
            return $this->result($locked, $user, $record);
        }, 3);
    }

    public function finish(ClubBook $book, User $user, string $reference, string $hash, bool $cancel): array
    {
        return DB::transaction(function () use ($book, $user, $reference, $hash, $cancel): array {
            $locked = ClubBook::lockForUpdate()->findOrFail($book->id);
            $record = $this->owned($locked, $user, $reference, $hash);
            $this->authorise($locked, $user, $record->purpose, $record->envelope);
            if ($cancel) {
                if ($record->status !== 'prepared' && $record->status !== 'cancelled') {
                    throw new InvalidArgumentException('This request is already recorded. Use the normal instruction decision workflow.');
                }
                // Covers clients that submitted the same key through the original API.
                $used = $record->purpose === 'instruction'
                    ? ClubInstruction::where('book_id', $book->id)->where('idempotency_key', $record->idempotency_key)->exists()
                    : ClubStatement::where('book_id', $book->id)->where('idempotency_key', $record->idempotency_key)->exists();
                if ($used) {
                    throw new InvalidArgumentException('The request key already has a recorded result. Resume it instead of cancelling.');
                }
                $record->update(['status' => 'cancelled', 'active_slot' => null]);
            } else {
                if ($record->status !== 'submitted') {
                    throw new InvalidArgumentException('Read the recorded result before acknowledging it.');
                }
                $this->result($locked, $user, $record);
                $record->update(['active_slot' => null, 'acknowledged_at' => now()]);
            }
            $this->audit->record($cancel ? 'club.client_request.cancelled' : 'club.client_request.acknowledged', $user, $record);
            return ['reference' => $record->reference, 'status' => $record->status, 'active' => $record->active_slot !== null];
        }, 3);
    }

    private function owned(ClubBook $book, User $user, string $reference, string $hash): ClubClientRequest
    {
        $record = ClubClientRequest::where('book_id', $book->id)->where('user_id', $user->id)
            ->where('reference', $reference)->lockForUpdate()->firstOrFail();
        $expected = hash('sha256', ClubLedger::canonical(['book_id' => $book->id, 'user_id' => $user->id,
            'purpose' => $record->purpose, 'envelope' => $record->envelope]));
        if (! hash_equals($record->content_hash, $hash) || ! hash_equals($record->content_hash, $expected)) {
            throw new RuntimeException('The saved request integrity does not match.');
        }
        return $record;
    }

    private function result(ClubBook $book, User $user, ClubClientRequest $record): array
    {
        $resource = $record->purpose === 'instruction'
            ? ClubInstruction::where('book_id', $book->id)->where('maker_id', $user->id)->findOrFail($record->resource_id)
            : $this->reports->read($book, ClubStatement::where('book_id', $book->id)->findOrFail($record->resource_id), $user);
        return [$record->purpose => $resource, 'client_request' => [
            'reference' => $record->reference, 'content_hash' => $record->content_hash,
            'purpose' => $record->purpose, 'status' => $record->status, 'resource_id' => $record->resource_id]];
    }

    private function authorise(ClubBook $book, User $user, string $purpose, array $envelope = []): void
    {
        if ($purpose === 'instruction') {
            $this->access->book($book, $user, write: true);
        } elseif ($purpose === 'statement') {
            // Reading the recovery slot is allowed only if the user can read
            // their own position; officers without a position may use club reports.
            $member = array_key_exists('member_user_id', $envelope)
                ? ($envelope['member_user_id'] === null ? null : (int) $envelope['member_user_id'])
                : ($envelope === [] && \App\Models\ClubMember::where('book_id', $book->id)->where('user_id', $user->id)->exists()
                    ? (int) $user->id : null);
            $this->reports->authorise($book, $user, $member);
        } else {
            throw new InvalidArgumentException('Unsupported saved request purpose.');
        }
    }

    private function slot(ClubBook $book, User $user, string $purpose): string
    {
        return $book->id.':'.$user->id.':'.$purpose;
    }
}
