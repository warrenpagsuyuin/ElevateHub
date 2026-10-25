@extends('layouts.app')
@section('title', 'Questionnaire Comment Blind Review | Elevate DGM')

@push('styles')
@include('admin.engagement-research._styles')
@include('admin.sentiment-research._styles')
.comment-review-layout { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(320px, 0.9fr); gap: 20px; align-items: start; margin-top: 18px; }
@media (max-width: 900px) { .comment-review-layout { grid-template-columns: 1fr; } }
.comment-card { border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; background: #fff; margin-bottom: 14px; }
.comment-card.active { border-color: #b42318; box-shadow: 0 0 0 2px rgba(180, 35, 24, 0.15); }
.comment-text-box { font-size: 15px; line-height: 1.6; color: #1e293b; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin: 12px 0; white-space: pre-wrap; word-break: break-word; }
.badge { display: inline-block; padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 700; background: #f1f5f9; color: #475569; }
.badge-final { background: #dcfce7; color: #166534; }
.badge-adjudicate { background: #fef3c7; color: #92400e; }
.badge-awaiting { background: #e0e7ff; color: #3730a3; }
.btn-label-group { display: flex; flex-wrap: wrap; gap: 8px; margin: 12px 0; }
.btn-label { min-height: 44px; padding: 8px 16px; border-radius: 8px; font-weight: 700; cursor: pointer; border: 1px solid #cbd5e1; background: #fff; color: #334155; }
.btn-label:hover { background: #f1f5f9; }
.btn-label-positive { border-color: #86efac; color: #15803d; }
.btn-label-positive:hover { background: #dcfce7; }
.btn-label-neutral { border-color: #cbd5e1; color: #334155; }
.btn-label-neutral:hover { background: #f1f5f9; }
.btn-label-negative { border-color: #fca5a5; color: #b91c1c; }
.btn-label-negative:hover { background: #fee2e2; }
.btn-label-skip { border-color: #e2e8f0; color: #64748b; }
@endpush

@section('content')
<div class="research sentiment-workspace directory-workspace">
    <header class="research-head">
        <div>
            <a class="sentiment-back" href="{{ route('admin.analytics.sentiment') }}">Analytics / Sentiment</a>
            <h1>Questionnaire Comment Blind Review</h1>
            <p class="subtitle">Review anonymous questionnaire comments. Predictions stay completely hidden to maintain research validity.</p>
        </div>
    </header>

    <nav class="sentiment-tabs" aria-label="Sentiment workspace">
        @foreach([
            'overview' => ['Overview', route('admin.analytics.sentiment')],
            'comment-review' => ['Review', route('admin.satisfaction-comments.review')],
        ] as $key => $tab)
            <a href="{{ $tab[1] }}" @if($section === $key) aria-current="page" @endif>{{ $tab[0] }}</a>
        @endforeach
    </nav>

    @if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
    @if(session('error'))<div class="notice error" role="alert">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="notice error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

    <x-directory-toolbar tag="div" class="comment-review-toolbar">
        <form method="GET" action="{{ route('admin.satisfaction-comments.review') }}" style="display: flex; gap: 10px; align-items: center; margin: 0;">
            <label class="comment-queue-label">Filter Queue:
                <select name="status" onchange="this.form.submit()">
                    @foreach($filters as $key => $name)
                        <option value="{{ $key }}" @selected($filter === $key)>{{ $name }}</option>
                    @endforeach
                </select>
            </label>
        </form>

        <span class="seekers-result-count">Inter-rater agreement: <strong>{{ $agreement['observed'] !== null ? number_format($agreement['observed'] * 100, 1).'%' : 'N/A' }}</strong>
            @if($agreement['kappa'] !== null) (κ = {{ number_format($agreement['kappa'], 3) }}) @endif
        </span></x-directory-toolbar>

    <div class="comment-review-layout">
        <div class="review-queue-list">
            @forelse($queue as $item)
                @php
                    $isActive = $current && $current->id === $item->id;
                    $state = \App\Services\SatisfactionCommentService::stateOf($item);
                @endphp
                <div class="comment-card {{ $isActive ? 'active' : '' }}">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-size: 12px; font-weight: 800; color: #64748b;">Ref: {{ \App\Services\SatisfactionCommentEvaluation::ref($item->id) }}</span>
                        <span class="badge {{ str_contains($state, 'Final') ? 'badge-final' : (str_contains($state, 'adjudication') ? 'badge-adjudicate' : 'badge-awaiting') }}">{{ $state }}</span>
                    </div>
                    <p style="margin: 0 0 10px; font-size: 14px; color: #1e293b; line-height: 1.5;">{{ \Illuminate\Support\Str::limit($item->comment, 200) }}</p>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 12px; color: #64748b;">Reviews: {{ $item->reviewer_count }} / 2</span>
                        <a href="{{ route('admin.satisfaction-comments.review', ['status' => $filter, 'comment' => $item->id, 'page' => $queue->currentPage()]) }}" class="button" style="font-size: 13px; padding: 6px 12px;">
                            {{ $isActive ? 'Viewing' : 'Select →' }}
                        </a>
                    </div>
                </div>
            @empty
                <div class="panel empty-state">
                    <h3>No comments in this queue</h3>
                    <p style="color: #64748b;">There are currently no questionnaire comments matching the "{{ $filters[$filter] }}" status.</p>
                </div>
            @endforelse

            <div style="margin-top: 14px;">
                {{ $queue->links() }}
            </div>
        </div>

        <div class="review-workbench">
            @if($current)
                <div class="panel" style="position: sticky; top: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 12px;">
                        <div>
                            <h2 style="font-size: 18px; margin: 0;">Review Workbench</h2>
                            <small style="color: #64748b;">Reference {{ \App\Services\SatisfactionCommentEvaluation::ref($current->id) }}</small>
                        </div>
                        <span class="badge {{ str_contains(\App\Services\SatisfactionCommentService::stateOf($current), 'Final') ? 'badge-final' : '' }}">
                            {{ \App\Services\SatisfactionCommentService::stateOf($current) }}
                        </span>
                    </div>

                    <label style="font-size: 12px; font-weight: 800; color: #64748b; text-transform: uppercase;">Comment Text (Blind to prediction)</label>
                    <div class="comment-text-box">{{ $current->comment }}</div>

                    @if($mode === 'review')
                        <p style="font-size: 13px; color: #475569; margin: 8px 0 12px;">
                            Select your independent assessment of this comment's sentiment. The model's prediction is hidden.
                        </p>
                        <form method="POST" action="{{ route('admin.satisfaction-comments.label', $current->id) }}" id="review-label-form">
                            @csrf
                            <input type="hidden" name="status" value="{{ $filter }}">
                            <input type="hidden" name="page" value="{{ $queue->currentPage() }}">
                            <div class="btn-label-group">
                                @foreach($labels as $label)
                                    <button type="submit" name="label" value="{{ $label }}" class="btn-label btn-label-{{ strtolower(str_replace(' ', '-', $label)) }}">
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </form>
                    @elseif($mode === 'adjudicate')
                        <div style="background: #fffbeb; border: 1px solid #fef3c7; border-radius: 8px; padding: 12px; margin-bottom: 12px;">
                            <strong style="color: #92400e; font-size: 13px;">Adjudication Required</strong>
                            <p style="margin: 4px 0 0; font-size: 12px; color: #78350f;">
                                The previous two independent reviewers disagreed on this comment. As an independent third reviewer, assign the final ground-truth reference label.
                            </p>
                        </div>
                        <form method="POST" action="{{ route('admin.satisfaction-comments.adjudicate', $current->id) }}" id="adjudicate-label-form">
                            @csrf
                            <input type="hidden" name="status" value="{{ $filter }}">
                            <input type="hidden" name="page" value="{{ $queue->currentPage() }}">
                            <div class="btn-label-group">
                                @foreach($labels as $label)
                                    <button type="submit" name="label" value="{{ $label }}" class="btn-label btn-label-{{ strtolower(str_replace(' ', '-', $label)) }}">
                                        Final: {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </form>
                    @else
                        <div style="background: #f1f5f9; border-radius: 8px; padding: 14px; text-align: center; color: #475569; font-size: 13px;">
                            @if($current->final_label !== null)
                                <strong>Final label resolved: {{ $current->final_label }}</strong>
                                <small style="display: block; margin-top: 4px; color: #64748b;">Source: {{ $current->final_label_source }}</small>
                            @elseif($currentReviews->contains('reviewer_id', auth()->id()))
                                <strong>You have already recorded your blind review.</strong>
                                <small style="display: block; margin-top: 4px; color: #64748b;">Awaiting second review by another admin.</small>
                            @else
                                <em>No review action needed from your account.</em>
                            @endif
                        </div>
                    @endif

                    <div style="margin-top: 20px; padding-top: 14px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                        <form method="POST" action="{{ route('admin.satisfaction-comments.reanalyze', $current->id) }}" onsubmit="return confirm('Re-run sentiment prediction on this single comment?');" style="margin: 0;">
                            @csrf
                            <input type="hidden" name="confirm" value="1">
                            <button type="submit" class="button" style="font-size: 12px; padding: 6px 12px;">Re-analyze prediction</button>
                        </form>

                        <a href="{{ route('admin.satisfaction-comments.review', ['status' => $filter, 'page' => $queue->currentPage(), 'after' => $current->id]) }}" class="button" style="font-size: 12px; padding: 6px 12px;">
                            Skip / Next →
                        </a>
                    </div>
                </div>
            @else
                <div class="panel empty-state">
                    <h3>Select a comment</h3>
                    <p style="color: #64748b;">Choose a comment from the queue on the left to begin or continue review.</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
