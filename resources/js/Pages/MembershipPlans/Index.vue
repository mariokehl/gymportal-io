<template>
  <AppLayout title="Verträge">
    <template #header>
      Verträge verwalten
    </template>

    <!-- Header with Add Button -->
    <div class="mb-6 flex justify-between items-center">
      <div>
        <h2 class="text-2xl font-semibold text-ink">Mitgliedschaftspläne</h2>
        <p class="text-ink-secondary mt-1">Verwalten Sie die Vertragsoptionen für Ihr Fitnessstudio</p>
      </div>
      <Link
        v-if="isOwnerOrAdmin"
        :href="route('contracts.create')"
        class="bg-brand hover:bg-brand-strong text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition-colors"
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
        :class="isLegacyPlan(plan) ? 'border-2 border-dashed border-warning' : 'border border-line'"
        class="bg-surface rounded-lg shadow-sm p-6 hover:shadow-md transition-shadow flex flex-col"
      >
        <!-- Plan Header -->
        <div class="flex justify-between items-start mb-4">
          <div class="flex-1">
            <div class="flex items-center space-x-2">
              <h3 class="font-semibold text-lg text-ink">{{ plan.name }}</h3>
              <span
                v-if="plan.is_free_trial_plan"
                class="px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800"
              >
                Gratis-Test
              </span>
              <span
                v-else
                :class="{
                  'bg-success-soft text-success-strong': plan.is_active,
                  'bg-fill text-ink': !plan.is_active
                }"
                class="px-2 py-1 rounded-full text-xs font-medium"
              >
                {{ plan.is_active ? 'Aktiv' : 'Inaktiv' }}
              </span>
              <span
                v-if="plan.start_date_mode === 'fixed' && plan.fixed_start_date"
                class="px-2 py-1 rounded-full text-xs font-medium bg-info-soft text-info-strong"
              >
                Start: {{ formatDate(plan.fixed_start_date) }}
              </span>
              <span
                v-if="isLegacyPlan(plan)"
                class="px-2 py-1 rounded-full text-xs font-medium bg-warning-soft text-warning-strong"
              >
                Altvertrag
              </span>
            </div>
            <p v-if="plan.description" class="text-ink-secondary text-sm mt-1">{{ plan.description }}</p>
          </div>
        </div>

        <!-- Plan Details -->
        <div class="space-y-3 mb-6 flex-grow">
          <!-- Regular plan fields - hidden for free trial plans -->
          <template v-if="!plan.is_free_trial_plan">
            <div class="flex justify-between">
              <span class="text-ink-secondary text-sm">Preis:</span>
              <span class="font-medium">{{ formatPrice(plan.price) }} / {{ formatBillingCycle(plan.billing_cycle) }}</span>
            </div>

            <div v-if="plan.setup_fee && plan.setup_fee > 0" class="flex justify-between">
              <span class="text-ink-secondary text-sm">Aktivierungsgebühr:</span>
              <span class="font-medium text-orange-600">{{ formatPrice(plan.setup_fee) }}</span>
            </div>

            <div v-if="plan.commitment_months" class="flex justify-between">
              <span class="text-ink-secondary text-sm">Mindestlaufzeit:</span>
              <span class="font-medium">{{ plan.commitment_months }} Monat{{ plan.commitment_months !== 1 ? 'e' : '' }}</span>
            </div>

            <div class="flex justify-between">
              <span class="text-ink-secondary text-sm">Kündigungsfrist:</span>
              <span class="font-medium">{{ plan.formatted_cancellation_period }}</span>
            </div>

            <div v-if="isLegacyPlan(plan)" class="flex justify-between">
              <span class="text-ink-secondary text-sm">Verlängerung:</span>
              <span class="font-medium">{{ plan.renewal_months }} Monate</span>
            </div>
          </template>

          <div class="flex justify-between">
            <span class="text-ink-secondary text-sm">Aktive Mitglieder:</span>
            <span class="font-medium text-brand">{{ plan.member_count || 0 }}</span>
          </div>
        </div>

        <!-- Legacy plan notice -->
        <div v-if="isLegacyPlan(plan)" class="mb-4 flex items-start gap-2 rounded-md bg-warning-subtle p-3 text-xs text-warning-strong">
          <History class="w-4 h-4 shrink-0" />
          <span>Nicht mehr online abschließbar.</span>
        </div>

        <!-- Plan Actions -->
        <div class="flex space-x-2 mt-auto">
          <Link
            :href="route('contracts.show', plan.id)"
            :class="isOwnerOrAdmin ? 'flex-1' : 'w-full'"
            class="bg-fill hover:bg-fill-strong text-ink-secondary px-3 py-2 rounded-md text-sm font-medium text-center transition-colors"
          >
            Anzeigen
          </Link>
          <Link
            v-if="isOwnerOrAdmin"
            :href="route('contracts.edit', plan.id)"
            class="flex-1 bg-brand-soft hover:bg-brand-line text-brand-strong px-3 py-2 rounded-md text-sm font-medium text-center transition-colors"
          >
            Bearbeiten
          </Link>
          <button
            v-if="isOwnerOrAdmin"
            @click="confirmDelete(plan)"
            class="flex-1 bg-danger-soft hover:bg-danger-soft text-danger px-3 py-2 rounded-md text-sm font-medium transition-colors"
          >
            Löschen
          </button>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-12">
      <FilePlus class="w-16 h-16 text-ink-muted mx-auto mb-4" />
      <h3 class="text-lg font-semibold text-ink mb-2">Keine Verträge vorhanden</h3>
      <p class="text-ink-secondary mb-6">{{ isOwnerOrAdmin ? 'Erstellen Sie Ihren ersten Mitgliedschaftsplan für Ihr Fitnessstudio.' : 'Es wurden noch keine Mitgliedschaftspläne erstellt.' }}</p>
      <Link
        v-if="isOwnerOrAdmin"
        :href="route('contracts.create')"
        class="bg-brand hover:bg-brand-strong text-white px-6 py-3 rounded-lg inline-flex items-center space-x-2 transition-colors"
      >
        <Plus class="w-5 h-5" />
        <span>Ersten Vertrag erstellen</span>
      </Link>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50">
      <div class="relative top-20 mx-auto p-5 border border-line-subtle w-96 shadow-lg rounded-md bg-surface">
        <div class="flex items-center space-x-3 mb-4">
          <div class="bg-danger-soft p-2 rounded-full">
            <AlertTriangle class="w-6 h-6 text-danger" />
          </div>
          <h3 class="text-lg font-semibold text-ink">Vertrag löschen</h3>
        </div>

        <div v-if="deleteInfo.canDelete" class="mb-6">
          <p class="text-ink-secondary">
            Sind Sie sicher, dass Sie den Vertrag "<strong>{{ planToDelete?.name }}</strong>" löschen möchten?
            Diese Aktion kann nicht rückgängig gemacht werden.
          </p>
        </div>

        <div v-else class="mb-6">
          <p class="text-ink-secondary mb-4">
            Der Vertrag "<strong>{{ planToDelete?.name }}</strong>" kann nicht gelöscht werden,
            da noch <strong>{{ deleteInfo.activeMembersCount }}</strong> aktive Mitglieder diesen nutzen:
          </p>
          <div class="bg-surface-muted rounded-lg p-3 max-h-32 overflow-y-auto">
            <ul class="text-sm text-ink-secondary space-y-1">
              <li v-for="member in deleteInfo.activeMembers" :key="member.id">
                {{ member.name }} ({{ member.email }})
              </li>
            </ul>
          </div>
          <p class="text-sm text-ink-secondary mt-3">
            Bitte kündigen oder wechseln Sie diese Mitgliedschaften zuerst, bevor Sie den Vertrag löschen.
          </p>
        </div>

        <div class="flex space-x-3">
          <button
            @click="closeDeleteModal"
            class="flex-1 bg-fill hover:bg-fill-strong text-ink-secondary px-4 py-2 rounded-lg font-medium transition-colors"
          >
            Abbrechen
          </button>
          <button
            v-if="deleteInfo.canDelete"
            @click="deletePlan"
            class="flex-1 bg-danger-solid hover:bg-danger-solid-strong text-white px-4 py-2 rounded-lg font-medium transition-colors"
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
import AppShell from '@/Layouts/AppShell.vue'
import { Plus, FilePlus, AlertTriangle, History } from '@lucide/vue'
import { formatPrice, formatBillingCycle, formatDate } from '@/utils/formatters'

defineOptions({ layout: AppShell })

const page = usePage()

// Props
const props = defineProps({
  membershipPlans: Array
})

// Computed properties
const isOwnerOrAdmin = computed(() => {
  const user = page.props.auth.user
  return user?.role_id === 1 || user?.role_id === 2
})

// Reactive data
const showDeleteModal = ref(false)
const planToDelete = ref(null)
const deleteInfo = ref({ canDelete: true, activeMembersCount: 0, activeMembers: [] })

// Methods
// Fixed-term renewal plans only cover contracts concluded before 01.03.2022.
const isLegacyPlan = (plan) => plan.auto_renew_type === 'fixed'

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
