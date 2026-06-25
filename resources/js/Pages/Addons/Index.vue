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

    <!-- Flash Messages -->
    <div v-if="flash?.message" class="mb-6">
      <div
        :class="{
          'bg-green-50 border-green-200 text-green-800': flash.type === 'success',
          'bg-red-50 border-red-200 text-red-800': flash.type === 'error',
          'bg-indigo-50 border-indigo-200 text-indigo-800': flash.type === 'info'
        }"
        class="border rounded-lg p-4"
      >
        {{ flash.message }}
      </div>
    </div>

    <!-- Header with Add Button -->
    <div class="mb-6 flex justify-between items-center">
      <div>
        <h2 class="text-2xl font-semibold text-gray-900">Add-ons</h2>
        <p class="text-gray-600 mt-1">
          Zusatzleistungen, die zu einem Vertrag inklusive oder optional buchbar sind
          (z.&nbsp;B. Trainereinweisung). Sie werden einmalig zum Vertragsstart abgerechnet.
        </p>
      </div>
      <Link
        v-if="isOwnerOrAdmin"
        :href="route('contracts.addons.create')"
        class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition-colors"
      >
        <Plus class="w-4 h-4" />
        <span>Neues Add-on</span>
      </Link>
    </div>

    <!-- Add-ons Grid -->
    <div v-if="addons.length > 0" class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
      <div
        v-for="addon in addons"
        :key="addon.id"
        class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow flex flex-col"
      >
        <div class="flex justify-between items-start mb-4">
          <div class="flex-1">
            <div class="flex items-center space-x-2">
              <h3 class="font-semibold text-lg text-gray-900">{{ addon.name }}</h3>
              <span
                :class="{
                  'bg-green-100 text-green-800': addon.is_active,
                  'bg-gray-100 text-gray-800': !addon.is_active
                }"
                class="px-2 py-1 rounded-full text-xs font-medium"
              >
                {{ addon.is_active ? 'Aktiv' : 'Inaktiv' }}
              </span>
            </div>
            <p v-if="addon.description" class="text-gray-600 text-sm mt-1">{{ addon.description }}</p>
          </div>
        </div>

        <div class="space-y-3 mb-6 flex-grow">
          <div class="flex justify-between">
            <span class="text-gray-600 text-sm">Preis:</span>
            <span class="font-medium">{{ formatPrice(addon.price) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600 text-sm">Zahlweise:</span>
            <span class="font-medium">{{ addon.payment_method ? formatPaymentMethod(addon.payment_method) : 'Standard-Zahlungsart' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600 text-sm">Zugeordnete Verträge:</span>
            <span class="font-medium text-indigo-600">{{ addon.membership_plans_count || 0 }}</span>
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
import { Plus, PackagePlus, AlertTriangle } from 'lucide-vue-next'
import { formatPrice } from '@/utils/formatters'

const page = usePage()

defineProps({
  addons: Array,
  flash: Object
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
