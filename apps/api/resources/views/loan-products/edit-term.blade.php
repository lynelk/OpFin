@extends('layouts.app')

@section('title', 'Edit Loan Product Term')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Edit Loan Product Term</h3>
        <a href="{{ route('loan-products.show', $loanProduct->id) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <strong>Term Details</strong>
        </div>
        <div class="card-body">
            <form action="{{ route('loan-products.update-term', [$loanProduct->id, $term->id]) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="interest_rate" class="form-label">Interest Rate (%) <span
                                class="text-danger">*</span></label>
                        <input type="number" step="0.01"
                            class="form-control @error('interest_rate') is-invalid @enderror" id="interest_rate"
                            name="interest_rate" value="{{ old('interest_rate', $term->interest_rate) }}" required>
                        @error('interest_rate')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="interest_type" class="form-label">Interest Type <span
                                class="text-danger">*</span></label>
                        <select class="form-select @error('interest_type') is-invalid @enderror" id="interest_type"
                            name="interest_type" required>
                            <option value="Flat"
                                {{ old('interest_type', $term->interest_type) == 'Flat' ? 'selected' : '' }}>Flat</option>
                            <option value="Amortization"
                                {{ old('interest_type', $term->interest_type) == 'Amortization' ? 'selected' : '' }}>
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
                            <option value="Daily"
                                {{ old('interest_cycle', $term->interest_cycle) == 'Daily' ? 'selected' : '' }}>Daily
                            </option>
                            <option value="Weekly"
                                {{ old('interest_cycle', $term->interest_cycle) == 'Weekly' ? 'selected' : '' }}>Weekly
                            </option>
                            <option value="Monthly"
                                {{ old('interest_cycle', $term->interest_cycle) == 'Monthly' ? 'selected' : '' }}>Monthly
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
                            <option value="Daily"
                                {{ old('repayment_frequency', $term->repayment_frequency) == 'Daily' ? 'selected' : '' }}>
                                Daily</option>
                            <option value="Weekly"
                                {{ old('repayment_frequency', $term->repayment_frequency) == 'Weekly' ? 'selected' : '' }}>
                                Weekly</option>
                            <option value="Monthly"
                                {{ old('repayment_frequency', $term->repayment_frequency) == 'Monthly' ? 'selected' : '' }}>
                                Monthly</option>
                        </select>
                        @error('repayment_frequency')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="duration" class="form-label">Duration (in days) <span
                                class="text-danger">*</span></label>
                        <input type="number" class="form-control @error('duration') is-invalid @enderror" id="duration"
                            name="duration" value="{{ old('duration', $term->duration) }}" required>
                        @error('duration')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="guarantors_required" class="form-label">Guarantors required</label>
                        <select class="form-select @error('guarantors_required') is-invalid @enderror"
                            id="guarantors_required" name="guarantors_required">
                            <option value="0" {{ old('guarantors_required', $term->guarantors_required ?? 0) == 0 ? 'selected' : '' }}>None</option>
                            <option value="1" {{ old('guarantors_required', $term->guarantors_required) == 1 ? 'selected' : '' }}>1 verified guarantor</option>
                            <option value="2" {{ old('guarantors_required', $term->guarantors_required) == 2 ? 'selected' : '' }}>2 verified guarantors</option>
                        </select>
                        <div class="form-text">Maximum two contacts. Guarantor numbers must be electronically verified; contact-list access is prohibited.</div>
                        @error('guarantors_required')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="umra_interest_approval_reference" class="form-label">UMRA approval reference for interest-rate change</label>
                        <input type="text" class="form-control @error('umra_interest_approval_reference') is-invalid @enderror"
                            id="umra_interest_approval_reference" name="umra_interest_approval_reference"
                            value="{{ old('umra_interest_approval_reference', $term->umra_interest_approval_reference) }}">
                        <div class="form-text">Required only when changing the interest rate on an existing term.</div>
                        @error('umra_interest_approval_reference')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="umra_interest_approval_document_hash" class="form-label">Approval evidence SHA-256</label>
                        <input type="text" minlength="64" maxlength="64"
                            class="form-control @error('umra_interest_approval_document_hash') is-invalid @enderror"
                            id="umra_interest_approval_document_hash" name="umra_interest_approval_document_hash"
                            value="{{ old('umra_interest_approval_document_hash', $term->umra_interest_approval_document_hash) }}">
                        <div class="form-text">Hash the prior written UMRA approval evidence. The document itself belongs in the controlled compliance repository, not this form.</div>
                        @error('umra_interest_approval_document_hash')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                        <select class="form-select @error('status') is-invalid @enderror" id="status" name="status"
                            required>
                            <option value="Active" {{ old('status', $term->status) == 'Active' ? 'selected' : '' }}>Active
                            </option>
                            <option value="Inactive" {{ old('status', $term->status) == 'Inactive' ? 'selected' : '' }}>
                                Inactive</option>
                        </select>
                        @error('status')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-1"></i> Update Term
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
