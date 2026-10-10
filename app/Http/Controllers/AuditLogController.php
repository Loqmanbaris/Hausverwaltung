<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $events = $q !== '' ? AuditLog::eventsMatching($q) : [];

        $logs = AuditLog::query()
            ->when($q !== '', function ($query) use ($q, $events) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(function ($w) use ($like, $events) {
                    $w->where('event', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('user_label', 'like', $like)
                        ->orWhere('ip_address', 'like', $like);

                    if ($events) {
                        $w->orWhereIn('event', $events);
                    }
                });
            })
            ->orderByDesc('id')
            ->simplePaginate(50)
            ->withQueryString();

        return view('audit.index', ['logs' => $logs, 'q' => $q]);
    }
}
