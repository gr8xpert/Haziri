<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Mail\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** The landing page contact form: emails the enquiry to us (config hazri.contact_email). */
class ContactController extends Controller
{
    public function __invoke(ContactRequest $request): RedirectResponse
    {
        $contact = $request->safe()->only(['name', 'email', 'phone', 'company', 'employees', 'message']) + array_fill_keys(['phone', 'company', 'employees'], null);
        // Logged first, so an enquiry is never lost when the mail server is down.
        Log::info('Website enquiry', $contact);

        try {
            Mail::to(config('hazri.contact_email'))->send(new ContactMessage($contact));
        } catch (Throwable $e) {
            report($e);

            return redirect('/#contact')->withInput()
                ->with('contact_error', 'Sorry, your message could not be sent just now. Please try again in a few minutes.');
        }

        return redirect('/#contact')->with('contact_sent', true);
    }
}
