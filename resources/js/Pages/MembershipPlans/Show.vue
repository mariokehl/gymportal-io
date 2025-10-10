<template>
  <AppLayout :title="membershipPlan.name">
    <template #header>
      Vertrag Details
    </template>

    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm">
      <Link :href="route('contracts.index')" class="text-indigo-600 hover:text-indigo-800">
        Verträge
      </Link>
      <span class="text-gray-500 mx-2">/</span>
      <span class="text-gray-900">{{ membershipPlan.name }}</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Main Content -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Plan Details Card -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <div class="flex justify-between items-start mb-6">
            <div>
              <div class="flex items-center space-x-3 mb-2">
                <h1 class="text-2xl font-bold text-gray-900">{{ membershipPlan.name }}</h1>
                <span
                  :class="{
                    'bg-green-100 text-green-800': membershipPlan.is_active,
                    'bg-gray-100 text-gray-800': !membershipPlan.is_active
                  }"
                  class="px-3 py-1 rounded-full text-sm font-medium"
                >
                  {{ membershipPlan.is_active ? 'Aktiv' : 'Inaktiv' }}
                </span>
              </div>
              <p v-if="membershipPlan.description" class="text-gray-600">
                {{ membershipPlan.description }}
              </p>
            </div>
            <div v-if="isOwnerOrAdmin" class="flex space-x-2">
              <Link
                :href="route('contracts.edit', membershipPlan.id)"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center space-x-2"
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
                <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Preisgestaltung</h3>
                <div class="mt-2">
                  <div class="text-3xl font-bold text-gray-900">
                    {{ formatPrice(membershipPlan.price) }}
                  </div>
                  <div class="text-sm text-gray-600">
                    pro {{ formatBillingCycle(membershipPlan.billing_cycle) }}
                  </div>
                  <div v-if="membershipPlan.setup_fee && membershipPlan.setup_fee > 0" class="mt-2">
                    <div class="text-lg font-semibold text-orange-600">
                      {{ formatPrice(membershipPlan.setup_fee) }}
                    </div>
                    <div class="text-sm text-gray-600">
                      Aktivierungsgebühr (einmalig)
                    </div>
                  </div>
                </div>
              </div>

              <div>
                <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Abrechnungszyklus</h3>
                <p class="mt-1 text-lg text-gray-900 capitalize">
                  {{ formatBillingCycle(membershipPlan.billing_cycle) }}
                </p>
              </div>
            </div>

            <div class="space-y-4">
              <div>
                <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Mindestlaufzeit</h3>
                <p class="mt-1 text-lg text-gray-900">
                  {{ membershipPlan.commitment_months
                    ? `${membershipPlan.commitment_months} Monat${membershipPlan.commitment_months !== 1 ? 'e' : ''}`
                    : 'Keine Mindestlaufzeit' }}
                </p>
              </div>

              <div>
                <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Kündigungsfrist</h3>
                <p class="mt-1 text-lg text-gray-900">
                  {{ membershipPlan.cancellation_period_days }} Tag{{ membershipPlan.cancellation_period_days !== 1 ? 'e' : '' }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Active Members Card -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold text-gray-900">Aktive Mitgliedschaften</h2>
            <span class="bg-indigo-100 text-indigo-800 px-3 py-1 rounded-full text-sm font-medium">
              {{ activeMembersCount }} {{ activeMembersCount === 1 ? 'Mitglied' : 'Mitglieder' }}
            </span>
          </div>

          <div v-if="activeMembersCount > 0" class="space-y-3">
            <div
              v-for="membership in activeMemberships"
              :key="membership.id"
              class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
            >
              <div class="flex items-center space-x-3">
                <div class="w-8 h-8 bg-indigo-500 rounded-full text-white flex items-center justify-center text-sm font-semibold">
                  {{ getUserInitials(membership.member) }}
                </div>
                <div>
                  <p class="font-medium text-gray-900">
                    {{ membership.member.first_name }} {{ membership.member.last_name }}
                  </p>
                  <p class="text-sm text-gray-600">{{ membership.member.email }}</p>
                </div>
              </div>
              <div class="text-right">
                <span class="text-sm text-gray-500">Mitglied seit</span>
                <p class="text-sm font-medium text-gray-900">
                  {{ formatDate(membership.member.created_at) }}
                </p>
              </div>
            </div>
          </div>

          <div v-else class="text-center py-8">
            <Users class="w-12 h-12 text-gray-400 mx-auto mb-3" />
            <p class="text-gray-600">Keine aktiven Mitgliedschaften für diesen Vertrag</p>
          </div>
        </div>
      </div>

      <!-- Sidebar -->
      <div class="space-y-6">
        <!-- Quick Stats -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Statistiken</h3>
          <div class="space-y-4">
            <div class="flex justify-between items-center">
              <span class="text-gray-600">Aktive Mitglieder</span>
              <span class="text-2xl font-bold text-indigo-600">{{ activeMembersCount }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-gray-600">Monatlicher Umsatz</span>
              <span class="text-lg font-semibold text-green-600" :class="{ 'blur-sm select-none': !isOwnerOrAdmin }">
                {{ formatMonthlyRevenue() }}
              </span>
            </div>
            <div v-if="membershipPlan.setup_fee && membershipPlan.setup_fee > 0" class="flex justify-between items-center">
              <span class="text-gray-600">Aktivierungsgebühr</span>
              <span class="text-lg font-semibold text-orange-600">
                {{ formatPrice(membershipPlan.setup_fee) }}
              </span>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div v-if="isOwnerOrAdmin" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Aktionen</h3>
          <div class="space-y-3">
            <Link
              :href="route('contracts.edit', membershipPlan.id)"
              class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center justify-center space-x-2"
            >
              <Edit class="w-4 h-4" />
              <span>Bearbeiten</span>
            </Link>

            <button
              @click="confirmDelete"
              class="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center justify-center space-x-2"
            >
              <Trash2 class="w-4 h-4" />
              <span>Löschen</span>
            </button>
          </div>
        </div>

        <!-- Plan Details -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Vertragsinformationen</h3>
          <div class="space-y-3 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-600">Erstellt am:</span>
              <span class="font-medium">{{ formatDate(membershipPlan.created_at) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Zuletzt geändert:</span>
              <span class="font-medium">{{ formatDate(membershipPlan.updated_at) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50">
      <div class="relative top-20 mx-auto p-5 border border-gray-50 w-96 shadow-lg rounded-md bg-white">
        <div class="flex items-center space-x-3 mb-4">
          <div class="bg-red-100 p-2 rounded-full">
            <AlertTriangle class="w-6 h-6 text-red-600" />
          </div>
          <h3 class="text-lg font-medium text-gray-900">Vertrag löschen</h3>
        </div>

        <div v-if="deleteInfo.canDelete" class="mb-6">
          <p class="text-gray-600">
            Sind Sie sicher, dass Sie den Vertrag "<strong>{{ membershipPlan.name }}</strong>" löschen möchten?
            Diese Aktion kann nicht rückgängig gemacht werden.
          </p>
        </div>

        <div v-else class="mb-6">
          <p class="text-gray-600 mb-4">
            Der Vertrag "<strong>{{ membershipPlan.name }}</strong>" kann nicht gelöscht werden,
            da noch <strong>{{ deleteInfo.activeMembersCount }}</strong> aktive Mitglieder diesen nutzen.
          </p>
          <p class="text-sm text-gray-600">
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
import { ref, computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Edit, Trash2, Users, AlertTriangle } from 'lucide-vue-next'
import { formatPrice, formatDateLong as formatDate, formatBillingCycle } from '@/utils/formatters'

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
