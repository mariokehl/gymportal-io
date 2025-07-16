<template>
  <div class="relative" ref="popupContainer">
    <!-- Notification Bell -->
    <button
      @click="togglePopup"
      class="relative p-2 rounded-full hover:bg-gray-100 transition-colors"
      :class="{ 'bg-gray-100': isOpen }"
    >
      <Bell class="w-5 h-5 text-gray-500" />
      <span
        v-if="unreadCount > 0"
        class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs font-medium"
      >
        {{ unreadCount > 99 ? '99+' : unreadCount }}
      </span>
    </button>

    <!-- Notification Popup -->
    <div
      v-if="isOpen"
      class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-200 z-50"
    >
      <!-- Header -->
      <div class="flex items-center justify-between p-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Benachrichtigungen</h3>
        <button
          v-if="unreadCount > 0"
          @click="markAllAsRead"
          class="text-sm text-blue-600 hover:text-blue-800"
        >
          Alle als gelesen markieren
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="p-4 text-center">
        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600 mx-auto"></div>
        <p class="mt-2 text-sm text-gray-500">Lade Benachrichtigungen...</p>
      </div>

      <!-- Notifications List -->
      <div v-else-if="notifications.length > 0" class="max-h-96 overflow-y-auto">
        <div
          v-for="notification in notifications"
          :key="notification.id"
          class="p-4 border-b border-gray-100 hover:bg-gray-50 cursor-pointer transition-colors"
          @click="handleNotificationClick(notification)"
          :class="{ 'bg-blue-50': !notification.is_read }"
        >
          <div class="flex items-start space-x-3">
            <div class="flex-shrink-0">
              <div
                class="w-2 h-2 rounded-full mt-2"
                :class="notification.is_read ? 'bg-gray-300' : 'bg-blue-500'"
              ></div>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-900">
                {{ notification.title }}
              </p>
              <p class="text-sm text-gray-600 mt-1">
                {{ notification.content }}
              </p>
              <p class="text-xs text-gray-500 mt-2">
                {{ notification.created_at }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="p-8 text-center">
        <Bell class="w-12 h-12 text-gray-400 mx-auto mb-4" />
        <p class="text-gray-500">Keine ungelesenen Benachrichtigungen</p>
      </div>

      <!-- Footer -->
      <div class="p-4 border-t border-gray-200">
        <Link
          :href="route('notifications.index')"
          class="block w-full text-center text-sm text-blue-600 hover:text-blue-800"
          @click="closePopup"
        >
          Alle Benachrichtigungen anzeigen
        </Link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { Bell } from 'lucide-vue-next'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'

const isOpen = ref(false)
const notifications = ref([])
const loading = ref(false)
const popupContainer = ref(null)

const unreadCount = computed(() => {
  return notifications.value.filter(n => !n.is_read).length
})

// WebSocket connection
let echo = null

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
  try {
    const response = await axios.get(route('v1.notifications.unread'))
    notifications.value = response.data
  } catch (error) {
    console.error('Fehler beim Laden der Benachrichtigungen:', error)
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
  } catch (error) {
    console.error('Fehler beim Markieren als gelesen:', error)
  }
}

const handleNotificationClick = async (notification) => {
  // Markiere als gelesen
  if (!notification.is_read) {
    try {
      await axios.post(route('v1.notifications.mark-read', { recipient: notification.id }))
      notification.is_read = true
    } catch (error) {
      console.error('Fehler beim Markieren als gelesen:', error)
    }
  }

  // Navigiere zum Link
  if (notification.link) {
    router.visit(notification.link)
  }

  closePopup()
}

// Handle clicks outside popup
const handleClickOutside = (event) => {
  if (popupContainer.value && !popupContainer.value.contains(event.target)) {
    closePopup()
  }
}

onMounted(() => {
  // Setup WebSocket connection mit Error Handling
  if (window.Echo && window.Laravel && window.Laravel.user) {
    try {
      echo = window.Echo.private(`notifications.${window.Laravel.user.id}`)
        .listen('NewNotificationEvent', (e) => {
          notifications.value.unshift(e.notification)

          // Limit to 10 notifications in popup
          if (notifications.value.length > 10) {
            notifications.value = notifications.value.slice(0, 10)
          }
        })
    } catch (error) {
      console.error('WebSocket connection failed:', error)
    }
  } else {
    console.warn('Echo or Laravel config not available - WebSocket features disabled')
    console.log('Echo available:', !!window.Echo)
    console.log('Laravel config:', window.Laravel)
  }

  // Add click outside listener
  document.addEventListener('mousedown', handleClickOutside)

  // Load initial notifications
  loadNotifications()
})

onUnmounted(() => {
  if (echo) {
    echo.stopListening('NewNotificationEvent')
  }

  document.removeEventListener('mousedown', handleClickOutside)
})

// Expose unreadCount for parent component
defineExpose({
  unreadCount
})
</script>
