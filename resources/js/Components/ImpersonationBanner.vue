<template>
  <!-- Solid danger surface: the admin pages refer to it as "the red banner",
       and white text needs ≥4.5:1 (a yellow-to-red gradient gave ~1.5:1). -->
  <div
    v-if="$page.props.impersonation.active"
    role="region"
    aria-label="Benutzer-Simulation"
    class="relative mb-6"
  >
    <div class="bg-danger-solid rounded-lg shadow-md">
      <div class="max-w-7xl mx-auto py-3 px-3 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between flex-wrap">
          <div class="w-0 flex-1 flex items-center">
            <span class="flex p-2 rounded-lg bg-white/15" aria-hidden="true">
              <AlertTriangle class="h-6 w-6 text-white" />
            </span>
            <p class="ml-3 font-medium text-white truncate">
              <span class="md:hidden">
                Simulation aktiv
              </span>
              <span class="hidden md:inline">
                <strong>ACHTUNG:</strong> Sie simulieren gerade den Benutzer
                <strong class="ml-1 underline">{{ impersonatedFullName }}</strong>
                <span class="ml-1 font-normal">({{ $page.props.impersonation.impersonated_user_email }})</span>
              </span>
            </p>
          </div>
          <div class="order-3 mt-2 flex-shrink-0 w-full sm:order-2 sm:mt-0 sm:w-auto">
            <button
              type="button"
              @click="stopImpersonation"
              class="flex items-center justify-center px-4 py-2 pointer-coarse:py-3 border border-transparent rounded-md shadow-sm text-sm font-medium text-danger bg-surface hover:bg-danger-subtle transition-colors"
            >
              <LogOut class="h-4 w-4 mr-2" />
              Zurück zu {{ impersonatorFullName }}
            </button>
          </div>
          <div class="order-2 flex-shrink-0 sm:order-3 sm:ml-3">
            <button
              @click="showDetails = !showDetails"
              type="button"
              :aria-expanded="showDetails"
              class="touch-target relative -mr-1 flex p-2 rounded-md hover:bg-white/15 sm:-mr-2"
            >
              <span class="sr-only">Details anzeigen</span>
              <Info class="h-6 w-6 text-white" />
            </button>
          </div>
        </div>

        <!-- Extended details -->
        <Transition
          enter-active-class="transition ease-out duration-200"
          enter-from-class="transform opacity-0 scale-95"
          enter-to-class="transform opacity-100 scale-100"
          leave-active-class="transition ease-in duration-75"
          leave-from-class="transform opacity-100 scale-100"
          leave-to-class="transform opacity-0 scale-95"
        >
          <div v-if="showDetails" class="mt-3 bg-white/10 rounded-lg p-3">
            <div class="text-sm text-white">
              <div class="flex items-center mb-2">
                <Shield class="h-4 w-4 mr-2 flex-shrink-0" />
                <p><strong>Ihr Admin-Account:</strong> {{ impersonatorFullName }}</p>
              </div>
              <div class="flex items-center mb-2">
                <User class="h-4 w-4 mr-2 flex-shrink-0" />
                <p><strong>Simulierter Benutzer:</strong> {{ impersonatedFullName }} ({{ $page.props.impersonation.impersonated_user_email }})</p>
              </div>
              <div class="flex items-start mt-3 pt-3 border-t border-white/20">
                <AlertCircle class="h-4 w-4 mr-2 flex-shrink-0 mt-0.5" />
                <p>
                  Alle Aktionen werden als simulierter Benutzer ausgeführt.
                  Seien Sie vorsichtig mit Änderungen!
                </p>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import {
  AlertTriangle,
  LogOut,
  Info,
  Shield,
  User,
  AlertCircle
} from '@lucide/vue'

const page = usePage()
const showDetails = ref(false)

// Computed Vollnamen
const impersonatorFullName = computed(() => {
  const fullName = page.props.impersonation.impersonator_name || ''
  return `${fullName}`.trim()
})

const impersonatedFullName = computed(() => {
  const fullName = page.props.impersonation.impersonated_user_name || ''
  return `${fullName}`.trim()
})

const stopImpersonation = () => {
  if (confirm('Möchten Sie die Simulation beenden und zu Ihrem Administrator-Account zurückkehren?')) {
    router.delete(route('impersonate.stop'), {}, {
      preserveScroll: true,
      preserveState: false
    })
  }
}
</script>
