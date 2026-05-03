@extends('layouts.app')

@section('title', 'Accounts')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Accounts</h3>
        <a href="#" class="btn btn-outline-primary">
            <i class="bi bi-download me-1"></i> Export
        </a>
    </div>

    <div class="card shadow-sm mb-5">
        <div class="card-header">
            <strong>Product Accounts List</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Balance</th>
                            <th>Loan Product</th>
                            <th>Updated At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($accounts as $account)
                            <tr>
                                <td>{{ $account->id }}</td>
                                <td>{{ $account->name }}</td>
                                <td>{{ number_format($account->balance, 2) }}</td>
                                <td>{{ $account->loanProduct?->name }}</td>
                                <td>{{ $account->updated_at?->format('M d, Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="bi bi-exclamation-circle me-1"></i> No accounts found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header">
                    <strong>Collection Accounts List</strong>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Balance</th>
                                    <th>Updated At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($collectionAccounts as $account)
                                    <tr>
                                        <td>{{ $account->id }}</td>
                                        <td>{{ $account->name }}</td>
                                        <td>{{ number_format($account->balance, 2) }}</td>
                                        <td>{{ $account->updated_at?->format('M d, Y H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            <i class="bi bi-exclamation-circle me-1"></i> No accounts found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header">
                    <strong>Disbursement Accounts</strong>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Balance</th>
                                    <th>Updated At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($disbursementAccounts as $account)
                                    <tr>
                                        <td>{{ $account->id }}</td>
                                        <td>{{ $account->name }}</td>
                                        <td>{{ number_format($account->balance, 2) }}</td>
                                        <td>{{ $account->updated_at?->format('M d, Y H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            <i class="bi bi-exclamation-circle me-1"></i> No accounts found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
