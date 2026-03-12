<?php

declare(strict_types=1);

namespace App\Dto;

use Illuminate\Support\Collection;

final readonly class ConformanceReport
{
    public int $passed;
    public int $failed;
    public int $partial;
    public int $notTested;
    public int $totalTested;
    public float $percentage;
    /** @var array<string, array{passed: int, total: int, percentage: float}> */
    public array $categories;
    /** @var array<int, array<string, mixed>> */
    public array $results;
    public string $protocolVersion;

    /**
     * @param Collection<int, \App\Models\ConformanceResult> $results
     */
    public function __construct(Collection $results, string $protocolVersion)
    {
        $this->protocolVersion = $protocolVersion;
        $this->passed = $results->where('status', 'passed')->count();
        $this->failed = $results->where('status', 'failed')->count();
        $this->partial = $results->where('status', 'partial')->count();
        $this->notTested = $results->where('status', 'not_tested')->count();
        $this->totalTested = $this->passed + $this->failed + $this->partial;
        $this->percentage = $this->totalTested > 0
            ? round(($this->passed / $this->totalTested) * 100, 1)
            : 0;

        $this->categories = $this->calculateCategories($results);
        $this->results = $results->map(fn ($r) => $r->toArray())->values()->toArray();
    }

    /**
     * @param Collection<int, \App\Models\ConformanceResult> $results
     * @return array<string, array{passed: int, total: int, percentage: float}>
     */
    private function calculateCategories(Collection $results): array
    {
        /** @var array<string, list<string>> $categoryMap */
        $categoryMap = config('conformance.categories');

        $categories = [];
        foreach ($categoryMap as $category => $actions) {
            $categoryResults = $results->whereIn('action', $actions);
            $passed = $categoryResults->where('status', 'passed')->count();
            $total = $categoryResults->where('status', '!=', 'not_tested')->count();

            $categories[$category] = [
                'passed' => $passed,
                'total' => $total,
                'percentage' => $total > 0 ? round(($passed / $total) * 100, 1) : 0,
            ];
        }

        return $categories;
    }
}
