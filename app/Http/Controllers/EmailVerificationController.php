<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmailVerificationController extends Controller
{
    public function notice()
    {
        return Inertia::render('Auth/VerifyEmail', [
            'message' => session('message')
        ]);
    }

    public function verify(EmailVerificationRequest $request)
    {
        $request->fulfill();

        return redirect()->intended(route('dashboard'))
            ->with('message', 'E-Mail erfolgreich verifiziert!');
    }

    public function resend(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard'));
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('message', 'Verifizierungslink wurde erneut gesendet!');
    }
}
