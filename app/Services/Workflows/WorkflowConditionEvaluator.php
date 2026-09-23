<?php

namespace App\Services\Workflows;

use App\Models\WorkflowCondition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class WorkflowConditionEvaluator
{
    /**
     * Evaluate a collection of WorkflowConditions against an entity or payload array.
     *
     * @param Collection<int, WorkflowCondition> $conditions
     * @param Model|array $target
     */
    public function evaluate(Collection $conditions, Model|array $target): bool
    {
        if ($conditions->isEmpty()) {
            return true;
        }

        $result = null;

        foreach ($conditions as $condition) {
            $fieldValue = $this->extractFieldValue($target, $condition->field);
            $passed = $this->compare($fieldValue, $condition->operator, $condition->value);

            if ($result === null) {
                $result = $passed;
            } else {
                if (strtoupper($condition->logical_operator) === 'OR') {
                    $result = $result || $passed;
                } else {
                    $result = $result && $passed;
                }
            }
        }

        return (bool) $result;
    }

    protected function extractFieldValue(Model|array $target, string $field): mixed
    {
        if (is_array($target)) {
            return data_get($target, $field);
        }

        // Support dot notation like "contact.type" or direct attributes
        return data_get($target, $field);
    }

    protected function compare(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            'equals', '=' => (string) $actual === (string) $expected,
            'not_equals', '!=' => (string) $actual !== (string) $expected,
            'greater_than', '>' => (float) $actual > (float) $expected,
            'less_than', '<' => (float) $actual < (float) $expected,
            'greater_than_or_equal', '>=' => (float) $actual >= (float) $expected,
            'less_than_or_equal', '<=' => (float) $actual <= (float) $expected,
            'exists' => !empty($actual),
            'is_empty' => empty($actual),
            'contains' => is_string($actual) && str_contains(strtolower($actual), strtolower((string) $expected)),
            default => false,
        };
    }
}
