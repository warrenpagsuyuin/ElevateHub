@extends('layouts.app')

@section('title', 'Satisfaction Questionnaire | Elevate DGM')

@push('styles')
    .feedback-page { max-width: 920px; margin: 0 auto; }
    @include('feedback._satisfaction-styles')
@endpush

@section('content')
<div class="feedback-page">
    <div class="header" style="margin-bottom: 20px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 700; color: var(--text-primary, #111); margin: 0 0 6px;">Satisfaction Questionnaire</h1>
            <p class="page-subtitle" style="color: var(--text-muted, #667085); margin: 0; font-size: 14px;">Share your experience with Sunday services and church activities.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="notice error" role="alert" tabindex="-1" data-validation-summary style="margin-bottom: 20px;">
            Please correct the fields indicated below before submitting.
        </div>
    @endif

    {{-- Already answered pop-up notification modal --}}
    @php
        $hasAlreadyAnsweredError = $errors->has('service_date') && str_contains((string) $errors->first('service_date'), 'already answered');
        $showModal = $hasAlreadyAnsweredError || ($satisfactionAlreadyAnswered && !old('service_date'));
        $answeredDate = old('service_date', $satisfactionToday);
    @endphp

    @if($showModal)
    <div class="satisfaction-modal-backdrop" id="answeredModalBackdrop" style="position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 9999; padding: 20px;">
        <div class="satisfaction-modal" role="dialog" aria-modal="true" aria-labelledby="answeredModalTitle" style="background: #ffffff; border-radius: 16px; max-width: 480px; width: 100%; padding: 28px 24px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2); border: 1px solid #e5e7eb; text-align: center;">
            <div style="width: 52px; height: 52px; background: #fef2f2; border: 2px solid #fee2e2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 24px; color: #dc2626;">
                🗓️
            </div>
            <h2 id="answeredModalTitle" style="font-size: 1.25rem; font-weight: 700; color: #111827; margin: 0 0 8px;">You already answered for this date</h2>
            <p style="color: #6b7280; font-size: 0.9375rem; line-height: 1.5; margin: 0 0 24px;">
                Your anonymous answers for <strong>{{ \Carbon\Carbon::parse($answeredDate)->format('F j, Y') }}</strong> have already been recorded. You can review the questions again or pick a different date to submit.
            </p>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <button type="button" onclick="closeAnsweredModal()" class="btn btn-primary" style="display: block; width: 100%; padding: 12px 20px; font-size: 0.9375rem; font-weight: 700; border-radius: 9999px; cursor: pointer;">
                    Review Again / Choose Another Date
                </button>
            </div>
        </div>
    </div>
    @endif

    @include('feedback._satisfaction-form')
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const textarea = document.getElementById('satisfaction-comment');
    const counter = document.getElementById('satisfaction-comment-counter');
    if (textarea && counter) {
        const updateCount = () => {
            const current = textarea.value.length;
            counter.textContent = current + ' / 1000';
            if (current > 1000) {
                counter.style.color = '#dc2626';
                counter.style.fontWeight = '700';
            } else {
                counter.style.color = '#64748b';
                counter.style.fontWeight = 'normal';
            }
        };
        textarea.addEventListener('input', updateCount);
        updateCount();
    }

    const form = document.querySelector('form[data-satisfaction-form]');
    const progressBadge = document.getElementById('satisfaction-progress-badge');
    const footerStatus = document.getElementById('satisfaction-footer-status');

    if (form) {
        const fieldsets = Array.from(form.querySelectorAll('.satisfaction-question'));
        const total = fieldsets.length;

        const updateProgress = () => {
            let answered = 0;
            fieldsets.forEach((fs) => {
                const checked = fs.querySelector('input[type="radio"]:checked');
                if (checked) {
                    answered++;
                    fs.classList.add('is-answered');
                } else {
                    fs.classList.remove('is-answered');
                }
            });

            if (progressBadge && total > 0) {
                if (answered === total) {
                    progressBadge.textContent = '✓ All ' + total + ' answered';
                    progressBadge.classList.add('is-complete');
                } else {
                    progressBadge.textContent = answered + ' of ' + total + ' answered';
                    progressBadge.classList.remove('is-complete');
                }
            }

            if (footerStatus && total > 0) {
                if (answered === total) {
                    footerStatus.textContent = 'All ' + total + ' questions answered — ready to submit.';
                    footerStatus.style.color = '#065f46';
                    footerStatus.style.fontWeight = '600';
                } else {
                    footerStatus.textContent = 'Please answer each question shown for the selected date (' + (total - answered) + ' remaining).';
                    footerStatus.style.color = '#475467';
                    footerStatus.style.fontWeight = '500';
                }
            }
        };

        form.addEventListener('change', updateProgress);
        updateProgress();

        form.addEventListener('submit', function () {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Submitting answers...';
            }
        });
    }
});

function closeAnsweredModal() {
    const backdrop = document.getElementById('answeredModalBackdrop');
    if (backdrop) {
        backdrop.style.display = 'none';
        const dateInput = document.getElementById('satisfaction-service-date');
        if (dateInput) {
            dateInput.focus();
        }
    }
}
</script>
@endpush
