<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Form;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AdminFormController extends Controller
{
    /**
     * Create a new form under an event, then redirect to builder.
     */
    public function store(Request $request, Event $event)
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active'   => ['boolean'],
        ]);

        $form = $event->forms()->create([
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active'   => $validated['is_active'] ?? true,
        ]);

        return redirect()->route('admin.forms.builder', [$event, $form]);
    }
}
