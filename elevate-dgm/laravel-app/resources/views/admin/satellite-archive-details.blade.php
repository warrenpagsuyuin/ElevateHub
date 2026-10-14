@extends('layouts.app')
@section('title', 'Archived satellite')
@section('content')
<h1>{{ $satellite->display_name }} ? Archived</h1>
<p>Archived on {{ $satellite->archived_at->timezone('Asia/Manila')->format('M d, Y H:i').' PHT' }}. This page is read-only. All linked history is retained.</p>
<dl><dt>Hub</dt><dd>{{ $satellite->hub }}</dd><dt>Slug (reserved)</dt><dd>{{ $satellite->slug }}</dd><dt>Members (including archived)</dt><dd>{{ $satellite->allMembers()->count() }}</dd><dt>Seekers</dt><dd>{{ $satellite->seekers()->count() }}</dd><dt>Assigned users</dt><dd>{{ $satellite->assignedUsers()->count() }}</dd></dl>
<a href="{{ route('admin.satellites', ['view' => 'archived']) }}">Back to Archived</a>
@endsection
