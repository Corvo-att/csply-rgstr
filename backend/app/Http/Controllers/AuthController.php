<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ThrottlesLogins;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class AuthController extends Controller
{
    use ThrottlesLogins;

    public function showLogin()
    {
        return Inertia::render('Auth/Login');
    }

    public function login(LoginRequest $request)
    {
        $this->ensureNotThrottled($request, 'user');

        if (! Auth::attempt($request->validated(), $request->boolean('remember'))) {
            $this->hitThrottle($request, 'user');

            return back()->withErrors(['email' => 'Invalid email or password.'])->onlyInput('email');
        }

        $this->clearThrottle($request, 'user');
        $request->session()->regenerate();

        return redirect()->intended(route('cosplay.profile'));
    }

    public function showRegister()
    {
        return Inertia::render('Auth/Register');
    }

    public function register(RegisterRequest $request)
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('cosplay.register');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('welcome');
    }
}
