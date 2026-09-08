<?php

namespace App\Support\Reporting;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final readonly class ReportPeriod
{
    public string $start;

    public string $end;

    public function __construct(public string $from, public string $to)
    {
        $start = CarbonImmutable::parse($from, config('erp.timezone'))->startOfDay();
        $end = CarbonImmutable::parse($to, config('erp.timezone'))->addDay()->startOfDay();
        if ($end->lessThanOrEqualTo($start) || $start->diffInDays($end) > 366) {
            throw ValidationException::withMessages(['to' => 'Choose an ordered date range of no more than one year.']);
        }
        $this->start = $start->utc()->format('Y-m-d H:i:s');
        $this->end = $end->utc()->format('Y-m-d H:i:s');
    }

    public static function fromRequest(Request $request): self
    {
        $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d']]);
        $today = now(config('erp.timezone'));

        return new self($request->input('from') ?: $today->copy()->startOfMonth()->format('Y-m-d'), $request->input('to') ?: $today->format('Y-m-d'));
    }

    public function apply($query, string $column = 'posted_at')
    {
        return $query->where($column, '>=', $this->start)->where($column, '<', $this->end);
    }
}
