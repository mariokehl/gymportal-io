<template>
  <div class="flex h-dvh bg-canvas">
    <a
      href="#main-content"
      class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[80] focus:rounded-lg focus:bg-surface focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-brand-ink-strong focus:shadow-md"
    >
      Zum Inhalt springen
    </a>

    <!-- Sidebar (static on large screens) -->
    <!-- relative/z-20 lets the member search dropdown overlap the content area -->
    <aside class="hidden lg:flex relative z-20 w-64 bg-surface shadow-md flex-col" aria-label="Seitenleiste">
      <SidebarNav ref="desktopNav" />
    </aside>

    <!-- Mobile drawer (sidebar as slide-in overlay) -->
    <div ref="drawerRoot" class="lg:hidden" :class="drawerOpen ? '' : 'pointer-events-none'">
      <!-- Backdrop -->
      <div
        aria-hidden="true"
        class="fixed inset-0 z-40 bg-inverse/45 transition-opacity duration-300"
        :class="drawerOpen ? 'opacity-100' : 'opacity-0'"
        @click="drawerOpen = false"
      />
      <!-- Panel. Closed, it is inert (out of the tab order and hidden from
           assistive tech) and drops its shadow so nothing bleeds into view. -->
      <div
        id="mobile-drawer"
        ref="drawerPanel"
        role="dialog"
        aria-modal="true"
        aria-label="Hauptmenü"
        :inert="drawerOpen ? null : true"
        class="fixed top-0 bottom-0 left-0 z-50 w-72 max-w-[85%] bg-surface flex flex-col transition-[translate,box-shadow] duration-300 ease-out"
        :class="drawerOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full shadow-none'"
      >
        <SidebarNav ref="drawerNav" dismissible @close="closeDrawer" />
      </div>
    </div>

    <!-- Main Content. `scroll-region` lets Inertia reset and restore this
         container's scroll position, since the shell outlives page visits. -->
    <div class="flex-1 min-w-0 overflow-y-auto" scroll-region>
      <!-- Header -->
      <!-- Same 72px height and hairline as the sidebar's brand header, so both
           read as one strip. -->
      <header class="h-18 px-4 bg-surface border-b border-line flex justify-between items-center sticky top-0 z-10">
        <div class="flex items-center min-w-0 flex-1">
          <!-- Hamburger (mobile only) -->
          <button
            ref="hamburger"
            type="button"
            class="lg:hidden -ml-1 mr-2 p-2 rounded-xl text-ink-secondary hover:bg-fill transition-colors flex-shrink-0"
            aria-label="Menü öffnen"
            aria-controls="mobile-drawer"
            :aria-expanded="drawerOpen"
            @click="openDrawer"
          >
            <Menu class="w-6 h-6" />
          </button>
          <!-- Filled by the page's AppLayout `header` slot -->
          <h1 :id="APP_HEADER_TITLE_ID" class="text-xl font-semibold min-w-0 flex-1 truncate" />
        </div>

        <div class="flex items-center gap-2 sm:gap-4 flex-shrink-0">
          <!-- Member search (mobile only): opens the drawer with the search focused -->
          <button
            v-if="memberSearchAvailable"
            type="button"
            class="touch-target relative lg:hidden p-2 rounded-xl text-ink-secondary hover:bg-fill transition-colors"
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
            class="flex items-center gap-2 sm:gap-4"
            :class="shell.pageOptions.hideHeaderUserOnMobile ? 'hidden lg:flex' : 'flex'"
          >
            <!-- Admin Badge -->
            <Link v-if="isAdmin" :href="route('impersonate.index')" class="flex items-center gap-1 rounded-md border border-line px-2 py-1 text-ink-secondary hover:bg-fill transition-colors">
              <Shield class="h-3.5 w-3.5" aria-hidden="true" />
              <span class="text-xs font-medium">Admin</span>
            </Link>

            <!-- Notification Popup Component -->
            <NotificationPopup />

            <!-- Account: profile and signing out -->
            <UserMenu />
          </div>
        </div>
      </header>

      <!-- Page Content -->
      <main id="main-content" tabindex="-1" class="p-4 sm:p-6 mb-20 focus:outline-none">
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
import { usePage, Link } from '@inertiajs/vue3'
import { Shield, Menu, Search } from '@lucide/vue'
import SidebarNav from '@/Components/SidebarNav.vue'
import NotificationPopup from '@/Components/NotificationPopup.vue'
import ImpersonationBanner from '@/Components/ImpersonationBanner.vue'
import UserMenu from '@/Components/UserMenu.vue'
import Chatwoot from '@/Components/Chatwoot.vue'
import { useMemberSearchHistory } from '@/composables/useMemberSearchHistory'
import { APP_SHELL_KEY, createAppShellState } from '@/composables/useAppShell'
import { useDismissable } from '@/composables/useDismissable'
import { useFocusTrap } from '@/composables/useFocusTrap'

// Persistent application shell (sidebar, header, drawer, Chatwoot). Pages opt
// in with `defineOptions({ layout: AppShell })` and render their content via
// AppLayout, so the shell survives Inertia visits instead of remounting.
// AppLayout may already have created the state when it wraps a page that did
// not opt in (non-persistent fallback); reuse it in that case.
const shell = inject(APP_SHELL_KEY, null) ?? createAppShellState()
provide(APP_SHELL_KEY, shell)

const page = usePage()

// Mobile drawer: a modal dialog that traps focus and closes on Escape, on a
// press outside (the backdrop) and after every Inertia visit.
const drawerOpen = ref(false)
const drawerRoot = ref(null)
const drawerPanel = ref(null)
const hamburger = ref(null)
// The root includes the backdrop: closing on its pointerdown would turn the
// wrapper click-through before pointerup and leak the tap to the page below.
// The backdrop closes on click instead.
useDismissable(drawerRoot, drawerOpen, { triggerRef: hamburger })
useFocusTrap(drawerPanel, drawerOpen)

function openDrawer() {
  // Lift inert synchronously: callers may focus inside the panel within the
  // same tap (mobile search), before Vue re-renders the attribute away.
  if (drawerPanel.value) drawerPanel.value.inert = false
  drawerOpen.value = true
}

// Chatwoot's launcher sits on a max z-index and would cover the modal drawer.
watch(drawerOpen, (isOpen) => {
  window.$chatwoot?.toggleBubbleVisibility(isOpen ? 'hide' : 'show')
})

function closeDrawer() {
  drawerOpen.value = false
  hamburger.value?.focus()
}

// Global member search lives in the sidebar (desktop) and the drawer (mobile).
const desktopNav = ref(null)
const drawerNav = ref(null)

const memberSearchAvailable = computed(() => {
  const status = page.props.subscription_status
  if (!status) return true

  return status.trial.is_active || status.subscription.is_active
})

function openMobileSearch() {
  openDrawer()
  // Focus synchronously within the tap so mobile browsers show the keyboard.
  drawerNav.value?.focusSearch()
}

// "/" focuses the member search unless the user is typing somewhere.
function onGlobalKeydown(event) {
  if (event.key !== '/' || event.metaKey || event.ctrlKey || event.altKey) return

  const target = event.target
  if (target?.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target?.tagName)) return

  const isDesktop = window.matchMedia('(min-width: 1024px)').matches
  if (!isDesktop) openDrawer()
  const focused = isDesktop ? desktopNav.value?.focusSearch() : drawerNav.value?.focusSearch()

  if (focused) {
    event.preventDefault()
  } else if (!isDesktop) {
    drawerOpen.value = false
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

const isAdmin = computed(() => user.value?.role_id === 1)

// Keep the user available globally for WebSocket; the shell outlives page
// visits, so follow changes (e.g. a profile update) instead of setting it once.
watch(user, (value) => {
  window.Laravel = window.Laravel || {}
  window.Laravel.user = value
}, { immediate: true })
</script>
