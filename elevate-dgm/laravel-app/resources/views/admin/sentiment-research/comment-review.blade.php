@extends('layouts.app')
@section('title', 'Questionnaire Comment Blind Review | Elevate DGM')

@push('styles')
@include('admin.engagement-research._styles')
@include('admin.sentiment-research._styles')
.sentiment-workspace.directory-workspace { max-width: 1320px; width: 100%; box-sizing: border-box; }
.unified-review-stream { width: 100%; max-width: 100%; margin: 18px 0 0; display: flex; flex-direction: column; gap: 16px; box-sizing: border-box; }
.unified-comment-card { width: 100%; max-width: 100%; box-sizing: border-box; border: 1px solid #e2e8f0; border-radius: 14px; padding: clamp(16px, 2vw, 24px); background: #ffffff; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03); transition: all 0.15s ease; }
.unified-comment-card:hover { border-color: #cbd5e1; box-shadow: 0 6px 16px rgba(15, 23, 42, 0.05); }
.unified-comment-card.active { border: 2px solid #450d07; background: #ffffff; box-shadow: 0 8px 24px rgba(69, 13, 7, 0.08); }
.unified-card-head { width: 100%; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9; margin-bottom: 14px; }
.unified-card-meta { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.unified-ref { font-size: 13px; font-weight: 800; color: #1e293b; letter-spacing: 0.01em; }
.unified-comment-card.active .unified-ref { color: #450d07; }
.unified-reviews-count { font-size: 12px; font-weight: 600; color: #64748b; background: #f8fafc; border: 1px solid #e2e8f0; padding: 3px 10px; border-radius: 9999px; }
.comment-text-box { width: 100%; box-sizing: border-box; font-size: clamp(14.5px, 1.1vw, 16px); font-weight: 500; line-height: 1.65; color: #0f172a; background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #450d07; border-radius: 10px; padding: clamp(14px, 1.5vw, 18px) clamp(16px, 2vw, 22px); margin: 0 0 16px; white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-word; height: auto; min-height: 54px; }
.badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 9999px; font-size: 12px; font-weight: 700; background: #f1f5f9; color: #475569; white-space: nowrap; }
.badge-final { background: #dcfce7; color: #166534; }
.badge-adjudicate { background: #fef3c7; color: #92400e; }
.badge-awaiting { background: #fef9c3; color: #854d0e; }
.sentiment-workspace .btn-label-group { width: 100%; display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin: 10px 0 0; }
.sentiment-workspace button.btn-label { width: 100%; min-height: 46px; padding: 10px 16px; border-radius: 10px; font-weight: 700; font-size: 14px; cursor: pointer; border: 1.5px solid #cbd5e1; background: #ffffff; color: #1e293b; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.12s ease; box-sizing: border-box; }
.sentiment-workspace button.btn-label:hover { transform: translateY(-1px); border-color: #450d07; box-shadow: 0 4px 10px rgba(15, 23, 42, 0.06); }
.sentiment-workspace button.btn-label-positive:hover { background: #f0fdf4; border-color: #16a34a; color: #166534; }
.sentiment-workspace button.btn-label-neutral:hover { background: #f8fafc; border-color: #475569; color: #0f172a; }
.sentiment-workspace button.btn-label-negative:hover { background: #fef2f2; border-color: #dc2626; color: #991b1b; }
.sentiment-workspace button.btn-label-cannot-judge:hover { background: #f1f5f9; border-color: #64748b; color: #334155; }
.kbd-hint { display: inline-flex; align-items: center; justify-content: center; min-width: 22px; height: 22px; padding: 0 6px; border-radius: 5px; font-size: 11px; font-weight: 700; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; background: #f1f5f9; border: 1px solid #e2e8f0; color: #334155; }
.unified-card-footer { width: 100%; margin-top: 16px; padding-top: 14px; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; }
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

    <x-directory-toolbar tag="div" class="comment-review-toolbar" style="display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; width: 100%; box-sizing: border-box;">
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

    <div class="unified-review-stream">
        @php
            $items = collect($queue->items());
            if ($current && !$items->contains('id', $current->id)) {
                $items->prepend($current);
            }
        @endphp

        @forelse($items as $item)
            @php
                $isActive = $current && $current->id === $item->id;
                $state = \App\Services\SatisfactionCommentService::stateOf($item);
                $itemMode = null;
                if ($isActive) {
                    $itemMode = $mode;
                } else {
                    if ($item->final_label === null && (int) $item->reviewer_count < \App\Services\SatisfactionCommentService::REVIEWS_REQUIRED && $filter === 'mine') {
                        $itemMode = 'review';
                    } elseif ($item->final_label === null && (int) ($item->distinct_labels ?? 0) > 1 && $filter === 'adjudication') {
                        $itemMode = 'adjudicate';
                    }
                }
            @endphp

            <article class="unified-comment-card {{ $isActive ? 'active' : '' }}" id="comment-card-{{ $item->id }}">
                <div class="unified-card-head">
                    <div class="unified-card-meta">
                        <span class="unified-ref">
                            @if($isActive) ● @endif Ref: {{ \App\Services\SatisfactionCommentEvaluation::ref($item->id) }}
                        </span>
                        <span class="unified-reviews-count">Reviews: {{ $item->reviewer_count }} / 2</span>
                        @if($isActive)
                            <span class="soft-badge" style="background: #1A1A1A; color: #ffffff; border: 1px solid #450d07;">Active · Keys 1–4</span>
                        @endif
                    </div>
                    <span class="badge {{ str_contains($state, 'Final') ? 'badge-final' : (str_contains($state, 'adjudication') ? 'badge-adjudicate' : 'badge-awaiting') }}">{{ $state }}</span>
                </div>

                <div class="comment-text-box">{{ $item->comment }}</div>

                @if($itemMode === 'review')
                    <p style="font-size: 13px; color: #475569; margin: 0 0 8px;">
                        Select your independent assessment{{ $isActive ? ' (click or press keys 1–4). Automatically advances to the next comment.' : '.' }}
                    </p>
                    <form method="POST" action="{{ route('admin.satisfaction-comments.label', $item->id) }}" @if($isActive) id="review-label-form" data-hotkey-form @endif style="width: 100%; margin: 0;">
                        @csrf
                        <input type="hidden" name="status" value="{{ $filter }}">
                        <input type="hidden" name="page" value="{{ $queue->currentPage() }}">
                        <div class="btn-label-group">
                            @foreach($labels as $index => $label)
                                <button type="submit" name="label" value="{{ $label }}" @if($isActive) data-hotkey="{{ $index + 1 }}" @endif class="btn-label btn-label-{{ strtolower(str_replace(' ', '-', $label)) }}">
                                    <span>{{ $label }}</span>
                                    @if($isActive)
                                        <kbd class="kbd-hint" aria-hidden="true">{{ $index + 1 }}</kbd>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </form>
                @elseif($itemMode === 'adjudicate')
                    <div style="background: #fffbeb; border: 1px solid #fef3c7; border-radius: 8px; padding: 10px 14px; margin-bottom: 10px;">
                        <strong style="color: #92400e; font-size: 13px;">Adjudication Required:</strong>
                        <span style="font-size: 12.5px; color: #78350f;">Previous two independent reviewers disagreed. Assign the final ground-truth label.</span>
                    </div>
                    <form method="POST" action="{{ route('admin.satisfaction-comments.adjudicate', $item->id) }}" @if($isActive) id="adjudicate-label-form" data-hotkey-form @endif style="width: 100%; margin: 0;">
                        @csrf
                        <input type="hidden" name="status" value="{{ $filter }}">
                        <input type="hidden" name="page" value="{{ $queue->currentPage() }}">
                        <div class="btn-label-group">
                            @foreach($labels as $index => $label)
                                <button type="submit" name="label" value="{{ $label }}" @if($isActive) data-hotkey="{{ $index + 1 }}" @endif class="btn-label btn-label-{{ strtolower(str_replace(' ', '-', $label)) }}">
                                    <span>Final: {{ $label }}</span>
                                    @if($isActive)
                                        <kbd class="kbd-hint" aria-hidden="true">{{ $index + 1 }}</kbd>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </form>
                @else
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; color: #475569; font-size: 13px;">
                        <div>
                            @if($item->final_label !== null)
                                <strong>Final label resolved: {{ $item->final_label }}</strong>
                                <span style="margin-left: 8px; color: #64748b;">(Source: {{ $item->final_label_source }})</span>
                            @elseif($isActive && $currentReviews->contains('reviewer_id', auth()->id()))
                                <strong>You have already recorded your blind review.</strong>
                                <span style="margin-left: 6px; color: #64748b;">Awaiting second review by another admin.</span>
                            @else
                                <span>Select this card to inspect review status or re-analyze.</span>
                            @endif
                        </div>
                    </div>
                @endif

                <div class="unified-card-footer">
                    <form method="POST" action="{{ route('admin.satisfaction-comments.reanalyze', $item->id) }}" onsubmit="return confirm('Re-run sentiment prediction on this single comment?');" style="margin: 0;">
                        @csrf
                        <input type="hidden" name="confirm" value="1">
                        <button type="submit" class="button" style="font-size: 12px; padding: 6px 12px; color: #64748b;">Re-analyze prediction</button>
                    </form>

                    <div style="display: flex; align-items: center; gap: 8px;">
                        @if($isActive)
                            <a href="{{ route('admin.satisfaction-comments.review', ['status' => $filter, 'page' => $queue->currentPage(), 'after' => $item->id]) }}" id="skip-next-comment-link" class="button" style="font-size: 12.5px; padding: 6px 14px; display: inline-flex; align-items: center; gap: 6px;">
                                <span>Skip / Next →</span>
                                <kbd class="kbd-hint" aria-hidden="true">S</kbd>
                            </a>
                        @else
                            <a href="{{ route('admin.satisfaction-comments.review', ['status' => $filter, 'comment' => $item->id, 'page' => $queue->currentPage()]) }}" class="button" style="font-size: 12.5px; padding: 6px 14px;">
                                Focus card →
                            </a>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="panel empty-state">
                <h3>No comments in this queue</h3>
                <p style="color: #64748b;">There are currently no questionnaire comments matching the "{{ $filters[$filter] }}" status.</p>
            </div>
        @endforelse

        <div style="margin-top: 8px;">
            {{ $queue->links() }}
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
