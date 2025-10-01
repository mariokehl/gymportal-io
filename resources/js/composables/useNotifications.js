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

        // Subscribe to user notifications
        if (window.Echo && page.props.auth?.user) {
            const userId = page.props.auth.user.id;

            console.log('Echo initialized, subscribing to user notification channel...', { userId });

            // Listen to user-specific notifications
            const userChannel = window.Echo.private(`App.Models.User.${userId}`);

            // Listen for Laravel notification events
            userChannel.notification((notification) => {
                console.log('✅ Received user notification via .notification():', notification);
                handleNewNotification(notification);
            });

            // Also listen for broadcast notification events explicitly
            userChannel.listen('.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated', (data) => {
                console.log('✅ Received broadcast notification event:', data);
            });

            // Listen to ALL events for debugging
            userChannel.listenForWhisper('*', (event) => {
                console.log('✅ Received whisper event:', event);
            });

            userChannel.error((error) => {
                console.error('❌ Error on user notification channel:', error);
            });

            console.log(`✅ Subscribed to App.Models.User.${userId} channel for user notifications`);
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
            const userId = page.props.auth.user.id;
            window.Echo.leave(`private-App.Models.User.${userId}`);
        }
    });

    return {
        notifications,
        unreadCount,
        markAsRead,
        markAllAsRead,
    };
}
