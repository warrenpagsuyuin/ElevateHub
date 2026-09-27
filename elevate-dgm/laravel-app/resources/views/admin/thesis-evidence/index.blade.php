@extends('layouts.app')
@section('title','Thesis Research Evidence')
@push('styles')
@include('admin.thesis-evidence.styles')
@endpush
@section('content')
<div class="evidence">
<p class="eyebrow">Elevate DGM · Authorized research</p><h1>Thesis Research Evidence</h1>
<p>Current records, documented eligibility and reproducible functional checks.</p>
<nav class="tools"><a href="{{ route('admin.thesis-evidence.export') }}">Export Thesis Evidence Report</a><a href="{{ route('admin.thesis-evidence.export',['format'=>'json']) }}">Download audit JSON</a></nav>
@include('admin.thesis-evidence.content', ['only'=>null,'interactive'=>true])
</div>
@endsection
