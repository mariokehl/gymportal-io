<?php

namespace App\Services\Fraud;

class FraudIdentifierNormalizer
{
    /**
     * Mobilnummer auf internationale Ziffernfolge normalisieren.
     * +49 151 12345678 → 4915112345678
     */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        // Führende 00 → entfernen
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        // Deutsche führende 0 → Ländercode 49
        if (str_starts_with($digits, '0')) {
            $digits = '49' . substr($digits, 1);
        }

        return $digits;
    }

    /**
     * IBAN: Leerzeichen entfernen, Großbuchstaben.
     * DE89 3704 0044 → DE89370400440532013000
     */
    public static function normalizeIban(string $iban): string
    {
        return strtoupper(preg_replace('/\s+/', '', $iban));
    }

    /**
     * Nachnamen für Levenshtein normalisieren (Kleinbuchstaben, keine Sonderzeichen).
     * Müller → muller, von der Heyden → vonderheyden
     */
    public static function normalizeName(string $name): string
    {
        $name = strtolower(trim($name));

        // Umlaute ersetzen
        $name = str_replace(
            ['ä', 'ö', 'ü', 'ß', 'é', 'è', 'ê', 'à', 'â'],
            ['ae', 'oe', 'ue', 'ss', 'e', 'e', 'e', 'a', 'a'],
            $name
        );

        // Alles außer a-z entfernen
        return preg_replace('/[^a-z]/', '', $name);
    }

    /**
     * Adresse normalisieren für Hash-Vergleich.
     * Musterstraße 1A, 34117 Kassel → 34117kasselmusters1a
     */
    public static function normalizeAddress(string $address, string $postalCode, string $city): string
    {
        $address = strtolower($address);

        // Straße/Strasse → str
        $address = preg_replace('/stra(ß|ss)e\b/iu', 'str', $address);
        $address = preg_replace('/\s+/', '', $address);

        $city = preg_replace('/[^a-z0-9]/', '', strtolower($city));

        return "{$postalCode}{$city}{$address}";
    }

    /**
     * Identifier mit App-Salt hashen.
     */
    public static function hash(string $value): string
    {
        return hash('sha256', config('fraud.salt') . $value);
    }
}
