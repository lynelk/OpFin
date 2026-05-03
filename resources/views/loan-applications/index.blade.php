@extends('layouts.app')

@section('title', 'Loan Applications')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">{{ $status ?? '' }} Loan Applications</h3>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Institution</th>
                            <th>Loan Product</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Applied At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($applications as $application)
                            <tr>
                                <td>{{ $application->id }}</td>
                                <td>{{ $application->user->name ?? 'N/A' }}</td>
                                <td>{{ $application->institution->name ?? 'N/A' }}</td>
                                <td>{{ $application->loanProduct->name ?? 'N/A' }}</td>
                                <td>{{ number_format($application->amount, 2) }}</td>
                                <td>
                                    <span
                                        class="badge bg-{{ $application->status == 'Pending'
                                            ? 'secondary'
                                            : ($application->status == 'Disbursed'
                                                ? 'success'
                                                : ($application->status == 'Rejected'
                                                    ? 'danger'
                                                    : 'info')) }}">
                                        {{ $application->status }}
                                    </span>
                                </td>
                                <td>{{ $application->created_at?->format('M d, Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="bi bi-exclamation-circle me-1"></i> No loan applications found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-center mt-4">
                {{ $applications->links() }}
            </div>
        </div>
    </div>
@endsection
