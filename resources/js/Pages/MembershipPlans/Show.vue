<template>
  <AppLayout :title="membershipPlan.name">
    <template #header>
      Vertrag Details
    </template>

    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm">
      <Link :href="route('contracts.index')" class="text-brand hover:text-brand-strong">
        Verträge
      </Link>
      <span class="text-ink-muted mx-2">/</span>
      <span class="text-ink">{{ membershipPlan.name }}</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Main Content -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Plan Details Card -->
        <div class="bg-surface rounded-lg shadow-sm border border-line p-6">
          <div class="flex justify-between items-start mb-6">
            <div>
              <div class="flex items-center space-x-3 mb-2">
                <h1 class="text-2xl font-bold text-ink tabular-nums">{{ membershipPlan.name }}</h1>
                <span
                  :class="{
                    'bg-success-soft text-success-strong': membershipPlan.is_active,
                    'bg-fill text-ink': !membershipPlan.is_active
                  }"
                  class="px-3 py-1 rounded-full text-sm font-medium"
                >
                  {{ membershipPlan.is_active ? 'Aktiv' : 'Inaktiv' }}
                </span>
                <span
                  v-if="membershipPlan.start_date_mode === 'fixed' && membershipPlan.fixed_start_date"
                  class="px-3 py-1 rounded-full text-sm font-medium bg-info-soft text-info-strong"
                >
                  Start: {{ formatDate(membershipPlan.fixed_start_date) }}
                </span>
              </div>
              <p v-if="membershipPlan.description" class="text-ink-secondary">
                {{ membershipPlan.description }}
              </p>
            </div>
            <div v-if="isOwnerOrAdmin" class="flex space-x-2">
              <Link
                :href="route('contracts.edit', membershipPlan.id)"
                class="bg-brand hover:bg-brand-strong text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center space-x-2"
              >
                <Edit class="w-4 h-4" />
                <span>Bearbeiten</span>
              </Link>
            </div>
          </div>

          <!-- Plan Information Grid -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-4">
              <div>
                <h3 class="text-sm font-medium text-ink-muted uppercase tracking-wide">Preisgestaltung</h3>
                <div class="mt-2">
                  <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-bold text-ink tabular-nums">
                      {{ formatPrice(membershipPlan.price) }}
                    </span>
                    <span
                      v-if="membershipPlan.original_price && Number(membershipPlan.original_price) > Number(membershipPlan.price)"
                      class="text-lg font-medium text-ink-muted line-through"
                    >
                      {{ formatPrice(membershipPlan.original_price) }}
                    </span>
                  </div>
                  <div class="text-sm text-ink-secondary">
                    pro {{ formatBillingCycle(membershipPlan.billing_cycle) }}
                  </div>
                  <div v-if="membershipPlan.setup_fee && membershipPlan.setup_fee > 0" class="mt-2">
                    <div class="text-lg font-semibold text-orange-600">
                      {{ formatPrice(membershipPlan.setup_fee) }}
                    </div>
                    <div class="text-sm text-ink-secondary">
                      Aktivierungsgebühr (einmalig)
                    </div>
                  </div>
                </div>
              </div>

              <div>
                <h3 class="text-sm font-medium text-ink-muted uppercase tracking-wide">Abrechnungszyklus</h3>
                <p class="mt-1 text-lg text-ink capitalize">
                  {{ formatBillingCycle(membershipPlan.billing_cycle) }}
                </p>
              </div>
            </div>

            <div class="space-y-4">
              <div>
                <h3 class="text-sm font-medium text-ink-muted uppercase tracking-wide">Mindestlaufzeit</h3>
                <p class="mt-1 text-lg text-ink">
                  {{ membershipPlan.commitment_months
                    ? `${membershipPlan.commitment_months} Monat${membershipPlan.commitment_months !== 1 ? 'e' : ''}`
                    : 'Keine Mindestlaufzeit' }}
                </p>
              </div>

              <div>
                <h3 class="text-sm font-medium text-ink-muted uppercase tracking-wide">Kündigungsfrist</h3>
                <p class="mt-1 text-lg text-ink">
                  {{ membershipPlan.formatted_cancellation_period }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Active Members Card -->
        <div class="bg-surface rounded-lg shadow-sm border border-line p-6">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold text-ink">Aktive Mitgliedschaften</h2>
            <span class="bg-brand-soft text-brand-strong px-3 py-1 rounded-full text-sm font-medium">
              {{ activeMembersCount }} {{ activeMembersCount === 1 ? 'Mitglied' : 'Mitglieder' }}
            </span>
          </div>

          <div v-if="activeMembersCount > 0" class="space-y-3">
            <div
              v-for="membership in activeMemberships"
              :key="membership.id"
              class="flex items-center justify-between p-3 bg-surface-muted rounded-lg"
            >
              <div class="flex items-center space-x-3">
                <div class="w-8 h-8 bg-brand rounded-full text-white flex items-center justify-center text-sm font-semibold">
                  {{ getUserInitials(membership.member) }}
                </div>
                <div>
                  <p class="font-medium text-ink">
                    {{ membership.member.first_name }} {{ membership.member.last_name }}
                  </p>
                  <p class="text-sm text-ink-secondary">{{ membership.member.email }}</p>
                </div>
              </div>
              <div class="text-right">
                <span class="text-sm text-ink-muted">Mitglied seit</span>
                <p class="text-sm font-medium text-ink">
                  {{ formatDate(membership.member.created_at) }}
                </p>
              </div>
            </div>
          </div>

          <div v-else class="text-center py-8">
            <Users class="w-12 h-12 text-ink-muted mx-auto mb-3" />
            <p class="text-ink-secondary">Keine aktiven Mitgliedschaften für diesen Vertrag</p>
          </div>
        </div>
      </div>

      <!-- Sidebar -->
      <div class="space-y-6">
        <!-- Quick Stats -->
        <div class="bg-surface rounded-lg shadow-sm border border-line p-6">
          <h3 class="text-lg font-semibold text-ink mb-4">Statistiken</h3>
          <div class="space-y-4">
            <div class="flex justify-between items-center">
              <span class="text-ink-secondary">Aktive Mitglieder</span>
              <span class="text-2xl font-bold text-brand tabular-nums">{{ activeMembersCount }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-ink-secondary">Monatlicher Umsatz</span>
              <span class="text-lg font-semibold text-success" :class="{ 'blur-sm select-none': !isOwnerOrAdmin }">
                {{ formatMonthlyRevenue() }}
              </span>
            </div>
            <div v-if="membershipPlan.setup_fee && membershipPlan.setup_fee > 0" class="flex justify-between items-center">
              <span class="text-ink-secondary">Aktivierungsgebühr</span>
              <span class="text-lg font-semibold text-orange-600">
                {{ formatPrice(membershipPlan.setup_fee) }}
              </span>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div v-if="isOwnerOrAdmin" class="bg-surface rounded-lg shadow-sm border border-line p-6">
          <h3 class="text-lg font-semibold text-ink mb-4">Aktionen</h3>
          <div class="space-y-3">
            <Link
              :href="route('contracts.edit', membershipPlan.id)"
              class="w-full bg-brand hover:bg-brand-strong text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center justify-center space-x-2"
            >
              <Edit class="w-4 h-4" />
              <span>Bearbeiten</span>
            </Link>

            <button
              @click="confirmDelete"
              class="w-full bg-danger-solid hover:bg-danger-solid-strong text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center justify-center space-x-2"
            >
              <Trash2 class="w-4 h-4" />
              <span>Löschen</span>
            </button>
          </div>
        </div>

        <!-- Plan Details -->
        <div class="bg-surface rounded-lg shadow-sm border border-line p-6">
          <h3 class="text-lg font-semibold text-ink mb-4">Vertragsinformationen</h3>
          <div class="space-y-3 text-sm">
            <div class="flex justify-between">
              <span class="text-ink-secondary">Erstellt am:</span>
              <span class="font-medium">{{ formatDate(membershipPlan.created_at) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-ink-secondary">Zuletzt geändert:</span>
              <span class="font-medium">{{ formatDate(membershipPlan.updated_at) }}</span>
            </div>
          </div>
        </div>
      </div>
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
            Sind Sie sicher, dass Sie den Vertrag "<strong>{{ membershipPlan.name }}</strong>" löschen möchten?
            Diese Aktion kann nicht rückgängig gemacht werden.
          </p>
        </div>

        <div v-else class="mb-6">
          <p class="text-ink-secondary mb-4">
            Der Vertrag "<strong>{{ membershipPlan.name }}</strong>" kann nicht gelöscht werden,
            da noch <strong>{{ deleteInfo.activeMembersCount }}</strong> aktive Mitglieder diesen nutzen.
          </p>
          <p class="text-sm text-ink-secondary">
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
import { Edit, Trash2, Users, AlertTriangle } from '@lucide/vue'
import { formatPrice, formatDateLong as formatDate, formatBillingCycle } from '@/utils/formatters'

defineOptions({ layout: AppShell })

const page = usePage()

// Props
const props = defineProps({
  membershipPlan: Object,
  activeMemberships: Array,
  activeMembersCount: Number
})

// Computed properties
const isOwnerOrAdmin = computed(() => {
  const user = page.props.auth.user
  return user?.role_id === 1 || user?.role_id === 2
})

// Reactive data
const showDeleteModal = ref(false)
const deleteInfo = ref({ canDelete: true, activeMembersCount: 0, activeMemberships: [] })

// Methods
const getUserInitials = (user) => {
  const first = user.first_name?.charAt(0) || ''
  const last = user.last_name?.charAt(0) || ''
  return (first + last).toUpperCase()
}

const formatMonthlyRevenue = () => {
  let monthlyRevenue = 0

  if (props.membershipPlan.billing_cycle === 'monthly') {
    monthlyRevenue = props.membershipPlan.price * props.activeMembersCount
  } else if (props.membershipPlan.billing_cycle === 'quarterly') {
    monthlyRevenue = (props.membershipPlan.price * props.activeMembersCount) / 3
  } else if (props.membershipPlan.billing_cycle === 'biannual') {
    monthlyRevenue = (props.membershipPlan.price * props.activeMembersCount) / 6
  } else if (props.membershipPlan.billing_cycle === 'yearly') {
    monthlyRevenue = (props.membershipPlan.price * props.activeMembersCount) / 12
  }

  return formatPrice(monthlyRevenue)
}

const confirmDelete = async () => {
  try {
    const response = await fetch(route('contracts.check-deletion', props.membershipPlan.id))
    const data = await response.json()
    deleteInfo.value = data
    showDeleteModal.value = true
  } catch (error) {
    console.error('Error checking deletion:', error)
    deleteInfo.value = { canDelete: true, activeMembersCount: 0, activeMemberships: [] }
    showDeleteModal.value = true
  }
}

const deletePlan = () => {
  router.delete(route('contracts.destroy', props.membershipPlan.id))
  closeDeleteModal()
}

const closeDeleteModal = () => {
  showDeleteModal.value = false
  deleteInfo.value = { canDelete: true, activeMembersCount: 0, activeMemberships: [] }
}
</script>
