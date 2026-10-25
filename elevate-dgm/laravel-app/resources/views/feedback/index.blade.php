@extends('layouts.app')

@section('content')
<div>
    <div class="header page-header" style="align-items: flex-start; gap: 16px;">
        <div>
            <h1>My Feedback</h1>
            <p style="margin: 8px 0 0; color: #6b7280; font-weight: 700;">Your submitted system feedback and explainable sentiment results.</p>
        </div>
        <a href="{{ route('feedback.create') }}" class="btn-primary" style="display: inline-flex; align-items: center; padding: 11px 16px; border-radius: 8px; text-decoration: none; font-weight: 800;">New Feedback</a>
    </div>

    @if (session('status'))
        <div class="notice" style="border-color: #bbf7d0; color: #166534;">
            {{ session('status') }}
        </div>
    @endif

    <div class="table-card" style="background: #fff; border-radius: 8px; box-shadow: 0 12px 28px rgba(17, 24, 39, 0.10); overflow: hidden;">
        <div class="table-scroll">
        <table style="width: 100%; min-width: 900px; border-collapse: collapse;">
            <thead>
                <tr style="background: #111; color: #fff;">
                    <th style="padding: 15px; text-align: left; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;">Submitted</th>
                    <th style="padding: 15px; text-align: left; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;">Predicted Sentiment</th>
                    <th style="padding: 15px; text-align: left; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;">Original Feedback</th>
                    <th>Research consent</th>
                    <th style="padding: 15px; text-align: left; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;">Why This Sentiment?</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($feedbackItems as $feedback)
                    @php
                        $sentiment = $feedback->sentiment;
                        $sentimentBg = match($sentiment) {
                            'Positive' => '#ffe5df',
                            'Negative' => '#fee2e2',
                            'Neutral' => '#f3f4f6',
                            default => '#f9fafb',
                        };
                        $sentimentColor = match($sentiment) {
                            'Positive' => '#c92f1b',
                            'Negative' => '#7f1d1d',
                            'Neutral' => '#111',
                            default => '#6b7280',
                        };
                        $decisionPath = $feedback->sentiment_metrics['decision_path'] ?? [];
                    @endphp
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 15px; font-weight: 800;">{{ $feedback->created_at?->format('M d, Y g:i A') }}</td>
                        <td style="padding: 15px;">
                            <span style="display: inline-block; padding: 5px 10px; background: {{ $sentimentBg }}; color: {{ $sentimentColor }}; border-radius: 4px; font-size: 12px; font-weight: 900;">{{ $sentiment ?? 'Unanalyzed' }}</span>
                            @if ($feedback->sentiment_confidence !== null)
                                <small style="display: block; margin-top: 6px; color: #6b7280; font-weight: 700;">{{ number_format($feedback->sentiment_confidence * 100, 0) }}% confidence</small>
                            @endif
                        </td>
                        <td style="padding: 15px; color: #374151; line-height: 1.45;">{{ Str::limit($feedback->message, 180) }}</td>
                        <td><details><summary>Research consent: {{ $feedback->research_consent_status }}</summary><form method="POST" action="{{ route('feedback.research-consent',$feedback) }}">@csrf @method('PATCH')<label>My decision<select name="consent_status"><option value="Denied">Do not use for research</option><option value="Granted">I consent to research use and de-identified quotations</option></select></label><input type="hidden" name="consent_source" value="System Evaluation Form"><input type="hidden" name="evidence" value="Respondent updated their own research consent through My Feedback."><label><input type="checkbox" name="attested" value="1" required> This is my actual consent decision.</label><button type="submit">Save consent</button></form></details></td>
                        <td style="padding: 15px; color: #374151; font-size: 13px;">
                            @if ($decisionPath)
                                <ul style="margin: 0; padding-left: 18px;">
                                    @foreach ($decisionPath as $step)
                                        <li style="margin-bottom: 5px;">{{ $step }}</li>
                                    @endforeach
                                </ul>
                            @else
                                <span style="color: #6b7280;">No explanation is available yet.</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="padding: 34px; text-align: center; color: #6b7280; font-weight: 700;">No feedback has been submitted yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
        @include('partials.table-pagination', ['paginator' => $feedbackItems, 'pageSizes' => $pageSizes, 'currentPageSize' => $perPage, 'recordLabel' => 'feedback entries', 'tableId' => 'feedback'])
    </div>
</div>
@endsection
