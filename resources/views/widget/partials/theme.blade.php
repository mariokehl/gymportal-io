{{-- JSON-Konfiguration für Widget --}}
@php
    // Helper function für Farbhelligkeit
    if (!function_exists('adjustBrightness')) {
        function adjustBrightness($hex, $percent) {
            $hex = str_replace('#', '', $hex);

            if (strlen($hex) == 3) {
                $hex = str_repeat(substr($hex, 0, 1), 2) . str_repeat(substr($hex, 1, 1), 2) . str_repeat(substr($hex, 2, 1), 2);
            }

            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));

            $r = max(0, min(255, $r + ($r * $percent / 100)));
            $g = max(0, min(255, $g + ($g * $percent / 100)));
            $b = max(0, min(255, $b + ($b * $percent / 100)));

            return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT) . str_pad(dechex($g), 2, '0', STR_PAD_LEFT) . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
        }
    }

    $widgetSettings = $gymData['widget_settings'] ?? [];
    $colors = $widgetSettings['colors'] ?? [];
    $features = $widgetSettings['features'] ?? [];

    // Farben mit Fallbacks
    $primaryColor = $colors['primary'] ?? '#3b82f6';
    $secondaryColor = $colors['secondary'] ?? '#f8fafc';
    $textColor = $colors['text'] ?? '#1f2937';

    // Abgeleitete Farben berechnen
    $primaryHover = adjustBrightness($primaryColor, -15);
    $primaryLight = adjustBrightness($primaryColor, 40);
    $borderColor = adjustBrightness($textColor, 75);
@endphp

<script type="application/json" id="gymportal-widget-theme">
@json([
    'primaryColor' => $primaryColor,
    'secondaryColor' => $secondaryColor,
    'textColor' => $textColor
])
</script>

