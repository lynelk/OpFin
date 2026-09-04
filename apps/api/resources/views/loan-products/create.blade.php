@extends('layouts.app')

@section('title', 'Loan Products')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Add Loan Product</h3>
        <a href="{{ route('loan-products.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to List
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <strong>Loan Product Details</strong>
        </div>
        <div class="card-body">
            <form action="{{ route('loan-products.store') }}" method="POST">
                @csrf

                <div class="mb-3">
                    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name"
                        class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="type" class="form-label">Type <span class="text-danger">*</span></label>
                    <select id="type" name="type" class="form-select @error('type') is-invalid @enderror" required>
                        <option value="">-- Select Type --</option>
                        <option value="Cash" {{ old('type') === 'Cash' ? 'selected' : '' }}>Cash</option>
                        <option value="Asset" {{ old('type') === 'Asset' ? 'selected' : '' }}>Asset</option>
                    </select>
                    @error('type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-circle me-1"></i> Create Loan Product
                </button>
            </form>
        </div>
    </div>
@endsection
