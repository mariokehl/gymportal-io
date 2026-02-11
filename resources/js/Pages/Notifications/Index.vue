<template>
  <AppLayout title="Benachrichtigungen">
    <template #header>
      Benachrichtigungen
    </template>

    <div class="max-w-4xl mx-auto">
      <!-- Header Actions -->
      <div class="flex justify-between items-center mb-6">
        <div class="flex items-center space-x-4">
          <h2 class="text-2xl font-bold text-gray-900">Alle Benachrichtigungen</h2>
          <span class="bg-indigo-100 text-indigo-800 px-3 py-1 rounded-full text-sm font-medium">
            {{ unreadCount }} ungelesen
          </span>
        </div>

        <button
          v-if="unreadCount > 0"
          @click="markAllAsRead"
          class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors"
        >
          Alle als gelesen markieren
        </button>
      </div>

      <!-- Notifications List -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div v-if="notifications.data.length === 0" class="p-8 text-center">
          <Bell class="w-16 h-16 text-gray-400 mx-auto mb-4" />
          <h3 class="text-lg font-medium text-gray-900 mb-2">Keine Benachrichtigungen</h3>
          <p class="text-gray-500">Sie haben noch keine Benachrichtigungen erhalten.</p>
        </div>

        <div v-else>
          <div
            v-for="(notification, index) in notifications.data"
            :key="notification.id"
            class="p-6 hover:bg-gray-50 cursor-pointer transition-colors"
            :class="[
              !notification.read_at ? 'bg-indigo-50 border-l-4 border-indigo-500' : '',
              index !== notifications.data.length - 1 ? 'border-b border-gray-200' : ''
            ]"
            @click="handleNotificationClick(notification)"
          >
            <div class="flex items-start space-x-4">
              <!-- Status Indicator -->
              <div class="flex-shrink-0 mt-1">
                <div
                  class="w-3 h-3 rounded-full"
                  :class="notification.read_at ? 'bg-gray-300' : 'bg-indigo-500'"
                ></div>
              </div>

              <!-- Notification Content -->
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between">
                  <h4 class="text-lg font-medium text-gray-900">
                    {{ notification.data.title || 'Benachrichtigung' }}
                  </h4>
                  <span class="text-sm text-gray-500">
                    {{ formatDate(notification.created_at) }}
                  </span>
                </div>

                <p class="text-gray-600 mt-1">
                  {{ notification.data.message || '' }}
                </p>

                <!-- Type Badge -->
                <div class="mt-3">
                  <span
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                    :class="getTypeBadgeClass(notification.data.type)"
                  >
                    {{ getTypeText(notification.data.type) }}
                  </span>
                </div>
              </div>

              <!-- Actions -->
              <div class="flex-shrink-0">
                <button
                  v-if="!notification.read_at"
                  @click.stop="markAsRead(notification)"
                  class="text-indigo-600 hover:text-indigo-800 text-sm font-medium"
                >
                  Als gelesen markieren
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="notifications.links && notifications.links.length > 3" class="mt-6">
        <Pagination
          :links="notifications.links"
          :from="notifications.from"
          :to="notifications.to"
          :total="notifications.total"
          :prev-page-url="notifications.prev_page_url"
          :next-page-url="notifications.next_page_url"
        />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Bell } from 'lucide-vue-next'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import axios from 'axios'
import { formatDateRelative as formatDate } from '@/utils/formatters'

const props = defineProps({
  notifications: {
    type: Object,
    required: true
  }
})

const unreadCount = computed(() => {
  return props.notifications.data.filter(notification => !notification.read_at).length
})

const markAsRead = async (notification) => {
  try {
    await axios.post(route('v1.notifications.mark-read', { notification: notification.id }))
    notification.read_at = new Date().toISOString()
  } catch (error) {
    console.error('Fehler beim Markieren als gelesen:', error)
  }
}

const markAllAsRead = async () => {
  try {
    await axios.post(route('v1.notifications.mark-all-read'))
    props.notifications.data.forEach(notification => {
      notification.read_at = new Date().toISOString()
    })
  } catch (error) {
    console.error('Fehler beim Markieren als gelesen:', error)
  }
}

const handleNotificationClick = async (notification) => {
  try {
    // Markiere als gelesen
    if (!notification.read_at) {
      await markAsRead(notification)
    }

    // Navigiere zum entsprechenden Link
    const link = getNotificationLink(notification)

    if (link) {
      router.visit(link)
    } else {
      console.warn('No link generated, staying on current page')
    }
  } catch (error) {
    console.error('Navigation failed:', error)
    console.error('Error stack:', error.stack)

    // Fallback zur Notifications-Übersicht
    try {
      router.visit(route('notifications.index'))
    } catch (fallbackError) {
      console.error('Even fallback failed:', fallbackError)
    }
  }
}

const getNotificationLink = (notification) => {
  try {
    const data = notification.data
    const type = data.type

    switch (type) {
      case 'member_registered':
        const memberId = data.member?.id

        if (memberId && route().has('members.show')) {
          return route('members.show', memberId)
        }
        if (route().has('members.index')) {
          return route('members.index')
        }
        break

      case 'contract_expiring':
        if (route().has('contracts.index')) {
          return route('contracts.index')
        }
        break

      case 'contract_withdrawn':
        const withdrawnMemberId = data.member?.id
        if (withdrawnMemberId && route().has('members.show')) {
          return route('members.show', withdrawnMemberId)
        }
        if (route().has('members.index')) {
          return route('members.index')
        }
        break

      case 'payment_failed':
        if (route().has('finances.index')) {
          return route('finances.index')
        }
        break

      default:
        console.log('Using default notifications route for type:', type)
    }

    // Fallback zur Notifications-Seite
    return route('notifications.index')

  } catch (error) {
    console.error('Route generation failed:', error)
    console.error('Error details:', error.message, error.stack)
    return null
  }
}

const getTypeText = (type) => {
  const types = {
    'member_registered': 'Neues Mitglied',
    'contract_expiring': 'Vertragsablauf',
    'contract_withdrawn': 'Widerrufsrecht',
    'payment_failed': 'Mahnung',
    'announcement': 'Ankündigung',
    'promotion': 'Aktion',
    'system': 'System',
    'reminder': 'Erinnerung'
  }
  return types[type] || type
}

const getTypeBadgeClass = (type) => {
  const classes = {
    'member_registered': 'bg-green-100 text-green-800',
    'contract_expiring': 'bg-yellow-100 text-yellow-800',
    'contract_withdrawn': 'bg-purple-100 text-purple-800',
    'payment_failed': 'bg-red-100 text-red-800',
    'announcement': 'bg-indigo-100 text-indigo-800',
    'promotion': 'bg-purple-100 text-purple-800',
    'system': 'bg-gray-100 text-gray-800',
    'reminder': 'bg-orange-100 text-orange-800'
  }
  return classes[type] || 'bg-gray-100 text-gray-800'
}
</script>
