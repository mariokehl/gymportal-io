<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use Illuminate\Http\JsonResponse;

class GuestGymController extends Controller
{
    /**
     * Get gym details by slug (public, no auth required).
     */
    public function show(string $slug): JsonResponse
    {
        $gym = Gym::where('slug', $slug)
            ->where('guest_enabled', true)
            ->first();

        if (!$gym) {
            return response()->json([
                'success' => false,
                'message' => 'Studio nicht gefunden oder Gäste-Zugang nicht aktiviert.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $gym->id,
                'name' => $gym->getDisplayName(),
                'slug' => $gym->slug,
                'description' => $gym->description,
                'address' => $gym->address,
                'city' => $gym->city,
                'postal_code' => $gym->postal_code,
                'phone' => $gym->phone,
                'email' => $gym->email,
                'website' => $gym->website,
                'logo_url' => $gym->logo_url,
                'theme' => $gym->theme,
                'opening_hours' => $gym->opening_hours,
                'legal_urls' => $gym->getLegalUrlsArray(),
                'mollie_configured' => $gym->hasMollieConfigured(),
            ],
        ]);
    }
}
