<template data-activity-history>
    @include('satellites.activity-history')
</template>

<section class="dashboard-intro" aria-labelledby="movement-summary-title">
    <p class="summary-title" id="movement-summary-title">ELEVATE MOVEMENT WIDE SUMMARY</p>
</section>

<div class="stats-grid admin-summary-grid">
    @foreach ($movementSummaryCards as $card)
        @include('admin.dashboard-directory.card', ['card' => $card])
    @endforeach
</div>
