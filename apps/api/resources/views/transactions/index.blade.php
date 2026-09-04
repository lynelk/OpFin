@extends('layouts.app')

@section('title', 'Transactions')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0"> {{ $status ?? '' }} Transactions</h3>
        <a href="#" class="btn btn-outline-primary">
            <i class="bi bi-download me-1"></i> Export
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <strong>Transaction List</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Phone</th>
                            <th>User</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Network</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $transaction)
                            <tr>
                                <td>{{ $transaction->id }}</td>
                                <td>{{ $transaction->user->phone }}</td>
                                <td>{{ $transaction->user->name ?? 'N/A' }}</td>
                                <td>{{ $transaction->type }}</td>
                                <td>{{ number_format($transaction->amount, 2) }}</td>
                                <td>{{ $transaction->network }}</td>
                                <td><small class="text-muted">{{ $transaction->data }}</small></td>
                                <td>
                                    <span
                                        class="badge bg-{{ $transaction->status == 'PENDING'
                                            ? 'secondary'
                                            : ($transaction->status == 'SUCCESSFUL'
                                                ? 'success'
                                                : ($transaction->status == 'FAILED'
                                                    ? 'danger'
                                                    : 'info')) }}">
                                        {{ $transaction->status }}
                                    </span>
                                </td>
                                <td>{{ $transaction->created_at->format('M d, Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    <i class="bi bi-exclamation-circle me-1"></i> No transactions found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-center mt-4">
                {{ $transactions->links() }}
            </div>
        </div>
    </div>
@endsection
