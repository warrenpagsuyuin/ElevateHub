@extends('layouts.app')

@section('title', $user->name.' | Elevate DGM')

@push('styles')
.header { display: flex; align-items: center; justify-content: space-between; gap: 18px; margin-bottom: 22px; }
        .header h1 { margin: 0; font-size: 32px; font-weight: 900; }
        .back-link { color: #e33c24; font-weight: 900; text-decoration: none; }
        .account-card { max-width: 720px; border-radius: 16px; background: #fff; padding: 28px; box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08); }
        .account-grid { display: grid; grid-template-columns: 170px 1fr; gap: 16px; font-size: 16px; }
        .label { color: #64748b; font-weight: 800; }
        .value { color: #111827; font-weight: 900; }
@endpush

@section('content')
<div class="header">
            <h1>{{ $user->name }}</h1>
            <a class="back-link" href="{{ route('admin.users') }}">Back to Users</a>
        </div>

        <section class="account-card">
            <div class="account-grid">
                <div class="label">Name</div><div class="value">{{ $user->name }}</div>
                <div class="label">Email</div><div class="value">{{ $user->email }}</div>
                <div class="label">Role</div><div class="value">{{ ucwords(str_replace('_', ' ', $user->role ?: 'member')) }}</div>
                <div class="label">District</div><div class="value">{{ $user->district ?: 'Unassigned' }}</div>
                <div class="label">Created</div><div class="value">{{ $user->created_at?->format('M d, Y') }}</div>
            </div>
        </section>
@endsection
