@extends('layouts.app')

@section('title', 'Float Management')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-0">Float Management</h3>
            <p class="text-muted small mb-0">Current Balance: <strong>{{ number_format($account->balance ?? 0, 2) }}</strong></p>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFloatTopupModal">
            <i class="bi bi-plus-lg me-1"></i> Add Float Top-up
        </button>

        <!-- Modal -->
        <div class="modal fade" id="addFloatTopupModal" tabindex="-1" aria-labelledby="addFloatTopupModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('float-topups.store') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="addFloatTopupModalLabel">New Float Top-up</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="float-amount" class="form-label">Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">Amount</span>
                                    <input id="float-amount" type="number" name="amount" step="0.01" min="0" class="form-control" placeholder="0.00" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="float-image" class="form-label">Image (optional)</label>
                                <input id="float-image" type="file" name="image" accept="image/*" class="form-control">
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-upload me-1"></i> Submit
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <strong>Float Management</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Account</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Image</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($floatTopups as $floatTopup)
                            <tr>
                                <td>{{ $floatTopup->id }}</td>
                                <td>{{ $account->name }}</td>
                                <td>{{ number_format($floatTopup->amount, 2) }}</td>
                                <td>{{ $floatTopup->status }}</td>
                                <td>                            
                                    @if($floatTopup->image)
                                        <a href="{{ asset('storage/' . $floatTopup->image) }}" target="_blank">View Image</a>
                                    @else
                                        N/A
                                    @endif 
                                </td>
                                <td>{{ $floatTopup->created_at?->format('M d, Y H:i') }}</td>
                                
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="bi bi-exclamation-circle me-1"></i> No float top-ups found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
