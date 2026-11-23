<template>
  <div class="flex flex-col h-full">
    <!-- Brand header -->
    <div class="p-4 border-b border-line flex items-start justify-between">
      <div>
        <Logo class="h-5 w-auto" />
        <p class="mt-1 text-xs text-ink-muted">Mitgliederverwaltung</p>
      </div>
      <!-- Close button (drawer only) -->
      <button
        v-if="dismissible"
        type="button"
        class="lg:hidden -mr-1 -mt-1 p-2 rounded-xl text-ink-muted hover:bg-fill transition-colors"
        aria-label="Menü schließen"
        @click="$emit('close')"
      >
        <X class="w-5 h-5" />
      </button>
    </div>

    <!-- Global member search -->
    <div v-if="canAccessPremiumFeatures" class="px-4 pt-4 pb-2">
      <MemberQuickSearch ref="quickSearch" />
    </div>

    <nav aria-label="Hauptnavigation" class="flex-1 overflow-y-auto px-2 pb-4" :class="canAccessPremiumFeatures ? 'pt-2' : 'pt-4'">
      <div class="flex flex-col gap-0.5">
        <SidebarItem
          :icon="BarChart"
          label="Dashboard"
          :active="isCurrent('dashboard')"
          :href="route('dashboard')"
        />
        <SidebarItem
          :icon="Users"
          label="Mitglieder"
          :active="isCurrent('members.*') || isCurrent('blocklist.*')"
          :current="isCurrent('members.*')"
          :href="route('members.index')"
          :disabled="!canAccessPremiumFeatures"
        >
          <template v-if="isOwnerOrAdmin" #children>
            <SidebarSubItem label="Sperrliste" :href="route('blocklist.index')" :current="isCurrent('blocklist.*')" />
          </template>
        </SidebarItem>
        <SidebarItem
          :icon="FilePlus"
          label="Verträge"
          :active="isCurrent('contracts.*')"
          :current="isCurrent('contracts.*') && !isCurrent('contracts.addons.*')"
          :href="route('contracts.index')"
          :disabled="!canAccessPremiumFeatures"
        >
          <template v-if="isOwnerOrAdmin" #children>
            <SidebarSubItem label="Add-ons" :href="route('contracts.addons.index')" :current="isCurrent('contracts.addons.*')" />
          </template>
        </SidebarItem>
        <SidebarItem
          v-if="isOwnerOrAdmin"
          :icon="DollarSign"
          label="Finanzen"
          :active="isCurrent('finances.index')"
          :href="route('finances.index')"
          :disabled="!canAccessPremiumFeatures"
        />
        <SidebarItem
          v-if="isOwnerOrAdmin"
          :icon="Bell"
          label="Benachrichtigungen"
          :active="isCurrent('notifications.index')"
          :href="route('notifications.index')"
          :disabled="!canAccessPremiumFeatures"
        />
        <SidebarItem
          :icon="DoorOpen"
          label="Zugangskontrolle"
          :active="isCurrent('access-control.*')"
          :href="route('access-control.index')"
          :disabled="!canAccessPremiumFeatures"
        />
      </div>

      <!-- Back-office pages, apart from the daily work above -->
      <div v-if="isOwnerOrAdmin" role="group" aria-labelledby="sidebar-group-admin" class="mt-6 flex flex-col gap-0.5">
        <p id="sidebar-group-admin" class="px-2 pb-1.5 text-xs font-medium text-ink-muted">Verwaltung</p>
        <SidebarItem
          :icon="ArrowDownUp"
          label="Import/Export"
          :active="isCurrent('data-transfer.*')"
          :href="route('data-transfer.index')"
        />
        <SidebarItem
          :icon="Settings"
          label="Einstellungen"
          :active="isCurrent('settings.index')"
          :href="route('settings.index')"
        />
      </div>
    </nav>

    <!-- Signing out is an action, not a destination: kept out of the nav list -->
    <div class="px-2 pb-2">
      <SidebarItem
        :icon="LogOut"
        label="Abmelden"
        @click="handleLogout"
      />
    </div>

    <OrganizationSwitcher />

    <!-- Trial/Subscription Status + Version -->
    <div class="p-4 border-t border-line flex flex-col gap-3">
      <template v-if="subscriptionStatus">
        <!-- One quiet status line with a live dot (the product's status
             signature); only the expired trial asks for action. -->
        <p v-if="subscriptionStatus.trial.is_active" class="flex items-center gap-2 px-1 text-xs">
          <span class="size-2 flex-none rounded-full bg-brand" aria-hidden="true"></span>
          <span class="font-medium text-ink-secondary">Testphase</span>
          <span class="ml-auto tabular-nums text-ink-muted">{{ subscriptionStatus.trial.days_left }} Tage verbleibend</span>
        </p>

        <p v-else-if="subscriptionStatus.subscription.is_active" class="flex items-center gap-2 px-1 text-xs">
          <span class="size-2 flex-none rounded-full bg-success-solid" aria-hidden="true"></span>
          <span class="font-medium text-ink-secondary">SaaS Hosted</span>
          <span class="ml-auto text-ink-muted">Aktiv</span>
        </p>

        <!-- Same one-line shape as the other states: status left, the one
             action right. Neither part may wrap in the 256px sidebar. -->
        <div v-else class="flex items-center gap-2 px-1 text-xs whitespace-nowrap">
          <span class="size-2 flex-none rounded-full bg-danger-solid" aria-hidden="true"></span>
          <span class="min-w-0 truncate font-medium text-danger">Testphase abgelaufen</span>
          <Link :href="route('billing.index')" class="ml-auto flex-none font-medium text-brand-ink underline underline-offset-2 hover:text-brand-ink-strong">
            Upgraden
          </Link>
        </div>
      </template>

      <!-- App-Version -->
      <div class="flex items-center justify-center">
        <AppVersion />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { router, usePage, Link } from '@inertiajs/vue3'
import {
  Users, Bell, DollarSign,
  BarChart, Settings, LogOut,
  FilePlus, DoorOpen, ArrowDownUp, X
} from '@lucide/vue'
import SidebarItem from '@/Components/SidebarItem.vue'
import SidebarSubItem from '@/Components/SidebarSubItem.vue'
import OrganizationSwitcher from '@/Components/OrganizationSwitcher.vue'
import Logo from '@/Components/Logo.vue'
import AppVersion from '@/Components/AppVersion.vue'
import MemberQuickSearch from '@/Components/MemberQuickSearch.vue'

defineProps({
  // When true, the brand header shows a close button (drawer usage on mobile).
  dismissible: {
    type: Boolean,
    default: false
  }
})

defineEmits(['close'])

const page = usePage()

const user = computed(() => page.props.auth.user)

// Ziggy's route().current() reads window.location and is not reactive. The
// sidebar lives in the persistent AppShell, so tie the check to page.url to
// re-render the active state after every Inertia visit.
const isCurrent = (name) => {
  void page.url
  return route().current(name)
}

const subscriptionStatus = computed(() => {
  return page.props.subscription_status || null
})

const canAccessPremiumFeatures = computed(() => {
  if (!subscriptionStatus.value) return true

  return subscriptionStatus.value.trial.is_active ||
         subscriptionStatus.value.subscription.is_active
})

const isOwnerOrAdmin = computed(() => {
  return user.value?.role_id === 1 || user.value?.role_id === 2
})

const handleLogout = () => {
  if (confirm('Möchten Sie sich wirklich abmelden?')) {
    router.post('/logout')
  }
}

const quickSearch = ref(null)

// Returns false when the search is not available (e.g. expired trial).
const focusSearch = () => {
  if (!quickSearch.value) return false

  quickSearch.value.focus()
  return true
}

defineExpose({ focusSearch })
</script>
