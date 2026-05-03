@extends('layouts.app')

@section('title', 'Loan Products')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Loan Products</h3>
        <a href="{{ route('loan-products.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Add Loan Product
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Portfolio</th>
                            <th>Institution</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($loanProducts as $product)
                            <tr>
                                <td>{{ $product->id }}</td>
                                <td>{{ $product->name }}</td>
                                <td>{{ $product->type }}</td>
                                <td>{{ $product->account?->balance }}</td>
                                <td>{{ $product->institution?->name ?? 'N/A' }}</td>
                                <td>
                                    <span
                                        class="badge {{ $product->status === 'Active' ? 'bg-success' : 'bg-secondary' }} text-white">
                                        {{ $product->status }}
                                    </span>
                                </td>
                                <td>{{ $product->created_at?->format('M d, Y') }}</td>
                                <td>{{ $product->updated_at?->format('M d, Y') }}</td>
                                <td class="d-flex gap-1">
                                    <a href="{{ route('loan-products.show', $product->id) }}"
                                        class="btn btn-sm btn-outline-primary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('loan-products.edit', $product->id) }}"
                                        class="btn btn-sm btn-outline-warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="{{ route('loan-products.change-status', $product->id) }}" method="POST"
                                        onsubmit="return confirm('Are you sure you want to update the status of this product?');">
                                        @csrf
                                        @method('PUT')
                                        <button type="submit"
                                            class="btn btn-sm btn-outline-{{ $product->status === 'Active' ? 'secondary' : 'success' }}"
                                            title="{{ $product->status === 'Active' ? 'Deactivate' : 'Activate' }}">
                                            <i
                                                class="bi {{ $product->status === 'Active' ? 'bi-x-circle' : 'bi-check-circle' }}"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    <i class="bi bi-exclamation-circle me-1"></i> No loan products found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
