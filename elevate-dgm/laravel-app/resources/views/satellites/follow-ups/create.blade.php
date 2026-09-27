@extends('layouts.app')

@section('title', 'New Follow-Up | Elevate DGM')

@section('content')
<div>
    <div style="margin-bottom: 26px;">
        <a href="{{ route('satellites.follow-ups.index', $satellite) }}" style="color: #e33c24; text-decoration: none; font-weight: 700; margin-bottom: 16px; display: inline-block;">← Back to Follow-Ups</a>
        <h1 style="margin: 0; font-size: 32px; font-weight: 900;">New Follow-Up</h1>
    </div>

    @if ($errors->any())
        <div role="alert" tabindex="-1" data-validation-summary style="padding: 16px; background: #fee2e2; color: #7f1d1d; border-radius: 8px; margin-bottom: 16px; border-left: 4px solid #dc2626;">
            <strong>Please fix the following errors:</strong>
            <ul style="margin: 8px 0 0; padding-left: 20px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('satellites.follow-ups.store', $satellite) }}" style="background: #fff; border-radius: 14px; box-shadow: 0 16px 34px rgba(17, 24, 39, 0.12); padding: 32px; max-width: 600px;">
        @csrf

        <div style="margin-bottom: 24px;">
            @if ($seeker || $member)
                <p style="margin: 0 0 8px; font-weight: 700; font-size: 14px;">Target</p>
            @else
                <label for="follow-up-target" style="display: block; margin-bottom: 8px; font-weight: 700; font-size: 14px;">Target</label>
            @endif
            @if ($seeker)
                <input type="hidden" name="seeker_id" value="{{ $seeker->id }}">
                <div style="padding: 12px; background: #f3f4f6; border-radius: 8px; border-left: 4px solid #e33c24;">
                    <strong>{{ $seeker->name }}</strong><br>
                    <small style="color: #666;">Seeker</small>
                </div>
            @elseif ($member)
                <input type="hidden" name="member_id" value="{{ $member->id }}">
                <div style="padding: 12px; background: #f3f4f6; border-radius: 8px; border-left: 4px solid #e33c24;">
                    <strong>{{ $member->display_name }}</strong><br>
                    <small style="color: #666;">Member</small>
                </div>
            @else
                <select id="follow-up-target" name="target" @error('target') aria-invalid="true" aria-describedby="follow-up-target-error" @enderror style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;">
                    <option value="">Select a seeker or member...</option>
                    <optgroup label="Seekers">
                        @foreach ($satellite->seekers()->whereNull('converted_member_id')->get() as $s)
                            <option value="seeker-{{ $s->id }}" {{ old('target') === 'seeker-'.$s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                        @endforeach
                    </optgroup>
                    <optgroup label="Members">
                        @foreach ($satellite->members()->get() as $m)
                            <option value="member-{{ $m->id }}" {{ old('target') === 'member-'.$m->id ? 'selected' : '' }}>{{ $m->display_name }}</option>
                        @endforeach
                    </optgroup>
                </select>
                @error('target')<p id="follow-up-target-error" class="field-error">{{ $message }}</p>@enderror
            @endif
        </div>

        <div style="margin-bottom: 24px;">
            <label for="follow-up-date" style="display: block; margin-bottom: 8px; font-weight: 700; font-size: 14px;">Follow-Up Date</label>
            <input id="follow-up-date" type="date" name="follow_up_date" value="{{ old('follow_up_date') }}" @error('follow_up_date') aria-invalid="true" aria-describedby="follow-up-date-error" @enderror style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;">
            @error('follow_up_date')<p id="follow-up-date-error" class="field-error">{{ $message }}</p>@enderror
        </div>

        <div style="margin-bottom: 24px;">
            <label for="follow-up-method" style="display: block; margin-bottom: 8px; font-weight: 700; font-size: 14px;">Method (required)</label>
            <select id="follow-up-method" name="method" required @error('method') aria-invalid="true" aria-describedby="follow-up-method-error" @enderror style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;">
                <option value="">Select method...</option>
                @foreach ($methods as $method)
                    <option value="{{ $method }}" {{ old('method') === $method ? 'selected' : '' }}>{{ $method }}</option>
                @endforeach
            </select>
            @error('method')<p id="follow-up-method-error" class="field-error">{{ $message }}</p>@enderror
        </div>

        <div style="margin-bottom: 24px;">
            <label for="follow-up-status" style="display: block; margin-bottom: 8px; font-weight: 700; font-size: 14px;">Status (required)</label>
            <select id="follow-up-status" name="status" required @error('status') aria-invalid="true" aria-describedby="follow-up-status-error" @enderror style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;">
                <option value="">Select status...</option>
                @foreach (['Pending', 'Completed', 'Cancelled'] as $status)
                    <option value="{{ $status }}" {{ old('status') === $status ? 'selected' : '' }}>{{ $status }}</option>
                @endforeach
            </select>
            @error('status')<p id="follow-up-status-error" class="field-error">{{ $message }}</p>@enderror
        </div>

        <div style="margin-bottom: 24px;">
            <label for="follow-up-notes" style="display: block; margin-bottom: 8px; font-weight: 700; font-size: 14px;">Notes</label>
            <textarea id="follow-up-notes" name="notes" maxlength="1000" aria-describedby="follow-up-notes-help{{ $errors->has('notes') ? ' follow-up-notes-error' : '' }}" @error('notes') aria-invalid="true" @enderror style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; min-height: 120px; resize: vertical;">{{ old('notes') }}</textarea>
            <small id="follow-up-notes-help" style="display: block; margin-top: 8px; color: #6b7280;">Maximum 1,000 characters. Sentiment will be analyzed after saving and used only for follow-up trends.</small>
            @error('notes')<p id="follow-up-notes-error" class="field-error">{{ $message }}</p>@enderror
        </div>

        <div style="display: flex; gap: 12px; justify-content: flex-end;">
            <a href="{{ route('satellites.follow-ups.index', $satellite) }}" style="padding: 12px 24px; background: #f3f4f6; color: #111; text-decoration: none; border-radius: 8px; font-weight: 800; cursor: pointer;">Cancel</a>
            <button type="submit" style="padding: 12px 24px; background: #e33c24; color: #fff; border: none; border-radius: 8px; font-weight: 800; cursor: pointer;">Create Follow-Up</button>
        </div>
    </form>
</div>
@endsection
