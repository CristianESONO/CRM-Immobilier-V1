<?php

namespace App\DTOs\Analytics;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class AnalyticsFilterData
{
    public function __construct(
        public readonly string $period = 'all',
        public readonly ?Carbon $startDate = null,
        public readonly ?Carbon $endDate = null,
        public readonly ?int $propertyId = null,
        public readonly ?int $assignedTo = null,
        public readonly ?string $unitType = null,
    ) {}

    public static function fromArray(array $data = []): self
    {
        $period = $data['period'] ?? 'all';
        $startDate = null;
        $endDate = null;

        if (!empty($data['start_date'])) {
            $startDate = Carbon::parse($data['start_date'])->startOfDay();
        }
        if (!empty($data['end_date'])) {
            $endDate = Carbon::parse($data['end_date'])->endOfDay();
        }

        if ($period !== 'custom' && $period !== 'all') {
            $now = Carbon::now();
            switch ($period) {
                case 'today':
                    $startDate = $now->copy()->startOfDay();
                    $endDate = $now->copy()->endOfDay();
                    break;
                case '7_days':
                    $startDate = $now->copy()->subDays(7)->startOfDay();
                    $endDate = $now->copy()->endOfDay();
                    break;
                case '30_days':
                    $startDate = $now->copy()->subDays(30)->startOfDay();
                    $endDate = $now->copy()->endOfDay();
                    break;
                case '90_days':
                    $startDate = $now->copy()->subDays(90)->startOfDay();
                    $endDate = $now->copy()->endOfDay();
                    break;
                case 'year':
                    $startDate = $now->copy()->startOfYear();
                    $endDate = $now->copy()->endOfYear();
                    break;
            }
        }

        return new self(
            period: $period,
            startDate: $startDate,
            endDate: $endDate,
            propertyId: isset($data['property_id']) ? (int) $data['property_id'] : null,
            assignedTo: isset($data['assigned_to']) ? (int) $data['assigned_to'] : null,
            unitType: $data['unit_type'] ?? null,
        );
    }

    public function applyDateFilter(Builder $query, string $column = 'created_at'): Builder
    {
        if ($this->startDate) {
            $query->where($column, '>=', $this->startDate);
        }
        if ($this->endDate) {
            $query->where($column, '<=', $this->endDate);
        }
        return $query;
    }
}
