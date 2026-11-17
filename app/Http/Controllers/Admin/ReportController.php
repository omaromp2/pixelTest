<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GenerationStatus;
use App\Http\Controllers\Controller;
use App\Models\Generation;
use App\Services\Pixelforge;
use App\Services\PixelforgeCost;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    private const DAYS = 30;

    /**
     * Credit spend, provider cost and profit per UTC day and user, next to what Pixelforge itself reports.
     * Generations count on the day they completed, the same rule Pixelforge's usage report uses.
     */
    public function __invoke(Pixelforge $pixelforge): Response
    {
        $startDate = now()->subDays(self::DAYS - 1)->toDateString();
        $endDate = now()->toDateString();

        $rows = Generation::query()
            ->join('users', 'users.id', '=', 'generations.user_id')
            ->where('status', GenerationStatus::Succeeded)
            ->where('completed_at', '>=', $startDate)
            ->selectRaw('date(completed_at) as date, users.name, users.email, count(*) as generations, sum(charged_cost) as charged, sum(provider_cost) as provider_cost')
            ->groupByRaw('date(completed_at), users.id, users.name, users.email')
            ->orderByDesc('date')
            ->orderBy('users.name')
            ->toBase()
            ->get();

        $rowsByDate = $rows->groupBy('date');

        /** @var list<array{date: string, cost_usd: string}> $usage Empty when Pixelforge is unreachable. */
        $usage = rescue(fn () => $pixelforge->usage($startDate, $endDate)['data'], []);
        $pixelforgeCosts = collect($usage)
            ->mapWithKeys(fn (array $day) => [$day['date'] => PixelforgeCost::toNanodollars($day['cost_usd'])]);

        $days = collect(CarbonPeriod::create($startDate, $endDate)->toArray())
            ->reverse()
            ->map(function (CarbonInterface $day) use ($rowsByDate, $pixelforgeCosts) {
                $users = $rowsByDate->get($day->toDateString(), collect());

                return [
                    'date' => $day->toDateString(),
                    'charged' => (int) $users->sum('charged'),
                    'provider_cost' => (int) $users->sum('provider_cost'),
                    'pixelforge_reported_cost' => $pixelforgeCosts->get($day->toDateString()),
                    'users' => $users->values(),
                ];
            })
            ->filter(fn (array $day) => $day['users']->isNotEmpty() || $day['pixelforge_reported_cost'])
            ->values();

        return Inertia::render('admin/Report', ['days' => $days]);
    }
}
