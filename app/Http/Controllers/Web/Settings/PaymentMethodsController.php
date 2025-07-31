<?php

namespace App\Http\Controllers\Web\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentMethodsController extends Controller
{
    /**
     * Zahlungsmethoden-Status abrufen
     */
    public function index(Request $request): JsonResponse
    {
        $gym = $request->user()->currentGym;

        if (!$gym) {
            return response()->json(['error' => 'Kein Gym ausgewählt'], 404);
        }

        return response()->json([
            'standard_methods' => $gym->getStandardPaymentMethods(),
            'mollie_methods' => $gym->getMolliePaymentMethods(),
            'all_methods' => $gym->getAllPaymentMethods(),
            'enabled_methods' => $gym->getEnabledPaymentMethods(),
            'mollie_status' => [
                'isActive' => $gym->hasMollieConfigured(),
                'isTestMode' => $gym->isInTestMode(),
                'methodCount' => count($gym->getMollieEnabledMethods()),
                'enabledMethods' => $gym->getMollieEnabledMethods(),
            ],
        ]);
    }

    /**
     * Standard-Zahlungsmethode aktivieren/deaktivieren
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'method' => 'required|string',
            'enabled' => 'required|boolean',
        ]);

        $gym = $request->user()->currentGym;

        if (!$gym) {
            return response()->json(['error' => 'Kein Gym ausgewählt'], 404);
        }

        $success = $gym->updateStandardPaymentMethod(
            $request->input('method'),
            $request->input('enabled')
        );

        if (!$success) {
            return response()->json([
                'error' => 'Unbekannte Zahlungsmethode'
            ], 400);
        }

        return response()->json([
            'message' => 'Zahlungsmethode erfolgreich aktualisiert',
            'payment_methods' => $gym->getStandardPaymentMethods(),
        ]);
    }

    /**
     * Mollie-Konfiguration-Status abrufen
     */
    public function mollieStatus(Request $request): JsonResponse
    {
        $gym = $request->user()->currentGym;

        if (!$gym) {
            return response()->json(['error' => 'Kein Gym ausgewählt'], 404);
        }

        if (!$gym->hasMollieConfigured()) {
            return response()->json([
                'isActive' => false,
                'message' => 'Mollie nicht konfiguriert'
            ]);
        }

        return response()->json([
            'isActive' => true,
            'test_mode' => $gym->isInTestMode(),
            'enabled_methods' => $gym->getMollieEnabledMethods(),
            'webhook_url' => $gym->getMollieWebhookUrl(),
            'redirect_url' => $gym->getMollieRedirectUrl(),
        ]);
    }

    /**
     * Mollie-Konfiguration entfernen
     */
    public function removeMollieConfig(Request $request): JsonResponse
    {
        $gym = $request->user()->currentGym;

        if (!$gym) {
            return response()->json(['error' => 'Kein Gym ausgewählt'], 404);
        }

        $gym->update(['mollie_config' => null]);

        return response()->json([
            'message' => 'Mollie-Konfiguration erfolgreich entfernt',
            'payment_methods' => $gym->getStandardPaymentMethods(),
        ]);
    }

    /**
     * Zahlungsmethoden-Übersicht für Frontend
     */
    public function overview(Request $request): JsonResponse
    {
        $gym = $request->user()->currentGym;

        if (!$gym) {
            return response()->json(['error' => 'Kein Gym ausgewählt'], 404);
        }

        $standardMethods = $gym->getStandardPaymentMethods();
        $mollieMethods = $gym->getMolliePaymentMethods();
        $enabledMethods = $gym->getEnabledPaymentMethods();

        return response()->json([
            'overview' => [
                'total_methods' => count($standardMethods) + count($mollieMethods),
                'enabled_methods' => count($enabledMethods),
                'standard_methods_count' => count(array_filter($standardMethods, fn($m) => $m['enabled'])),
                'mollie_methods_count' => count($mollieMethods),
                'requires_sepa_mandate' => $gym->requiresSepaMandate(),
            ],
            'methods' => [
                'standard' => $standardMethods,
                'mollie' => $mollieMethods,
                'enabled' => $enabledMethods,
            ],
            'mollie_status' => [
                'is_active' => $gym->hasMollieConfigured(),
                'is_test_mode' => $gym->isInTestMode(),
                'method_count' => count($gym->getMollieEnabledMethods()),
            ],
        ]);
    }
}
