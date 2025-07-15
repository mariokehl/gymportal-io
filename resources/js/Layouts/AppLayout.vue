<template>
  <div class="flex h-screen bg-gray-100">
    <Head :title="pageTitle || 'gymportal.io'" />

    <!-- Sidebar -->
    <div class="w-64 bg-white shadow-md flex flex-col">
      <div class="p-4 border-b border-gray-200">
        <h2 class="text-xl font-bold text-blue-600">gymportal.io</h2>
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
        />
        <SidebarItem
          :icon="FilePlus"
          label="Verträge"
          :active="route().current('contracts.index')"
          :href="route('contracts.index')"
        />
        <SidebarItem
          :icon="DollarSign"
          label="Finanzen"
          :active="route().current('finances.index')"
          :href="route('finances.index')"
        />
        <SidebarItem
          :icon="Bell"
          label="Benachrichtigungen"
          :active="route().current('notifications.index')"
          :href="route('notifications.index')"
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
            <div class="w-8 h-8 bg-blue-500 rounded-full text-white flex items-center justify-center text-xs font-semibold">
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
