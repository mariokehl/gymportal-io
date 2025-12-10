<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\GymLegalUrl;
use App\Models\GymUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SettingController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        /** @var User $user */
        $user = Auth::user();
        $currentGym = $user->currentGym;

        if (!$currentGym) {
            return redirect()->route('dashboard')->with('error', 'Kein Gym gefunden.');
        }

        $gymUsers = GymUser::where('gym_id', $currentGym->id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Settings/Index', [
            'currentGym' => $currentGym,
            'gymUsers' => $gymUsers,
            'user' => $user
        ]);
    }

    public function updateGym(Request $request, Gym $gym)
    {
        // Überprüfen ob der Benutzer berechtigt ist
        $this->authorize('update', $gym);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'display_name' => 'nullable|string|max:255',
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('gyms', 'slug')->ignore($gym->id)
            ],
            'description' => 'nullable|string|max:1000',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'required|string|max:2',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'account_holder' => 'nullable|string|max:255',
            'iban' => 'nullable|string|max:34',
            'bic' => 'nullable|string|max:11',
            'creditor_identifier' => 'nullable|string|max:35',
            'website' => 'nullable|url|max:255'
        ]);

        $gym->update($validated);

        return response()->json([
            'success' => true,
            'gym' => $gym,
            'message' => 'Organisation wurde erfolgreich aktualisiert.'
        ]);
    }

    public function uploadLogo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'logo' => [
                'required',
                'file',
                'image',
                'mimes:png,jpg,jpeg,gif',
                'max:10240' // 10MB in Kilobytes
            ],
            'gym_id' => 'required|exists:gyms,id'
        ], [
            'logo.required' => 'Bitte wählen Sie eine Datei aus.',
            'logo.file' => 'Die hochgeladene Datei ist ungültig.',
            'logo.image' => 'Die Datei muss ein Bild sein.',
            'logo.mimes' => 'Nur PNG, JPG und GIF Dateien sind erlaubt.',
            'logo.max' => 'Die Datei darf maximal 10 MB groß sein.',
            'gym_id.required' => 'Fitnessstudio ID ist erforderlich.',
            'gym_id.exists' => 'Fitnessstudio nicht gefunden.'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        try {
            $file = $request->file('logo');
            $gym = Gym::findOrFail($request->gym_id);

            $this->authorize('update', $gym);

            // Altes Logo löschen falls vorhanden
            if ($gym->logo_path) {
                Storage::disk('public')->delete($gym->logo_path);
            }

            // Eindeutigen Dateinamen generieren
            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

            // Datei in den logos Ordner speichern
            $logoPath = $file->storeAs('logos', $fileName, 'public');

            // Gym Model aktualisieren
            $gym->update(['logo_path' => $logoPath]);

            return back()->with([
                'success' => 'Logo wurde erfolgreich hochgeladen.',
                'logoPath' => Storage::url($logoPath)
            ]);

        } catch (\Exception $e) {
            return back()->withErrors([
                'logo' => 'Fehler beim Hochladen der Datei. Bitte versuchen Sie es erneut.'
            ]);
        }
    }

    public function deleteLogo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gym_id' => 'required|exists:gyms,id'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        try {
            $gym = Gym::findOrFail($request->gym_id);

            $this->authorize('update', $gym);

            if ($gym->logo_path) {
                Storage::disk('public')->delete($gym->logo_path);
                $gym->update(['logo_path' => null]);
            }

            return back()->with('success', 'Logo wurde erfolgreich entfernt.');

        } catch (\Exception $e) {
            return back()->withErrors([
                'logo' => 'Fehler beim Löschen des Logos.'
            ]);
        }
    }

    public function storeGymUser(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $currentGym = $user->ownedGyms()->first();

        if (!$currentGym) {
            return redirect()->back()->with('error', 'Kein Gym gefunden.');
        }

        // Überprüfen ob der Benutzer berechtigt ist, Team-Mitglieder hinzuzufügen
        $this->authorize('update', $currentGym);

        // E-Mail zu Kleinbuchstaben konvertieren
        $request->merge([
            'email' => strtolower($request->email)
        ]);

        $validated = $request->validate([
            'email' => 'required|email|indisposable|max:255',
            'first_name' => 'required_without:user_exists|string|max:255',
            'last_name' => 'required_without:user_exists|string|max:255',
            'role' => 'required|in:admin,staff,trainer'
        ]);

        // Versuche, den Benutzer zu finden oder zu erstellen
        $targetUser = User::where('email', $validated['email'])->first();

        if (!$targetUser) {
            // Validierung für neue Benutzer
            $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
            ]);

            // Hole die entsprechende Role basierend auf der Gym-Rolle
            $role = Role::where('slug', $validated['role'])->first();

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ungültige Rolle.'
                ], 422);
            }

            // Neuen Benutzer erstellen
            $targetUser = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'email_verified_at' => now(),
                'password' => Hash::make(Str::random(32)), // Zufälliges temporäres Passwort
                'role_id' => $role->id,
                'current_gym_id' => $currentGym->id,
            ]);
        }

        // Überprüfen ob der Benutzer bereits im Team ist
        $existingGymUser = GymUser::where('gym_id', $currentGym->id)
            ->where('user_id', $targetUser->id)
            ->first();

        if ($existingGymUser) {
            return response()->json([
                'success' => false,
                'message' => 'Dieser Benutzer ist bereits Teil des Teams.'
            ]);
        }

        $gymUser = GymUser::create([
            'gym_id' => $currentGym->id,
            'user_id' => $targetUser->id,
            'role' => $validated['role']
        ]);

        return response()->json([
            'success' => true,
            'gym_user' => [
                'id' => $gymUser->id,
                'gym_id' => $gymUser->gym_id,
                'user_id' => $gymUser->user_id,
                'role' => $gymUser->role,
                'created_at' => $gymUser->created_at,
                'user' => [
                    'id' => $targetUser->id,
                    'first_name' => $targetUser->first_name,
                    'last_name' => $targetUser->last_name,
                    'email' => $targetUser->email,
                ]
            ],
            'message' => 'Benutzer wurde erfolgreich zum Team hinzugefügt.'
        ]);
    }

    public function updateGymUser(Request $request, GymUser $gymUser)
    {
        // Überprüfen ob der Benutzer berechtigt ist
        $this->authorize('update', $gymUser->gym);

        $validated = $request->validate([
            'role' => 'required|in:admin,staff,trainer'
        ]);

        // Verhindern, dass der Besitzer seine eigene Rolle ändert
        if ($gymUser->user_id === Auth::id() && $gymUser->role === 'owner') {
            return redirect()->back()->with('error', 'Sie können Ihre eigene Besitzer-Rolle nicht ändern.');
        }

        $gymUser->update($validated);

        return response()->json([
            'success' => true,
            'gym_user' => $gymUser,
            'message' => 'Benutzerrolle wurde erfolgreich aktualisiert.'
        ]);
    }

    public function destroyGymUser(GymUser $gymUser)
    {
        // Überprüfen ob der Benutzer berechtigt ist
        $this->authorize('update', $gymUser->gym);

        // Verhindern, dass der Besitzer sich selbst entfernt
        if ($gymUser->user_id === Auth::id() && $gymUser->role === 'owner') {
            return redirect()->back()->with('error', 'Sie können sich nicht selbst aus dem Team entfernen.');
        }

        $gymUser->delete();

        return response()->json([
            'success' => true,
            'message' => 'Benutzer wurde erfolgreich aus dem Team entfernt.'
        ]);
    }

    /**
     * Legal URLs abrufen
     */
    public function getLegalUrls()
    {
        /** @var User $user */
        $user = Auth::user();
        $currentGym = $user->currentGym;

        if (!$currentGym) {
            return response()->json(['error' => 'Kein Gym gefunden.'], 404);
        }

        $legalUrls = $currentGym->legalUrls()->get()->map(function ($url) {
            return [
                'id' => $url->id,
                'type' => $url->type,
                'label' => $url->label,
                'url' => $url->url,
            ];
        });

        return response()->json([
            'success' => true,
            'legal_urls' => $legalUrls,
            'available_types' => GymLegalUrl::getTypes(),
        ]);
    }

    /**
     * Legal URL erstellen oder aktualisieren
     */
    public function storeLegalUrl(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $currentGym = $user->currentGym;

        if (!$currentGym) {
            return response()->json(['error' => 'Kein Gym gefunden.'], 404);
        }

        $this->authorize('update', $currentGym);

        $validated = $request->validate([
            'type' => 'required|string|in:' . implode(',', array_keys(GymLegalUrl::getTypes())),
            'url' => 'required|url|max:2048',
        ]);

        $legalUrl = GymLegalUrl::updateOrCreate(
            [
                'gym_id' => $currentGym->id,
                'type' => $validated['type'],
            ],
            [
                'url' => $validated['url'],
            ]
        );

        return response()->json([
            'success' => true,
            'legal_url' => [
                'id' => $legalUrl->id,
                'type' => $legalUrl->type,
                'label' => $legalUrl->label,
                'url' => $legalUrl->url,
            ],
            'message' => 'URL wurde erfolgreich gespeichert.',
        ]);
    }

    /**
     * Legal URL löschen
     */
    public function destroyLegalUrl(GymLegalUrl $legalUrl)
    {
        /** @var User $user */
        $user = Auth::user();
        $currentGym = $user->currentGym;

        if (!$currentGym || $legalUrl->gym_id !== $currentGym->id) {
            return response()->json(['error' => 'Nicht autorisiert.'], 403);
        }

        $this->authorize('update', $currentGym);

        $legalUrl->delete();

        return response()->json([
            'success' => true,
            'message' => 'URL wurde erfolgreich gelöscht.',
        ]);
    }

    /**
     * PWA-Einstellungen aktualisieren
     */
    public function updatePwaSettings(Request $request, Gym $gym)
    {
        $this->authorize('update', $gym);

        $validated = $request->validate([
            'pwa_enabled' => 'boolean',
            'primary_color' => ['nullable', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'secondary_color' => ['nullable', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'accent_color' => ['nullable', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'background_color' => ['nullable', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'text_color' => ['nullable', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'pwa_logo_url' => 'nullable|url|max:2048',
            'favicon_url' => 'nullable|url|max:2048',
            'custom_css' => 'nullable|string|max:10000',
            'member_app_description' => 'nullable|string|max:500',
            'opening_hours' => 'nullable|array',
            'opening_hours.*.open' => 'nullable|string',
            'opening_hours.*.close' => 'nullable|string',
            'opening_hours.*.closed' => 'nullable|boolean',
            'social_media' => 'nullable|array',
            'social_media.instagram' => 'nullable|url|max:255',
            'social_media.facebook' => 'nullable|url|max:255',
            'social_media.youtube' => 'nullable|url|max:255',
            'social_media.twitter' => 'nullable|url|max:255',
            'social_media.linkedin' => 'nullable|url|max:255',
            'social_media.tiktok' => 'nullable|url|max:255',
            'pwa_settings' => 'nullable|array',
            'pwa_settings.install_prompt_enabled' => 'nullable|boolean',
            'pwa_settings.offline_support_enabled' => 'nullable|boolean',
            'pwa_settings.push_notifications_enabled' => 'nullable|boolean',
            'pwa_settings.background_sync_enabled' => 'nullable|boolean',
        ]);

        $gym->update($validated);

        return response()->json([
            'success' => true,
            'gym' => $gym->fresh(),
            'message' => 'PWA-Einstellungen wurden erfolgreich aktualisiert.'
        ]);
    }
}
