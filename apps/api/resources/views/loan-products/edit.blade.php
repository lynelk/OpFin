@extends('layouts.app')

@section('title', 'Loan Products')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Add Loan Product</h3>
        <a href="{{ route('loan-products.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to List
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('loan-products.update', $loanProduct->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label for="institution_id" class="form-label">Institution</label>
                    <select class="form-select @error('institution_id') is-invalid @enderror" id="institution_id"
                        name="institution_id">
                        <option value="">Select Institution</option>
                        @foreach ($institutions as $institution)
                            <option value="{{ $institution->id }}" @selected(old('institution_id', $loanProduct->institution_id) == $institution->id)>{{ $institution->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('institution_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name"
                        name="name" value="{{ old('name', $loanProduct->name) }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="type" class="form-label">Type <span class="text-danger">*</span></label>
                    <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
                        <option value="Cash" @selected(old('type', $loanProduct->type) == 'Cash')>Cash</option>
                        <option value="Asset" @selected(old('type', $loanProduct->type) == 'Asset')>Asset</option>
                    </select>
                    @error('type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-success">Update Loan Product</button>
            </form>

        </div>
    </div>
@endsection
