<?php

namespace App\Services;

use App\Models\Member;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PKPass\PKPassException;

class WalletPassService
{
    public function generateApplePass(Member $member): string
    {
        $gym = $member->gym;
        $qrData = app(GuestService::class)->generateStaticQrData($member);

        $certBase64 = config('services.apple_wallet.certificate_base64');
        $certPassword = config('services.apple_wallet.certificate_password');
        $passTypeId = config('services.apple_wallet.pass_type_identifier');
        $teamId = config('services.apple_wallet.team_identifier');

        if (!$certBase64) {
            throw new \RuntimeException('Apple Wallet certificate not configured');
        }

        $pass = new \PKPass\PKPass();
        $pass->setCertificateString(base64_decode($certBase64));
        $pass->setCertificatePassword($certPassword);

        $passData = [
            'formatVersion' => 1,
            'passTypeIdentifier' => $passTypeId,
            'serialNumber' => 'guest-' . $member->id . '-' . time(),
            'teamIdentifier' => $teamId,
            'organizationName' => $gym->getDisplayName(),
            'description' => 'Gäste-Zugang ' . $gym->getDisplayName(),
            'foregroundColor' => $this->hexToRgb($gym->text_color ?? '#1f2937'),
            'backgroundColor' => $this->hexToRgb($gym->background_color ?? '#ffffff'),
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
            $logoContent = Storage::disk('public')->get($gym->logo_path);
            if ($logoContent) {
                $pass->addFileContent($logoContent, 'logo.png');
                $pass->addFileContent($logoContent, 'icon.png');
            }
        }

        try {
            $pkpass = $pass->create();
        } catch (PKPassException $e) {
            throw new \RuntimeException('Failed to generate Apple Wallet pass: ' . $e->getMessage());
        }

        return $pkpass;
    }

    public function generateGoogleWalletUrl(Member $member): string
    {
        $gym = $member->gym;
        $qrData = app(GuestService::class)->generateStaticQrData($member);

        $issuerId = config('services.google_wallet.issuer_id');
        $serviceAccountBase64 = config('services.google_wallet.service_account_base64');

        if (!$issuerId || !$serviceAccountBase64) {
            throw new \RuntimeException('Google Wallet not configured');
        }

        $serviceAccount = json_decode(base64_decode($serviceAccountBase64), true);

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
                        'hexBackgroundColor' => $gym->background_color ?? '#ffffff',
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
                        'textModulesData' => [
                            [
                                'id' => 'gym',
                                'header' => 'Studio',
                                'body' => $gym->getDisplayName(),
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
