@extends('layouts.app')

@section('content')
@php
    $user = auth()->user();
    $satellite = $user?->assignedSatellites()->latest()->first();
@endphp

<div class="header">
    <h1>Member Dashboard</h1>
</div>

<div class="stats">
    <div class="card red">
        <div class="info">
            <span class="label">Assigned Satellite</span>
            <span class="value">{{ $satellite?->display_name ?? 'Unassigned' }}</span>
        </div>
    </div>
    <div class="card black">
        <div class="info">
            <span class="label">Role</span>
            <span class="value">{{ ucfirst((string) ($user?->role ?? 'member')) }}</span>
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
    <h2>Member Summary</h2>
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
