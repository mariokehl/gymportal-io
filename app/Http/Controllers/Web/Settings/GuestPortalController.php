<?php

namespace App\Http\Controllers\Web\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SaveGuestProductRequest;
use App\Http\Requests\Settings\UpdateGuestPortalRequest;
use App\Models\GuestProduct;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Settings of the guest portal: switching it on and maintaining the products
 * guests can buy.
 */
class GuestPortalController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $gym = $request->user()->currentGym;
        $this->authorize('update', $gym);

        return response()->json([
            'guest_enabled' => (bool) $gym->guest_enabled,
            'guest_portal_url' => rtrim(config('gymportal.guests_url'), '/').'/'.$gym->slug,
            'mollie_configured' => $gym->hasMollieConfigured(),
            'products' => $gym->guestProducts()->orderBy('type')->orderBy('sort_order')->get(),
        ]);
    }

    public function update(UpdateGuestPortalRequest $request): JsonResponse
    {
        $gym = $request->user()->currentGym;
        $gym->update(['guest_enabled' => $request->boolean('guest_enabled')]);

        return response()->json([
            'guest_enabled' => (bool) $gym->guest_enabled,
            'message' => $gym->guest_enabled
                ? 'Das Gästeportal ist jetzt aktiv.'
                : 'Das Gästeportal wurde deaktiviert.',
        ]);
    }

    public function storeProduct(SaveGuestProductRequest $request): JsonResponse
    {
        $product = $request->user()->currentGym->guestProducts()->create($this->productAttributes($request));

        return response()->json([
            'product' => $product,
            'message' => 'Das Produkt wurde angelegt.',
        ], 201);
    }

    public function updateProduct(SaveGuestProductRequest $request, GuestProduct $guestProduct): JsonResponse
    {
        $guestProduct->update($this->productAttributes($request, $guestProduct->type));

        return response()->json([
            'product' => $guestProduct->fresh(),
            'message' => 'Das Produkt wurde gespeichert.',
        ]);
    }

    private function productAttributes(SaveGuestProductRequest $request, ?string $type = null): array
    {
        $validated = $request->validated();
        $type ??= $validated['type'];

        return array_merge($validated, [
            // A day pass always grants one day.
            'quantity' => $type === GuestProduct::TYPE_DAY_PASS ? 1 : (int) $validated['quantity'],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'active' => $request->boolean('active', true),
        ]);
    }
}
