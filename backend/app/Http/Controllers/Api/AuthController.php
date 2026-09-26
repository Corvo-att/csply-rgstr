<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Cosplayer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'ok'   => true,
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
            'cosplayer' => null,
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        $cosplayer = Cosplayer::where('user_id', $user->id)->first();

        return response()->json([
            'ok'   => true,
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
            'cosplayer' => $cosplayer,
        ]);
    }

    public function adminLogin(Request $request)
    {
        $validated = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $admin = Admin::where('email', $validated['email'])->first();

        if (! $admin || ! Hash::check($validated['password'], $admin->password)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Invalid admin credentials.',
            ], 401);
        }

        return response()->json([
            'ok'        => true,
            'adminUser' => [
                'id'    => $admin->id,
                'name'  => $admin->name,
                'email' => $admin->email,
                'role'  => $admin->role ?? 'admin',
            ],
        ]);
    }

    public function saveCosplayerProfile(Request $request)
    {
        $validated = $request->validate([
            'user_id'          => ['required', 'exists:users,id'],
            'character_name'   => ['required', 'string', 'max:255'],
            'series'           => ['required', 'string', 'max:255'],
            'experience_level' => ['required', 'string'],
            'bio'              => ['nullable', 'string'],
        ]);

        $cosplayer = Cosplayer::updateOrCreate(
            ['user_id' => $validated['user_id']],
            [
                'character_name'   => $validated['character_name'],
                'series'           => $validated['series'],
                'experience_level' => $validated['experience_level'],
                'bio'              => $validated['bio'] ?? null,
            ]
        );

        return response()->json([
            'ok'        => true,
            'cosplayer' => $cosplayer,
        ]);
    }

    public function getCosplayerProfile(Request $request)
    {
        $userId = $request->query('user_id');
        if (! $userId) {
            return response()->json(['cosplayer' => null]);
        }

        $cosplayer = Cosplayer::where('user_id', $userId)->first();
        return response()->json(['cosplayer' => $cosplayer]);
    }
}
