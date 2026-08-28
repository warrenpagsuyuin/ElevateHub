@extends('layouts.app')

@section('content')
@php
    $user = auth()->user();
    $assignedSatellite = $user?->assignedSatellites()->latest()->first();
@endphp

<div class="header">
    <h1>Welcome, {{ $user?->name ?? 'Member' }}</h1>
</div>

<div class="stats">
    <div class="card red">
        <div class="info">
            <span class="label">Role</span>
            <span class="value">{{ ucfirst((string) ($user?->role ?? 'member')) }}</span>
        </div>
    </div>
    <div class="card black">
        <div class="info">
            <span class="label">Assigned Satellite</span>
            <span class="value">{{ $assignedSatellite?->display_name ?? 'Unassigned' }}</span>
        </div>
    </div>
    <div class="card red">
        <div class="info">
            <span class="label">District</span>
            <span class="value">{{ $user?->district ?? 'Not Set' }}</span>
        </div>
    </div>
    <div class="card black">
        <div class="info">
            <span class="label">Status</span>
            <span class="value">Active</span>
        </div>
    </div>
</div>

<section class="table-card">
    <h2>Home Summary</h2>
    <table>
        <thead>
            <tr>
                <th>Account</th>
                <th>Satellite</th>
                <th>Access</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $user?->email ?? 'Guest' }}</td>
                <td>{{ $assignedSatellite?->display_name ?? 'Unassigned' }}</td>
                <td>{{ $assignedSatellite ? 'Satellite Access' : 'General Access' }}</td>
            </tr>
        </tbody>
    </table>
</section>
@endsection
