// WebSocket with Ably for Laravel Cloud
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Enable Pusher logging for debugging
// Pusher.logToConsole = true;

// Setup Echo with Ably broadcaster (using Pusher protocol)
if (import.meta.env.VITE_ABLY_KEY) {
  try {
    window.Pusher = Pusher;

    window.Echo = new Echo({
      broadcaster: 'pusher',
      key: import.meta.env.VITE_ABLY_KEY,
      wsHost: 'realtime-pusher.ably.io',
      wsPort: 443,
      wssPort: 443,
      forceTLS: true,
      encrypted: true,
      disableStats: true,
      enabledTransports: ['ws', 'wss'],
      cluster: 'eu',
      // Authentication for private channels
      authEndpoint: '/broadcasting/auth',
      auth: {
        headers: {
          'X-CSRF-TOKEN': window.Laravel.csrfToken || '',
          'Accept': 'application/json',
        },
      },
      // Ensure credentials (cookies) are sent with auth requests
      authorizer: (channel, options) => {
        return {
          authorize: (socketId, callback) => {
            fetch(options.authEndpoint, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': options.auth.headers['X-CSRF-TOKEN'],
                'Accept': 'application/json',
              },
              credentials: 'same-origin',
              body: new URLSearchParams({
                socket_id: socketId,
                channel_name: channel.name,
              }),
            })
              .then(response => {
                if (!response.ok) {
                  console.error('Authorization failed:', response.status, response.statusText);
                  return response.text().then(text => {
                    console.error('Response body:', text);
                    throw new Error(`Authorization failed: ${response.status}`);
                  });
                }
                return response.json();
              })
              .then(data => {
                callback(null, data);
              })
              .catch(error => {
                console.error('Authorization error:', error);
                callback(error, null);
              });
          }
        };
      },
    });

    console.log('Echo initialized with Ably broadcaster (Pusher protocol)');
  } catch (error) {
    console.error('Failed to initialize Echo with Ably:', error);
  }
} else {
  console.warn('VITE_ABLY_KEY not configured - WebSocket disabled');
}
