<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClubAsset;
use App\Models\ClubBook;
use App\Models\ClubInstruction;
use App\Models\ClubMember;
use App\Models\ClubStatement;
use App\Models\FinancialSpace;
use App\Services\ClubAccounting\ClubAccess;
use App\Services\ClubAccounting\ClubBooks;
use App\Services\ClubAccounting\ClubInstructions;
use App\Services\ClubAccounting\ClubIntegrity;
use App\Services\ClubAccounting\ClubReports;
use App\Services\ClubAccounting\ClubSchema;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ClubAccountingController extends Controller
{
    public function __construct(private readonly ClubAccess $access, private readonly ClubBooks $books,
        private readonly ClubInstructions $instructions, private readonly ClubReports $reports, private readonly ClubIntegrity $integrity) {}

    public function schema(): mixed
    {
        return ApiResponse::success('Club accounting operations and input guide.', app(ClubSchema::class)->describe());
    }

    public function index(Request $request, int $space): mixed
    {
        return $this->respond(function () use ($request, $space): array {
            $record = FinancialSpace::findOrFail($space);
            $role = $this->access->role($record, $request->user());
            $books = ClubBook::where('financial_space_id', $space)->orderBy('currency')->get();

            return ['space' => ['id' => $record->id, 'name' => $record->name, 'role' => $role],
                'can_make' => in_array($role, ClubAccess::MAKER_ROLES, true),
                'can_check' => in_array($role, ClubAccess::CHECKER_ROLES, true),
                'books' => $books->map(function (ClubBook $book) use ($request): array {
                    return ['id' => $book->id, 'public_id' => $book->public_id, 'currency' => $book->currency,
                        'ownership_model' => $book->ownership_model, 'status' => $book->status,
                        'cutover_date' => $book->cutover_date->toDateString(), 'unit_scale' => $book->unit_scale,
                        'initial_unit_price_minor' => $book->initial_unit_price_minor,
                        'closed_through' => $book->closed_through?->toDateString(),
                        'policy' => $book->policy,
                        'my_position' => ClubMember::where('book_id', $book->id)->where('user_id', $request->user()->id)
                            ->first(['user_id', 'capital_minor', 'units_micro'])];
                })->all()];
        });
    }

    public function myBooks(Request $request): mixed
    {
        return $this->respond(function () use ($request): array {
            $rows = DB::table('club_members as member')->join('club_books as book', 'book.id', '=', 'member.book_id')
                ->join('financial_spaces as space', 'space.id', '=', 'book.financial_space_id')
                ->where('member.user_id', $request->user()->id)->orderBy('book.id')
                ->get(['book.id', 'book.financial_space_id', 'book.currency', 'book.ownership_model', 'book.cutover_date',
                    'space.name', 'member.capital_minor', 'member.units_micro']);

            return ['books' => $rows, 'scope' => 'Your own capital history, including a former group membership; not access to other members or the whole book.'];
        });
    }

    public function createBook(Request $request, int $space): mixed
    {
        return $this->respond(fn (): array => ['book' => $this->books->create(FinancialSpace::findOrFail($space), $request->user(), $request->all())], 201);
    }

    public function instructions(Request $request, int $space, int $book): mixed
    {
        return $this->respond(function () use ($request, $space, $book): array {
            $record = $this->book($space, $book);
            $this->access->book($record, $request->user(), write: true);
            $input = $request->validate(['status' => ['nullable', 'in:pending,approved,rejected,cancelled'],
                'page' => ['nullable', 'integer', 'min:1'], 'limit' => ['nullable', 'integer', 'min:1', 'max:100']]);
            $query = ClubInstruction::where('book_id', $record->id)->when(isset($input['status']), static fn ($q) => $q->where('status', $input['status']))->latest('id');
            $page = $query->paginate((int) ($input['limit'] ?? 25));

            return ['instructions' => $page->items(), 'page' => $page->currentPage(), 'total' => $page->total(), 'has_more' => $page->hasMorePages()];
        });
    }

    public function submit(Request $request, int $space, int $book): mixed
    {
        return $this->respond(fn (): array => ['instruction' => $this->instructions->submit($this->book($space, $book), $request->user(), $request->all())], 201);
    }

    public function instruction(Request $request, int $space, int $book, int $instruction): mixed
    {
        return $this->respond(function () use ($request, $space, $book, $instruction): array {
            $record = $this->book($space, $book);
            $this->access->book($record, $request->user(), write: true);

            return ['instruction' => ClubInstruction::where('book_id', $book)->findOrFail($instruction)];
        });
    }

    public function approve(Request $request, int $space, int $book, int $instruction): mixed
    {
        return $this->respond(function () use ($request, $space, $book, $instruction): array {
            $data = $request->validate(['payload_hash' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D']]);
            $record = $this->book($space, $book);

            return ['instruction' => $this->instructions->approve($record,
                ClubInstruction::where('book_id', $book)->findOrFail($instruction), $request->user(), $data['payload_hash'])];
        });
    }

    public function preview(Request $request, int $space, int $book, int $instruction): mixed
    {
        return $this->respond(fn (): array => $this->instructions->preview($this->book($space, $book),
            ClubInstruction::where('book_id', $book)->findOrFail($instruction), $request->user()));
    }

    public function reject(Request $request, int $space, int $book, int $instruction): mixed
    {
        return $this->decision($request, $space, $book, $instruction, false);
    }

    public function cancel(Request $request, int $space, int $book, int $instruction): mixed
    {
        return $this->decision($request, $space, $book, $instruction, true);
    }

    public function report(Request $request, int $space, int $book): mixed
    {
        return $this->respond(function () use ($request, $space, $book): array {
            $record = $this->book($space, $book);
            $data = $request->validate(['period_start' => ['required', 'date_format:Y-m-d'],
                'period_end' => ['required', 'date_format:Y-m-d'], 'member_user_id' => ['nullable', 'integer', 'min:1']]);
            $member = isset($data['member_user_id']) ? (int) $data['member_user_id'] : null;

            return ['report' => $this->reports->report($record, $request->user(), $data['period_start'], $data['period_end'], $member)];
        });
    }

    public function catalogue(Request $request, int $space, int $book): mixed
    {
        return $this->respond(function () use ($request, $space, $book): array {
            $record = $this->book($space, $book);
            $this->access->book($record, $request->user(), write: true);

            return [
                'accounts' => DB::table('club_accounts')->where('book_id', $book)->orderBy('code')->get(),
                'treasury_links' => DB::table('club_treasury_links as link')
                    ->join('financial_space_treasury_accounts as treasury', 'treasury.id', '=', 'link.treasury_account_id')
                    ->where('link.book_id', $book)->get(['link.treasury_account_id', 'link.account_id', 'treasury.account_name', 'treasury.currency', 'treasury.current_balance_minor']),
                'members' => DB::table('financial_space_memberships as membership')->join('users as person', 'person.id', '=', 'membership.user_id')
                    ->where('membership.financial_space_id', $space)->whereNull('membership.deleted_at')->whereNull('person.deleted_at')
                    ->get(['person.id', 'person.name', 'membership.status', 'membership.role']),
                'positions' => ClubMember::where('book_id', $book)->orderBy('user_id')->get(),
                'assets' => ClubAsset::where('book_id', $book)->orderBy('id')->get(),
                'contribution_plans' => DB::table('club_contribution_plans')->where('book_id', $book)->orderBy('id')->get(),
                'capital_calls' => DB::table('club_capital_calls')->where('book_id', $book)->where('status', 'open')->orderBy('due_date')->get(),
                'distributions' => DB::table('club_distributions')->where('book_id', $book)->orderByDesc('id')->limit(100)->get(),
            ];
        });
    }

    public function journals(Request $request, int $space, int $book): mixed
    {
        return $this->respond(function () use ($request, $space, $book): array {
            $record = $this->book($space, $book);
            $this->access->book($record, $request->user(), write: true);
            $data = $request->validate(['page' => ['nullable', 'integer', 'min:1'], 'limit' => ['nullable', 'integer', 'min:1', 'max:100']]);
            $page = DB::table('club_journals')->where('book_id', $book)->orderByDesc('sequence')->paginate((int) ($data['limit'] ?? 25));
            $items = [];
            foreach ($page->items() as $journal) {
                $items[] = array_merge((array) $journal, ['entries' => DB::table('club_journal_entries as entry')
                    ->join('club_accounts as account', 'account.id', '=', 'entry.account_id')->where('entry.journal_id', $journal->id)
                    ->orderBy('entry.id')->get(['entry.*', 'account.code', 'account.name'])]);
            }

            return ['journals' => $items, 'page' => $page->currentPage(), 'total' => $page->total(), 'has_more' => $page->hasMorePages()];
        });
    }

    public function integrity(Request $request, int $space, int $book): mixed
    {
        return $this->respond(function () use ($request, $space, $book): array {
            $record = $this->book($space, $book);
            $this->access->book($record, $request->user(), write: true);

            return $this->integrity->check($record);
        });
    }

    public function issueStatement(Request $request, int $space, int $book): mixed
    {
        return $this->respond(fn (): array => ['statement' => $this->reports->issue($this->book($space, $book), $request->user(), $request->all())], 201);
    }

    public function statements(Request $request, int $space, int $book): mixed
    {
        return $this->respond(function () use ($request, $space, $book): array {
            $record = $this->book($space, $book);
            $input = $request->validate(['member_user_id' => ['nullable', 'integer', 'min:1'], 'page' => ['nullable', 'integer', 'min:1']]);
            $member = isset($input['member_user_id']) ? (int) $input['member_user_id'] : null;
            $this->reports->authorise($record, $request->user(), $member);
            $query = ClubStatement::where('book_id', $book)->when($member !== null, static fn ($q) => $q->where('member_user_id', $member))
                ->latest('id')->select(['id', 'reference', 'member_user_id', 'period_start', 'period_end', 'content_hash', 'created_at']);
            $page = $query->paginate(25);

            return ['statements' => $page->items(), 'page' => $page->currentPage(), 'total' => $page->total(), 'has_more' => $page->hasMorePages()];
        });
    }

    public function statement(Request $request, int $space, int $book, int $statement): mixed
    {
        return $this->respond(fn (): array => ['statement' => $this->reports->read($this->book($space, $book),
            ClubStatement::where('book_id', $book)->findOrFail($statement), $request->user())]);
    }

    public function export(Request $request, int $space, int $book, int $statement, string $format): mixed
    {
        return $this->respond(function () use ($request, $space, $book, $statement, $format): mixed {
            $record = $this->reports->read($this->book($space, $book), ClubStatement::where('book_id', $book)->findOrFail($statement), $request->user());
            if ($format === 'html') {
                return response()->view('club-accounting.statement', ['statement' => $record])->withHeaders([
                    'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
                    'Content-Security-Policy' => "default-src 'none'; style-src 'self'; img-src 'none'; base-uri 'none'; frame-ancestors 'none'",
                ]);
            }
            if ($format !== 'csv') {
                abort(404);
            }

            return response()->streamDownload(function () use ($record): void {
                $output = fopen('php://output', 'wb');
                $write = static function (array $cells) use ($output): void {
                    $safe = array_map(static function ($cell) {
                        if (! is_string($cell)) { return $cell; }
                        return preg_match('/^[\s]*[=+@-]/u', $cell) ? "'".$cell : $cell;
                    }, $cells);
                    fputcsv($output, $safe, ',', '"', '');
                };
                $snapshot = $record->snapshot;
                $write(['OpFin Financial Space statement', $record->reference, $snapshot['space']['name']]);
                $write(['Currency', $snapshot['book']['currency'], 'Period', $snapshot['period_start'], $snapshot['period_end']]);
                $write(['Basis', $snapshot['basis']]);
                $write(['Content SHA-256', $record->content_hash]);
                $write(['Member', 'Name', 'Capital (minor units)', 'Ownership (micro-units)', 'Indicative net asset share']);
                foreach ($snapshot['member_positions'] as $row) {
                    $write([$row['user_id'], $row['name'], $row['capital_minor'], $row['units_micro'], $row['indicative_net_asset_share_minor']]);
                }
                $write([]); $write(['Date', 'Event', 'Member', 'Capital change', 'Unit change', 'Recorded member cash flow', 'Instruction']);
                foreach ($snapshot['member_movements'] as $row) {
                    $write([$row['business_date'], $row['type'], $row['user_id'], $row['capital_delta_minor'], $row['units_delta_micro'], $row['cash_flow_minor'], $row['instruction_id']]);
                }
                if (isset($snapshot['trial_balance'])) {
                    $write([]); $write(['Account', 'Name', 'Type', 'Closing debit', 'Closing credit']);
                    foreach ($snapshot['trial_balance']['accounts'] as $row) {
                        $write([$row['code'], $row['name'], $row['kind'], $row['balance_debit_minor'], $row['balance_credit_minor']]);
                    }
                }
                fclose($output);
            }, 'opfin-club-'.$record->reference.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
        });
    }

    private function decision(Request $request, int $space, int $book, int $instruction, bool $cancel): mixed
    {
        return $this->respond(function () use ($request, $space, $book, $instruction, $cancel): array {
            $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

            return ['instruction' => $this->instructions->reject($this->book($space, $book),
                ClubInstruction::where('book_id', $book)->findOrFail($instruction), $request->user(), $data['reason'], $cancel)];
        });
    }

    private function book(int $space, int $book): ClubBook
    {
        return ClubBook::where('financial_space_id', $space)->findOrFail($book);
    }

    private function respond(callable $action, int $status = 200): mixed
    {
        try {
            $result = $action();

            return is_array($result) ? ApiResponse::success('Club accounting request completed.', $result, $status)
                ->withHeaders(['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']) : $result;
        } catch (AuthorizationException) {
            return ApiResponse::error('You are not authorised for this Financial Space accounting action.', 403);
        } catch (ModelNotFoundException) {
            return ApiResponse::error('The requested accounting record is not available in this Financial Space.', 404);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }
    }
}
