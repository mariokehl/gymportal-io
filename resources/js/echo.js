import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Pusher für Reverb verfügbar machen
window.Pusher = Pusher;

// Laravel Reverb Konfiguration
window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    // Wichtig für Reverb: Cluster und Host Override
    cluster: '', // Leerer Cluster für lokale Verbindung
    disableStats: true,
});
