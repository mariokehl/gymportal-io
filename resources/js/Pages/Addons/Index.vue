<template>
  <AppLayout title="Add-ons">
    <template #header>
      Add-ons verwalten
    </template>

    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm">
      <Link :href="route('contracts.index')" class="text-indigo-600 hover:text-indigo-800">
        Verträge
      </Link>
      <span class="text-gray-500 mx-2">/</span>
      <span class="text-gray-900">Add-ons</span>
    </nav>

    <!-- Header with Add Button -->
    <div class="mb-4 flex flex-wrap gap-3 justify-between items-start">
      <div class="max-w-3xl">
        <h2 class="text-2xl font-bold text-gray-900">Add-ons &amp; Leistungen</h2>
        <p class="text-gray-600 mt-1.5">
          Zusatzleistungen zu einem Vertrag – <strong class="text-gray-700">einmalig</strong> zum Vertragsstart
          oder <strong class="text-gray-700">wiederkehrend</strong> (monatlich, synchron zum Mitgliedsbeitrag).
          Nutzungsleistungen wie Getränke oder Sauna werden über ein Gerät mit Kontingent verrechnet.
        </p>
      </div>
      <Link
        v-if="isOwnerOrAdmin"
        :href="route('contracts.addons.create')"
        class="shrink-0 bg-indigo-600 hover:bg-indigo-700 text-white px-4.5 py-2.5 rounded-lg font-semibold flex items-center gap-2 transition-colors"
      >
        <Plus class="w-4 h-4" />
        <span>Neues Add-on</span>
      </Link>
    </div>

    <!-- Service type filter -->
    <div v-if="addons.length > 0" class="flex gap-2 flex-wrap my-4">
      <button
        v-for="filter in serviceTypeFilters"
        :key="filter.value"
        type="button"
        class="border rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
        :class="activeFilter === filter.value
          ? 'bg-gray-900 border-gray-900 text-white'
          : 'bg-white border-gray-200 text-gray-700 hover:border-gray-300'"
        :aria-pressed="activeFilter === filter.value"
        @click="activeFilter = filter.value"
      >
        {{ filter.label }}
      </button>
    </div>

    <!-- Add-ons Grid -->
    <div v-if="filteredAddons.length > 0" class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
      <div
        v-for="addon in filteredAddons"
        :key="addon.id"
        class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:shadow-md transition-shadow flex flex-col"
      >
        <div class="flex items-center gap-2.5 flex-wrap mb-1">
          <span
            class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0"
            :class="isUsageService(addon) ? 'bg-indigo-50 text-indigo-600' : 'bg-gray-100 text-gray-500'"
          >
            <component :is="isUsageService(addon) ? CupSoda : BadgeCheck" class="w-4.5 h-4.5" />
          </span>
          <h3 class="font-semibold text-lg text-gray-900">{{ addon.name }}</h3>
          <span
            :class="addon.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'"
            class="px-2.5 py-0.5 rounded-full text-xs font-semibold"
          >
            {{ addon.is_active ? 'Aktiv' : 'Inaktiv' }}
          </span>
        </div>

        <div class="flex gap-1.5 flex-wrap my-1.5">
          <span
            class="text-xs font-semibold px-2.5 py-0.5 rounded-full"
            :class="isUsageService(addon) ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-700'"
          >
            {{ isUsageService(addon) ? 'Nutzungsleistung' : 'Zusatzleistung' }}
          </span>
          <span
            class="text-xs font-semibold px-2.5 py-0.5 rounded-full"
            :class="isRecurring(addon) ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'"
          >
            {{ isRecurring(addon) ? 'Wiederkehrend' : 'Einmalig' }}
          </span>
        </div>

        <p class="text-gray-500 text-sm mb-3.5 min-h-5">{{ addon.description }}</p>

        <div class="flex flex-col gap-2.5 text-sm pt-3 border-t mb-6 border-gray-100 flex-grow">
          <div class="flex justify-between gap-2">
            <span class="text-gray-500">Preis</span>
            <span class="font-semibold text-gray-900 text-right">{{ formatAddonPrice(addon) }}</span>
          </div>
          <div class="flex justify-between gap-2">
            <span class="text-gray-500">Abrechnung</span>
            <span class="text-gray-700 text-right">
              {{ isRecurring(addon) ? 'Monatlich, synchron zum Beitrag' : 'Einmalig zum Vertragsstart' }}
            </span>
          </div>
          <template v-if="isUsageService(addon)">
            <div class="flex justify-between gap-2">
              <span class="text-gray-500">Nutzung</span>
              <span class="text-gray-700 text-right">{{ formatUsage(addon) }}</span>
            </div>
            <div v-if="addon.settled_via_device" class="flex justify-between items-center gap-2">
              <span class="text-gray-500">Gerät</span>
              <span class="inline-flex items-center gap-1.5 text-gray-700">
                <CupSoda class="w-3.5 h-3.5 text-indigo-600" />
                Ausgabeautomat
              </span>
            </div>
          </template>
          <div class="flex justify-between gap-2">
            <span class="text-gray-500">Zahlweise</span>
            <span class="text-gray-700 text-right">
              {{ addon.payment_method ? formatPaymentMethod(addon.payment_method) : 'Standard-Zahlungsart' }}
            </span>
          </div>
          <div class="flex justify-between gap-2">
            <span class="text-gray-500">Zugeordnete Verträge</span>
            <span class="font-semibold text-indigo-600">{{ addon.membership_plans_count || 0 }}</span>
          </div>
        </div>

        <div class="flex space-x-2 mt-auto">
          <Link
            v-if="isOwnerOrAdmin"
            :href="route('contracts.addons.edit', addon.id)"
            class="flex-1 bg-indigo-100 hover:bg-indigo-200 text-indigo-700 px-3 py-2 rounded-md text-sm font-medium text-center transition-colors"
          >
            Bearbeiten
          </Link>
          <button
            v-if="isOwnerOrAdmin"
            @click="confirmDelete(addon)"
            class="flex-1 bg-red-100 hover:bg-red-200 text-red-700 px-3 py-2 rounded-md text-sm font-medium transition-colors"
          >
            Löschen
          </button>
        </div>
      </div>
    </div>

    <!-- No match for the active filter -->
    <div v-else-if="addons.length > 0" class="text-center py-12">
      <PackagePlus class="w-12 h-12 text-gray-300 mx-auto mb-3" />
      <p class="text-gray-600">In dieser Kategorie sind keine Add-ons vorhanden.</p>
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-12">
      <PackagePlus class="w-16 h-16 text-gray-400 mx-auto mb-4" />
      <h3 class="text-lg font-medium text-gray-900 mb-2">Keine Add-ons vorhanden</h3>
      <p class="text-gray-600 mb-6">
        {{ isOwnerOrAdmin ? 'Erstellen Sie Ihr erstes Add-on und ordnen Sie es einem Vertrag zu.' : 'Es wurden noch keine Add-ons erstellt.' }}
      </p>
      <Link
        v-if="isOwnerOrAdmin"
        :href="route('contracts.addons.create')"
        class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-lg inline-flex items-center space-x-2 transition-colors"
      >
        <Plus class="w-5 h-5" />
        <span>Erstes Add-on erstellen</span>
      </Link>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50">
      <div class="relative top-20 mx-auto p-5 border border-gray-50 w-96 shadow-lg rounded-md bg-white">
        <div class="flex items-center space-x-3 mb-4">
          <div class="bg-red-100 p-2 rounded-full">
            <AlertTriangle class="w-6 h-6 text-red-600" />
          </div>
          <h3 class="text-lg font-medium text-gray-900">Add-on löschen</h3>
        </div>

        <div class="mb-6">
          <p class="text-gray-600">
            Sind Sie sicher, dass Sie das Add-on "<strong>{{ addonToDelete?.name }}</strong>" löschen möchten?
            Bestehende Buchungen bleiben unberührt, das Add-on wird aber bei neuen Verträgen nicht mehr angeboten.
          </p>
        </div>

        <div class="flex space-x-3">
          <button
            @click="closeDeleteModal"
            class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg font-medium transition-colors"
          >
            Abbrechen
          </button>
          <button
            @click="deleteAddon"
            class="flex-1 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors"
          >
            Löschen
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Plus, PackagePlus, AlertTriangle, BadgeCheck, CupSoda } from 'lucide-vue-next'
import { formatPrice } from '@/utils/formatters'

const page = usePage()

const props = defineProps({
  addons: Array
})

const isOwnerOrAdmin = computed(() => {
  const user = page.props.auth.user
  return user?.role_id === 1 || user?.role_id === 2
})

const showDeleteModal = ref(false)
const addonToDelete = ref(null)

const paymentMethodLabels = {
  sepa_direct_debit: 'SEPA-Lastschrift',
  cash: 'Barzahlung',
  banktransfer: 'Überweisung',
  invoice: 'Rechnung',
  standingorder: 'Dauerauftrag'
}

const formatPaymentMethod = (key) => paymentMethodLabels[key] || key

const serviceTypeFilters = [
  { value: 'all', label: 'Alle' },
  { value: 'additional', label: 'Zusatzleistungen' },
  { value: 'usage', label: 'Nutzungsleistungen' }
]

const activeFilter = ref('all')

const isUsageService = (addon) => addon.service_type === 'usage'
const isRecurring = (addon) => addon.billing_type === 'recurring'

const filteredAddons = computed(() => {
  if (activeFilter.value === 'all') {
    return props.addons
  }

  return props.addons.filter((addon) => (addon.service_type ?? 'additional') === activeFilter.value)
})

const formatAddonPrice = (addon) =>
  isRecurring(addon) ? `${formatPrice(addon.price)} / Monat` : formatPrice(addon.price)

const usagePeriodLabels = {
  single: 'Einmalige Nutzung',
  fixed_period: 'Fester Zeitraum',
  full_day: 'Ganzer Tag'
}

const quotaIntervalLabels = {
  day: 'Tag',
  week: 'Woche',
  month: 'Monat'
}

/**
 * Describes the quota and usage period of a usage service, e.g.
 * "Flatrate · ganzer Tag" or "8 Einheiten / Monat".
 */
const formatUsage = (addon) => {
  const quota = addon.quota_amount
    ? `${addon.quota_amount} Einheiten / ${quotaIntervalLabels[addon.quota_interval] ?? addon.quota_interval}`
    : 'Flatrate'

  const period = usagePeriodLabels[addon.usage_period]

  return period ? `${quota} · ${period.toLowerCase()}` : quota
}

const confirmDelete = (addon) => {
  addonToDelete.value = addon
  showDeleteModal.value = true
}

const deleteAddon = () => {
  if (addonToDelete.value) {
    router.delete(route('contracts.addons.destroy', addonToDelete.value.id))
    closeDeleteModal()
  }
}

const closeDeleteModal = () => {
  showDeleteModal.value = false
  addonToDelete.value = null
}
</script>
