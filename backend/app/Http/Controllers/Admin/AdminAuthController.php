<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ThrottlesLogins;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AdminAuthController extends Controller
{
    use ThrottlesLogins;

    public function create()
    {
        return Inertia::render('Admin/Login');
    }

    public function store(LoginRequest $request)
    {
        $this->ensureNotThrottled($request, 'admin');

        if (! Auth::guard('admin')->attempt($request->validated(), $request->boolean('remember'))) {
            $this->hitThrottle($request, 'admin');

            return back()->withErrors(['email' => 'Invalid admin credentials.'])->onlyInput('email');
        }

        $this->clearThrottle($request, 'admin');
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }

    public function destroy(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
