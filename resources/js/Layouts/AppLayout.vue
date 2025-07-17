<template>
  <div class="flex h-screen bg-gray-100">
    <Head :title="pageTitle || 'gymportal.io'" />

    <!-- Sidebar -->
    <div class="w-64 bg-white shadow-md flex flex-col">
      <div class="p-4 border-b border-gray-200">
        <h2 class="text-xl font-bold text-indigo-600">gymportal.io</h2>
        <p class="text-sm text-gray-500">Mitgliederverwaltung</p>
      </div>

      <nav class="mt-6 h-full">
        <SidebarItem
          :icon="BarChart"
          label="Dashboard"
          :active="route().current('dashboard')"
          :href="route('dashboard')"
        />
        <SidebarItem
          :icon="Users"
          label="Mitglieder"
          :active="route().current('members.index')"
          :href="route('members.index')"
          :disabled="!canAccessPremiumFeatures"
        />
        <SidebarItem
          :icon="FilePlus"
          label="Verträge"
          :active="route().current('contracts.index')"
          :href="route('contracts.index')"
          :disabled="!canAccessPremiumFeatures"
        />
        <SidebarItem
          :icon="DollarSign"
          label="Finanzen"
          :active="route().current('finances.index')"
          :href="route('finances.index')"
          :disabled="!canAccessPremiumFeatures"
        />

        <SidebarItem
          :icon="Bell"
          label="Benachrichtigungen"
          :active="route().current('notifications.index')"
          :href="route('notifications.index')"
          :disabled="!canAccessPremiumFeatures"
        />
        <SidebarItem
          :icon="Settings"
          label="Einstellungen"
          :active="route().current('settings.index')"
          :href="route('settings.index')"
        />
        <SidebarItem
          :icon="LogOut"
          label="Abmelden"
          :active="false"
          @click="handleLogout"
        />
      </nav>

      <OrganizationSwitcher />

      <!-- Trial/Subscription Status -->
      <div v-if="subscriptionStatus" class="p-4 border-t border-gray-200">
        <div v-if="subscriptionStatus.trial.is_active" class="bg-indigo-50 border border-indigo-200 rounded-lg p-3">
          <div class="flex items-center">
            <svg class="h-4 w-4 text-indigo-400 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
              <p class="text-xs font-medium text-indigo-800">Testphase</p>
              <p class="text-xs text-indigo-600">{{ subscriptionStatus.trial.days_left }} Tage verbleibend</p>
            </div>
          </div>
        </div>

        <div v-else-if="subscriptionStatus.subscription.is_active" class="bg-green-50 border border-green-200 rounded-lg p-3">
          <div class="flex items-center">
            <svg class="h-4 w-4 text-green-400 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <div>
              <p class="text-xs font-medium text-green-800">SaaS Hosted</p>
              <p class="text-xs text-green-600">Aktiv</p>
            </div>
          </div>
        </div>

        <div v-else class="bg-red-50 border border-red-200 rounded-lg p-3">
          <div class="flex items-center">
            <svg class="h-4 w-4 text-red-400 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.084 16.5c-.77.833.192 2.5 1.732 2.5z" />
            </svg>
            <div>
              <p class="text-xs font-medium text-red-800">Testphase abgelaufen</p>
              <Link :href="route('billing.index')" class="text-xs text-red-600 underline">
                Jetzt upgraden
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 overflow-y-auto">
      <!-- Header -->
      <header class="bg-white shadow-sm p-4 flex justify-between items-center sticky top-0 z-10">
        <h1 class="text-xl font-semibold">
          <slot name="header">Dashboard</slot>
        </h1>

        <div class="flex items-center space-x-4">
          <!-- Notification Popup Component -->
          <NotificationPopup ref="notificationPopup" />

          <!-- User Profile -->
          <div class="flex items-center">
            <div class="w-8 h-8 bg-indigo-500 rounded-full text-white flex items-center justify-center text-xs font-semibold">
              {{ userInitials }}
            </div>
            <span class="ml-2 text-sm font-medium">{{ user.first_name }} {{ user.last_name }}</span>
          </div>
        </div>
      </header>

      <!-- Page Content -->
      <main class="p-6">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router, usePage, Head, Link } from '@inertiajs/vue3'
import {
  Users, Bell, DollarSign,
  BarChart, Settings, LogOut,
  Plus, ChevronDown, FilePlus
} from 'lucide-vue-next'
import SidebarItem from '@/Components/SidebarItem.vue'
import OrganizationSwitcher from '@/Components/OrganizationSwitcher.vue'
import NotificationPopup from '@/Components/NotificationPopup.vue'

// Shared data
const page = usePage()
const notificationPopup = ref(null)

// Props
const props = defineProps({
  title: {
    type: String,
    default: null
  }
})

// Computed properties
const pageTitle = computed(() => {
  return props.title ? `${props.title}` : 'Unbekannt'
})

const user = computed(() => page.props.auth.user)

const userInitials = computed(() => {
  const first = page.props.auth.user.first_name?.charAt(0) || ''
  const last = page.props.auth.user.last_name?.charAt(0) || ''
  return (first + last).toUpperCase()
})

const subscriptionStatus = computed(() => {
  return page.props.subscription_status || null
})

const canAccessPremiumFeatures = computed(() => {
  if (!subscriptionStatus.value) return true

  return subscriptionStatus.value.trial.is_active ||
         subscriptionStatus.value.subscription.is_active
})

// Methods
const handleLogout = () => {
  if (confirm('Möchten Sie sich wirklich abmelden?')) {
    router.post('/logout')
  }
}

// Make user data available globally for WebSocket
onMounted(() => {
  if (typeof window !== 'undefined') {
    window.Laravel = window.Laravel || {}
    window.Laravel.user = user.value
  }
})
</script>
