@extends('layouts.app')

@section('title', 'Loans')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">{{ $status ?? '' }} Loans</h3>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Loan Product</th>
                            <th>Amount</th>
                            <th>Repayment</th>
                            <th>Outstanding</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Disbursed At</th>
                            <th>Repayment Start Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($loans as $loan)
                            <tr>
                                <td>{{ $loan->id }}</td>
                                <td>{{ $loan->user->name ?? 'N/A' }}</td>
                                <td>{{ $loan->loanProduct->name ?? 'N/A' }}</td>
                                <td>{{ number_format($loan->amount) }}</td>
                                <td>{{ number_format($loan->repayment_amount) }}</td>
                                <td>{{ number_format($loan->outstanding_balance) }}</td>
                                <td>{{ $loan->duration }} days</td>
                                <td>
                                    <span
                                        class="badge bg-{{ $loan->status == 'Disbursed' ? 'secondary' : ($loan->status == 'Cleared' ? 'success' : 'info') }}">
                                        {{ $loan->status }}
                                    </span>
                                </td>
                                <td>{{ $loan->disbursed_at->format('M d, Y H:i') }}</td>
                                <td>{{ $loan->repayment_start_date->format('M d, Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">
                                    <i class="bi bi-exclamation-circle me-1"></i> No loans found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($loans->hasPages())
                <div class="d-flex justify-content-center mt-4">
                    {{ $loans->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
