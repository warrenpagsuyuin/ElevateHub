<section class="satisfaction-form-section" aria-labelledby="satisfaction-form-heading">
    <h2 id="satisfaction-form-heading">Satisfaction questionnaire</h2>
    <p>Your Yes/No answers are stored separately from your name and written feedback.</p>
    @if($satisfactionSatelliteChoices->isNotEmpty())
        <form method="GET" action="{{ route('feedback.create') }}" class="feedback-field satisfaction-satellite-picker">
            <label for="satisfaction-satellite">Satellite</label>
            <select id="satisfaction-satellite" name="satellite" required>
                <option value="">Choose a satellite</option>
                @foreach($satisfactionSatelliteChoices as $choice)<option value="{{ $choice->slug }}" @selected($satisfactionSatellite?->slug === $choice->slug)>{{ $choice->name }}</option>@endforeach
            </select>
            <button type="submit" class="btn btn-secondary">Choose satellite</button>
        </form>
    @elseif($satisfactionSatellite)
        <p class="satisfaction-satellite-name">Satellite: <strong>{{ $satisfactionSatellite->display_name }}</strong></p>
    @endif
    @if(session('satisfaction_status'))<div class="notice success" role="status">{{ session('satisfaction_status') }}</div>@endif
    @if($satisfactionMessage)<div class="notice" role="status">{{ $satisfactionMessage }}</div>@endif
    @if($satisfactionAlreadyAnswered && !old('service_date'))<p class="notice" role="status">You already answered for today. Choose a different service or activity date if needed.</p>@endif
    @if($satisfactionCategories->isNotEmpty())
    <form method="POST" action="{{ route('feedback.satisfaction.store') }}" data-satisfaction-form>
        @csrf
        <input type="hidden" name="satellite" value="{{ $satisfactionSatellite->slug }}">
        <div class="feedback-field">
            <label for="satisfaction-service-date">Service or activity date</label>
            <input id="satisfaction-service-date" type="date" name="service_date" value="{{ old('service_date', $satisfactionToday) }}" max="{{ $satisfactionToday }}" required @error('service_date') aria-invalid="true" aria-describedby="satisfaction-date-error" @enderror>
            @error('service_date')<small id="satisfaction-date-error">{{ $message }}</small>@enderror
        </div>
        @foreach($satisfactionCategories as $category)
        <div class="satisfaction-category">
            <h3>{{ $category->name }}</h3>
            @foreach($category->questions as $question)
                @php($answerKey = 'answers.'.$question->id)
                <fieldset class="satisfaction-question" @error($answerKey) tabindex="-1" aria-invalid="true" aria-describedby="satisfaction-error-{{ $question->id }}" @enderror>
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

        <div class="satisfaction-comment-block" style="margin-top: 24px; padding: 20px; border: 1px solid var(--ui-border, #e5e7eb); border-radius: 8px; background: #fff;">
            <label for="satisfaction-comment" style="display: block; font-weight: 700; margin-bottom: 6px; font-size: 15px;">Any comments or suggestions? (optional)</label>
            <p style="margin: 0 0 10px; font-size: 13px; color: #6b7280;">Please do not include names or personal details. Only this written comment is analyzed for sentiment.</p>
            <textarea
                id="satisfaction-comment"
                name="comment"
                rows="4"
                maxlength="1000"
                style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 10px; font-family: inherit; font-size: 14px; box-sizing: border-box;"
                aria-describedby="satisfaction-comment-help satisfaction-comment-counter"
                @error('comment') aria-invalid="true" aria-describedby="satisfaction-comment-error satisfaction-comment-counter" @enderror
            >{{ old('comment') }}</textarea>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 6px; font-size: 12px; color: #6b7280;">
                <span id="satisfaction-comment-help">Maximum 1000 characters.</span>
                <span id="satisfaction-comment-counter" aria-live="polite">0 / 1000</span>
            </div>
            @error('comment')
                <small id="satisfaction-comment-error" class="satisfaction-error" style="color: #dc2626; display: block; margin-top: 4px; font-weight: 700;">{{ $message }}</small>
            @enderror

            <label style="display: flex; align-items: flex-start; gap: 8px; margin-top: 14px; font-size: 13px; cursor: pointer;">
                <input type="checkbox" name="comment_consent" value="1" @checked(old('comment_consent', true)) style="margin-top: 2px;">
                <span>I agree this anonymous comment may be read by reviewers and used, de-identified, to evaluate the sentiment tool.</span>
            </label>
        </div>

        <div class="feedback-actions"><p class="feedback-help">Please answer each question shown for the selected date.</p><button type="submit" class="btn btn-primary">Submit answers</button></div>
    </form>
    @endif
</section>
