@extends('layouts.app')

@section('content')
@php
    $user = auth()->user();
    $satellite = $user?->assignedSatellites()->latest()->first();
@endphp

<div class="header">
    <h1>Leader Dashboard</h1>
</div>

<div class="stats metric-card-grid">
    <div class="card metric-card elevate-stat-card">
        <div class="info">
            <span class="label stat-card-label">Assigned Satellite</span>
            <span class="value stat-card-value">{{ $satellite?->display_name ?? 'Unassigned' }}</span>
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
            <span class="label stat-card-label">District</span>
            <span class="value stat-card-value">{{ $user?->district ?? 'Not Set' }}</span>
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
    <h2>Leadership Summary</h2>
    <table>
        <thead>
            <tr>
                <th>Satellite</th>
                <th>Assigned User</th>
                <th>Access</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $satellite?->display_name ?? 'No Satellite' }}</td>
                <td>{{ $user?->name ?? 'Unknown User' }}</td>
                <td>{{ $satellite ? 'Open' : 'Pending' }}</td>
            </tr>
        </tbody>
    </table>
</section>
@endsection
