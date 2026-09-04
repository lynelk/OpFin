@extends('layouts.app')

@section('title', 'Loan Product')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">{{ $loanProduct->name }}</h3>
        <a href="{{ route('loan-products.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>

    <div class="card shadow-sm mb-5">
        <div class="card-header">
            <strong>Product Details</strong>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-12">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <strong>Name:</strong>
                            <span>{{ $loanProduct->name }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <strong>Type:</strong>
                            <span>{{ $loanProduct->type }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <strong>Portfolio Balance:</strong>
                            <span>{{ $loanProduct->account?->balance ?? 'N/A' }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <strong>Institution:</strong>
                            <span>{{ $loanProduct->institution?->name ?? 'N/A' }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <strong>Status:</strong>
                            <span class="badge @if ($loanProduct->status == 'Active') bg-success @else bg-secondary @endif">
                                {{ $loanProduct->status }}
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <strong>Created At:</strong>
                            <span>{{ $loanProduct->created_at?->format('M d, Y') }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <strong>Updated At:</strong>
                            <span>{{ $loanProduct->updated_at?->format('M d, Y') }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Product Terms</strong>
            <a href="{{ route('loan-products.add-term', $loanProduct->id) }}" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Add Term
            </a>
        </div>

        <div class="card-body">
            @if ($loanProduct->terms->isEmpty())
                <p class="text-muted mb-0">No terms available for this product.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Interest Rate</th>
                                <th>Interest Type</th>
                                <th>Interest Cycle</th>
                                <th>Repayment Frequency</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($loanProduct->terms as $term)
                                <tr>
                                    <td>{{ $term->interest_rate }}%</td>
                                    <td>{{ $term->interest_type }}</td>
                                    <td>{{ $term->interest_cycle }}</td>
                                    <td>{{ $term->repayment_frequency }}</td>
                                    <td>{{ $term->duration }} days</td>
                                    <td>
                                        <span
                                            class="badge {{ $term->status === 'Active' ? 'bg-success' : 'bg-secondary' }}">
                                            {{ $term->status }}
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ route('loan-products.edit-term', [$loanProduct->id, $term->id]) }}"
                                            class="btn btn-sm btn-outline-primary me-1">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form
                                            action="{{ route('loan-products.change-term-status', [$loanProduct->id, $term->id]) }}"
                                            method="POST" class="d-inline">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit"
                                                class="btn btn-sm btn-{{ $term->status == 'Active' ? 'outline-secondary' : 'outline-success' }}"
                                                onclick="return confirm('Are you sure you want to update the status of this term?')">
                                                <i class="bi {{ $term->status == 'Active' ? 'bi-pause' : 'bi-play' }}"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
