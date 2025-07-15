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
          <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
            {{ unreadCount }} ungelesen
          </span>
        </div>

        <button
          v-if="unreadCount > 0"
          @click="markAllAsRead"
          class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors"
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
            v-for="(recipient, index) in notifications.data"
            :key="recipient.id"
            class="p-6 hover:bg-gray-50 cursor-pointer transition-colors"
            :class="[
              !recipient.is_read ? 'bg-blue-50 border-l-4 border-blue-500' : '',
              index !== notifications.data.length - 1 ? 'border-b border-gray-200' : ''
            ]"
            @click="handleNotificationClick(recipient)"
          >
            <!-- Debug Info (temporär) -->
            <div class="text-xs text-gray-400 mb-2 font-mono">
              DEBUG: Type: {{ recipient.notification?.type || 'undefined' }} |
              ID: {{ recipient.id }} |
              Content: {{ recipient.notification?.content || 'undefined' }}
            </div>

            <div class="flex items-start space-x-4">
              <!-- Status Indicator -->
              <div class="flex-shrink-0 mt-1">
                <div
                  class="w-3 h-3 rounded-full"
                  :class="recipient.is_read ? 'bg-gray-300' : 'bg-blue-500'"
                ></div>
              </div>

              <!-- Notification Content -->
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between">
                  <h4 class="text-lg font-medium text-gray-900">
                    {{ recipient.notification.title }}
                  </h4>
                  <span class="text-sm text-gray-500">
                    {{ formatDate(recipient.created_at) }}
                  </span>
                </div>

                <p class="text-gray-600 mt-1">
                  {{ recipient.notification.content }}
                </p>

                <!-- Type Badge -->
                <div class="mt-3">
                  <span
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                    :class="getTypeBadgeClass(recipient.notification.type)"
                  >
                    {{ getTypeText(recipient.notification.type) }}
                  </span>
                </div>
              </div>

              <!-- Actions -->
              <div class="flex-shrink-0">
                <button
                  v-if="!recipient.is_read"
                  @click.stop="markAsRead(recipient)"
                  class="text-blue-600 hover:text-blue-800 text-sm font-medium"
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
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import axios from 'axios'

const props = defineProps({
  notifications: {
    type: Object,
    required: true
  }
})

const unreadCount = computed(() => {
  return props.notifications.data.filter(recipient => !recipient.is_read).length
})

const markAsRead = async (recipient) => {
  try {
    await axios.post(route('v1.notifications.mark-read', { recipient: recipient.id }))
    recipient.is_read = true
    recipient.read_at = new Date().toISOString()
  } catch (error) {
    console.error('Fehler beim Markieren als gelesen:', error)
  }
}

const markAllAsRead = async () => {
  try {
    await axios.post(route('v1.notifications.mark-all-read'))
    props.notifications.data.forEach(recipient => {
      recipient.is_read = true
      recipient.read_at = new Date().toISOString()
    })
  } catch (error) {
    console.error('Fehler beim Markieren als gelesen:', error)
  }
}

const handleNotificationClick = async (recipient) => {
  try {
    // Markiere als gelesen
    if (!recipient.is_read) {
      await markAsRead(recipient)
    }

    // Navigiere zum entsprechenden Link
    const link = getNotificationLink(recipient.notification)

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
    switch (notification.type) {
      case 'member_registered':
        const memberMatch = notification.content.match(/ID #(\d+)/)

        if (memberMatch && route().has('members.show')) {
          const memberRoute = route('members.show', memberMatch[1])
          return memberRoute
        }
        if (route().has('members.index')) {
          const memberIndexRoute = route('members.index')
          return memberIndexRoute
        }
        break

      case 'contract_expiring':
        if (route().has('contracts.index')) {
          const contractRoute = route('contracts.index')
          return contractRoute
        }
        break

      case 'payment_reminder':
        const paymentMatch = notification.content.match(/ID #(\d+)/)

        if (paymentMatch && route().has('finances.show')) {
          const financeShowRoute = route('finances.show', paymentMatch[1])
          return financeShowRoute
        }
        if (route().has('finances.index')) {
          const financeIndexRoute = route('finances.index')
          return financeIndexRoute
        }
        break

      default:
        console.log('Using default notifications route for type:', notification.type)
    }

    // Fallback zur Notifications-Seite
    const fallbackRoute = route('notifications.index')
    return fallbackRoute

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
    'payment_reminder': 'Zahlungserinnerung',
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
    'payment_reminder': 'bg-red-100 text-red-800',
    'announcement': 'bg-blue-100 text-blue-800',
    'promotion': 'bg-purple-100 text-purple-800',
    'system': 'bg-gray-100 text-gray-800',
    'reminder': 'bg-orange-100 text-orange-800'
  }
  return classes[type] || 'bg-gray-100 text-gray-800'
}

const formatDate = (dateString) => {
  const date = new Date(dateString)
  const now = new Date()
  const diffTime = Math.abs(now - date)
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))

  if (diffDays === 1) {
    return 'Heute'
  } else if (diffDays === 2) {
    return 'Gestern'
  } else if (diffDays <= 7) {
    return `Vor ${diffDays - 1} Tagen`
  } else {
    return date.toLocaleDateString('de-DE', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    })
  }
}
</script>
