<?php

namespace App\Http\Controllers\Web;

use App\Models\Gym;
use App\Models\User;
use App\Http\Controllers\Controller;
use App\Rules\SafeCss;
use App\Services\CssSanitizer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class GymController extends Controller
{
    use AuthorizesRequests;

    public function create()
    {
        return Inertia::render('Organization/Create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', Gym::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:10'],
            'country' => ['required', 'string', 'max:2'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
        ]);

        try {
            DB::beginTransaction();

            /** @var User $user */
            $user = Auth::user();

            // Create the gym with default theme
            $gym = Gym::create([
                ...$validated,
                'owner_id' => $user->id,
                // Default theme colors (can be customized later)
                'primary_color' => '#e11d48', // Rose-600 (matching widget default)
                'secondary_color' => '#64748b', // Slate-500
                'accent_color' => '#10b981',    // Emerald-500
            ]);

            // Set this gym as the user's current gym
            $user->update(['current_gym_id' => $gym->id]);

            DB::commit();

            return redirect()
                ->route('dashboard')
                ->with('success', 'Organisation wurde erfolgreich erstellt!');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()
                ->withErrors(['general' => 'Fehler beim Erstellen der Organisation. Bitte versuchen Sie es erneut.'])
                ->withInput();
        }
    }

    public function remove(Gym $gym)
    {
        $this->authorize('delete', $gym);

        /** @var User $user */
        $user = Auth::user();

        /** @var Gym|null $gym */
        $gym = $user->ownedGyms()->find($gym->id);

        // Set next possible gym in user
        $nextGym = $user->ownedGyms()->first();

        $user->update(['current_gym_id' => $nextGym->id]);

        // Remove entity
        $gym->delete();

        return redirect()
            ->route('dashboard')
            ->with('success', 'Organisation wurde entfernt!');
    }

    public function switchOrganization(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $this->authorize('view', $user->ownedGyms()->find($request->gym_id));

        $request->validate([
            'gym_id' => 'required|exists:gyms,id'
        ]);

        // Set current gym in user
        $user->update(['current_gym_id' => $request->gym_id]);

        return redirect()->route('dashboard');
    }

    /**
     * PWA Theme Settings Page
     */
    public function themeSettings(Gym $gym)
    {
        $this->authorize('update', $gym);

        return Inertia::render('Organization/ThemeSettings', [
            'gym' => $gym->load([]),
            'theme' => $gym->theme,
            'pwa_settings' => $gym->pwa_settings,
        ]);
    }

    /**
     * Update PWA Theme Settings
     */
    public function updateTheme(Request $request, Gym $gym)
    {
        $this->authorize('update', $gym);

        $validated = $request->validate([
            'primary_color' => ['required', 'regex:/^#[a-f0-9]{6}$/i'],
            'secondary_color' => ['required', 'regex:/^#[a-f0-9]{6}$/i'],
            'accent_color' => ['required', 'regex:/^#[a-f0-9]{6}$/i'],
            'background_color' => ['nullable', 'regex:/^#[a-f0-9]{6}$/i'],
            'text_color' => ['nullable', 'regex:/^#[a-f0-9]{6}$/i'],
            'pwa_logo_url' => ['nullable', 'url'],
            'favicon_url' => ['nullable', 'url'],
            'custom_css' => ['nullable', 'string', 'max:10000', new SafeCss],
            'member_app_description' => ['nullable', 'string', 'max:500'],
            'pwa_enabled' => ['boolean'],
            'opening_hours' => ['nullable', 'array'],
            'social_media' => ['nullable', 'array'],
        ]);

        // Sanitize custom CSS before storing
        if (isset($validated['custom_css'])) {
            $validated['custom_css'] = CssSanitizer::sanitize($validated['custom_css']);
        }

        try {
            $gym->update($validated);

            return redirect()
                ->back()
                ->with('success', 'Theme-Einstellungen wurden erfolgreich gespeichert!');
        } catch (\Exception $e) {
            return back()
                ->withErrors(['general' => 'Fehler beim Speichern der Theme-Einstellungen.'])
                ->withInput();
        }
    }
}
