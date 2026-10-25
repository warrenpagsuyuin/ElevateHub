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

<section class="stats-grid metric-card-grid" aria-label="Profile summary">
    <article class="stat-card metric-card elevate-stat-card">
        <div class="info">
            <span class="label stat-card-label">Role</span>
            <span class="value stat-card-value">{{ $roleLabel }}</span>
        </div>
    </article>
    <article class="stat-card metric-card elevate-stat-card">
        <div class="info">
            <span class="label stat-card-label">Assigned Satellite</span>
            <span class="value stat-card-value">{{ $assignedSatellite?->display_name ?? 'Unassigned' }}</span>
        </div>
    </article>
    <article class="stat-card metric-card elevate-stat-card">
        <div class="info">
            <span class="label stat-card-label">District</span>
            <span class="value stat-card-value">{{ $user?->district ?? 'Not Set' }}</span>
        </div>
    </article>
    <article class="stat-card metric-card elevate-stat-card">
        <div class="info">
            <span class="label stat-card-label">Status</span>
            <span class="value stat-card-value">Active</span>
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
