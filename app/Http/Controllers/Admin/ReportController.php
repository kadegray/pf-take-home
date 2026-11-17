<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GenerationStatus;
use App\Http\Controllers\Controller;
use App\Models\Generation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    private const MAX_RANGE_DAYS = 92;

    public function index(Request $request): Response
    {
        Gate::authorize('view-admin-report');

        $data = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        $endDate = isset($data['end_date']) ? Carbon::parse($data['end_date'], 'UTC') : now('UTC')->startOfDay();
        $startDate = isset($data['start_date'])
            ? Carbon::parse($data['start_date'], 'UTC')
            : $endDate->copy()->subDays(29);

        if ($startDate->diffInDays($endDate) > self::MAX_RANGE_DAYS) {
            $startDate = $endDate->copy()->subDays(self::MAX_RANGE_DAYS);
        }

        // Only succeeded generations are billed by Pixelforge, so this is
        // the exact population GET /v1/usage reports - no need to include
        // failed rows, which only ever contribute $0 anyway.
        $rows = Generation::query()
            ->join('users', 'users.id', '=', 'generations.user_id')
            ->where('generations.status', GenerationStatus::Succeeded)
            ->whereDate('generations.completed_at', '>=', $startDate)
            ->whereDate('generations.completed_at', '<=', $endDate)
            ->selectRaw('DATE(generations.completed_at) as day')
            ->selectRaw('generations.user_id')
            ->selectRaw('users.name as user_name')
            ->selectRaw('users.email as user_email')
            ->selectRaw('COUNT(*) as generation_count')
            ->selectRaw('SUM(generations.actual_cost_micros) as spend_micros')
            ->selectRaw('SUM(generations.provider_cost_micros) as provider_cost_micros')
            ->groupBy('day', 'generations.user_id', 'users.name', 'users.email')
            ->orderByDesc('day')
            ->get()
            ->map(fn ($row) => [
                'day' => $row->day,
                'user_id' => $row->user_id,
                'user_name' => $row->user_name,
                'user_email' => $row->user_email,
                'generation_count' => (int) $row->generation_count,
                'spend_micros' => (int) $row->spend_micros,
                'provider_cost_micros' => (int) $row->provider_cost_micros,
                'profit_micros' => (int) $row->spend_micros - (int) $row->provider_cost_micros,
            ]);

        $totals = [
            'generation_count' => $rows->sum('generation_count'),
            'spend_micros' => $rows->sum('spend_micros'),
            'provider_cost_micros' => $rows->sum('provider_cost_micros'),
            'profit_micros' => $rows->sum('profit_micros'),
        ];

        return Inertia::render('Admin/Report', [
            'rows' => $rows,
            'totals' => $totals,
            'startDate' => $startDate->toDateString(),
            'endDate' => $endDate->toDateString(),
        ]);
    }
}
