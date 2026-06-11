@component('mail::message')
# Einladung zu {{ $gymName }}

Sie wurden eingeladen, dem Team von **{{ $gymName }}** als **{{ $roleLabel }}** beizutreten.

Klicken Sie auf den folgenden Button, um die Einladung anzunehmen und Ihren Zugang einzurichten:

@component('mail::button', ['url' => $acceptUrl])
Einladung annehmen
@endcomponent

@if ($expiresAt)
Diese Einladung ist bis zum **{{ $expiresAt }}** gültig.
@endif

Falls Sie diese Einladung nicht erwartet haben, können Sie diese E-Mail ignorieren.

Viele Grüße,<br>
{{ $gymName }}
@endcomponent
