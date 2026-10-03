<?php

namespace App\Http\Middleware;

use App\Models\Cosplayer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /** The root template that's loaded on the first page visit. */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props shared with every page (usePage().props).
     * The cosplayer is a closure so it is only queried when a page actually reads it.
     */
    public function share(Request $request): array
    {
        $user = Auth::guard('web')->user();
        $adminUser = Auth::guard('admin')->user();

        return [
            ...parent::share($request),

            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ] : null,
                'cosplayer' => fn () => $user
                    ? Cosplayer::where('user_id', $user->id)->first(['id', 'character_name', 'series', 'experience_level'])
                    : null,
                'adminUser' => $adminUser ? [
                    'id' => $adminUser->id,
                    'name' => $adminUser->name,
                    'role' => $adminUser->role,
                    'can_manage' => $adminUser->canManage(),
                ] : null,
            ],

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
