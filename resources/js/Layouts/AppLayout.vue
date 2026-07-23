<template>
  <div class="flex h-screen bg-gray-100">
    <Head :title="pageTitle || 'gymportal.io'" />

    <!-- Sidebar (static on large screens) -->
    <div class="hidden lg:flex w-64 bg-white shadow-md flex-col">
      <SidebarNav />
    </div>

    <!-- Mobile drawer (sidebar as slide-in overlay) -->
    <div class="lg:hidden" :class="drawerOpen ? '' : 'pointer-events-none'">
      <!-- Backdrop -->
      <div
        class="fixed inset-0 z-40 bg-gray-900/45 transition-opacity duration-300"
        :class="drawerOpen ? 'opacity-100' : 'opacity-0'"
        @click="drawerOpen = false"
      />
      <!-- Panel -->
      <aside
        class="fixed top-0 bottom-0 left-0 z-50 w-72 max-w-[85%] bg-white shadow-2xl flex flex-col transition-transform duration-300 ease-out"
        :class="drawerOpen ? 'translate-x-0' : '-translate-x-full'"
      >
        <SidebarNav dismissible @close="drawerOpen = false" />
      </aside>
    </div>

    <!-- Main Content -->
    <div class="flex-1 overflow-y-auto">
      <!-- Header -->
      <header class="bg-white shadow-sm p-4 flex justify-between items-center sticky top-0 z-10">
        <div class="flex items-center min-w-0 flex-1">
          <!-- Hamburger (mobile only) -->
          <button
            type="button"
            class="lg:hidden -ml-1 mr-2 p-2 rounded-xl text-gray-700 hover:bg-gray-100 transition-colors flex-shrink-0"
            aria-label="Menü öffnen"
            @click="drawerOpen = true"
          >
            <Menu class="w-6 h-6" />
          </button>
          <h1 class="text-xl font-semibold min-w-0 flex-1">
            <slot name="header">Dashboard</slot>
          </h1>
        </div>

        <div class="flex items-center space-x-4 flex-shrink-0">
          <!-- Page-specific header actions. When a page fills this slot it can
               opt to hide the default user cluster on mobile via
               `hideHeaderUserOnMobile` (e.g. to surface its own actions there). -->
          <slot name="header-actions" />

          <!-- Default user cluster: admin badge, notifications, profile.
               Hidden on mobile when a page provides its own header actions. -->
          <div
            class="flex items-center space-x-4"
            :class="hideHeaderUserOnMobile ? 'hidden lg:flex' : 'flex'"
          >
            <!-- Admin Badge -->
            <Link v-if="isAdmin" :href="route('impersonate.index')" class="flex items-center bg-purple-100 text-purple-800 px-2 py-1 rounded-full hover:bg-purple-200 transition-colors cursor-pointer">
              <Shield class="h-3 w-3 mr-1" />
              <span class="text-xs font-medium">Admin</span>
            </Link>

            <!-- Notification Popup Component -->
            <NotificationPopup ref="notificationPopup" />

            <!-- User Profile -->
            <Link :href="route('profile.index')" class="flex items-center hover:bg-gray-100 rounded-lg px-2 py-1 transition-colors cursor-pointer">
              <div class="w-8 h-8 bg-indigo-500 rounded-full text-white flex items-center justify-center text-xs font-semibold">
                {{ userInitials }}
              </div>
              <span class="hidden sm:inline ml-2 text-sm font-medium">{{ user.first_name }} {{ user.last_name }}</span>
            </Link>
          </div>
        </div>
      </header>

      <!-- Page Content -->
      <main class="p-4 sm:p-6 mb-20">
        <ImpersonationBanner />
        <slot />
      </main>

      <!-- Chatwoot Integration -->
      <Chatwoot
        v-if="chatwootEnabled"
        :website-token="chatwootToken"
        :base-url="chatwootUrl"
        :identity-hash="chatwootIdentityHash"
      />
    </div>

    <!-- Global toast notifications -->
    <NotificationContainer />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { router, usePage, Head, Link } from '@inertiajs/vue3'
import { Shield, Menu } from 'lucide-vue-next'
import SidebarNav from '@/Components/SidebarNav.vue'
import NotificationPopup from '@/Components/NotificationPopup.vue'
import ImpersonationBanner from '@/Components/ImpersonationBanner.vue'
import Chatwoot from '@/Components/Chatwoot.vue'
import NotificationContainer from '@/Components/NotificationContainer.vue'
import { useToast } from '@/composables/useToast'

// Shared data
const page = usePage()
const notificationPopup = ref(null)

// Bridge Laravel/Inertia flash messages into the global toast system.
// Two flash shapes exist in this app:
//   1. The shared Inertia middleware exposes { message, error }.
//   2. Some controllers redirect ->with('flash', { type, message }) where
//      type is 'success' | 'error' | 'info'.
// Both are handled here so any flash automatically becomes a toast.
const { success: toastSuccess, error: toastError, info: toastInfo } = useToast()

function showFlash(flash) {
  if (!flash) {
    return
  }

  if (flash.error) {
    toastError(flash.error)
  }

  if (flash.message) {
    if (flash.type === 'error') {
      toastError(flash.message)
    } else if (flash.type === 'info') {
      toastInfo(flash.message)
    } else {
      toastSuccess(flash.message)
    }
  }
}

watch(() => page.props.flash, showFlash, { immediate: true, deep: true })

// Mobile drawer state — auto-closes after Inertia navigates to a new page.
const drawerOpen = ref(false)
const stopNavListener = router.on('navigate', () => { drawerOpen.value = false })
onBeforeUnmount(() => stopNavListener())

// Page
const chatwootEnabled = page.props.chatwoot?.enabled ?? false
const chatwootToken = page.props.chatwoot?.token
const chatwootUrl = page.props.chatwoot?.baseUrl
const chatwootIdentityHash = page.props.chatwoot?.identityHash

// Props
const props = defineProps({
  title: {
    type: String,
    default: null
  },
  // Hide the default header user cluster (admin badge, notifications, profile)
  // on mobile so a page can surface its own actions via the header-actions slot.
  hideHeaderUserOnMobile: {
    type: Boolean,
    default: false
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

// Admin Check
const isAdmin = computed(() => {
  return user.value?.role_id === 1
})

// Make user data available globally for WebSocket
onMounted(() => {
  if (typeof window !== 'undefined') {
    window.Laravel = window.Laravel || {}
    window.Laravel.user = user.value
  }
})
</script>
