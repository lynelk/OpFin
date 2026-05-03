@extends('layouts.app')

@section('title', 'Loan Product Terms')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Add New Term - {{ $loanProduct->name }}</h3>
        <a href="{{ URL::previous() }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <strong>Term Details</strong>
        </div>
        <div class="card-body">
            <form action="{{ route('loan-products.store-term', $loanProduct->id) }}" method="POST">
                @csrf

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="interest_rate" class="form-label">Interest Rate (%) <span
                                class="text-danger">*</span></label>
                        <input type="number" step="0.01"
                            class="form-control @error('interest_rate') is-invalid @enderror" id="interest_rate"
                            name="interest_rate" value="{{ old('interest_rate') }}" required>
                        @error('interest_rate')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="interest_type" class="form-label">Interest Type <span
                                class="text-danger">*</span></label>
                        <select class="form-select @error('interest_type') is-invalid @enderror" id="interest_type"
                            name="interest_type" required>
                            <option value="" disabled selected>Select interest type</option>
                            <option value="Flat" {{ old('interest_type') == 'Flat' ? 'selected' : '' }}>Flat</option>
                            <option value="Amortization" {{ old('interest_type') == 'Amortization' ? 'selected' : '' }}>
                                Amortization
                            </option>
                        </select>
                        @error('interest_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="interest_cycle" class="form-label">Interest Cycle <span
                                class="text-danger">*</span></label>
                        <select class="form-select @error('interest_cycle') is-invalid @enderror" id="interest_cycle"
                            name="interest_cycle" required>
                            <option value="" disabled selected>Select interest cycle</option>
                            <option value="Daily" {{ old('interest_cycle') == 'Daily' ? 'selected' : '' }}>Daily</option>
                            <option value="Weekly" {{ old('interest_cycle') == 'Weekly' ? 'selected' : '' }}>Weekly</option>
                            <option value="Monthly" {{ old('interest_cycle') == 'Monthly' ? 'selected' : '' }}>Monthly
                            </option>
                        </select>
                        @error('interest_cycle')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="repayment_frequency" class="form-label">Repayment Frequency <span
                                class="text-danger">*</span></label>
                        <select class="form-select @error('repayment_frequency') is-invalid @enderror"
                            id="repayment_frequency" name="repayment_frequency" required>
                            <option value="" disabled selected>Select repayment frequency</option>
                            <option value="Daily" {{ old('repayment_frequency') == 'Daily' ? 'selected' : '' }}>Daily
                            </option>
                            <option value="Weekly" {{ old('repayment_frequency') == 'Weekly' ? 'selected' : '' }}>Weekly
                            </option>
                            <option value="Monthly" {{ old('repayment_frequency') == 'Monthly' ? 'selected' : '' }}>Monthly
                            </option>
                        </select>
                        @error('repayment_frequency')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="duration" class="form-label">Duration (in days) <span
                                class="text-danger">*</span></label>
                        <input type="number" class="form-control @error('duration') is-invalid @enderror" id="duration"
                            name="duration" value="{{ old('duration') }}" required>
                        @error('duration')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                        <select class="form-select @error('status') is-invalid @enderror" id="status" name="status"
                            required>
                            <option value="Active" {{ old('status') == 'Active' ? 'selected' : '' }}>Active
                            </option>
                            <option value="Inactive" {{ old('status') == 'Inactive' ? 'selected' : '' }}>
                                Inactive</option>
                        </select>
                        @error('status')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-plus-circle me-1"></i> Add Term
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
