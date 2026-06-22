<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $contact = ContactMessage::create($validated);

        try {
            $adminEmail = config('mail.from.address', env('MAIL_FROM_ADDRESS'));

            if ($adminEmail) {
                Mail::raw(
                    "New contact enquiry received.\n\n" .
                    "Name: {$contact->full_name}\n" .
                    "Email: {$contact->email}\n" .
                    "Subject: {$contact->subject}\n\n" .
                    "Message:\n{$contact->message}",
                    function ($mail) use ($adminEmail, $contact) {
                        $mail->to($adminEmail)
                            ->subject('OJMAS Contact Enquiry: ' . $contact->subject);
                    }
                );
            }
        } catch (\Throwable $e) {
            // keep app running even if mail is not configured
        }

        return back()->with('success', 'Your message has been sent successfully.');
    }
}
