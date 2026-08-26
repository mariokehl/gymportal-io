<?php

namespace App\Http\Controllers\Web\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateInkassoSettingsRequest;
use App\Services\Diagonal\DiagonalClient;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class InkassoController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected DiagonalClient $client) {}

    /**
     * Current Inkasso settings without the stored secret.
     */
    public function index(Request $request): JsonResponse
    {
        $gym = $request->user()->currentGym;

        if (! $gym) {
            return response()->json(['error' => 'Kein Gym ausgewählt'], 404);
        }

        return response()->json(['settings' => $gym->getInkassoSettingsForDisplay()]);
    }

    /**
     * Persist the settings. The password is only replaced when a new one is
     * supplied, so the stored secret survives a normal save.
     */
    public function update(UpdateInkassoSettingsRequest $request): JsonResponse
    {
        $gym = $request->user()->currentGym;
        $validated = $request->validated();

        $settings = $gym->inkasso_settings;

        $password = $validated['password'] ?? null;
        unset($validated['password']);

        $environmentChanged = (bool) $settings['sandbox'] !== (bool) $validated['sandbox'];

        $settings = array_merge($settings, $validated);

        if (filled($password)) {
            $settings['password'] = Crypt::encryptString($password);
        }

        if (filled($password) || $environmentChanged) {
            // Force a fresh login with the new credentials or against the other host.
            $this->client->forgetToken($gym);
        }

        $gym->update(['inkasso_settings' => $settings]);

        return response()->json([
            'success' => true,
            'settings' => $gym->fresh()->getInkassoSettingsForDisplay(),
            'message' => 'Inkasso-Einstellungen gespeichert.',
        ]);
    }

    /**
     * Activate the partner after the setup wizard completed.
     */
    public function activate(Request $request): JsonResponse
    {
        $gym = $request->user()->currentGym;
        $this->authorize('update', $gym);

        $validated = $request->validate([
            'tenant_id' => ['required', 'string', 'max:64'],
            'client_number' => ['required', 'string', 'size:5'],
            'username' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string', 'max:190'],
            'sandbox' => ['sometimes', 'boolean'],
        ]);

        $settings = array_merge($gym->inkasso_settings, [
            'active' => true,
            'partner' => 'diagonal',
            'tenant_id' => $validated['tenant_id'],
            'client_number' => $validated['client_number'],
            'username' => $validated['username'],
            'password' => Crypt::encryptString($validated['password']),
            'sandbox' => (bool) ($validated['sandbox'] ?? $gym->usesInkassoSandbox()),
            'activated_at' => now()->toDateTimeString(),
        ]);

        $gym->update(['inkasso_settings' => $settings]);
        $this->client->forgetToken($gym);

        return response()->json([
            'success' => true,
            'settings' => $gym->fresh()->getInkassoSettingsForDisplay(),
            'message' => 'DIAGONAL Inkasso aktiviert.',
        ]);
    }

    /**
     * Deactivate the partner. Existing cases stay untouched, but no new cases
     * can be transmitted.
     */
    public function deactivate(Request $request): JsonResponse
    {
        $gym = $request->user()->currentGym;
        $this->authorize('update', $gym);

        $gym->update([
            'inkasso_settings' => array_merge($gym->inkasso_settings, ['active' => false]),
        ]);

        $this->client->forgetToken($gym);

        return response()->json([
            'success' => true,
            'settings' => $gym->fresh()->getInkassoSettingsForDisplay(),
            'message' => 'Inkassopartner deaktiviert.',
        ]);
    }

    /**
     * Verify the credentials against the partner API.
     */
    public function testConnection(Request $request): JsonResponse
    {
        $gym = $request->user()->currentGym;
        $this->authorize('update', $gym);

        // Allow testing credentials and an environment that are not stored yet.
        $validated = $request->validate([
            'tenant_id' => ['nullable', 'string', 'max:64'],
            'username' => ['nullable', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:190'],
            'sandbox' => ['sometimes', 'boolean'],
        ]);

        $overrides = ['sandbox' => (bool) ($validated['sandbox'] ?? $gym->usesInkassoSandbox())];

        if (filled($validated['username'] ?? null) && filled($validated['password'] ?? null)) {
            $overrides += [
                'tenant_id' => $validated['tenant_id'] ?? $gym->inkasso_settings['tenant_id'],
                'username' => $validated['username'],
                'password' => Crypt::encryptString($validated['password']),
            ];
        }

        $probe = clone $gym;
        $probe->inkasso_settings = array_merge($gym->inkasso_settings, $overrides);

        $result = $this->client->testConnection($probe);

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}
