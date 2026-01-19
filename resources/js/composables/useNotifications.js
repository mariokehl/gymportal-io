import { onMounted, onUnmounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Composable for handling real-time notifications via WebSocket
 *
 * Usage example:
 * ```
 * import { useNotifications } from '@/composables/useNotifications';
 *
 * const { notifications, unreadCount } = useNotifications();
 * ```
 */
export function useNotifications() {
    const page = usePage();
    const notifications = ref([]);
    const unreadCount = ref(0);

    const handleNewNotification = (data) => {
        console.log('New notification received:', data);

        // Add notification to the list
        notifications.value.unshift(data);
        unreadCount.value++;

        // Optional: Show browser notification
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(data.title || 'Neue Benachrichtigung', {
                body: data.message,
                icon: '/favicon.ico',
            });
        }
    };

    const requestNotificationPermission = async () => {
        if ('Notification' in window && Notification.permission === 'default') {
            await Notification.requestPermission();
        }
    };

    const markAsRead = (notificationId) => {
        const notification = notifications.value.find(n => n.id === notificationId);
        if (notification && !notification.read_at) {
            notification.read_at = new Date().toISOString();
            unreadCount.value = Math.max(0, unreadCount.value - 1);
        }
    };

    const markAllAsRead = () => {
        notifications.value.forEach(n => {
            if (!n.read_at) {
                n.read_at = new Date().toISOString();
            }
        });
        unreadCount.value = 0;
    };

    onMounted(() => {
        // Request notification permission
        requestNotificationPermission();

        // Subscribe to gym-specific notifications
        if (window.Echo && page.props.auth?.user) {
            const gymId = page.props.auth.user.current_gym?.id;

            if (!gymId) {
                console.warn('No current gym selected, skipping notification channel subscription');
                return;
            }

            console.log('Echo initialized, subscribing to gym notification channel...', { gymId });

            // Listen to gym-specific notifications
            const gymChannel = window.Echo.private(`gym.${gymId}`);

            // Listen for member registered notifications
            gymChannel.listen('.member.registered', (data) => {
                console.log('Received member.registered event:', data);
                handleNewNotification(data);
            });

            gymChannel.error((error) => {
                console.error('Error on gym notification channel:', error);
            });

            console.log(`Subscribed to gym.${gymId} channel for notifications`);
        } else {
            console.warn('Echo is not initialized or user is not authenticated', {
                hasEcho: !!window.Echo,
                hasUser: !!page.props.auth?.user,
                user: page.props.auth?.user
            });
        }
    });

    onUnmounted(() => {
        // Clean up channel subscription
        if (window.Echo && page.props.auth?.user) {
            const gymId = page.props.auth.user.current_gym?.id;
            if (gymId) {
                window.Echo.leave(`private-gym.${gymId}`);
            }
        }
    });

    return {
        notifications,
        unreadCount,
        markAsRead,
        markAllAsRead,
    };
}
