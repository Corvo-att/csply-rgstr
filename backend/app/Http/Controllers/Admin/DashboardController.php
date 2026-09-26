<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cosplayer;
use App\Exports\CosplayersExport;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class DashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_cosplayers'   => Cosplayer::count(),
                'total_events'       => \App\Models\Event::count(),
                'published_events'   => \App\Models\Event::where('status', 'published')->count(),
                'total_submissions'  => \App\Models\FormSubmission::count(),
            ],
            'cosplayers' => Cosplayer::with('user')
                ->latest()
                ->get()
                ->map(fn($c) => [
                    'id'              => $c->id,
                    'name'            => $c->user->name,
                    'email'           => $c->user->email,
                    'character_name'  => $c->character_name,
                    'series'          => $c->series,
                    'experience_level'=> $c->experience_level,
                    'created_at'      => $c->created_at->format('Y-m-d'),
                ]),
        ]);
    }

    public function exportCosplayers()
    {
        return Excel::download(new CosplayersExport, 'cosplayers.xlsx');
    }
}
