@extends('layouts.app')

@section('title', 'Institutions')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Institutions</h3>
        @if (Auth::user()->role === 'Super')
            <a href="{{ route('institutions.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Add Institution
            </a>
        @endif
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <strong>Institution List</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">#</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Address</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($institutions as $institution)
                            <tr>
                                <td>{{ $institution->id }}</td>
                                <td>{{ $institution->name }}</td>
                                <td>{{ $institution->phone }}</td>
                                <td>{{ $institution->email }}</td>
                                <td>{{ $institution->address }}</td>
                                <td>
                                    <span
                                        class="badge {{ $institution->status === 'Active' ? 'bg-success' : 'bg-secondary' }} text-white">
                                        {{ $institution->status }}
                                    </span>
                                </td>
                                <td>{{ $institution->created_at?->format('M d, Y') }}</td>
                                <td>{{ $institution->updated_at?->format('M d, Y') }}</td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <a href="{{ route('institutions.edit', $institution->id) }}"
                                            class="btn btn-sm btn-outline-primary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="{{ route('institutions.destroy', $institution->id) }}" method="POST"
                                            onsubmit="return confirm('Are you sure you want to delete this institution?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    <i class="bi bi-exclamation-circle me-1"></i> No institutions found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
