<?php

namespace App\Http\Controllers\Web\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdatePaymentExecutionSettingsRequest;
use App\Models\PaymentMethod;
use App\Services\MollieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentMethodsController extends Controller
{
    /**
     * Zahlungsmethoden-Status abrufen
     */
    public function index(Request $request): JsonResponse
    {
        $gym = $request->user()->currentGym;

        if (! $gym) {
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

        if (! $gym) {
            return response()->json(['error' => 'Kein Gym ausgewählt'], 404);
        }

        $success = $gym->updateStandardPaymentMethod(
            $request->input('method'),
            $request->input('enabled')
        );

        if (! $success) {
            return response()->json([
                'error' => 'Unbekannte Zahlungsmethode',
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

        if (! $gym) {
            return response()->json(['error' => 'Kein Gym ausgewählt'], 404);
        }

        if (! $gym->hasMollieConfigured()) {
            return response()->json([
                'isActive' => false,
                'message' => 'Mollie nicht konfiguriert',
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
     * Remove Mollie configuration
     */
    public function removeMollieConfig(Request $request): JsonResponse
    {
        $gym = $request->user()->currentGym;

        if (! $gym) {
            return response()->json(['error' => 'Kein Gym ausgewählt'], 404);
        }

        $config = $gym->mollie_config;

        MollieService::deleteWebhookIfAny($config, $gym->id);

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

        if (! $gym) {
            return response()->json(['error' => 'Kein Gym ausgewählt'], 404);
        }

        $standardMethods = $gym->getStandardPaymentMethods();
        $mollieMethods = $gym->getMolliePaymentMethods();
        $enabledMethods = $gym->getEnabledPaymentMethods();

        return response()->json([
            'overview' => [
                'total_methods' => count($standardMethods) + count($mollieMethods),
                'enabled_methods' => count($enabledMethods),
                'standard_methods_count' => count(array_filter($standardMethods, fn ($m) => $m['enabled'])),
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

    /**
     * Execution date settings of the current gym, one entry per enabled
     * payment method.
     */
    public function executionSettings(Request $request): JsonResponse
    {
        $gym = $request->user()->currentGym;

        if (! $gym) {
            return response()->json(['error' => 'Kein Gym ausgewählt'], 404);
        }

        return response()->json($this->executionSettingsPayload($gym));
    }

    /**
     * Store or reset the execution date offsets of a single payment method.
     * Passing a null offset resets the method back to the system default.
     */
    public function updateExecutionSettings(UpdatePaymentExecutionSettingsRequest $request): JsonResponse
    {
        $gym = $request->user()->currentGym;

        if (! $gym) {
            return response()->json(['error' => 'Kein Gym ausgewählt'], 404);
        }

        $methodKey = $request->validated('method');

        // Only methods the gym actually offers may be configured — this keeps a
        // crafted request from writing arbitrary keys into the settings column.
        if (! $this->isConfigurableMethod($gym, $methodKey)) {
            return response()->json(['error' => 'Unbekannte Zahlungsmethode'], 400);
        }

        $initial = $request->validated('initial');
        $recurring = $request->validated('recurring');

        if ($initial === null && $recurring === null) {
            $gym->resetPaymentExecutionOffsets($methodKey);
        } else {
            $defaults = PaymentMethod::getDefaultExecutionOffsets($methodKey);

            $gym->setPaymentExecutionOffsets(
                $methodKey,
                (int) ($initial ?? $defaults['initial']),
                (int) ($recurring ?? $defaults['recurring'])
            );
        }

        return response()->json(array_merge(
            ['message' => 'Ausführungsdaten erfolgreich aktualisiert'],
            $this->executionSettingsPayload($gym)
        ));
    }

    /**
     * Reset every payment method of the current gym to the system defaults.
     */
    public function resetExecutionSettings(Request $request): JsonResponse
    {
        $gym = $request->user()->currentGym;

        if (! $gym) {
            return response()->json(['error' => 'Kein Gym ausgewählt'], 404);
        }

        $gym->resetAllPaymentExecutionOffsets();

        return response()->json(array_merge(
            ['message' => 'Alle Ausführungsdaten auf Standard zurückgesetzt'],
            $this->executionSettingsPayload($gym)
        ));
    }

    /**
     * Only enabled payment methods are configurable; disabled ones are shown
     * read-only in the UI and must be activated first.
     */
    private function isConfigurableMethod($gym, string $methodKey): bool
    {
        foreach ($gym->getEnabledPaymentMethods() as $method) {
            if ($method['key'] === $methodKey) {
                return true;
            }
        }

        return false;
    }

    /**
     * Shared response shape for reading and writing execution settings.
     */
    private function executionSettingsPayload($gym): array
    {
        return [
            'methods' => $gym->getPaymentExecutionSettingsOverview(),
            'inactive_methods' => array_values(array_map(
                fn ($method) => ['key' => $method['key'], 'name' => $method['name']],
                array_filter(
                    $gym->getStandardPaymentMethods(),
                    fn ($method) => ! $method['enabled']
                )
            )),
            'limits' => [
                'min' => PaymentMethod::MIN_EXECUTION_OFFSET,
                'max' => PaymentMethod::MAX_EXECUTION_OFFSET,
            ],
        ];
    }
}
