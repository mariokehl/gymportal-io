<template>
  <div class="space-y-6">
    <!-- Active Memberships Section -->
    <div class="space-y-4">
      <div class="flex justify-between items-center">
        <h3 class="text-lg font-semibold text-gray-900">Mitgliedschaften</h3>
        <div class="flex gap-2">
          <button
            @click="openFreePeriodModal"
            type="button"
            class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2"
          >
            <Gift class="w-4 h-4" />
            Gratis-Zeitraum
          </button>
          <button
            @click="openAddMembershipModal"
            type="button"
            class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 flex items-center gap-2"
          >
            <Plus class="w-4 h-4" />
            Neue Mitgliedschaft
          </button>
        </div>
      </div>

      <!-- Active Memberships List -->
      <div v-if="activeMemberships.length > 0" class="space-y-4">
        <div v-for="membership in activeMemberships" :key="membership.id" class="border border-gray-200 rounded-lg p-4">
          <div class="flex justify-between items-start">
            <div>
              <h4 class="text-lg font-semibold">
                <span v-if="membership.membership_plan?.deleted_at" class="text-red-600">Gelöschter Vertrag: </span>
                {{ membership.membership_plan?.name || 'Unbekannter Vertrag' }}
                <span :class="getStatusBadgeClass(membership.status)" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ml-1">
                  {{ getStatusText(membership.status) }}
                </span>
              </h4>
              <p class="text-gray-600">{{ membership.membership_plan?.description || 'Keine Beschreibung verfügbar' }}</p>
              <div class="mt-2 space-y-1">
                <p class="text-sm"><span class="font-medium">Laufzeit:</span> {{ formatDate(membership.start_date) }} - {{ formatDate(membership.end_date) }}</p>
                <p v-if="membership.membership_plan?.commitment_months" class="text-sm">
                  <span class="font-medium">Mindestlaufzeit:</span> {{ membership.membership_plan.commitment_months }} Monate
                </p>
                <p v-if="membership.membership_plan?.cancellation_period_days" class="text-sm">
                  <span class="font-medium">Kündigungsfrist:</span> {{ membership.membership_plan.cancellation_period_days }} Tage
                </p>
                <p v-if="membership.cancellation_date" class="text-sm text-red-600">
                  <span class="font-medium">Gekündigt zum:</span> {{ formatDate(membership.cancellation_date) }}
                </p>
              </div>
            </div>
            <div class="flex flex-col items-end">
              <!-- Action buttons for memberships -->
              <div v-if="membership.status === 'active' || membership.status === 'paused' || membership.status === 'pending'" class="flex items-center justify-end gap-2 sm:gap-3 mb-3">
                <!-- Activate pending membership -->
                <button
                  v-if="membership.status === 'pending'"
                  @click="$emit('activate', membership)"
                  type="button"
                  class="text-sm text-green-600 hover:text-green-800 font-medium flex items-center gap-1 transition-colors"
                  :disabled="activatingMembership === membership.id"
                >
                  <CheckCircle class="w-4 h-4" />
                  <span>{{ activatingMembership === membership.id ? 'Wird aktiviert...' : 'Aktivieren' }}</span>
                </button>

                <!-- Pause button (not available for free trial periods) -->
                <button
                  v-if="membership.status === 'active' && !membership.cancellation_date && !membership.is_free_trial"
                  @click="$emit('pause', membership)"
                  type="button"
                  class="text-sm text-yellow-600 hover:text-yellow-800 font-medium flex items-center gap-1 transition-colors"
                  :disabled="pausingMembership === membership.id"
                >
                  <Clock class="w-4 h-4" />
                  <span class="hidden sm:inline">{{ pausingMembership === membership.id ? 'Wird stillgelegt...' : 'Stilllegen' }}</span>
                  <span class="sm:hidden">{{ pausingMembership === membership.id ? '...' : 'Pause' }}</span>
                </button>

                <!-- Continue button -->
                <button
                  v-if="membership.status === 'paused'"
                  @click="$emit('resume', membership)"
                  type="button"
                  class="text-sm text-green-600 hover:text-green-800 font-medium flex items-center gap-1 transition-colors"
                  :disabled="resumingMembership === membership.id"
                >
                  <PlayCircle class="w-4 h-4" />
                  <span class="hidden sm:inline">{{ resumingMembership === membership.id ? 'Wird aktiviert...' : 'Fortsetzen' }}</span>
                  <span class="sm:hidden">{{ resumingMembership === membership.id ? '...' : 'Weiter' }}</span>
                </button>

                <!-- Dividing line (only show when pause/cancel buttons are visible, not for free trials) -->
                <div v-if="(membership.status === 'active' || membership.status === 'paused') && !membership.cancellation_date && !membership.is_free_trial" class="hidden sm:block w-px h-4 bg-gray-300"></div>

                <!-- Cancel button (not available for free trial periods) -->
                <button
                  v-if="!membership.cancellation_date && membership.status !== 'pending' && !membership.is_free_trial"
                  @click="$emit('cancel', membership)"
                  type="button"
                  class="text-sm text-red-600 hover:text-red-800 font-medium flex items-center gap-1 transition-colors"
                  :disabled="cancellingMembership === membership.id"
                >
                  <XCircle class="w-4 h-4" />
                  <span class="hidden sm:inline">{{ cancellingMembership === membership.id ? 'Wird gekündigt...' : 'Kündigen' }}</span>
                  <span class="sm:hidden">{{ cancellingMembership === membership.id ? '...' : 'Kündigen' }}</span>
                </button>

                <!-- Stop button (only for free trial periods) -->
                <button
                  v-if="membership.is_free_trial && membership.status === 'active'"
                  @click="$emit('abort', membership)"
                  type="button"
                  class="text-sm text-red-600 hover:text-red-800 font-medium flex items-center gap-1 transition-colors"
                  :disabled="abortingMembership === membership.id"
                >
                  <StopCircle class="w-4 h-4" />
                  <span class="hidden sm:inline">{{ abortingMembership === membership.id ? 'Wird gestoppt...' : 'Stoppen' }}</span>
                  <span class="sm:hidden">{{ abortingMembership === membership.id ? '...' : 'Stopp' }}</span>
                </button>

                <!-- Cancel cancellation button -->
                <button
                  v-if="membership.cancellation_date"
                  @click="$emit('revoke-cancellation', membership)"
                  type="button"
                  class="text-sm text-blue-600 hover:text-blue-800 font-medium flex items-center gap-1 transition-colors"
                  :disabled="revokingCancellation === membership.id"
                >
                  <RotateCcw class="w-4 h-4" />
                  <span class="hidden sm:inline">{{ revokingCancellation === membership.id ? 'Wird zurückgenommen...' : 'Kündigung zurücknehmen' }}</span>
                  <span class="sm:hidden">{{ revokingCancellation === membership.id ? '...' : 'Zurück' }}</span>
                </button>
              </div>

              <!-- Price display -->
              <div class="text-right">
                <p class="text-2xl font-bold text-indigo-600">{{ formatCurrency(membership.membership_plan?.price || 0) }}</p>
                <p class="text-sm text-gray-500">pro {{ getBillingCycleText(membership.membership_plan?.billing_cycle || 'monthly') }}</p>
              </div>
            </div>
          </div>

          <div v-if="membership.membership_plan?.deleted_at" class="mt-3 p-3 bg-red-50 rounded-md">
            <p class="text-sm text-red-800">
              <AlertCircle class="w-4 h-4 inline mr-1" />
              Der Vertragsplan wurde gelöscht. Die Mitgliedschaft bleibt jedoch bestehen.
            </p>
          </div>

          <div v-if="membership.status === 'pending'" class="mt-3 p-3 bg-orange-50 rounded-md">
            <p class="text-sm text-orange-800">
              <AlertCircle class="w-4 h-4 inline mr-1" />
              Diese Mitgliedschaft wartet auf Aktivierung
            </p>
          </div>

          <div v-if="membership.pause_start_date" class="mt-3 p-3 bg-yellow-50 rounded-md">
            <p class="text-sm text-yellow-800">
              <Clock class="w-4 h-4 inline mr-1" />
              Pausiert vom {{ formatDate(membership.pause_start_date) }} bis {{ formatDate(membership.pause_end_date) }}
            </p>
          </div>

          <div v-if="membership.cancellation_date" class="mt-3 p-3 bg-red-50 rounded-md">
            <p class="text-sm text-red-800">
              <AlertCircle class="w-4 h-4 inline mr-1" />
              Kündigung wirksam zum {{ formatDate(membership.cancellation_date) }}
              <span v-if="membership.cancellation_reason" class="block mt-1">
                Grund: {{ membership.cancellation_reason }}
              </span>
            </p>
          </div>
        </div>
      </div>

      <!-- No Memberships -->
      <div v-if="activeMemberships.length === 0" class="text-center py-8">
        <UserX class="w-12 h-12 text-gray-400 mx-auto mb-4" />
        <p class="text-gray-500">Keine aktiven Mitgliedschaften vorhanden</p>
      </div>
    </div>

    <!-- Past Memberships Section -->
    <div v-if="pastMemberships.length > 0" class="space-y-4">
      <div class="flex justify-between items-center">
        <h3 class="text-lg font-semibold text-gray-900">Vergangene Mitgliedschaften</h3>
        <button
          @click="showPastMemberships = !showPastMemberships"
          class="text-gray-500 hover:text-gray-700 flex items-center gap-1 text-sm"
        >
          <span>{{ showPastMemberships ? 'Ausblenden' : 'Anzeigen' }}</span>
          <ChevronDown v-if="!showPastMemberships" class="w-4 h-4" />
          <ChevronUp v-else class="w-4 h-4" />
        </button>
      </div>

      <div v-if="showPastMemberships" class="space-y-4">
        <div
          v-for="membership in pastMemberships"
          :key="membership.id"
          class="border border-gray-200 rounded-lg p-4 bg-gray-50"
        >
          <div class="flex justify-between items-start">
            <div>
              <h4 class="text-lg font-semibold text-gray-700">
                <span v-if="membership.membership_plan?.deleted_at" class="text-red-500">Gelöschter Vertrag: </span>
                {{ membership.membership_plan?.name || 'Unbekannter Vertrag' }}
                <span :class="getStatusBadgeClass(membership.status)" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ml-1">
                  {{ getStatusText(membership.status) }}
                </span>
              </h4>
              <p class="text-gray-500">{{ membership.membership_plan?.description || 'Keine Beschreibung verfügbar' }}</p>
              <div class="mt-2 space-y-1">
                <p class="text-sm text-gray-600">
                  <span class="font-medium">Laufzeit:</span>
                  {{ formatDate(membership.start_date) }} - {{ formatDate(membership.end_date || membership.cancellation_date) }}
                </p>
                <p v-if="membership.cancellation_date" class="text-sm text-red-600">
                  <span class="font-medium">Gekündigt zum:</span> {{ formatDate(membership.cancellation_date) }}
                </p>
                <p v-if="membership.cancellation_reason" class="text-sm text-gray-500">
                  <span class="font-medium">Grund:</span> {{ membership.cancellation_reason }}
                </p>
              </div>
            </div>
            <div class="text-right">
              <p class="text-xl font-bold text-gray-500">{{ formatCurrency(membership.membership_plan?.price || 0) }}</p>
              <p class="text-sm text-gray-400">pro {{ getBillingCycleText(membership.membership_plan?.billing_cycle || 'monthly') }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Free Period Modal -->
    <teleport to="body">
      <div v-if="showFreePeriodModal" class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50" @click="closeFreePeriodModal">
        <div class="relative top-10 mx-auto p-5 border border-gray-50 w-11/12 md:w-1/2 lg:w-1/3 shadow-lg rounded-md bg-white" @click.stop>
          <form @submit.prevent="addFreePeriod">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
              <div class="mb-4">
                <h3 class="text-lg font-medium text-gray-900">
                  Gratis-Zeitraum hinzufügen
                </h3>
                <p class="text-sm text-gray-500 mt-1">
                  Erstelle einen kostenlosen Zeitraum, z.B. für Probetraining oder Überbrückung.
                </p>
              </div>

              <!-- Error message -->
              <div v-if="freePeriodForm.errors.error" class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex">
                  <AlertCircle class="h-5 w-5 text-red-400 flex-shrink-0" />
                  <p class="ml-3 text-sm text-red-800">{{ freePeriodForm.errors.error }}</p>
                </div>
              </div>

              <div class="space-y-4">
                <!-- Start Date -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">
                    Startdatum
                  </label>
                  <input
                    type="date"
                    v-model="freePeriodForm.start_date"
                    class="w-full border border-gray-300 rounded-md shadow-sm px-3 py-2 focus:ring-green-500 focus:border-green-500"
                    :class="{ 'border-red-500': freePeriodForm.errors.start_date }"
                  />
                  <p v-if="freePeriodForm.errors.start_date" class="mt-1 text-sm text-red-600">
                    {{ freePeriodForm.errors.start_date }}
                  </p>
                </div>

                <!-- End Date -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">
                    Enddatum
                  </label>
                  <input
                    type="date"
                    v-model="freePeriodForm.end_date"
                    class="w-full border border-gray-300 rounded-md shadow-sm px-3 py-2 focus:ring-green-500 focus:border-green-500"
                    :class="{ 'border-red-500': freePeriodForm.errors.end_date }"
                  />
                  <p v-if="freePeriodForm.errors.end_date" class="mt-1 text-sm text-red-600">
                    {{ freePeriodForm.errors.end_date }}
                  </p>
                </div>

                <!-- Link to Membership (optional) -->
                <div v-if="linkableMemberships.length > 0">
                  <label class="block text-sm font-medium text-gray-700 mb-1">
                    Mit Mitgliedschaft verknüpfen (optional)
                  </label>
                  <select
                    v-model="freePeriodForm.linked_membership_id"
                    class="w-full border border-gray-300 rounded-md shadow-sm px-3 py-2 focus:ring-green-500 focus:border-green-500"
                  >
                    <option :value="null">Keine Verknüpfung</option>
                    <option v-for="m in linkableMemberships" :key="m.id" :value="m.id">
                      {{ m.membership_plan?.name || 'Unbekannt' }} (ab {{ formatDate(m.start_date) }})
                    </option>
                  </select>
                  <p class="mt-1 text-xs text-gray-500">
                    Verknüpfe diesen Gratis-Zeitraum mit einer bestehenden oder neuen Mitgliedschaft.
                  </p>
                </div>

                <!-- Preview -->
                <div v-if="freePeriodForm.start_date && freePeriodForm.end_date" class="bg-green-50 border border-green-200 rounded-lg p-3">
                  <div class="flex items-start gap-2">
                    <Gift class="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" />
                    <div class="text-sm">
                      <p class="font-medium text-green-800">Vorschau</p>
                      <p class="text-green-700">
                        Gratis-Zeitraum vom {{ formatDateShort(freePeriodForm.start_date) }}
                        bis {{ formatDateShort(freePeriodForm.end_date) }}
                        ({{ freePeriodDays }} Tage)
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
              <button
                type="submit"
                :disabled="freePeriodForm.processing || !freePeriodForm.start_date || !freePeriodForm.end_date"
                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {{ freePeriodForm.processing ? 'Wird erstellt...' : 'Gratis-Zeitraum erstellen' }}
              </button>
              <button
                type="button"
                @click="closeFreePeriodModal"
                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
              >
                Abbrechen
              </button>
            </div>
          </form>
        </div>
      </div>
    </teleport>

    <!-- Add Membership Modal -->
    <teleport to="body">
      <div v-if="showAddMembershipModal" class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50" @click="closeAddMembershipModal">
        <div class="relative top-10 mx-auto p-5 border border-gray-50 w-11/12 md:w-3/4 lg:w-2/3 xl:w-1/2 shadow-lg rounded-md bg-white" @click.stop>
          <form @submit.prevent="addMembership">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
              <div class="mb-4">
                <h3 class="text-lg font-medium text-gray-900">
                  Neue Mitgliedschaft hinzufügen
                </h3>
              </div>

              <!-- Error message -->
              <div v-if="addMembershipForm.errors.membership" class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex">
                  <AlertCircle class="h-5 w-5 text-red-400 flex-shrink-0" />
                  <p class="ml-3 text-sm text-red-800">{{ addMembershipForm.errors.membership }}</p>
                </div>
              </div>

              <MembershipFormSection
                v-model="membershipFormData"
                :membership-plans="membershipPlans"
                :errors="addMembershipForm.errors"
              />
            </div>

            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
              <button
                type="submit"
                :disabled="addMembershipForm.processing || !membershipFormData.membership_plan_id"
                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {{ addMembershipForm.processing ? 'Wird erstellt...' : 'Mitgliedschaft erstellen' }}
              </button>
              <button
                type="button"
                @click="closeAddMembershipModal"
                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
              >
                Abbrechen
              </button>
            </div>
          </form>
        </div>
      </div>
    </teleport>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import {
  Plus, Clock, CheckCircle, XCircle, PlayCircle, StopCircle,
  RotateCcw, AlertCircle, UserX, ChevronDown, ChevronUp, Gift
} from 'lucide-vue-next'
import { formatCurrency, formatDate } from '@/utils/formatters'
import MembershipFormSection from '@/Components/Members/MembershipFormSection.vue'

const props = defineProps({
  member: {
    type: Object,
    required: true
  },
  membershipPlans: {
    type: Array,
    default: () => []
  },
  pausingMembership: {
    type: [Number, null],
    default: null
  },
  resumingMembership: {
    type: [Number, null],
    default: null
  },
  cancellingMembership: {
    type: [Number, null],
    default: null
  },
  revokingCancellation: {
    type: [Number, null],
    default: null
  },
  activatingMembership: {
    type: [Number, null],
    default: null
  },
  abortingMembership: {
    type: [Number, null],
    default: null
  }
})

const emit = defineEmits(['activate', 'pause', 'resume', 'cancel', 'revoke-cancellation', 'abort'])

// Local state
const showPastMemberships = ref(false)
const showAddMembershipModal = ref(false)
const showFreePeriodModal = ref(false)

// Free period form
const freePeriodForm = useForm({
  start_date: new Date().toISOString().split('T')[0],
  end_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0], // +30 Tage
  linked_membership_id: null
})

// Add membership form
const addMembershipForm = useForm({
  member_id: props.member.id,
  membership_plan_id: null,
  start_date: new Date().toISOString().split('T')[0],
  allow_past_start_date: false,
  billing_anchor_date: ''
})

const membershipFormData = ref({
  membership_plan_id: null,
  start_date: new Date().toISOString().split('T')[0],
  allow_past_start_date: false,
  billing_anchor_date: ''
})

// Computed properties
const activeMemberships = computed(() => {
  if (!props.member.memberships) return []
  return props.member.memberships.filter(m =>
    m.status === 'active' || m.status === 'pending' || m.status === 'paused'
  )
})

const pastMemberships = computed(() => {
  if (!props.member.memberships) return []
  return props.member.memberships.filter(m =>
    m.status === 'cancelled' || m.status === 'expired'
  )
})

// Memberships that can be linked to a free period
const linkableMemberships = computed(() => {
  if (!props.member.memberships) return []
  return props.member.memberships.filter(m =>
    (m.status === 'active' || m.status === 'pending') && !m.linked_free_membership_id
  )
})

// Calculate free period duration in days
const freePeriodDays = computed(() => {
  if (!freePeriodForm.start_date || !freePeriodForm.end_date) return 0
  const start = new Date(freePeriodForm.start_date)
  const end = new Date(freePeriodForm.end_date)
  const diffTime = Math.abs(end - start)
  return Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1
})

// Watch membershipFormData and sync to addMembershipForm
watch(membershipFormData, (newVal) => {
  addMembershipForm.membership_plan_id = newVal.membership_plan_id
  addMembershipForm.start_date = newVal.start_date
  addMembershipForm.allow_past_start_date = newVal.allow_past_start_date
  addMembershipForm.billing_anchor_date = newVal.billing_anchor_date
}, { deep: true })

// Modal functions
const openAddMembershipModal = () => {
  membershipFormData.value = {
    membership_plan_id: null,
    start_date: new Date().toISOString().split('T')[0],
    allow_past_start_date: false,
    billing_anchor_date: ''
  }
  addMembershipForm.reset()
  addMembershipForm.clearErrors()
  showAddMembershipModal.value = true
}

const closeAddMembershipModal = () => {
  showAddMembershipModal.value = false
  addMembershipForm.reset()
  addMembershipForm.clearErrors()
}

const addMembership = () => {
  addMembershipForm.post(route('members.memberships.store', props.member.id), {
    preserveScroll: true,
    onSuccess: () => {
      closeAddMembershipModal()
    }
  })
}

// Free period modal functions
const openFreePeriodModal = () => {
  freePeriodForm.reset()
  freePeriodForm.clearErrors()
  freePeriodForm.start_date = new Date().toISOString().split('T')[0]
  // Default: Ende des aktuellen Monats
  const endOfMonth = new Date()
  endOfMonth.setMonth(endOfMonth.getMonth() + 1)
  endOfMonth.setDate(0) // Letzter Tag des aktuellen Monats
  freePeriodForm.end_date = endOfMonth.toISOString().split('T')[0]
  freePeriodForm.linked_membership_id = null
  showFreePeriodModal.value = true
}

const closeFreePeriodModal = () => {
  showFreePeriodModal.value = false
  freePeriodForm.reset()
  freePeriodForm.clearErrors()
}

const addFreePeriod = () => {
  freePeriodForm.post(route('members.memberships.store-free-period', props.member.id), {
    preserveScroll: true,
    onSuccess: () => {
      closeFreePeriodModal()
    }
  })
}

// Helper function to format date in short German format
const formatDateShort = (dateString) => {
  if (!dateString) return ''
  const date = new Date(dateString)
  return date.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

// Helper functions
const getStatusBadgeClass = (status) => {
  const classes = {
    'active': 'bg-green-100 text-green-800',
    'pending': 'bg-orange-100 text-orange-800',
    'paused': 'bg-yellow-100 text-yellow-800',
    'cancelled': 'bg-red-100 text-red-800',
    'expired': 'bg-gray-100 text-gray-800'
  }
  return classes[status] || 'bg-gray-100 text-gray-800'
}

const getStatusText = (status) => {
  const texts = {
    'active': 'Aktiv',
    'pending': 'Ausstehend',
    'paused': 'Pausiert',
    'cancelled': 'Gekündigt',
    'expired': 'Abgelaufen'
  }
  return texts[status] || status
}

const getBillingCycleText = (cycle) => {
  const cycles = {
    'monthly': 'Monat',
    'quarterly': 'Quartal',
    'yearly': 'Jahr'
  }
  return cycles[cycle] || cycle
}
</script>
