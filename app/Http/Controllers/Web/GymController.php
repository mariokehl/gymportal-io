<?php

namespace App\Http\Controllers\Web;

use App\Models\Gym;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class GymController extends Controller
{
    public function create()
    {
        return Inertia::render('Organization/Create');
    }

    public function store(Request $request)
    {
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

            // Create the gym
            $gym = Gym::create([
                ...$validated,
                'owner_id' => $user->id,
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
        /** @var User $user */
        $user = Auth::user();

        /** @var Gym|null $gym */
        $gym = $user->ownedGyms()->find($gym->id);

        // Check if the user has access to the gym
        if (!$gym) {
            abort(403, 'Unauthorized');
        }

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
        $request->validate([
            'gym_id' => 'required|exists:gyms,id'
        ]);

        /** @var User $user */
        $user = Auth::user();

        // Check if the user has access to the gym
        if (!$user->ownedGyms()->find($request->gym_id)) {
            abort(403, 'Unauthorized');
        }

        // Set current gym in user
        $user->update(['current_gym_id' => $request->gym_id]);

        return redirect()->back();
    }
}
