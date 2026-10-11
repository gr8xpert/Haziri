<?php

declare(strict_types=1);

use App\Mail\ContactMessage;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    config(['hazri.contact_email' => 'sales@example.test']);
    $this->home = 'http://' . config('hazri.central_domain');
    $this->enquiry = ['name' => 'Ali Raza', 'email' => 'ali@factory.pk', 'phone' => '0300 1234567', 'company' => 'Raza Textiles',
        'employees' => '201-500', 'message' => 'We have 300 staff and four ZKTeco machines. Please send a price.'];
});

it('shows the contact form where the sign-in section was, and keeps sign in in a pop-up', function () {
    $this->get("{$this->home}/")->assertOk()
        ->assertSee('id="contact"', false)->assertSee('action="/contact"', false)
        ->assertSee('Sign in to your company')->assertSee('signin = true', false);
});

it('emails an enquiry to us, with replies going to the visitor', function () {
    $this->post("{$this->home}/contact", $this->enquiry)->assertRedirect('/#contact')->assertSessionHas('contact_sent');

    Mail::assertSent(ContactMessage::class, function (ContactMessage $mail): bool {
        $mail->assertSeeInHtml('Raza Textiles')->assertSeeInHtml('four ZKTeco machines');

        return $mail->hasTo('sales@example.test') && $mail->hasReplyTo('ali@factory.pk') && $mail->hasSubject('Website enquiry: Raza Textiles');
    });
    $this->get("{$this->home}/")->assertDontSee('action="/contact"', false);
});

it('needs a name, a real email and a message', function () {
    $this->post("{$this->home}/contact", ['name' => '', 'email' => 'not-an-email', 'message' => 'hi'])
        ->assertRedirect('/#contact')->assertSessionHasErrors(['name', 'email', 'message']);

    Mail::assertNothingSent();
});

it('drops messages from bots that fill in the hidden field', function () {
    $this->post("{$this->home}/contact", $this->enquiry + ['website' => 'http://spam.example'])->assertSessionHasErrors('website');

    Mail::assertNothingSent();
});
