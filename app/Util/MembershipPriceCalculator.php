<?php

namespace App\Util;

use InvalidArgumentException;

/**
 * Klasse zur Berechnung von Mitgliedschaftspreisen
 */
class MembershipPriceCalculator
{
    /**
     * Berechnet den Gesamtpreis über die Vertragsdauer
     *
     * @param float $regularPrice Regulärer Preis (monatlich oder jährlich)
     * @param string $billingCycle 'monthly' oder 'yearly'
     * @param int $minimumDurationMonths Mindestlaufzeit in Monaten
     * @param float $activationFee Einmalige Aktivierungsgebühr
     * @param int $customDurationMonths Optionale benutzerdefinierte Laufzeit (falls länger als Mindestlaufzeit)
     * @return array Detaillierte Preisberechnung
     */
    public static function calculateTotalPrice(
        float $regularPrice,
        string $billingCycle,
        int $minimumDurationMonths,
        float $activationFee = 0.0,
        int $customDurationMonths = null
    ): array {
        // Validierung
        if ($regularPrice < 0) {
            throw new InvalidArgumentException('Regulärer Preis darf nicht negativ sein');
        }

        if ($activationFee < 0) {
            throw new InvalidArgumentException('Aktivierungsgebühr darf nicht negativ sein');
        }

        if ($minimumDurationMonths < 0) {
            throw new InvalidArgumentException('Mindestlaufzeit darf nicht negativ sein');
        }

        if (!in_array($billingCycle, ['monthly', 'quarterly', 'yearly'])) {
            throw new InvalidArgumentException('Abrechnungszyklus muss "monthly", "quarterly" oder "yearly" sein');
        }

        // Tatsächliche Vertragsdauer bestimmen
        $actualDurationMonths = $customDurationMonths ?? $minimumDurationMonths;

        if ($actualDurationMonths < $minimumDurationMonths) {
            throw new InvalidArgumentException('Vertragsdauer darf nicht unter der Mindestlaufzeit liegen');
        }

        // Berechnung basierend auf Abrechnungszyklus
        if ($billingCycle === 'monthly') {
            $recurringCosts = self::calculateMonthlyRecurringCosts(
                $regularPrice,
                $actualDurationMonths
            );
        } elseif ($billingCycle === 'quarterly') {
            $recurringCosts = self::calculateQuarterlyRecurringCosts(
                $regularPrice,
                $actualDurationMonths
            );
        } else {
            $recurringCosts = self::calculateYearlyRecurringCosts(
                $regularPrice,
                $actualDurationMonths
            );
        }

        // Gesamtpreis berechnen
        $totalPrice = $activationFee + $recurringCosts['total'];

        // Durchschnittlicher Monatspreis
        $averageMonthlyPrice = $actualDurationMonths > 0 ? $totalPrice / $actualDurationMonths : 0;

        return [
            'activation_fee' => $activationFee,
            'regular_price' => $regularPrice,
            'billing_cycle' => $billingCycle,
            'minimum_duration_months' => $minimumDurationMonths,
            'actual_duration_months' => $actualDurationMonths,
            'recurring_costs' => $recurringCosts,
            'total_price' => round($totalPrice, 2),
            'average_monthly_price' => round($averageMonthlyPrice, 2),
            'savings_vs_monthly' => $billingCycle !== 'monthly' ?
                self::calculateSavingsVsMonthly($regularPrice, $billingCycle, $actualDurationMonths) : 0
        ];
    }

    /**
     * Berechnet wiederkehrende Kosten für monatliche Abrechnung
     */
    private static function calculateMonthlyRecurringCosts(float $monthlyPrice, int $durationMonths): array
    {
        $total = $monthlyPrice * $durationMonths;

        return [
            'type' => 'monthly',
            'unit_price' => $monthlyPrice,
            'number_of_payments' => $durationMonths,
            'total' => round($total, 2)
        ];
    }

    /**
     * Berechnet wiederkehrende Kosten für quartalsweise Abrechnung
     */
    private static function calculateQuarterlyRecurringCosts(float $quarterlyPrice, int $durationMonths): array
    {
        $fullQuarters = floor($durationMonths / 3);
        $remainingMonths = $durationMonths % 3;

        // Annahme: Bei Restmonaten wird der Quartalspreis anteilig berechnet
        $monthlyEquivalent = $quarterlyPrice / 3;

        $fullQuartersCost = $fullQuarters * $quarterlyPrice;
        $remainingMonthsCost = $remainingMonths * $monthlyEquivalent;
        $total = $fullQuartersCost + $remainingMonthsCost;

        return [
            'type' => 'quarterly',
            'unit_price' => $quarterlyPrice,
            'full_quarters' => $fullQuarters,
            'remaining_months' => $remainingMonths,
            'full_quarters_cost' => round($fullQuartersCost, 2),
            'remaining_months_cost' => round($remainingMonthsCost, 2),
            'monthly_equivalent' => round($monthlyEquivalent, 2),
            'total' => round($total, 2)
        ];
    }
    private static function calculateYearlyRecurringCosts(float $yearlyPrice, int $durationMonths): array
    {
        $fullYears = floor($durationMonths / 12);
        $remainingMonths = $durationMonths % 12;

        // Annahme: Bei Restmonaten wird der Jahrespreis anteilig berechnet
        $monthlyEquivalent = $yearlyPrice / 12;

        $fullYearsCost = $fullYears * $yearlyPrice;
        $remainingMonthsCost = $remainingMonths * $monthlyEquivalent;
        $total = $fullYearsCost + $remainingMonthsCost;

        return [
            'type' => 'yearly',
            'unit_price' => $yearlyPrice,
            'full_years' => $fullYears,
            'remaining_months' => $remainingMonths,
            'full_years_cost' => round($fullYearsCost, 2),
            'remaining_months_cost' => round($remainingMonthsCost, 2),
            'monthly_equivalent' => round($monthlyEquivalent, 2),
            'total' => round($total, 2)
        ];
    }

    /**
     * Berechnet Ersparnis gegenüber monatlicher Zahlung
     */
    private static function calculateSavingsVsMonthly(float $price, string $billingCycle, int $durationMonths): float
    {
        if ($billingCycle === 'monthly') {
            return 0;
        }

        // Berechne monatliches Äquivalent des aktuellen Plans
        $monthlyEquivalent = match($billingCycle) {
            'quarterly' => $price / 3,
            'yearly' => $price / 12,
            default => $price
        };

        // Typische Preisaufschläge für kürzere Abrechnungszyklen
        $monthlyPriceMultiplier = match($billingCycle) {
            'quarterly' => 1.05, // 5% Aufschlag für monatliche vs. quartalsweise Zahlung
            'yearly' => 1.15,    // 15% Aufschlag für monatliche vs. jährliche Zahlung
            default => 1.0
        };

        $assumedMonthlyPrice = $monthlyEquivalent * $monthlyPriceMultiplier;
        $totalAssumedMonthlyCost = $assumedMonthlyPrice * $durationMonths;
        $actualTotalCost = $monthlyEquivalent * $durationMonths;

        return round($totalAssumedMonthlyCost - $actualTotalCost, 2);
    }

    /**
     * Erstellt eine formatierte Preisübersicht
     */
    public static function generatePriceSummary(array $calculation): string
    {
        $summary = "=== PREISÜBERSICHT ===" . PHP_EOL;
        $summary .= "Vertragsdauer: {$calculation['actual_duration_months']} Monate" . PHP_EOL;
        $summary .= "Mindestlaufzeit: {$calculation['minimum_duration_months']} Monate" . PHP_EOL;
        $summary .= PHP_EOL;

        $summary .= "KOSTEN:" . PHP_EOL;
        $summary .= "Aktivierungsgebühr: {$calculation['activation_fee']} EUR (einmalig)" . PHP_EOL;

        if ($calculation['billing_cycle'] === 'monthly') {
            $summary .= "Monatsbeitrag: {$calculation['regular_price']} EUR" . PHP_EOL;
            $summary .= "Anzahl Zahlungen: {$calculation['recurring_costs']['number_of_payments']}" . PHP_EOL;
        } elseif ($calculation['billing_cycle'] === 'quarterly') {
            $summary .= "Quartalsbeitrag: {$calculation['regular_price']} EUR" . PHP_EOL;
            $summary .= "Volle Quartale: {$calculation['recurring_costs']['full_quarters']}" . PHP_EOL;
            $summary .= "Restmonate: {$calculation['recurring_costs']['remaining_months']}" . PHP_EOL;
        } else {
            $summary .= "Jahresbeitrag: {$calculation['regular_price']} EUR" . PHP_EOL;
            $summary .= "Volle Jahre: {$calculation['recurring_costs']['full_years']}" . PHP_EOL;
            $summary .= "Restmonate: {$calculation['recurring_costs']['remaining_months']}" . PHP_EOL;
        }

        $summary .= PHP_EOL;
        $summary .= "GESAMT: {$calculation['total_price']} EUR" . PHP_EOL;
        $summary .= "Durchschnittlich pro Monat: {$calculation['average_monthly_price']} EUR" . PHP_EOL;

        if ($calculation['savings_vs_monthly'] > 0) {
            $summary .= "Ersparnis gegenüber monatlicher Zahlung: {$calculation['savings_vs_monthly']} EUR" . PHP_EOL;
        }

        return $summary;
    }
}
