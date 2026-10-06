<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'actor' => ['nullable', 'string', 'max:255'],
            'action' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $actor = $filters['actor'] ?? null;
        $search = $filters['search'] ?? $filters['action'] ?? null;
        $fromDate = $request->filled('date_from') ? Carbon::parse($request->date_from, config('app.timezone'))->startOfDay() : null;
        $throughDate = $request->filled('date_to') ? Carbon::parse($request->date_to, config('app.timezone'))->endOfDay() : null;

        $perPage = max(10, min(100, (int) $request->input('per_page', 10)));
        $logs = ActivityLog::query()
            ->when($actor, fn ($query, $value) => $query->where('actor_email', $value))
            ->when($search, function ($query, $value) {
                $term = '%'.str($value)->lower()->toString().'%';

                $query->where(function ($scope) use ($term) {
                    $scope->whereRaw('LOWER(action) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(description) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(actor_name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(actor_email) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(method) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(ip_address) LIKE ?', [$term]);
                });
            })
            ->when($fromDate, fn ($query, $date) => $query->where('created_at', '>=', $date->toDateTimeString()))
            ->when($throughDate, fn ($query, $date) => $query->where('created_at', '<=', $date->toDateTimeString()))
            ->latest()->paginate($perPage)->withQueryString();
        $actors = ActivityLog::whereNotNull('actor_email')->select('actor_email', 'actor_name')->distinct()->orderBy('actor_name')->get();

        return view('admin.activity-logs', compact('logs', 'actors', 'perPage'));
    }
}
