@extends('layouts.app')

@section('content')
@php
    $user = auth()->user();
    $satellites = $user?->assignedSatellites()->with('assignedUsers')->latest()->get() ?? collect();
@endphp

<div class="header">
    <h1>District Dashboard</h1>
</div>

<div class="stats metric-card-grid">
    <div class="card metric-card elevate-stat-card">
        <div class="info">
            <span class="label stat-card-label">District</span>
            <span class="value stat-card-value">{{ $user?->district ?? 'Not Set' }}</span>
        </div>
    </div>
    <div class="card metric-card elevate-stat-card">
        <div class="info">
            <span class="label stat-card-label">Assigned Satellites</span>
            <span class="value stat-card-value">{{ $satellites->count() }}</span>
        </div>
    </div>
    <div class="card metric-card elevate-stat-card">
        <div class="info">
            <span class="label stat-card-label">Role</span>
            <span class="value stat-card-value">{{ ucfirst((string) ($user?->role ?? 'member')) }}</span>
        </div>
    </div>
    <div class="card metric-card elevate-stat-card">
        <div class="info">
            <span class="label stat-card-label">Status</span>
            <span class="value stat-card-value">Active</span>
        </div>
    </div>
</div>

<section class="table-card">
    <h2>Satellite Overview</h2>
    <table>
        <thead>
            <tr>
                <th>Satellite</th>
                <th>Assigned Users</th>
                <th>Access</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($satellites as $satellite)
                <tr>
                    <td>{{ $satellite->display_name }}</td>
                    <td>{{ $satellite->assignedUsers->pluck('name')->implode(', ') ?: 'Unassigned' }}</td>
                    <td>Open</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">No satellites assigned.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</section>
@endsection
