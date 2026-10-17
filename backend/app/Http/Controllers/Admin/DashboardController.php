<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cosplayer;
use App\Models\Event;
use App\Models\FormSubmission;
use App\Support\CsvDownload;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $cosplayers = $this->query($request)
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Cosplayer $c) => [
                'id' => $c->id,
                'name' => $c->user->name,
                'email' => $c->user->email,
                'character_name' => $c->character_name,
                'series' => $c->series,
                'experience_level' => $c->experience_level,
                'created_at' => $c->created_at->format('Y-m-d'),
            ]);

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_cosplayers' => Cosplayer::count(),
                'total_events' => Event::count(),
                'published_events' => Event::where('status', 'published')->count(),
                'total_submissions' => FormSubmission::count(),
            ],
            'cosplayers' => $cosplayers,
            'filters' => $request->only('q'),
        ]);
    }

    public function exportCosplayers(Request $request)
    {
        $rows = (function () use ($request) {
            foreach ($this->query($request)->orderBy('id')->lazyById(200) as $c) {
                yield [$c->id, $c->user->name, $c->user->email, $c->character_name, $c->series, $c->experience_level, $c->created_at->format('Y-m-d H:i')];
            }
        })();

        return CsvDownload::make(
            'cosplayers.csv',
            ['ID', 'Name', 'Email', 'Character', 'Series', 'Experience', 'Registered At'],
            $rows
        );
    }

    private function query(Request $request)
    {
        $query = Cosplayer::with('user');

        if ($q = trim((string) $request->query('q'))) {
            $like = '%'.addcslashes($q, '%_\\').'%';

            $query->where(fn ($w) => $w->where('character_name', 'like', $like)
                ->orWhere('series', 'like', $like)
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like)));
        }

        return $query;
    }
}
