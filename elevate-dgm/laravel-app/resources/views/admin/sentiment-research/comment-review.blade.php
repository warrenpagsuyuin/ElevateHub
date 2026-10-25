@extends('layouts.app')
@section('title', 'Questionnaire Comment Blind Review | Elevate DGM')

@push('styles')
@include('admin.engagement-research._styles')
@include('admin.sentiment-research._styles')
.comment-review-layout { display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(340px, 1fr); gap: 20px; align-items: start; margin-top: 18px; }
@media (max-width: 960px) { .comment-review-layout { grid-template-columns: 1fr; } }
.comment-card { border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 18px; background: #fff; margin-bottom: 12px; transition: all 0.15s ease; }
.comment-card:hover { border-color: #cbd5e1; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04); }
.comment-card.active { border: 2px solid #450d07; background: #fcfcfd; box-shadow: 0 6px 18px rgba(69, 13, 7, 0.08); }
.comment-text-box { font-size: 16px; font-weight: 500; line-height: 1.6; color: #0f172a; background: #f8fafc; border: 1px solid #cbd5e1; border-left: 4px solid #450d07; border-radius: 10px; padding: 18px; margin: 10px 0 16px; white-space: pre-wrap; word-break: break-word; }
.badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 9999px; font-size: 12px; font-weight: 700; background: #f1f5f9; color: #475569; }
.badge-final { background: #dcfce7; color: #166534; }
.badge-adjudicate { background: #fef3c7; color: #92400e; }
.badge-awaiting { background: #e0e7ff; color: #3730a3; }
.btn-label-group { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; margin: 12px 0; }
.btn-label { min-height: 50px; padding: 10px 14px; border-radius: 10px; font-weight: 700; font-size: 14px; cursor: pointer; border: 1.5px solid #cbd5e1; background: #fff; color: #1e293b; display: flex; align-items: center; justify-content: space-between; gap: 8px; transition: all 0.12s ease; }
.btn-label:hover { transform: translateY(-1px); box-shadow: 0 4px 10px rgba(15, 23, 42, 0.06); }
.btn-label-positive { border-color: #10b981; color: #065f46; background: #f0fdf4; }
.btn-label-positive:hover { background: #dcfce7; border-color: #059669; }
.btn-label-neutral { border-color: #94a3b8; color: #1e293b; background: #f8fafc; }
.btn-label-neutral:hover { background: #e2e8f0; border-color: #475569; }
.btn-label-negative { border-color: #f87171; color: #991b1b; background: #fef2f2; }
.btn-label-negative:hover { background: #fee2e2; border-color: #dc2626; }
.btn-label-cannot-judge { border-color: #cbd5e1; color: #475569; background: #ffffff; }
.btn-label-cannot-judge:hover { background: #f1f5f9; border-color: #64748b; }
.kbd-hint { display: inline-flex; align-items: center; justify-content: center; min-width: 22px; height: 22px; padding: 0 6px; border-radius: 5px; font-size: 11px; font-weight: 700; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; background: rgba(15, 23, 42, 0.08); color: currentColor; }
.quick-inline-actions { display: inline-flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.quick-inline-btn { min-height: 32px !important; padding: 4px 10px !important; border-radius: 9999px !important; font-size: 12px !important; font-weight: 700 !important; cursor: pointer; border: 1px solid #cbd5e1; background: #ffffff; color: #334155; }
.quick-inline-btn.is-pos { border-color: #6ee7b7; color: #065f46; background: #ecfdf5; }
.quick-inline-btn.is-pos:hover { background: #d1fae5; }
.quick-inline-btn.is-neu { border-color: #cbd5e1; color: #334155; background: #f8fafc; }
.quick-inline-btn.is-neu:hover { background: #e2e8f0; }
.quick-inline-btn.is-neg { border-color: #fca5a5; color: #991b1b; background: #fef2f2; }
.quick-inline-btn.is-neg:hover { background: #fee2e2; }
.queue-filter-pills { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.queue-filter-pill { display: inline-flex; align-items: center; padding: 6px 12px; border-radius: 9999px; font-size: 12.5px; font-weight: 600; text-decoration: none; border: 1px solid #cbd5e1; background: #ffffff; color: #334155; transition: all 0.15s ease; }
.queue-filter-pill:hover { border-color: #450d07; }
.queue-filter-pill.is-active { background: #1A1A1A; color: #ffffff; border: 2px solid #450d07; }
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

    <x-directory-toolbar tag="div" class="comment-review-toolbar" style="display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
            <form method="GET" action="{{ route('admin.satisfaction-comments.review') }}" style="display: flex; gap: 10px; align-items: center; margin: 0;">
                <label class="comment-queue-label">Filter Queue:
                    <select name="status" onchange="this.form.submit()">
                        @foreach($filters as $key => $name)
                            <option value="{{ $key }}" @selected($filter === $key)>{{ $name }}</option>
                        @endforeach
                    </select>
                </label>
            </form>

            <div class="queue-filter-pills" aria-label="Quick queue filters">
                @foreach($filters as $key => $name)
                    <a href="{{ route('admin.satisfaction-comments.review', ['status' => $key]) }}" class="queue-filter-pill {{ $filter === $key ? 'is-active' : '' }}">{{ $name }}</a>
                @endforeach
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <span class="soft-badge" style="background: #1A1A1A; color: #ffffff; padding: 6px 12px; border-radius: 9999px; font-size: 12.5px;">
                {{ $queue->total() }} {{ \Illuminate\Support\Str::plural('comment', $queue->total()) }} in queue
            </span>
            <span class="seekers-result-count">Inter-rater agreement: <strong>{{ $agreement['observed'] !== null ? number_format($agreement['observed'] * 100, 1).'%' : 'N/A' }}</strong>
                @if($agreement['kappa'] !== null) (κ = {{ number_format($agreement['kappa'], 3) }}) @endif
            </span>
        </div>
    </x-directory-toolbar>

    <div class="comment-review-layout">
        <div class="review-queue-list">
            @forelse($queue as $item)
                @php
                    $isActive = $current && $current->id === $item->id;
                    $state = \App\Services\SatisfactionCommentService::stateOf($item);
                    $canInlineReview = $item->final_label === null && (int) $item->reviewer_count < \App\Services\SatisfactionCommentService::REVIEWS_REQUIRED && $filter === 'mine';
                @endphp
                <div class="comment-card {{ $isActive ? 'active' : '' }}">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; gap: 8px;">
                        <span style="font-size: 12px; font-weight: 800; color: {{ $isActive ? '#450d07' : '#64748b' }};">
                            @if($isActive) ● @endif Ref: {{ \App\Services\SatisfactionCommentEvaluation::ref($item->id) }}
                        </span>
                        <span class="badge {{ str_contains($state, 'Final') ? 'badge-final' : (str_contains($state, 'adjudication') ? 'badge-adjudicate' : 'badge-awaiting') }}">{{ $state }}</span>
                    </div>
                    <p style="margin: 0 0 12px; font-size: 14.5px; color: #1e293b; line-height: 1.5; font-weight: {{ $isActive ? '600' : '400' }};">{{ \Illuminate\Support\Str::limit($item->comment, 220) }}</p>
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; padding-top: 8px; border-top: 1px solid #f1f5f9;">
                        <span style="font-size: 12px; color: #64748b; font-weight: 600;">Reviews: {{ $item->reviewer_count }} / 2</span>

                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            @if($canInlineReview && !$isActive)
                                <form method="POST" action="{{ route('admin.satisfaction-comments.label', $item->id) }}" class="quick-inline-actions" style="margin: 0;">
                                    @csrf
                                    <input type="hidden" name="status" value="{{ $filter }}">
                                    <input type="hidden" name="page" value="{{ $queue->currentPage() }}">
                                    <button type="submit" name="label" value="Positive" class="quick-inline-btn is-pos" title="Quick label: Positive">+ Positive</button>
                                    <button type="submit" name="label" value="Neutral" class="quick-inline-btn is-neu" title="Quick label: Neutral">= Neutral</button>
                                    <button type="submit" name="label" value="Negative" class="quick-inline-btn is-neg" title="Quick label: Negative">− Negative</button>
                                </form>
                            @endif

                            <a href="{{ route('admin.satisfaction-comments.review', ['status' => $filter, 'comment' => $item->id, 'page' => $queue->currentPage()]) }}" class="button" style="font-size: 12.5px; padding: 6px 12px; min-height: 32px; {{ $isActive ? 'background: #1A1A1A; color: #fff; border-color: #450d07;' : '' }}">
                                {{ $isActive ? 'Active in Workbench' : 'Select →' }}
                            </a>
                        </div>
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
                <div class="panel" style="position: sticky; top: 20px; border-top: 3px solid #450d07;">
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 12px;">
                        <div>
                            <h2 style="font-size: 18px; margin: 0;">Review Workbench</h2>
                            <small style="color: #64748b; font-weight: 600;">Reference {{ \App\Services\SatisfactionCommentEvaluation::ref($current->id) }}</small>
                        </div>
                        <span class="badge {{ str_contains(\App\Services\SatisfactionCommentService::stateOf($current), 'Final') ? 'badge-final' : '' }}">
                            {{ \App\Services\SatisfactionCommentService::stateOf($current) }}
                        </span>
                    </div>

                    <label style="font-size: 11.5px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em;">Comment Text (Blind to prediction)</label>
                    <div class="comment-text-box">{{ $current->comment }}</div>

                    @if($mode === 'review')
                        <p style="font-size: 13px; color: #475569; margin: 8px 0 10px;">
                            Select your independent assessment (click or press keys <strong>1–4</strong>). Automatically advances to the next comment.
                        </p>
                        <form method="POST" action="{{ route('admin.satisfaction-comments.label', $current->id) }}" id="review-label-form" data-hotkey-form>
                            @csrf
                            <input type="hidden" name="status" value="{{ $filter }}">
                            <input type="hidden" name="page" value="{{ $queue->currentPage() }}">
                            <div class="btn-label-group">
                                @foreach($labels as $index => $label)
                                    <button type="submit" name="label" value="{{ $label }}" data-hotkey="{{ $index + 1 }}" class="btn-label btn-label-{{ strtolower(str_replace(' ', '-', $label)) }}">
                                        <span>{{ $label }}</span>
                                        <kbd class="kbd-hint" aria-hidden="true">{{ $index + 1 }}</kbd>
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
                        <form method="POST" action="{{ route('admin.satisfaction-comments.adjudicate', $current->id) }}" id="adjudicate-label-form" data-hotkey-form>
                            @csrf
                            <input type="hidden" name="status" value="{{ $filter }}">
                            <input type="hidden" name="page" value="{{ $queue->currentPage() }}">
                            <div class="btn-label-group">
                                @foreach($labels as $index => $label)
                                    <button type="submit" name="label" value="{{ $label }}" data-hotkey="{{ $index + 1 }}" class="btn-label btn-label-{{ strtolower(str_replace(' ', '-', $label)) }}">
                                        <span>Final: {{ $label }}</span>
                                        <kbd class="kbd-hint" aria-hidden="true">{{ $index + 1 }}</kbd>
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

                    <div style="margin-top: 16px; padding-top: 14px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                        <form method="POST" action="{{ route('admin.satisfaction-comments.reanalyze', $current->id) }}" onsubmit="return confirm('Re-run sentiment prediction on this single comment?');" style="margin: 0;">
                            @csrf
                            <input type="hidden" name="confirm" value="1">
                            <button type="submit" class="button" style="font-size: 12px; padding: 6px 12px; color: #64748b;">Re-analyze prediction</button>
                        </form>

                        <a href="{{ route('admin.satisfaction-comments.review', ['status' => $filter, 'page' => $queue->currentPage(), 'after' => $current->id]) }}" id="skip-next-comment-link" class="button" style="font-size: 12.5px; padding: 6px 14px; display: inline-flex; align-items: center; gap: 6px;">
                            <span>Skip / Next →</span>
                            <kbd class="kbd-hint" aria-hidden="true">S</kbd>
                        </a>
                    </div>
                </div>
            @else
                <div class="panel empty-state">
                    <h3>All caught up!</h3>
                    <p style="color: #64748b;">No comments are currently selected or waiting in this queue.</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form[data-hotkey-form]');
    const skipLink = document.getElementById('skip-next-comment-link');
    let submitting = false;

    document.addEventListener('keydown', function (e) {
        if (submitting || e.ctrlKey || e.metaKey || e.altKey) return;
        const tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
        if (tag === 'input' || tag === 'textarea' || tag === 'select' || (e.target && e.target.isContentEditable)) return;

        if (form && ['1', '2', '3', '4'].includes(e.key)) {
            const btn = form.querySelector('button[data-hotkey="' + e.key + '"]');
            if (btn) {
                e.preventDefault();
                submitting = true;
                btn.click();
            }
        } else if (skipLink && (e.key === 's' || e.key === 'S' || e.key === 'ArrowRight')) {
            e.preventDefault();
            submitting = true;
            window.location.href = skipLink.href;
        }
    });
});
</script>
@endpush
