@php
    $slug = (string) tenant('slug');
    $password = config('hazri.demo.password');
    $logins = [
        __('Owner (sees everything)') => \App\Support\DemoMode::email($slug, 'owner'),
        __('HR') => \App\Support\DemoMode::email($slug, 'hr'),
        __('Manager (own team only)') => \App\Support\DemoMode::email($slug, 'manager'),
        __('Reception (visitors)') => \App\Support\DemoMode::email($slug, 'reception'),
    ];
@endphp
<div class="hz-demo-logins" style="border: 1px solid rgb(16 185 129 / .35); background: rgb(16 185 129 / .07); border-radius: .75rem; padding: .875rem 1rem; font-size: .875rem; line-height: 1.5;">
    <p style="font-weight: 600; margin-bottom: .375rem;">{{ __('Demo company: try any login') }}</p>
    <table style="width: 100%;">
        @foreach ($logins as $who => $email)
            <tr>
                <td style="padding-right: .75rem; opacity: .75;">{{ $who }}</td>
                <td><button type="button" style="font-family: ui-monospace, monospace; text-decoration: underline dotted;"
                        x-on:click="$wire.set('data.email', @js($email)); $wire.set('data.password', @js($password))">{{ $email }}</button></td>
            </tr>
        @endforeach
    </table>
    <p style="margin-top: .375rem; opacity: .75;">
        {{ __('Password: :password · Click an email to fill the form. Staff app: /me with :email.', ['password' => $password, 'email' => \App\Support\DemoMode::email($slug, 'employee')]) }}<br>
        {{ __('Everything you change is reset every night. No real messages are sent.') }}
    </p>
</div>
