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

        $nextVolumeNo = Volume::max('volume_no') + 1;
        if ($nextVolumeNo < 1) {
            $nextVolumeNo = 1;
        }

        $slug = \Illuminate\Support\Str::slug($validated['title']) . '-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(4));

        $volume = new Volume();
        $volume->title = $validated['title'];
        $volume->year = $validated['year'];
        $volume->status = $validated['status'];
        $volume->description = $validated['description'];
        $volume->volume_no = $nextVolumeNo;
        $volume->slug = $slug;
        $volume->is_active = true;
        $volume->save();

        return redirect()
            ->route('editor.volumes.index')
            ->with('success', 'Volume created successfully.');
    }

    public function toggleStatus(Volume $volume): RedirectResponse
    {
        $newStatus = $volume->status === 'published' ? 'draft' : 'published';
        $volume->update(['status' => $newStatus]);

        return redirect()
            ->back()
            ->with('success', "Volume status updated to '{$newStatus}' successfully.");
    }
}
