<div class="reservation-timeline {{ $reservation->status === 'cancelled' ? 'reservation-timeline--cancelled' : '' }}">
    @foreach($reservation->timelineSteps() as $step)
        <div class="timeline-step timeline-step--{{ $step['state'] }}">
            <span class="timeline-step-marker" aria-hidden="true">
                @if($step['state'] === 'complete') &#10003;
                @elseif($step['state'] === 'current') &#9679;
                @elseif($step['state'] === 'cancelled') &#10007;
                @endif
            </span>
            <div class="timeline-step-body">
                <div class="timeline-step-label">{{ $step['label'] }}</div>
                <p class="timeline-step-desc">{{ $step['description'] }}</p>
            </div>
        </div>
    @endforeach
</div>
<style>
    .reservation-timeline{display:grid;grid-auto-flow:column;grid-auto-columns:1fr;margin:.5rem 0 2rem}
    .timeline-step{position:relative;display:flex;flex-direction:column;align-items:center;text-align:center;padding:0 .4rem}
    .timeline-step-marker{position:relative;z-index:1;display:grid;place-items:center;width:32px;height:32px;flex:0 0 auto;border-radius:50%;border:2px solid var(--line);background:var(--paper);color:#b7b0a4;font-weight:800;font-size:.95rem;line-height:1}
    .timeline-step:not(:last-child):before{content:'';position:absolute;top:15px;left:calc(50% + 16px);width:calc(100% - 32px);height:2px;background:var(--line);z-index:0}
    .timeline-step--complete .timeline-step-marker{background:var(--wine);border-color:var(--wine);color:#fff}
    .timeline-step--complete:not(:last-child):before{background:var(--wine)}
    .timeline-step--current .timeline-step-marker{border-color:var(--wine);color:var(--wine);background:var(--paper);box-shadow:0 0 0 4px rgba(109,48,36,.14)}
    .timeline-step--cancelled .timeline-step-marker{background:#a73838;border-color:#a73838;color:#fff}
    .timeline-step-label{margin-top:.6rem;font-weight:800;font-size:.8rem;color:var(--ink)}
    .timeline-step-desc{margin-top:.3rem;font-size:.72rem;line-height:1.45;color:var(--muted);max-width:170px}
    .timeline-step--upcoming .timeline-step-label{color:var(--muted)}
    .timeline-step--upcoming .timeline-step-desc{opacity:.75}
    .timeline-step--current .timeline-step-label{color:var(--wine)}
    body.dark-mode .timeline-step-marker{background:#201f1d}
    @media(max-width:767px){
        .reservation-timeline{display:flex;flex-direction:column;margin:.5rem 0 2rem}
        .timeline-step{flex-direction:row;align-items:flex-start;text-align:left;padding:0 0 1.35rem;gap:.85rem}
        .timeline-step:last-child{padding-bottom:0}
        .timeline-step:not(:last-child):before{top:32px;left:15px;width:2px;height:calc(100% - 18px)}
        .timeline-step-desc{max-width:none}
    }
</style>
