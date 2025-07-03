<?php
// app/Http/Controllers/Web/MemberController.php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\PaymentMethod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    /**
     * Display a listing of the members.
     */
    public function index(Request $request)
    {
        /** @var User $auth */
        $user = Auth::user();

        $query = Member::query()
            ->with(['user', 'gym'])
            ->where('gym_id', $user->current_gym_id);

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('member_number', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Order by latest joined
        $query->orderBy('joined_date', 'desc');

        $members = $query->paginate(15)->withQueryString();

        // Add additional data to each member
        $members->getCollection()->transform(function ($member) {
            // Get last visit from visits table (assuming you have a visits table)
            //$member->last_visit = $member->visits()->latest()->first()?->visited_at;

            // Get contract end date from contracts table (assuming you have a contracts table)
            //$member->contract_end_date = $member->contracts()
            //    ->where('status', 'active')
            //    ->first()?->end_date;

            return $member;
        });

        return Inertia::render('Members/Index', [
            'members' => $members,
            'filters' => $request->only(['search', 'status'])
        ]);
    }

    /**
     * Show the form for creating a new member.
     */
    public function create()
    {
        $user = Auth::user();

        $membershipPlans = MembershipPlan::where('gym_id', $user->current_gym_id)
            ->withCount(['memberships' => function ($query) {
                $query->where('status', 'active');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Members/Create', [
            'membershipPlans' => $membershipPlans
        ]);
    }

    /**
     * Store a newly created member in storage.
     */
    public function store(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('members', 'email')->whereNull('deleted_at')],
            'phone' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['active', 'inactive', 'paused', 'overdue'])],
            'joined_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
        ]);

        // Next member number
        $lastMember = Member::orderBy('id', 'desc')->first();
        $nextNumber = $lastMember ? $lastMember->id + 100000 : 100001;
        $validated['member_number'] = $nextNumber; // TODO: Implement number ranges

        // Add gym_id to the validated data
        $validated['gym_id'] = $user->current_gym_id;
        $validated['user_id'] = $user->id;

        try {
            DB::beginTransaction();

            // Create the member
            $newMember = Member::create($validated);

            // Get membership plan to calculate end date
            $membershipPlan = MembershipPlan::findOrFail($request->membership_plan_id);

            // Create membership
            Membership::create([
                'member_id' => $newMember->id,
                'membership_plan_id' => $request->membership_plan_id,
                'start_date' => $validated['joined_date'],
                'end_date' => Carbon::parse($validated['joined_date'])->addMonths($membershipPlan->commitment_months)
            ]);

            // Select payment method
            PaymentMethod::create([
                'member_id' => $newMember->id,
                'type' => $request->payment_method
            ]);

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

        return redirect()->route('members.index')
            ->with('success', 'Mitglied wurde erfolgreich erstellt.');
    }

    /**
     * Display the specified member.
     */
    public function show(Member $member)
    {
        // Ensure the member belongs to the current gym
        //$this->authorize('view', $member);

        $member->load([
            'user',
            'gym',
            'memberships.membershipPlan',
            'checkIns' => function ($query) {
                $query->latest()->take(10);
            },
        ]);

        return Inertia::render('Members/Show', [
            'member' => $member
        ]);
    }

    /**
     * Update the specified member in storage.
     */
    public function update(Request $request, Member $member)
    {
        // Ensure the member belongs to the current gym
        //$this->authorize('update', $member);

        $validated = $request->validate([
            'member_number' => ['required', 'string', 'max:50',
                Rule::unique('members', 'member_number')->ignore($member->id)
            ],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255',
                Rule::unique('members', 'email')->ignore($member->id)
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['active', 'inactive', 'paused', 'overdue'])],
            'joined_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
        ]);

        $member->update($validated);

        return redirect()->route('members.show', ['member' => $member->id])->with('success', 'Mitglied wurde erfolgreich aktualisiert.');
    }

    /**
     * Remove the specified member from storage.
     */
    public function destroy(Member $member)
    {
        // Ensure the member belongs to the current gym
        //$this->authorize('delete', $member);

        $memberName = $member->first_name . ' ' . $member->last_name;

        $member->delete();

        return redirect()->route('members.index')
            ->with('success', "Mitglied {$memberName} wurde erfolgreich gelöscht.");
    }
}
