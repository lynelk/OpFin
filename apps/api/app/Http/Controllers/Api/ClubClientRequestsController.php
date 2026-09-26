<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClubBook;
use App\Models\ClubClientRequest;
use App\Services\ClubAccounting\ClubClientRequests;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class ClubClientRequestsController extends Controller
{
    public function __construct(private readonly ClubClientRequests $requests) {}

    public function index(Request $request): mixed
    {
        return $this->respond(function () use ($request): array {
            $input = $request->validate(['page' => ['nullable', 'integer', 'min:1'],
                'space_id' => ['nullable', 'integer', 'min:1']]);
            $page = ClubClientRequest::where('user_id', $request->user()->id)->whereNotNull('active_slot')
                ->when(isset($input['space_id']), fn ($query) => $query->whereIn('book_id',
                    ClubBook::where('financial_space_id', $input['space_id'])->select('id')))
                ->orderBy('id')->paginate(25);
            $rows = [];
            foreach ($page->items() as $record) {
                try {
                    $book = ClubBook::findOrFail($record->book_id);
                    $this->requests->current($book, $request->user(), $record->purpose);
                    $rows[] = ['reference' => $record->reference, 'content_hash' => $record->content_hash,
                        'book_id' => $book->id, 'financial_space_id' => $book->financial_space_id,
                        'currency' => $book->currency, 'purpose' => $record->purpose,
                        'status' => $record->status, 'resource_id' => $record->resource_id];
                } catch (AuthorizationException|ModelNotFoundException) {
                    // A revoked role must not expose a previously accessible club request.
                }
            }
            return ['requests' => $rows, 'page' => $page->currentPage(), 'has_more' => $page->hasMorePages()];
        });
    }

    public function prepare(Request $request, int $space, int $book, string $purpose): mixed
    {
        return $this->respond(function () use ($request, $space, $book, $purpose): array {
            $record = ClubBook::where('financial_space_id', $space)->findOrFail($book);
            $saved = $this->requests->prepare($record, $request->user(), $purpose, $request->all());
            return ['client_request' => ['reference' => $saved->reference, 'content_hash' => $saved->content_hash,
                'purpose' => $saved->purpose, 'status' => $saved->status]];
        }, 201);
    }

    public function inspect(Request $request, int $space, int $book): mixed
    {
        return $this->respond(function () use ($request, $space, $book): array {
            $input = $this->identity($request);
            return $this->requests->inspect(ClubBook::where('financial_space_id', $space)->findOrFail($book),
                $request->user(), $input['reference'], $input['content_hash']);
        });
    }

    public function submit(Request $request, int $space, int $book): mixed
    {
        return $this->respond(function () use ($request, $space, $book): array {
            $input = $this->identity($request);
            return $this->requests->submit(ClubBook::where('financial_space_id', $space)->findOrFail($book),
                $request->user(), $input['reference'], $input['content_hash']);
        });
    }

    public function acknowledge(Request $request, int $space, int $book): mixed
    {
        return $this->finish($request, $space, $book, false);
    }

    public function cancel(Request $request, int $space, int $book): mixed
    {
        return $this->finish($request, $space, $book, true);
    }

    private function finish(Request $request, int $space, int $book, bool $cancel): mixed
    {
        return $this->respond(function () use ($request, $space, $book, $cancel): array {
            $input = $this->identity($request);
            return $this->requests->finish(ClubBook::where('financial_space_id', $space)->findOrFail($book),
                $request->user(), $input['reference'], $input['content_hash'], $cancel);
        });
    }

    private function identity(Request $request): array
    {
        return $request->validate(['reference' => ['required', 'uuid'],
            'content_hash' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D']]);
    }

    private function respond(callable $action, int $status = 200): mixed
    {
        try {
            return ApiResponse::success('Saved accounting request.', $action(), $status)
                ->withHeaders(['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
        } catch (AuthorizationException) {
            return ApiResponse::error('You are not authorised to recover this accounting request.', 403);
        } catch (ModelNotFoundException) {
            return ApiResponse::error('The requested accounting record is unavailable.', 404);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        } catch (RuntimeException) {
            return ApiResponse::error('The saved request could not complete safely. Retain its identity and check its recorded state.', 409);
        }
    }
}
