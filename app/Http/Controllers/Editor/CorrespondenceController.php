<?php

namespace App\Http\Controllers\Editor;

use App\Http\Controllers\Controller;
use App\Models\CorrespondenceMessage;
use App\Models\Submission;
use App\Notifications\CorrespondenceMessageNotification;
use Illuminate\Http\Request;

class CorrespondenceController extends Controller
{
    public function index(Submission $submission)
    {
        $messages = CorrespondenceMessage::with(['sender', 'recipient'])
            ->where('submission_id', $submission->id)
            ->latest()
            ->paginate(20);

        return view('editor.correspondence.index', compact('submission', 'messages'));
    }

    public function store(Request $request, Submission $submission)
    {
        $validated = $request->validate([
            'recipient_id' => ['required', 'exists:users,id'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
        ]);

        $message = CorrespondenceMessage::create([
            'submission_id' => $submission->id,
            'sender_id' => auth()->id(),
            'recipient_id' => $validated['recipient_id'],
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'sent_at' => now(),
        ]);

        $message->recipient?->notify(new CorrespondenceMessageNotification($message));

        return back()->with('success', 'Correspondence message sent successfully.');
    }
}
