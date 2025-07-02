<template>
  <AppLayout title="Verträge">
    <template #header>
      Verträge verwalten
    </template>

    <!-- Flash Messages -->
    <div v-if="flash?.message" class="mb-6">
      <div
        :class="{
          'bg-green-50 border-green-200 text-green-800': flash.type === 'success',
          'bg-red-50 border-red-200 text-red-800': flash.type === 'error',
          'bg-blue-50 border-blue-200 text-blue-800': flash.type === 'info'
        }"
        class="border rounded-lg p-4"
      >
        {{ flash.message }}
      </div>
    </div>

    <!-- Header with Add Button -->
    <div class="mb-6 flex justify-between items-center">
      <div>
        <h2 class="text-2xl font-semibold text-gray-900">Mitgliedschaftspläne</h2>
        <p class="text-gray-600 mt-1">Verwalten Sie die Vertragsoptionen für Ihr Fitnessstudio</p>
      </div>
      <Link
        :href="route('contracts.create')"
        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition-colors"
      >
        <Plus class="w-4 h-4" />
        <span>Neuer Vertrag</span>
      </Link>
    </div>

    <!-- Membership Plans Grid -->
    <div v-if="membershipPlans.length > 0" class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
      <div
        v-for="plan in membershipPlans"
        :key="plan.id"
        class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow"
      >
        <!-- Plan Header -->
        <div class="flex justify-between items-start mb-4">
          <div class="flex-1">
            <div class="flex items-center space-x-2">
              <h3 class="font-semibold text-lg text-gray-900">{{ plan.name }}</h3>
              <span
                :class="{
                  'bg-green-100 text-green-800': plan.is_active,
                  'bg-gray-100 text-gray-800': !plan.is_active
                }"
                class="px-2 py-1 rounded-full text-xs font-medium"
              >
                {{ plan.is_active ? 'Aktiv' : 'Inaktiv' }}
              </span>
            </div>
            <p v-if="plan.description" class="text-gray-600 text-sm mt-1">{{ plan.description }}</p>
          </div>
        </div>

        <!-- Plan Details -->
        <div class="space-y-3 mb-6">
          <div class="flex justify-between">
            <span class="text-gray-600 text-sm">Preis:</span>
            <span class="font-medium">{{ formatPrice(plan.price) }} / {{ formatBillingCycle(plan.billing_cycle) }}</span>
          </div>

          <div v-if="plan.commitment_months" class="flex justify-between">
            <span class="text-gray-600 text-sm">Mindestlaufzeit:</span>
            <span class="font-medium">{{ plan.commitment_months }} Monat{{ plan.commitment_months !== 1 ? 'e' : '' }}</span>
          </div>

          <div class="flex justify-between">
            <span class="text-gray-600 text-sm">Kündigungsfrist:</span>
            <span class="font-medium">{{ plan.cancellation_period_days }} Tag{{ plan.cancellation_period_days !== 1 ? 'e' : '' }}</span>
          </div>

          <div class="flex justify-between">
            <span class="text-gray-600 text-sm">Aktive Mitglieder:</span>
            <span class="font-medium text-blue-600">{{ plan.members_count || 0 }}</span>
          </div>
        </div>

        <!-- Plan Actions -->
        <div class="flex space-x-2">
          <Link
            :href="route('contracts.show', plan.id)"
            class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded-md text-sm font-medium text-center transition-colors"
          >
            Anzeigen
          </Link>
          <Link
            :href="route('contracts.edit', plan.id)"
            class="flex-1 bg-blue-100 hover:bg-blue-200 text-blue-700 px-3 py-2 rounded-md text-sm font-medium text-center transition-colors"
          >
            Bearbeiten
          </Link>
          <button
            @click="confirmDelete(plan)"
            class="flex-1 bg-red-100 hover:bg-red-200 text-red-700 px-3 py-2 rounded-md text-sm font-medium transition-colors"
          >
            Löschen
          </button>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-12">
      <FilePlus class="w-16 h-16 text-gray-400 mx-auto mb-4" />
      <h3 class="text-lg font-medium text-gray-900 mb-2">Keine Verträge vorhanden</h3>
      <p class="text-gray-600 mb-6">Erstellen Sie Ihren ersten Mitgliedschaftsplan für Ihr Fitnessstudio.</p>
      <Link
        :href="route('contracts.create')"
        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg inline-flex items-center space-x-2 transition-colors"
      >
        <Plus class="w-5 h-5" />
        <span>Ersten Vertrag erstellen</span>
      </Link>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="fixed inset-0 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg max-w-md w-full mx-4 p-6">
        <div class="flex items-center space-x-3 mb-4">
          <div class="bg-red-100 p-2 rounded-full">
            <AlertTriangle class="w-6 h-6 text-red-600" />
          </div>
          <h3 class="text-lg font-medium text-gray-900">Vertrag löschen</h3>
        </div>

        <div v-if="deleteInfo.canDelete" class="mb-6">
          <p class="text-gray-600">
            Sind Sie sicher, dass Sie den Vertrag "<strong>{{ planToDelete?.name }}</strong>" löschen möchten?
            Diese Aktion kann nicht rückgängig gemacht werden.
          </p>
        </div>

        <div v-else class="mb-6">
          <p class="text-gray-600 mb-4">
            Der Vertrag "<strong>{{ planToDelete?.name }}</strong>" kann nicht gelöscht werden,
            da noch <strong>{{ deleteInfo.activeMembersCount }}</strong> aktive Mitglieder diesen nutzen:
          </p>
          <div class="bg-gray-50 rounded-lg p-3 max-h-32 overflow-y-auto">
            <ul class="text-sm text-gray-700 space-y-1">
              <li v-for="member in deleteInfo.activeMembers" :key="member.id">
                {{ member.name }} ({{ member.email }})
              </li>
            </ul>
          </div>
          <p class="text-sm text-gray-600 mt-3">
            Bitte kündigen oder wechseln Sie diese Mitgliedschaften zuerst, bevor Sie den Vertrag löschen.
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
            v-if="deleteInfo.canDelete"
            @click="deletePlan"
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
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Plus, FilePlus, AlertTriangle } from 'lucide-vue-next'

// Props
const props = defineProps({
  membershipPlans: Array,
  flash: Object
})

// Reactive data
const showDeleteModal = ref(false)
const planToDelete = ref(null)
const deleteInfo = ref({ canDelete: true, activeMembersCount: 0, activeMembers: [] })

// Methods
const formatPrice = (price) => {
  return new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency: 'EUR'
  }).format(price)
}

const formatBillingCycle = (cycle) => {
  const cycles = {
    monthly: 'Monat',
    quarterly: 'Quartal',
    yearly: 'Jahr'
  }
  return cycles[cycle] || cycle
}

const confirmDelete = async (plan) => {
  planToDelete.value = plan

  try {
    const response = await fetch(route('contracts.check-deletion', plan.id))
    const data = await response.json()
    deleteInfo.value = data
    showDeleteModal.value = true
  } catch (error) {
    console.error('Error checking deletion:', error)
    deleteInfo.value = { canDelete: true, activeMembersCount: 0, activeMembers: [] }
    showDeleteModal.value = true
  }
}

const deletePlan = () => {
  if (planToDelete.value) {
    router.delete(route('contracts.destroy', planToDelete.value.id))
    closeDeleteModal()
  }
}

const closeDeleteModal = () => {
  showDeleteModal.value = false
  planToDelete.value = null
  deleteInfo.value = { canDelete: true, activeMembersCount: 0, activeMembers: [] }
}
</script>
