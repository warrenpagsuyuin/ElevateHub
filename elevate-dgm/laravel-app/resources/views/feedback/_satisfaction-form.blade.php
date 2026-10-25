@php
    $totalQuestionCount = $satisfactionCategories->sum(fn ($cat) => $cat->questions->count());
@endphp
<section class="satisfaction-form-section" aria-labelledby="satisfaction-form-heading">
    <h2 id="satisfaction-form-heading" class="sr-only">Satisfaction questionnaire</h2>

    @if($satisfactionSatelliteChoices->isNotEmpty())
        <form method="GET" action="{{ route('feedback.create') }}" class="feedback-field satisfaction-satellite-picker">
            <label for="satisfaction-satellite">Satellite</label>
            <select id="satisfaction-satellite" name="satellite" required>
                <option value="">Choose a satellite</option>
                @foreach($satisfactionSatelliteChoices as $choice)<option value="{{ $choice->slug }}" @selected($satisfactionSatellite?->slug === $choice->slug)>{{ $choice->name }}</option>@endforeach
            </select>
            <button type="submit" class="btn btn-secondary">Choose satellite</button>
        </form>
    @endif

    @if(session('satisfaction_status'))<div class="notice success" role="status" style="margin-bottom: 16px;">{{ session('satisfaction_status') }}</div>@endif
    @if($satisfactionMessage)<div class="notice" role="status" style="margin-bottom: 16px;">{{ $satisfactionMessage }}</div>@endif
    @if($satisfactionAlreadyAnswered && !old('service_date'))<p class="notice" role="status" style="margin-bottom: 16px;">You already answered for today. Choose a different service or activity date if needed.</p>@endif

    @if($satisfactionCategories->isNotEmpty())
    <form method="POST" action="{{ route('feedback.satisfaction.store') }}" data-satisfaction-form>
        @csrf
        <input type="hidden" name="satellite" value="{{ $satisfactionSatellite->slug }}">

        <div class="satisfaction-top-bar">
            <div>
                <div class="satisfaction-meta-group">
                    @if($satisfactionSatellite)
                        <p class="satisfaction-satellite-badge">Satellite: <strong>{{ $satisfactionSatellite->display_name }}</strong></p>
                    @endif
                    <span class="satisfaction-progress-badge" id="satisfaction-progress-badge" aria-live="polite" data-total-questions="{{ $totalQuestionCount }}">
                        0 of {{ $totalQuestionCount }} answered
                    </span>
                </div>
            </div>

            <div class="feedback-field satisfaction-date-field">
                <label for="satisfaction-service-date">Service or activity date</label>
                <input id="satisfaction-service-date" type="date" name="service_date" value="{{ old('service_date', $satisfactionToday) }}" max="{{ $satisfactionToday }}" required @error('service_date') aria-invalid="true" aria-describedby="satisfaction-date-error" @enderror>
                @error('service_date')<small id="satisfaction-date-error" class="satisfaction-error" style="width: 100%;">{{ $message }}</small>@enderror
            </div>
        </div>

        @foreach($satisfactionCategories as $category)
        <div class="satisfaction-category">
            <div class="satisfaction-category-header">
                <h3>{{ $category->name }}</h3>
                <span class="satisfaction-category-count">{{ $category->questions->count() }} {{ \Illuminate\Support\Str::plural('question', $category->questions->count()) }}</span>
            </div>
            @foreach($category->questions as $question)
                @php($answerKey = 'answers.'.$question->id)
                <fieldset class="satisfaction-question {{ old($answerKey) ? 'is-answered' : '' }}" @error($answerKey) tabindex="-1" aria-invalid="true" aria-describedby="satisfaction-error-{{ $question->id }}" @enderror>
                    <legend>{{ $question->text }}</legend>
                    <div class="satisfaction-options">
                        @foreach(['yes' => 'Yes', 'no' => 'No'] as $value => $label)
                            <label><input type="radio" name="answers[{{ $question->id }}]" value="{{ $value }}" @checked(old($answerKey) === $value) required><span><span class="satisfaction-check" aria-hidden="true">✓</span>{{ $label }}</span></label>
                        @endforeach
                    </div>
                    @error($answerKey)<small id="satisfaction-error-{{ $question->id }}" class="satisfaction-error">{{ $message }}</small>@enderror
                </fieldset>
            @endforeach
        </div>
        @endforeach

        <div class="satisfaction-comment-block" style="margin-top: 20px; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px; background: #f8fafc;">
            <label for="satisfaction-comment" style="display: block; font-weight: 700; color: #1e293b; margin-bottom: 4px; font-size: 15px;">Any comments or suggestions? <span style="font-weight: 500; color: #64748b; font-size: 13px;">(optional)</span></label>
            <p style="margin: 0 0 10px; font-size: 13px; color: #475467;">Please do not include names or personal details. Only this written comment is analyzed for sentiment.</p>
            <textarea
                id="satisfaction-comment"
                name="comment"
                rows="3"
                maxlength="1000"
                placeholder="Share what went well or how we can improve..."
                style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; font-family: inherit; font-size: 14px; background: #ffffff; box-sizing: border-box;"
                aria-describedby="satisfaction-comment-help satisfaction-comment-counter"
                @error('comment') aria-invalid="true" aria-describedby="satisfaction-comment-error satisfaction-comment-counter" @enderror
            >{{ old('comment') }}</textarea>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 6px; font-size: 12px; color: #64748b;">
                <span id="satisfaction-comment-help">Maximum 1000 characters.</span>
                <span id="satisfaction-comment-counter" aria-live="polite">0 / 1000</span>
            </div>
            @error('comment')
                <small id="satisfaction-comment-error" class="satisfaction-error" style="color: #dc2626; display: block; margin-top: 4px; font-weight: 700;">{{ $message }}</small>
            @enderror

            <label style="display: flex; align-items: flex-start; gap: 8px; margin-top: 12px; font-size: 13px; color: #334155; cursor: pointer;">
                <input type="checkbox" name="comment_consent" value="1" @checked(old('comment_consent', true)) style="margin-top: 2px;">
                <span>Share this anonymous comment (de-identified) with church reviewers to help improve future services and evaluate sentiment trends.</span>
            </label>
        </div>

        <div class="feedback-actions">
            <p class="feedback-help" id="satisfaction-footer-status">Please answer each question shown for the selected date.</p>
            <button type="submit" class="btn btn-primary">Submit answers</button>
        </div>
    </form>
    @endif
</section>
