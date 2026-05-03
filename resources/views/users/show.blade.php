@extends('layouts.app')

@section('title', 'View User')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">{{ $user->name }}</h3>
        <a href="{{ route('users.index') }}" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Users
        </a>
    </div>

    @if ($latestScore)
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Credit Score</span>
                <small class="text-muted">
                    Fetched {{ $latestScore->created_at->diffForHumans() }}
                </small>
            </div>

            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <h4 class="fw-bold">{{ $latestScore->score }}</h4>
                        <small class="text-muted">Score</small>
                    </div>

                    <div class="col-md-3">
                        <h4>{{ $latestScore->band }}</h4>
                        <small class="text-muted">Band</small>
                    </div>

                    <div class="col-md-3">
                        <span class="badge bg-{{ $latestScore->rating === 'Very Poor' ? 'danger' : 'success' }}">
                            <h5>{{ $latestScore->rating }}</h5>
                        </span> <br>
                        <small class="text-muted">Rating</small>
                    </div>

                    <div class="col-md-3">
                        <h4>{{ $latestScore->probability_of_default_percent }}%</h4>
                        <small class="text-muted">Default Probability</small>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="alert alert-warning">
            No credit score found for this user.
        </div>
    @endif
    @if ($scoreHistory->count())
        <div class="card">
            <div class="card-header">
                <button class="btn btn-link p-0" data-bs-toggle="collapse" data-bs-target="#creditHistory">
                    View Credit Score History
                </button>
            </div>

            <div id="creditHistory" class="collapse">
                <div class="card-body p-0">
                    <table class="table mb-0 table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Score</th>
                                <th>Band</th>
                                <th>Rating</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($scoreHistory as $score)
                                <tr>
                                    <td>{{ $score->created_at->format('d M Y') }}</td>
                                    <td>{{ $score->score }}</td>
                                    <td>{{ $score->band }}</td>
                                    <td>{{ $score->rating }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

@endsection
