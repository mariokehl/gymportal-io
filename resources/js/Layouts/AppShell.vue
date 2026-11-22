<template>
  <div class="flex h-screen bg-gray-100">
    <!-- Sidebar (static on large screens) -->
    <!-- relative/z-20 lets the member search dropdown overlap the content area -->
    <div class="hidden lg:flex relative z-20 w-64 bg-white shadow-md flex-col">
      <SidebarNav ref="desktopNav" />
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
        <SidebarNav ref="drawerNav" dismissible @close="drawerOpen = false" />
      </aside>
    </div>

    <!-- Main Content. `scroll-region` lets Inertia reset and restore this
         container's scroll position, since the shell outlives page visits. -->
    <div class="flex-1 overflow-y-auto" scroll-region>
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
          <!-- Filled by the page's AppLayout `header` slot -->
          <h1 :id="APP_HEADER_TITLE_ID" class="text-xl font-semibold min-w-0 flex-1" />
        </div>

        <div class="flex items-center gap-4 flex-shrink-0">
          <!-- Member search (mobile only): opens the drawer with the search focused -->
          <button
            v-if="memberSearchAvailable"
            type="button"
            class="lg:hidden p-2 rounded-xl text-gray-700 hover:bg-gray-100 transition-colors"
            aria-label="Mitglied suchen"
            @click="openMobileSearch"
          >
            <Search class="w-5 h-5" />
          </button>

          <!-- Filled by the page's AppLayout `header-actions` slot. `contents`
               keeps the actions as direct flex items of this row. -->
          <div :id="APP_HEADER_ACTIONS_ID" class="contents" />

          <!-- Default user cluster: admin badge, notifications, profile.
               Hidden on mobile when the page asks for it. -->
          <div
            class="flex items-center gap-4"
            :class="shell.pageOptions.hideHeaderUserOnMobile ? 'hidden lg:flex' : 'flex'"
          >
            <!-- Admin Badge -->
            <Link v-if="isAdmin" :href="route('impersonate.index')" class="flex items-center bg-purple-100 text-purple-800 px-2 py-1 rounded-full hover:bg-purple-200 transition-colors cursor-pointer">
              <Shield class="h-3 w-3 mr-1" />
              <span class="text-xs font-medium">Admin</span>
            </Link>

            <!-- Notification Popup Component -->
            <NotificationPopup />

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
  </div>
</template>

<script>
// Teleport targets for the page-owned parts of the header.
export const APP_HEADER_TITLE_ID = 'app-header-title'
export const APP_HEADER_ACTIONS_ID = 'app-header-actions'
</script>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, inject, provide } from 'vue'
import { router, usePage, Link } from '@inertiajs/vue3'
import { Shield, Menu, Search } from '@lucide/vue'
import SidebarNav from '@/Components/SidebarNav.vue'
import NotificationPopup from '@/Components/NotificationPopup.vue'
import ImpersonationBanner from '@/Components/ImpersonationBanner.vue'
import Chatwoot from '@/Components/Chatwoot.vue'
import { useMemberSearchHistory } from '@/composables/useMemberSearchHistory'
import { APP_SHELL_KEY, createAppShellState } from '@/composables/useAppShell'

// Persistent application shell (sidebar, header, drawer, Chatwoot). Pages opt
// in with `defineOptions({ layout: AppShell })` and render their content via
// AppLayout, so the shell survives Inertia visits instead of remounting.
// AppLayout may already have created the state when it wraps a page that did
// not opt in (non-persistent fallback); reuse it in that case.
const shell = inject(APP_SHELL_KEY, null) ?? createAppShellState()
provide(APP_SHELL_KEY, shell)

const page = usePage()

// Mobile drawer state — auto-closes after Inertia navigates to a new page.
const drawerOpen = ref(false)
const stopNavListener = router.on('navigate', () => { drawerOpen.value = false })
onBeforeUnmount(() => stopNavListener())

// Global member search lives in the sidebar (desktop) and the drawer (mobile).
const desktopNav = ref(null)
const drawerNav = ref(null)

const memberSearchAvailable = computed(() => {
  const status = page.props.subscription_status
  if (!status) return true

  return status.trial.is_active || status.subscription.is_active
})

function openMobileSearch() {
  drawerOpen.value = true
  // Focus synchronously within the tap so mobile browsers show the keyboard.
  drawerNav.value?.focusSearch()
}

// "/" focuses the member search unless the user is typing somewhere.
function onGlobalKeydown(event) {
  if (event.key !== '/' || event.metaKey || event.ctrlKey || event.altKey) return

  const target = event.target
  if (target?.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target?.tagName)) return

  const isDesktop = window.matchMedia('(min-width: 1024px)').matches
  const focused = isDesktop ? desktopNav.value?.focusSearch() : drawerNav.value?.focusSearch()

  if (focused) {
    event.preventDefault()
    if (!isDesktop) drawerOpen.value = true
  }
}

onMounted(() => window.addEventListener('keydown', onGlobalKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onGlobalKeydown))

// Every visited member detail page feeds the "recently viewed" list,
// regardless of whether it was opened via the search or a link elsewhere.
const memberSearchHistory = useMemberSearchHistory()
watch(
  () => (page.component === 'Members/Show' ? page.props.member?.id : null),
  (memberId) => {
    if (memberId) memberSearchHistory.remember(memberId)
  },
  { immediate: true }
)

const chatwootEnabled = page.props.chatwoot?.enabled ?? false
const chatwootToken = page.props.chatwoot?.token
const chatwootUrl = page.props.chatwoot?.baseUrl
const chatwootIdentityHash = page.props.chatwoot?.identityHash

const user = computed(() => page.props.auth.user)

const userInitials = computed(() => {
  const first = user.value.first_name?.charAt(0) || ''
  const last = user.value.last_name?.charAt(0) || ''
  return (first + last).toUpperCase()
})

const isAdmin = computed(() => user.value?.role_id === 1)

// Keep the user available globally for WebSocket; the shell outlives page
// visits, so follow changes (e.g. a profile update) instead of setting it once.
watch(user, (value) => {
  window.Laravel = window.Laravel || {}
  window.Laravel.user = value
}, { immediate: true })
</script>
