@extends('layouts.app')

@section('content')
@php
    $user = auth()->user();
    $satellites = $user?->assignedSatellites()->latest()->get() ?? collect();
@endphp

<div class="header">
    <h1>District Dashboard</h1>
</div>

<div class="stats">
    <div class="card red">
        <div class="info">
            <span class="label">District</span>
            <span class="value">{{ $user?->district ?? 'Not Set' }}</span>
        </div>
    </div>
    <div class="card black">
        <div class="info">
            <span class="label">Assigned Satellites</span>
            <span class="value">{{ $satellites->count() }}</span>
        </div>
    </div>
    <div class="card red">
        <div class="info">
            <span class="label">Role</span>
            <span class="value">{{ ucfirst((string) ($user?->role ?? 'member')) }}</span>
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
    <h2>Satellite Overview</h2>
    <table>
        <thead>
            <tr>
                <th>Satellite</th>
                <th>Assigned User</th>
                <th>Access</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($satellites as $satellite)
                <tr>
                    <td>{{ $satellite->display_name }}</td>
                    <td>{{ $satellite->assignedUser?->name ?? 'Unassigned' }}</td>
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
