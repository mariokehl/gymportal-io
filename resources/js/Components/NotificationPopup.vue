<template>
  <div class="relative" ref="popupContainer">
    <!-- Notification Bell -->
    <button
      ref="trigger"
      type="button"
      @click="togglePopup"
      class="relative p-2 rounded-full hover:bg-gray-100 transition-colors"
      :class="{ 'bg-gray-100': isOpen }"
      :aria-label="bellLabel"
      :aria-expanded="isOpen"
      :aria-controls="panelId"
    >
      <Bell class="w-5 h-5 text-ink-muted" aria-hidden="true" />
      <span
        v-if="unreadCount > 0"
        aria-hidden="true"
        class="absolute -top-1 -right-1 bg-danger-solid text-white rounded-full w-5 h-5 flex items-center justify-center text-xs font-medium"
      >
        {{ unreadCount > 99 ? '99+' : unreadCount }}
      </span>
    </button>

    <!-- Notification Popup -->
    <div
      v-if="isOpen"
      :id="panelId"
      role="region"
      aria-labelledby="notification-popup-title"
      class="absolute right-0 mt-2 w-80 max-w-[calc(100vw-2rem)] bg-surface rounded-lg shadow-lg border border-line z-50"
    >
      <!-- Header -->
      <div class="flex items-center justify-between gap-3 p-4 border-b border-line">
        <h2 id="notification-popup-title" class="text-lg font-semibold text-ink">Benachrichtigungen</h2>
        <button
          v-if="unreadCount > 0"
          type="button"
          @click="markAllAsRead"
          class="text-sm text-right text-brand hover:text-brand-strong"
        >
          Alle als gelesen markieren
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="p-4 text-center" role="status">
        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-brand mx-auto" aria-hidden="true"></div>
        <p class="mt-2 text-sm text-ink-muted">Benachrichtigungen werden geladen …</p>
      </div>

      <!-- Error State -->
      <div v-else-if="loadError" class="p-6 text-center" role="alert">
        <p class="text-sm text-ink-secondary">Die Benachrichtigungen konnten nicht geladen werden.</p>
        <button
          type="button"
          @click="loadNotifications"
          class="mt-3 text-sm font-medium text-brand hover:text-brand-strong"
        >
          Erneut versuchen
        </button>
      </div>

      <!-- Notifications List -->
      <ul v-else-if="notifications.length > 0" class="max-h-96 overflow-y-auto">
        <li v-for="notification in notifications" :key="notification.id">
          <button
            type="button"
            class="w-full text-left p-4 border-b border-gray-100 hover:bg-surface-muted transition-colors"
            :class="{ 'bg-brand-subtle': !notification.is_read }"
            @click="handleNotificationClick(notification)"
          >
            <span class="flex items-start gap-3">
              <span
                class="flex-shrink-0 w-2 h-2 rounded-full mt-2"
                :class="notification.is_read ? 'bg-line-strong' : 'bg-brand'"
                aria-hidden="true"
              ></span>
              <span class="flex-1 min-w-0">
                <span v-if="!notification.is_read" class="sr-only">Ungelesen: </span>
                <span class="block text-sm font-medium text-ink break-words">
                  {{ notification.title }}
                </span>
                <span v-if="notification.message" class="block text-sm text-ink-secondary mt-1 break-words line-clamp-3">
                  {{ notification.message }}
                </span>
                <span class="block text-xs text-ink-muted mt-2">
                  {{ notification.created_at }}
                </span>
              </span>
            </span>
          </button>
        </li>
      </ul>

      <!-- Empty State -->
      <div v-else class="p-8 text-center">
        <Bell class="w-12 h-12 text-ink-muted mx-auto mb-4" aria-hidden="true" />
        <p class="text-ink-muted">Keine ungelesenen Benachrichtigungen</p>
      </div>

      <!-- Footer -->
      <div class="p-4 border-t border-line">
        <Link
          :href="route('notifications.index')"
          class="block w-full text-center text-sm text-brand hover:text-brand-strong"
          @click="closePopup"
        >
          Alle Benachrichtigungen anzeigen
        </Link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch, useId } from 'vue'
import { Bell } from '@lucide/vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import { useNotifications } from '@/composables/useNotifications'
import { useDismissable } from '@/composables/useDismissable'
import { useToast } from '@/composables/useToast'

const isOpen = ref(false)
const notifications = ref([])
const loading = ref(false)
const loadError = ref(false)
const popupContainer = ref(null)
const trigger = ref(null)
const panelId = `notification-popup-${useId()}`
const toast = useToast()

useDismissable(popupContainer, isOpen, { triggerRef: trigger })

// Use the WebSocket notifications composable
const {
  notifications: realtimeNotifications,
  markAsRead: markRealtimeAsRead,
  markAllAsRead: markAllRealtimeAsRead
} = useNotifications()

const unreadCount = computed(() => {
  return notifications.value.filter(n => !n.is_read).length
})

const bellLabel = computed(() => {
  const count = unreadCount.value
  if (count === 0) return 'Benachrichtigungen'
  return count === 1 ? 'Benachrichtigungen, 1 ungelesen' : `Benachrichtigungen, ${count} ungelesen`
})

const togglePopup = async () => {
  isOpen.value = !isOpen.value

  if (isOpen.value) {
    await loadNotifications()
  }
}

const closePopup = () => {
  isOpen.value = false
}

const loadNotifications = async () => {
  loading.value = true
  loadError.value = false
  try {
    const response = await axios.get(route('v1.notifications.unread'))
    notifications.value = response.data
  } catch {
    loadError.value = true
  } finally {
    loading.value = false
  }
}

const markAllAsRead = async () => {
  try {
    await axios.post(route('v1.notifications.mark-all-read'))
    notifications.value.forEach(notification => {
      notification.is_read = true
    })
    markAllRealtimeAsRead()
  } catch {
    toast.error('Benachrichtigungen konnten nicht als gelesen markiert werden', 'Bitte versuchen Sie es erneut.')
  }
}

const handleNotificationClick = async (notification) => {
  // Mark as read; a failure is not worth blocking the navigation, the entry
  // simply stays unread.
  if (!notification.is_read) {
    try {
      await axios.post(route('v1.notifications.mark-read', { notification: notification.id }))
      notification.is_read = true

      // Mark realtime notification as read if it exists
      if (notification.id) {
        markRealtimeAsRead(notification.id)
      }
    } catch {
      // Intentionally silent, see above.
    }
  }

  // Follow the notification's link
  if (notification.link) {
    router.visit(notification.link)
  }

  closePopup()
}

// Watch for new realtime notifications and merge them
watch(realtimeNotifications, (newNotifications) => {
  if (newNotifications.length > 0) {
    // Get the latest realtime notification
    const latestNotification = newNotifications[0]

    // Check if notification already exists in the list
    const exists = notifications.value.some(n => n.id === latestNotification.id)

    if (!exists) {
      // Transform realtime notification to match API format
      const transformedNotification = {
        id: latestNotification.id || `realtime-${Date.now()}`,
        title: latestNotification.title || 'Neue Benachrichtigung',
        message: latestNotification.message || '',
        is_read: false,
        created_at: latestNotification.created_at || new Date().toISOString(),
        link: latestNotification.data?.link || null,
      }

      notifications.value.unshift(transformedNotification)

      // Limit to 10 notifications in popup
      if (notifications.value.length > 10) {
        notifications.value = notifications.value.slice(0, 10)
      }
    }
  }
}, { deep: true })

onMounted(() => {
  loadNotifications()
})

// Expose unreadCount for parent component
defineExpose({
  unreadCount
})
</script>
