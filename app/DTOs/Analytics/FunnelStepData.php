<?php

namespace App\DTOs\Analytics;

class FunnelStepData
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly int $count,
        public readonly float $stepConversionRate,
        public readonly float $globalConversionRate,
        public readonly string $color = '#3b82f6',
        public readonly ?string $drillDownUrl = null,
    ) {}

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'count' => $this->count,
            'step_conversion_rate' => $this->stepConversionRate,
            'global_conversion_rate' => $this->globalConversionRate,
            'color' => $this->color,
            'drill_down_url' => $this->drillDownUrl,
        ];
    }
}
