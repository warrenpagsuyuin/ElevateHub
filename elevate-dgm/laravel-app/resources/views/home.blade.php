@extends('layouts.app')

@section('title', 'Dashboard | Elevate DGM')

@section('content')
@php
    $user = auth()->user();
    $assignedSatellite = $user?->assignedSatellites()->latest()->first();
    $roleLabel = \Illuminate\Support\Str::of((string) ($user?->role ?? 'member'))
        ->replace(['_', '-'], ' ')
        ->title();
@endphp

<div class="page-header">
    <div>
        <h1>Welcome, {{ $user?->name ?? 'Member' }}</h1>
        <p class="page-subtitle">{{ now()->format('F j, Y') }}</p>
    </div>
</div>

<section class="stats-grid" aria-label="Profile summary">
    <article class="stat-card">
        <div class="info">
            <span class="stat-value">{{ $roleLabel }}</span>
            <span class="stat-label">Role</span>
        </div>
    </article>
    <article class="stat-card">
        <div class="info">
            <span class="stat-value">{{ $assignedSatellite?->display_name ?? 'Unassigned' }}</span>
            <span class="stat-label">Assigned Satellite</span>
        </div>
    </article>
    <article class="stat-card">
        <div class="info">
            <span class="stat-value">{{ $user?->district ?? 'Not Set' }}</span>
            <span class="stat-label">District</span>
        </div>
    </article>
    <article class="stat-card">
        <div class="info">
            <span class="stat-value">Active</span>
            <span class="stat-label">Status</span>
        </div>
    </article>
</section>

<section class="data-card" aria-labelledby="account-summary-title">
    <h2 class="section-title" id="account-summary-title">Account Summary</h2>
    <div style="overflow-x: auto;">
        <table class="data-table" aria-labelledby="account-summary-title">
            <thead>
                <tr>
                    <th scope="col">Account</th>
                    <th scope="col">Satellite</th>
                    <th scope="col">Access</th>
                    <th scope="col">Feedback</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $user?->email ?? 'Guest' }}</td>
                    <td>{{ $assignedSatellite?->display_name ?? 'Unassigned' }}</td>
                    <td>{{ $assignedSatellite ? 'Satellite Access' : 'General Access' }}</td>
                    <td><a href="{{ route('feedback.create') }}" style="color: var(--brand-red); font-weight: 800; text-decoration: underline; text-underline-offset: 3px;">Send feedback</a></td>
                </tr>
            </tbody>
        </table>
    </div>
</section>
@endsection
