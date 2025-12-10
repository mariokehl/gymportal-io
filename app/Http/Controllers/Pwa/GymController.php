<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use Illuminate\Http\JsonResponse;

class GymController extends Controller
{
    /**
     * Gym mit Theme-Daten anhand Slug laden (für PWA)
     */
    public function show(string $slug): JsonResponse
    {
        $gym = Gym::where('slug', $slug)
            ->pwaEnabled()
            ->with('legalUrls')
            ->first();

        if (!$gym) {
            return response()->json([
                'success' => false,
                'message' => 'Gym nicht gefunden oder PWA nicht aktiviert',
                'error_code' => 'GYM_NOT_FOUND'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $gym->getMemberAppData()
        ]);
    }

    /**
     * Nur Theme-Daten für ein Gym
     */
    public function theme(string $slug): JsonResponse
    {
        $gym = Gym::where('slug', $slug)
            ->pwaEnabled()
            ->first();

        if (!$gym) {
            return response()->json([
                'success' => false,
                'message' => 'Gym nicht gefunden'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $gym->theme
        ]);
    }

    /**
     * PWA Manifest für Gym generieren
     */
    public function manifest(string $slug): JsonResponse
    {
        $gym = Gym::where('slug', $slug)
            ->pwaEnabled()
            ->first();

        if (!$gym) {
            return response()->json(['error' => 'Gym not found'], 404);
        }

        return response()->json($gym->pwa_manifest)
            ->header('Content-Type', 'application/manifest+json');
    }

    /**
     * Gym-Liste für Dropdown/Auswahl (optional, für Admin-Interface)
     */
    public function index(): JsonResponse
    {
        $gyms = Gym::pwaEnabled()
            ->select('id', 'name', 'slug', 'pwa_logo_url', 'logo_path', 'primary_color')
            ->orderBy('name')
            ->get()
            ->map(function ($gym) {
                return [
                    'id' => $gym->id,
                    'name' => $gym->name,
                    'slug' => $gym->slug,
                    'logo_url' => $gym->getPwaLogoUrl(),
                    'primary_color' => $gym->primary_color,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $gyms
        ]);
    }

    /**
     * Alle verwandten Gyms anhand des Slugs laden
     *
     * @param string $slug
     * @return JsonResponse
     */
    public function related(string $slug): JsonResponse
    {
        $gym = Gym::where('slug', $slug)->pwaEnabled()->first();

        if (!$gym) {
            return response()->json(['error' => 'Gym not found'], 404);
        }

        // Return sibling gyms without exposing owner_id in the API
        $relatedGyms = Gym::where('owner_id', $gym->owner_id)
            ->pwaEnabled()
            ->select('id', 'name', 'slug', 'address', 'postal_code', 'city', 'pwa_logo_url', 'logo_path', 'primary_color')
            ->orderBy('name')
            ->get()
            ->map(function ($gym) {
                return [
                    'id' => $gym->id,
                    'name' => $gym->name,
                    'slug' => $gym->slug,
                    'address' => $gym->address,
                    'postal_code' => $gym->postal_code,
                    'city' => $gym->city,
                    'logo_url' => $gym->getPwaLogoUrl(),
                    'primary_color' => $gym->primary_color,
                    'website' => $gym->website,
                ];
            });

        return response()->json(['success' => true, 'data' => $relatedGyms]);
    }
}
