<?php

namespace App\Services;

use App\Models\Member;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WalletPassService
{
    /**
     * Generate an Apple Wallet .pkpass file for the given member.
     *
     * Requires: composer require pkpass/pkpass
     * Config keys in .env:
     *   APPLE_PASS_CERTIFICATE_PATH - Path to .p12 certificate
     *   APPLE_PASS_CERTIFICATE_PASSWORD - Certificate password
     *   APPLE_PASS_TYPE_IDENTIFIER - e.g. pass.io.gymportal.guest
     *   APPLE_PASS_TEAM_IDENTIFIER - Apple Developer Team ID
     */
    public function generateApplePass(Member $member): string
    {
        $gym = $member->gym;
        $qrData = app(GuestService::class)->generateStaticQrData($member);

        $certPath = config('services.apple_wallet.certificate_path');
        $certPassword = config('services.apple_wallet.certificate_password');
        $passTypeId = config('services.apple_wallet.pass_type_identifier');
        $teamId = config('services.apple_wallet.team_identifier');

        if (!$certPath || !file_exists($certPath)) {
            throw new \RuntimeException('Apple Wallet certificate not configured');
        }

        $pass = new \PKPass\PKPass($certPath, $certPassword);

        $passData = [
            'formatVersion' => 1,
            'passTypeIdentifier' => $passTypeId,
            'serialNumber' => 'guest-' . $member->id . '-' . time(),
            'teamIdentifier' => $teamId,
            'organizationName' => $gym->getDisplayName(),
            'description' => 'Gäste-Zugang ' . $gym->getDisplayName(),
            'foregroundColor' => 'rgb(255, 255, 255)',
            'backgroundColor' => $this->hexToRgb($gym->primary_color ?? '#1a1a2e'),
            'generic' => [
                'primaryFields' => [
                    [
                        'key' => 'name',
                        'label' => 'Gast',
                        'value' => $member->first_name . ' ' . $member->last_name,
                    ],
                ],
                'secondaryFields' => [
                    [
                        'key' => 'gym',
                        'label' => 'Studio',
                        'value' => $gym->getDisplayName(),
                    ],
                ],
                'backFields' => [
                    [
                        'key' => 'address',
                        'label' => 'Adresse',
                        'value' => implode(', ', array_filter([
                            $gym->address,
                            $gym->postal_code . ' ' . $gym->city,
                        ])),
                    ],
                    [
                        'key' => 'phone',
                        'label' => 'Telefon',
                        'value' => $gym->phone ?? '-',
                    ],
                    [
                        'key' => 'website',
                        'label' => 'Website',
                        'value' => $gym->website ?? '-',
                    ],
                ],
            ],
            'barcode' => [
                'format' => 'PKBarcodeFormatQR',
                'message' => $qrData['qr_code'],
                'messageEncoding' => 'iso-8859-1',
            ],
            'barcodes' => [
                [
                    'format' => 'PKBarcodeFormatQR',
                    'message' => $qrData['qr_code'],
                    'messageEncoding' => 'iso-8859-1',
                ],
            ],
        ];

        $pass->setData(json_encode($passData));

        // Add logo if available
        if ($gym->logo_path) {
            $logoPath = Storage::disk('s3')->temporaryUrl($gym->logo_path, now()->addMinutes(5));
            $logoContent = file_get_contents($logoPath);
            if ($logoContent) {
                $pass->addFile('icon.png', $logoContent);
                $pass->addFile('logo.png', $logoContent);
            }
        }

        $pkpass = $pass->create(true);

        if (!$pkpass) {
            throw new \RuntimeException('Failed to generate Apple Wallet pass: ' . $pass->getError());
        }

        return $pkpass;
    }

    /**
     * Generate a Google Wallet save URL for the given member.
     *
     * Config keys in .env:
     *   GOOGLE_WALLET_ISSUER_ID - Google Wallet issuer ID
     *   GOOGLE_WALLET_SERVICE_ACCOUNT_KEY_PATH - Path to service account JSON key
     */
    public function generateGoogleWalletUrl(Member $member): string
    {
        $gym = $member->gym;
        $qrData = app(GuestService::class)->generateStaticQrData($member);

        $issuerId = config('services.google_wallet.issuer_id');
        $keyPath = config('services.google_wallet.service_account_key_path');

        if (!$issuerId || !$keyPath || !file_exists($keyPath)) {
            throw new \RuntimeException('Google Wallet not configured');
        }

        $serviceAccount = json_decode(file_get_contents($keyPath), true);

        $objectId = $issuerId . '.guest-' . $member->id;

        $payload = [
            'iss' => $serviceAccount['client_email'],
            'aud' => 'google',
            'origins' => ['https://guests.gymportal.io'],
            'typ' => 'savetowallet',
            'payload' => [
                'genericObjects' => [
                    [
                        'id' => $objectId,
                        'classId' => $issuerId . '.gymportal-guest-pass',
                        'genericType' => 'GENERIC_TYPE_UNSPECIFIED',
                        'hexBackgroundColor' => $gym->primary_color ?? '#1a1a2e',
                        'cardTitle' => [
                            'defaultValue' => [
                                'language' => 'de',
                                'value' => 'Gäste-Zugang',
                            ],
                        ],
                        'header' => [
                            'defaultValue' => [
                                'language' => 'de',
                                'value' => $gym->getDisplayName(),
                            ],
                        ],
                        'subheader' => [
                            'defaultValue' => [
                                'language' => 'de',
                                'value' => $member->first_name . ' ' . $member->last_name,
                            ],
                        ],
                        'barcode' => [
                            'type' => 'QR_CODE',
                            'value' => $qrData['qr_code'],
                        ],
                    ],
                ],
            ],
        ];

        $jwt = JWT::encode($payload, $serviceAccount['private_key'], 'RS256');

        return 'https://pay.google.com/gp/v/save/' . $jwt;
    }

    /**
     * Convert hex color to Apple pass RGB format.
     */
    private function hexToRgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return "rgb($r, $g, $b)";
    }
}
