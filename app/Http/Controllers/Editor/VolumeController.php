<?php

namespace App\Http\Controllers\Editor;

use App\Http\Controllers\Controller;
use App\Models\Volume;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VolumeController extends Controller
{
    public function index(): View
    {
        $volumes = Volume::latest()->paginate(15);

        return view('auth.editor.volumes.index', compact('volumes'));
    }

    public function create(): View
    {
        return view('auth.editor.volumes.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'year' => ['required', 'digits:4'],
            'status' => ['required', 'in:draft,published'],
            'description' => ['nullable', 'string'],
        ]);

        Volume::create($validated);

        return redirect()
            ->route('editor.volumes.index')
            ->with('success', 'Volume created successfully.');
    }
}
