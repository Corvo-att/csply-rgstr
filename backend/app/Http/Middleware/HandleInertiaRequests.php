<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\Cosplayer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     * These are available on every page via usePage().props.
     */
    public function share(Request $request): array
    {
        $user      = Auth::guard('web')->user();
        $adminUser = Auth::guard('admin')->user();
        $cosplayer = $user ? Cosplayer::where('user_id', $user->id)->first() : null;

        return [
            ...parent::share($request),

            'auth' => [
                'user' => $user ? [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                ] : null,
                'cosplayer' => $cosplayer ? [
                    'id'               => $cosplayer->id,
                    'character_name'   => $cosplayer->character_name,
                    'series'           => $cosplayer->series,
                    'experience_level' => $cosplayer->experience_level,
                ] : null,
                'adminUser' => $adminUser ? [
                    'id'   => $adminUser->id,
                    'name' => $adminUser->name,
                ] : null,
            ],

            // Flash messages
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
                'error'   => fn() => $request->session()->get('error'),
            ],
        ];
    }
}
