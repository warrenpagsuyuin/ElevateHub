@extends('layouts.app')
@section('title', 'Checkpoint Review | Elevate DGM')
@push('styles')
@include('admin.movement-analytics.styles')
@endpush
@section('content')
<div class="movement">
    <header class="mv-header"><div><p class="mv-eyebrow">ELEVATE / CHECKPOINT REVIEW</p><h1>Confirm your unit counts</h1><p class="mv-subtitle">Leaders confirm their assigned units. Administrators finalize the movement checkpoint.</p></div></header>
    <section class="mv-panel"><label for="qc-checkpoint">Checkpoint</label><select id="qc-checkpoint">@foreach($checkpoints as $checkpoint)<option value="{{ $checkpoint->key }}" @selected($checkpoint->key === $checkpoints->last(fn ($cp) => $cp->as_of_date->toDateString() < now('Asia/Manila')->toDateString())?->key)>{{ $checkpoint->label }} · {{ $checkpoint->status }}</option>@endforeach</select><div style="margin-top:20px">@include('admin.movement-analytics.checkpoint-workflow')</div></section>
</div>
@endsection
