@extends('layouts.app')

@section('title', 'SMS Messages')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">{{ $status ?? '' }} SMS Messages</h3>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <strong>SMS Log</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Recipient</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Sent At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($smsMessages as $sms)
                            <tr>
                                <td>{{ $sms->id }}</td>
                                <td>{{ $sms->to }}</td>
                                <td class="text-truncate" style="max-width: 300px;" title="{{ $sms->message }}">
                                    {{ Str::limit($sms->message, 50) }}
                                </td>
                                <td>
                                    <span
                                        class="badge bg-{{ $sms->status == 'Pending' ? 'secondary' : ($sms->status == 'Failed' ? 'danger' : 'success') }}">
                                        {{ $sms->status }}
                                    </span>
                                </td>
                                <td>{{ $sms->created_at->format('M d, Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    <i class="bi bi-exclamation-circle me-1"></i> No SMS messages found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($smsMessages->hasPages())
                <div class="d-flex justify-content-center mt-4">
                    {{ $smsMessages->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
