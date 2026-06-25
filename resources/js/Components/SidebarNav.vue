<template>
  <div class="flex flex-col h-full">
    <!-- Brand header -->
    <div class="p-4 border-b border-gray-200 flex items-start justify-between">
      <div>
        <Logo class="h-5 w-auto" />
        <p class="mt-1 text-xs text-gray-500 tracking-wide">Mitgliederverwaltung</p>
      </div>
      <!-- Close button (drawer only) -->
      <button
        v-if="dismissible"
        type="button"
        class="lg:hidden -mr-1 -mt-1 p-2 rounded-xl text-gray-500 hover:bg-gray-100 transition-colors"
        aria-label="Menü schließen"
        @click="$emit('close')"
      >
        <X class="w-5 h-5" />
      </button>
    </div>

    <nav class="mt-6 flex-1 overflow-y-auto">
      <SidebarItem
        :icon="BarChart"
        label="Dashboard"
        :active="route().current('dashboard')"
        :href="route('dashboard')"
      />
      <SidebarItem
        :icon="Users"
        label="Mitglieder"
        :active="route().current('members.*') || route().current('blocklist.*')"
        :href="route('members.index')"
        :disabled="!canAccessPremiumFeatures"
      >
        <template #children>
          <Link
            v-if="isOwnerOrAdmin"
            :href="route('blocklist.index')"
            :class="[
              'flex items-center px-4 py-2 text-xs font-medium transition-colors',
              route().current('blocklist.*')
                ? 'text-indigo-700'
                : 'text-gray-500 hover:text-gray-700'
            ]"
          >
            <span class="w-5 mr-3 flex justify-center">
              <span class="w-px h-full min-h-4 bg-gray-300" />
            </span>
            Sperrliste
          </Link>
        </template>
      </SidebarItem>
      <SidebarItem
        :icon="FilePlus"
        label="Verträge"
        :active="route().current('contracts.*')"
        :href="route('contracts.index')"
        :disabled="!canAccessPremiumFeatures"
      >
        <template #children>
          <Link
            v-if="isOwnerOrAdmin"
            :href="route('contracts.addons.index')"
            :class="[
              'flex items-center px-4 py-2 text-xs font-medium transition-colors',
              route().current('contracts.addons.*')
                ? 'text-indigo-700'
                : 'text-gray-500 hover:text-gray-700'
            ]"
          >
            <span class="w-5 mr-3 flex justify-center">
              <span class="w-px h-full min-h-4 bg-gray-300" />
            </span>
            Add-ons
          </Link>
        </template>
      </SidebarItem>
      <SidebarItem
        v-if="isOwnerOrAdmin"
        :icon="DollarSign"
        label="Finanzen"
        :active="route().current('finances.index')"
        :href="route('finances.index')"
        :disabled="!canAccessPremiumFeatures"
      />
      <SidebarItem
        v-if="isOwnerOrAdmin"
        :icon="Bell"
        label="Benachrichtigungen"
        :active="route().current('notifications.index')"
        :href="route('notifications.index')"
        :disabled="!canAccessPremiumFeatures"
      />
      <SidebarItem
        :icon="DoorOpen"
        label="Zugangskontrolle"
        :active="route().current('access-control.*')"
        :href="route('access-control.index')"
        :disabled="!canAccessPremiumFeatures"
      />
      <SidebarItem
        v-if="isOwnerOrAdmin"
        :icon="ArrowDownUp"
        label="Import/Export"
        :active="route().current('data-transfer.*')"
        :href="route('data-transfer.index')"
      />
      <SidebarItem
        v-if="isOwnerOrAdmin"
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

    <!-- Trial/Subscription Status + Version -->
    <div class="p-4 border-t border-gray-200 flex flex-col gap-3">
      <template v-if="subscriptionStatus">
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
      </template>

      <!-- App-Version -->
      <div class="flex items-center justify-center">
        <AppVersion />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { router, usePage, Link } from '@inertiajs/vue3'
import {
  Users, Bell, DollarSign,
  BarChart, Settings, LogOut,
  FilePlus, DoorOpen, ArrowDownUp, X
} from 'lucide-vue-next'
import SidebarItem from '@/Components/SidebarItem.vue'
import OrganizationSwitcher from '@/Components/OrganizationSwitcher.vue'
import Logo from '@/Components/Logo.vue'
import AppVersion from '@/Components/AppVersion.vue'

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
</script>
