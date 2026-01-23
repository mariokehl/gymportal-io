<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class PaymentReturnController extends Controller
{
    /**
     * Handle the return from a payment provider.
     * Redirects to the gym's website or shows a fallback page.
     */
    public function __invoke(Gym $organization): RedirectResponse|Response
    {
        // If the gym has a website configured, redirect there
        if ($organization->website) {
            $url = $organization->website;

            // Ensure the URL has a protocol
            if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
                $url = 'https://' . $url;
            }

            return redirect()->away($url);
        }

        // No website configured - show a simple HTML fallback page
        $gymName = htmlspecialchars($organization->getDisplayName(), ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zahlung abgeschlossen - {$gymName}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: white;
            border-radius: 16px;
            padding: 48px;
            max-width: 480px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        .icon {
            width: 80px;
            height: 80px;
            background: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        .icon svg {
            width: 40px;
            height: 40px;
            color: white;
        }
        h1 {
            color: #1f2937;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        p {
            color: #6b7280;
            font-size: 16px;
            line-height: 1.6;
        }
        .gym-name {
            color: #4f46e5;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
        </div>
        <h1>Zahlung abgeschlossen</h1>
        <p>
            Vielen Dank! Der Zahlungsvorgang bei <span class="gym-name">{$gymName}</span> wurde abgeschlossen.
            Sie können dieses Fenster jetzt schließen.
        </p>
    </div>
</body>
</html>
HTML;

        return response($html);
    }
}
