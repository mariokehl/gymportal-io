{{-- Fortschrittsbalken --}}
<div class="progress-bar">
    @php
        $steps = [
            1 => 'Vertrag',
            2 => 'Deine Daten',
            3 => 'Übersicht'
        ];
    @endphp

    @foreach($steps as $stepNumber => $stepLabel)
        @php
            $stepClass = '';
            if ($currentStep > $stepNumber) {
                $stepClass = 'completed';
            } elseif ($currentStep == $stepNumber) {
                $stepClass = 'active';
            }
        @endphp

        <div class="step {{ $stepClass }}">
            <span class="step-number">
                @if($currentStep > $stepNumber)
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M13.5 4.5L6 12L2.5 8.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                @else
                    {{ $stepNumber }}
                @endif
            </span>
            <span class="step-label">{{ $stepLabel }}</span>
        </div>
    @endforeach
</div>
