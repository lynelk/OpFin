@extends('layouts.app')

@section('title', 'Delete Account - ' . config('app.name'))

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        <h2 class="h5 mb-0">Delete Your Account</h2>
                    </div>

                    <div class="card-body">
                        <div class="alert alert-warning">
                            <strong>Warning:</strong> This action is permanent and cannot be undone.
                            All your personal data will be anonymized, but financial records will
                            be retained as required by law.
                        </div>
                        @if (session('error'))
                            <div class="alert alert-danger">
                                {{ session('error') }}
                            </div>
                        @endif

                        <form method="POST" action="{{ route('account.destroy') }}">
                            @csrf
                            @method('DELETE')
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="phone" class="form-control" id="phone" name="phone" value="256"
                                    required>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>

                            <div class="mb-4">
                                <label for="confirmation" class="form-label">
                                    Type <strong>DELETE</strong> to confirm
                                </label>
                                <input type="text" class="form-control" id="confirmation" name="confirmation" required>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-danger"
                                    onclick="return confirm('Are you absolutely sure? This cannot be undone.')">
                                    Permanently Delete My Account
                                </button>
                                <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
