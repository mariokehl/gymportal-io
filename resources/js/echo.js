// WebSocket deaktiviert für Deployment
// import Echo from 'laravel-echo';
// import Pusher from 'pusher-js';

// Echo Setup für lokale Entwicklung
if (import.meta.env.DEV) {
  try {
    const Echo = await import('laravel-echo');
    const Pusher = await import('pusher-js');

    window.Pusher = Pusher.default;

    window.Echo = new Echo.default({
      broadcaster: 'pusher',
      key: import.meta.env.VITE_REVERB_APP_KEY,
      wsHost: import.meta.env.VITE_REVERB_HOST,
      wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
      wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
      forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
      enabledTransports: ['ws', 'wss'],
      cluster: '',
      disableStats: true,
    });
  } catch (error) {
    console.log('Echo setup skipped for production');
  }
} else {
  console.log('WebSocket disabled for production deployment');
}
