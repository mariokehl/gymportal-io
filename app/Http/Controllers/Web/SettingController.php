<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\GymUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SettingController extends Controller
{
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
        //$this->authorize('update', $gym);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
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
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255'
        ]);

        $gym->update($validated);

        return response()->json([
            'success' => true,
            'gym' => $gym,
            'message' => 'Gym-Einstellungen wurden erfolgreich aktualisiert.'
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
            $gym = \App\Models\Gym::findOrFail($request->gym_id);

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
            $gym = \App\Models\Gym::findOrFail($request->gym_id);

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
        //$this->authorize('update', $currentGym);

        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
            'role' => 'required|in:admin,staff,trainer'
        ]);

        $targetUser = User::where('email', $validated['email'])->first();

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
            'gym_user' => $gymUser,
            'message' => 'Benutzer wurde erfolgreich zum Team hinzugefügt.'
        ]);
    }

    public function updateGymUser(Request $request, GymUser $gymUser)
    {
        // Überprüfen ob der Benutzer berechtigt ist
        //$this->authorize('update', $gymUser->gym);

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
        //$this->authorize('update', $gymUser->gym);

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
}
