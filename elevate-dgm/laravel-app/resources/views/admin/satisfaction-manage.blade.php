@extends('layouts.app')
@section('title', 'Satisfaction Questions | Elevate DGM')
@push('styles')
.satisfaction-manage{max-width:1100px}.satisfaction-manage .panel{padding:20px;margin:16px 0;background:var(--ui-surface);border:1px solid var(--ui-border);border-radius:var(--radius-lg)}
.satisfaction-manage form{display:grid;grid-template-columns:minmax(0,2fr) minmax(100px,1fr) auto auto auto;gap:10px;align-items:end;margin:12px 0}
.satisfaction-manage label{display:grid;gap:5px;font-size:13px;font-weight:700}.satisfaction-manage :is(input,select){min-height:44px;min-width:0;padding:8px;border:1px solid var(--select-border);border-radius:var(--radius-sm);font:inherit}
.satisfaction-manage :is(input,select,button):focus-visible{outline:2px solid var(--select-focus);outline-offset:2px}.satisfaction-manage button{min-height:44px;border-radius:9999px}
@media(max-width:760px){.satisfaction-manage form{grid-template-columns:1fr 1fr}.satisfaction-manage label:first-of-type{grid-column:1/-1}}
@endpush
@section('content')
<div class="satisfaction-manage"><a class="button" href="{{ route('admin.analytics.sentiment') }}">← Sentiment Analytics</a><h1>Satisfaction questions</h1><p>Answered questions keep their meaning. For a new meaning, deactivate the old question and add another. Cosmetic text corrections are recorded.</p>
@if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="notice error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<section class="panel"><h2>Add category</h2><form method="POST" action="{{ route('admin.satisfaction.categories.store') }}">@csrf<label>Name<input name="name" required maxlength="120"></label><label>Order<input name="position" type="number" min="1" value="{{ $categories->count() + 1 }}" required></label><label>State<select name="active"><option value="1">Active</option><option value="0">Inactive</option></select></label><button type="submit" class="dark-action">Add category</button></form></section>
@foreach($categories as $category)
<section class="panel"><h2>{{ $category->name }}</h2><form method="POST" action="{{ route('admin.satisfaction.categories.update', $category->id) }}">@csrf @method('PATCH')<label>Name<input name="name" value="{{ $category->name }}" required maxlength="120"></label><label>Order<input name="position" type="number" min="1" value="{{ $category->position }}" required></label><label>State<select name="active"><option value="1" @selected($category->active)>Active</option><option value="0" @selected(!$category->active)>Inactive</option></select></label><button type="submit" class="dark-action">Save category</button></form>
@foreach($category->questions as $question)
<form method="POST" action="{{ route('admin.satisfaction.questions.update', $question->id) }}">@csrf @method('PATCH')<input type="hidden" name="category_id" value="{{ $category->id }}"><label>Question<input name="text" value="{{ $question->text }}" required maxlength="500"></label><label>Order<input name="position" type="number" min="1" value="{{ $question->position }}" required></label><label>Audience<select name="dgroup_only"><option value="0" @selected(!$question->dgroup_only)>All members</option><option value="1" @selected($question->dgroup_only)>DGroup only</option></select></label><label>State<select name="active"><option value="1" @selected($question->active)>Active</option><option value="0" @selected(!$question->active)>Inactive</option></select></label><label><input type="checkbox" name="cosmetic_only" value="1">Text correction only</label><button type="submit" class="dark-action">Save</button></form>
@endforeach
<h3>Add question</h3><form method="POST" action="{{ route('admin.satisfaction.questions.store') }}">@csrf<input type="hidden" name="category_id" value="{{ $category->id }}"><label>Question<input name="text" required maxlength="500"></label><label>Order<input name="position" type="number" min="1" value="{{ $category->questions->count() + 1 }}" required></label><label>Audience<select name="dgroup_only"><option value="0">All members</option><option value="1">DGroup only</option></select></label><input type="hidden" name="active" value="1"><button type="submit" class="dark-action">Add question</button></form></section>
@endforeach
</div>
@endsection
