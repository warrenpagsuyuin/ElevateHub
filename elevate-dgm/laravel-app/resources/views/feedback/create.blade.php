@extends('layouts.app')

@section('title', 'System Feedback | Elevate DGM')

@push('styles')
    .feedback-page { max-width: 980px; }
    .feedback-form {
        background: var(--ui-surface);
        border-radius: var(--radius-lg);
        padding: var(--ui-space-6);
        box-shadow: var(--shadow-card);
    }
    .feedback-consent {
        display: flex;
        align-items: flex-start;
        gap: var(--ui-space-3);
        margin: 0 0 var(--ui-space-6);
        cursor: pointer;
    }
    .feedback-consent input {
        width: var(--ui-target);
        height: var(--ui-target);
        flex: 0 0 var(--ui-target);
        margin: 0;
        accent-color: var(--ui-primary);
        cursor: pointer;
    }
    .feedback-fields { display: grid; gap: var(--ui-space-5); }
    .feedback-field { display: grid; gap: var(--ui-space-2); }
    .feedback-field label { font-size: var(--ui-text-sm); font-weight: 700; color: var(--ui-text); }
    .feedback-field textarea {
        width: 100%;
        resize: vertical;
        border: 1px solid var(--ui-border);
        border-radius: var(--radius-sm);
        padding: var(--ui-space-3) var(--ui-space-4);
        font: inherit;
        line-height: 1.5;
    }
    .feedback-field small { color: var(--ui-error); font-size: var(--ui-text-sm); font-weight: 700; }
    .feedback-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: var(--ui-space-4);
        margin-top: var(--ui-space-6);
        flex-wrap: wrap;
    }
    .feedback-help { margin: 0; color: var(--ui-muted); font-size: var(--ui-text-sm); max-width: 620px; }
@endpush

@section('content')
<div class="feedback-page">
    <div class="header">
        <div>
            <h1>System Feedback</h1>
            <p class="page-subtitle">Submit evaluator feedback after testing the developed system.</p>
        </div>
        <a class="btn btn-secondary" href="{{ route('feedback.index') }}">My Feedback</a>
    </div>

    @if ($errors->any())
        <div class="notice error" role="alert" tabindex="-1" data-validation-summary>
            Please correct the feedback fields below.
        </div>
    @endif

    <form method="POST" action="{{ route('feedback.store') }}" class="feedback-form">
        @csrf
        <label class="feedback-consent"><input type="checkbox" name="research_consent" value="1" @checked(old('research_consent'))><span>I consent to use of my written system feedback for research and system evaluation, including de-identified quotations. This is optional; I can submit feedback without consenting.</span></label>

        <div class="feedback-fields">
            @foreach ($questions as $key => $question)
                <div class="feedback-field">
                    <label for="feedback-{{ $key }}">{{ $question }} (required)</label>
                    <textarea
                        id="feedback-{{ $key }}"
                        name="{{ $key }}"
                        rows="4"
                        required
                        @error($key) aria-invalid="true" aria-describedby="feedback-{{ $key }}-error" @enderror
                    >{{ old($key) }}</textarea>
                    @error($key)
                        <small id="feedback-{{ $key }}-error">{{ $message }}</small>
                    @enderror
                </div>
            @endforeach

            <div class="feedback-field">
                <label for="feedback-message">Additional comments</label>
                <textarea
                    id="feedback-message"
                    name="message"
                    rows="3"
                    @error('message') aria-invalid="true" aria-describedby="feedback-message-error" @enderror
                >{{ old('message') }}</textarea>
                @error('message')
                    <small id="feedback-message-error">{{ $message }}</small>
                @enderror
            </div>
        </div>

        <div class="feedback-actions">
            <p class="feedback-help">Original feedback is preserved, analyzed for Positive, Neutral, or Negative sentiment, and compared with admin reference labels for validation metrics.</p>
            <button type="submit" class="btn btn-primary">Submit Feedback</button>
        </div>
    </form>
</div>
@endsection
