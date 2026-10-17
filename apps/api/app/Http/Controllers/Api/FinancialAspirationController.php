<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PersonalFinancialSpaceService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FinancialAspirationController extends Controller
{
    private const CATEGORIES = ['stability', 'education', 'family', 'enterprise', 'protection', 'growth', 'other'];

    private const STATUSES = ['active', 'paused', 'completed', 'archived'];

    public function __construct(
        private readonly PersonalFinancialSpaceService $spaces,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Aspirations are personal plans, never regulated savings deposits or credit applications.
     * The personal Space is resolved on the server, never from submitted form data.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->person($request);
        $rows = $this->owned($user)->orderByDesc('is_priority')->orderByDesc('created_at')->get();

        return response()->json(['data' => [
            'aspirations' => $rows->map(fn ($row) => $this->payload($row))->values()->all(),
            'financial_state' => 'plan_only',
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->person($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:120'],
            'category' => ['sometimes', Rule::in(self::CATEGORIES)],
            'target_amount_minor' => ['nullable', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3', Rule::in(['UGX'])],
            'target_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $reference = (string) Str::uuid();
        DB::table('financial_aspirations')->insert([
            'reference' => $reference,
            'user_id' => $user->id,
            'financial_space_id' => $this->spaces->ensure($user)->id,
            'institution_id' => $user->institution_id,
            'title' => trim($data['title']),
            'category' => $data['category'] ?? 'other',
            'target_amount_minor' => $data['target_amount_minor'] ?? null,
            'currency' => 'UGX',
            'target_date' => $data['target_date'] ?? null,
            'status' => 'active',
            'is_priority' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->audit->record('financial.aspiration.created', $user, null, ['reference' => $reference], $request);

        return response()->json(['data' => $this->payload($this->owned($user)->where('reference', $reference)->firstOrFail())], 201);
    }

    public function update(Request $request, string $reference): JsonResponse
    {
        $user = $this->person($request);
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'min:2', 'max:120'],
            'category' => ['sometimes', Rule::in(self::CATEGORIES)],
            'target_amount_minor' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'target_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'status' => ['sometimes', Rule::in(self::STATUSES)],
            'is_priority' => ['sometimes', 'boolean'],
        ]);
        if ($data === []) {
            return response()->json(['message' => 'Provide a goal change.'], 422);
        }

        DB::transaction(function () use ($user, $reference, $data) {
            // Serialise priority changes for this person's account.
            DB::table('users')->where('id', $user->id)->lockForUpdate()->firstOrFail();
            $record = $this->owned($user)->where('reference', $reference)->firstOrFail();
            $status = $data['status'] ?? $record->status;
            $priority = $data['is_priority'] ?? (bool) $record->is_priority;

            if (($data['is_priority'] ?? false) && $status !== 'active') {
                abort(422, 'Only an active goal can be your current priority.');
            }
            if ($priority && $status === 'active') {
                $this->owned($user)->where('is_priority', true)->update(['is_priority' => false, 'updated_at' => now()]);
            }
            $changes = array_intersect_key($data, array_flip(['title', 'category', 'target_amount_minor', 'target_date', 'status', 'is_priority']));
            if ($status !== 'active') {
                $changes['is_priority'] = false;
            } elseif (array_key_exists('is_priority', $data)) {
                $changes['is_priority'] = $priority;
            }
            $changes['updated_at'] = now();
            $this->owned($user)->where('id', $record->id)->update($changes);
        }, 3);

        $this->audit->record('financial.aspiration.updated', $user, null, ['reference' => $reference], $request);

        return response()->json(['data' => $this->payload($this->owned($user)->where('reference', $reference)->firstOrFail())]);
    }

    private function person(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function owned(User $user): Builder
    {
        $query = DB::table('financial_aspirations')
            ->where('user_id', $user->id)
            ->where('financial_space_id', $this->spaces->ensure($user)->id);

        return $user->institution_id === null
            ? $query->whereNull('institution_id')
            : $query->where('institution_id', $user->institution_id);
    }

    private function payload(object $row): array
    {
        return [
            'reference' => $row->reference,
            'title' => $row->title,
            'category' => $row->category,
            'target_amount_minor' => $row->target_amount_minor === null ? null : (int) $row->target_amount_minor,
            'currency' => $row->currency,
            'target_date' => $row->target_date,
            'status' => $row->status,
            'is_priority' => (bool) $row->is_priority,
            'financial_state' => 'plan_only',
            'updated_at' => $row->updated_at,
        ];
    }
}
