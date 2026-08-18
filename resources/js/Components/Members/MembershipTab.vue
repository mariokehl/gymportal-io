<template>
  <div class="flex flex-col gap-6">
    <!-- Active Memberships Section -->
    <div class="flex flex-col gap-3.5">
      <h3 class="text-lg font-bold text-gray-900">Mitgliedschaften</h3>

      <!-- Primary actions -->
      <div class="grid grid-cols-2 gap-2.5">
        <button
          @click="openFreePeriodModal"
          type="button"
          class="inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-lg bg-green-700 text-white text-sm font-semibold hover:bg-green-800 transition-colors whitespace-nowrap"
        >
          <Gift class="w-[17px] h-[17px] flex-none" />
          <span>Gratis-Zeitraum</span>
        </button>
        <button
          @click="openAddMembershipModal"
          type="button"
          class="inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 transition-colors whitespace-nowrap"
        >
          <Plus class="w-[17px] h-[17px] flex-none" />
          <span class="sm:hidden">Neue</span>
          <span class="hidden sm:inline">Neue Mitgliedschaft</span>
        </button>
      </div>

      <!-- Active Memberships List -->
      <div v-if="activeMemberships.length > 0" class="flex flex-col gap-3.5">
        <template v-for="membership in displayableMemberships" :key="membership.id">
          <!-- Linked memberships: stacked (mobile) / side-by-side (desktop) -->
          <div
            v-if="membership.linkedMembership"
            class="rounded-lg border border-gray-200 bg-white overflow-hidden"
          >
            <div class="flex flex-col lg:grid lg:grid-cols-[1fr_1px_1fr] lg:items-stretch">
              <!-- Free trial period (left/top side) -->
              <MembershipCard
                :membership="membership"
                :is-secondary="!isCurrentlyActive(membership)"
                :resumingMembership="resumingMembership"
                :revokingCancellation="revokingCancellation"
                :activatingMembership="activatingMembership"
                :abortingMembership="abortingMembership"
                :withdrawingMembership="withdrawingMembership"
                :forcingMembershipStatus="forcingMembershipStatus"
                @activate="$emit('activate', $event)"
                @pause="$emit('pause', $event)"
                @resume="$emit('resume', $event)"
                @cancel="$emit('cancel', $event)"
                @revoke-cancellation="$emit('revoke-cancellation', $event)"
                @abort="$emit('abort', $event)"
                @withdraw="$emit('withdraw', $event)"
                @force-status="(m, s) => $emit('force-status', m, s)"
              />

              <!-- Connector: pill (mobile) / vertical divider (desktop) -->
              <div class="flex items-center gap-2.5 px-4 py-1 lg:hidden">
                <span class="flex-1 h-px bg-gray-200"></span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-600 text-xs font-semibold">
                  <Link class="w-3 h-3" /> Verknüpft
                </span>
                <span class="flex-1 h-px bg-gray-200"></span>
              </div>
              <div class="hidden lg:block bg-gray-100"></div>

              <!-- Linked membership (right/bottom side) -->
              <MembershipCard
                :membership="membership.linkedMembership"
                :is-secondary="!isCurrentlyActive(membership.linkedMembership)"
                :resumingMembership="resumingMembership"
                :revokingCancellation="revokingCancellation"
                :activatingMembership="activatingMembership"
                :abortingMembership="abortingMembership"
                :withdrawingMembership="withdrawingMembership"
                :forcingMembershipStatus="forcingMembershipStatus"
                @activate="$emit('activate', $event)"
                @pause="$emit('pause', $event)"
                @resume="$emit('resume', $event)"
                @cancel="$emit('cancel', $event)"
                @revoke-cancellation="$emit('revoke-cancellation', $event)"
                @abort="$emit('abort', $event)"
                @withdraw="$emit('withdraw', $event)"
                @force-status="(m, s) => $emit('force-status', m, s)"
              />
            </div>
          </div>

          <!-- Standalone membership (no link) -->
          <div v-else class="rounded-lg border border-gray-200 bg-white overflow-hidden">
            <MembershipCard
              :membership="membership"
              :is-secondary="false"
              :resumingMembership="resumingMembership"
              :revokingCancellation="revokingCancellation"
              :activatingMembership="activatingMembership"
              :abortingMembership="abortingMembership"
              :withdrawingMembership="withdrawingMembership"
              :forcingMembershipStatus="forcingMembershipStatus"
              @activate="$emit('activate', $event)"
              @pause="$emit('pause', $event)"
              @resume="$emit('resume', $event)"
              @cancel="$emit('cancel', $event)"
              @revoke-cancellation="$emit('revoke-cancellation', $event)"
              @abort="$emit('abort', $event)"
              @withdraw="$emit('withdraw', $event)"
              @force-status="(m, s) => $emit('force-status', m, s)"
            />
          </div>
        </template>
      </div>

      <!-- No Memberships -->
      <div v-if="activeMemberships.length === 0" class="text-center py-8">
        <UserX class="w-12 h-12 text-gray-400 mx-auto mb-4" />
        <p class="text-gray-500">Keine aktiven Mitgliedschaften vorhanden</p>
      </div>
    </div>

    <!-- Past Memberships Section -->
    <div v-if="pastMemberships.length > 0" class="flex flex-col gap-3.5">
      <div class="flex justify-between items-center gap-2.5">
        <h3 class="text-lg font-bold text-gray-900">Frühere Mitgliedschaften</h3>
        <button
          @click="showPastMemberships = !showPastMemberships"
          type="button"
          class="flex-none text-gray-500 hover:text-gray-700 flex items-center gap-1.5 text-sm font-medium transition-colors whitespace-nowrap"
        >
          <span>{{ showPastMemberships ? 'Ausblenden' : 'Einblenden' }}</span>
          <ChevronDown v-if="!showPastMemberships" class="w-4 h-4" />
          <ChevronUp v-else class="w-4 h-4" />
        </button>
      </div>

      <div v-if="showPastMemberships" class="flex flex-col gap-3.5">
        <template v-for="membership in displayablePastMemberships" :key="membership.id">
          <!-- Linked memberships: stacked (mobile) / side-by-side (desktop) -->
          <div
            v-if="membership.linkedMembership"
            class="rounded-lg border border-gray-200 bg-gray-50 overflow-hidden"
          >
            <div class="flex flex-col lg:grid lg:grid-cols-[1fr_1px_1fr] lg:items-stretch">
              <!-- Free trial period (left/top side) -->
              <MembershipCard
                :membership="membership"
                :is-secondary="true"
                @force-status="(m, s) => $emit('force-status', m, s)"
              />

              <!-- Connector: pill (mobile) / vertical divider (desktop) -->
              <div class="flex items-center gap-2.5 px-4 py-1 lg:hidden">
                <span class="flex-1 h-px bg-gray-200"></span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-gray-100 text-gray-500 text-xs font-semibold">
                  <Link class="w-3 h-3" /> Verknüpft
                </span>
                <span class="flex-1 h-px bg-gray-200"></span>
              </div>
              <div class="hidden lg:block bg-gray-200"></div>

              <!-- Linked membership (right/bottom side) -->
              <MembershipCard
                :membership="membership.linkedMembership"
                :is-secondary="true"
                @force-status="(m, s) => $emit('force-status', m, s)"
              />
            </div>
          </div>

          <!-- Standalone past membership (no link) -->
          <div v-else class="rounded-lg border border-gray-200 bg-gray-50 overflow-hidden">
            <MembershipCard
              :membership="membership"
              :is-secondary="true"
              @force-status="(m, s) => $emit('force-status', m, s)"
            />
          </div>
        </template>
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
  Plus, AlertCircle, UserX, ChevronDown, ChevronUp, Gift, Link
} from 'lucide-vue-next'
import { formatDate, getDisplayTimezone } from '@/utils/formatters'
import MembershipFormSection from '@/Components/Members/MembershipFormSection.vue'
import MembershipCard from '@/Components/Members/MembershipCard.vue'

const props = defineProps({
  member: {
    type: Object,
    required: true
  },
  membershipPlans: {
    type: Array,
    default: () => []
  },
  resumingMembership: {
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
  },
  forcingMembershipStatus: {
    type: [Number, null],
    default: null
  },
  withdrawingMembership: {
    type: [Number, null],
    default: null
  }
})

const emit = defineEmits(['activate', 'pause', 'resume', 'cancel', 'revoke-cancellation', 'abort', 'withdraw', 'force-status'])

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
    m.status === 'cancelled' || m.status === 'expired' || m.status === 'withdrawn'
  )
})

// Memberships that can be linked to a free period
const linkableMemberships = computed(() => {
  if (!props.member.memberships) return []
  return props.member.memberships.filter(m =>
    (m.status === 'active' || m.status === 'pending') && !m.linked_free_membership_id
  )
})

// Displayable memberships - groups linked free periods with their memberships
const displayableMemberships = computed(() => {
  if (!activeMemberships.value.length) return []

  const result = []
  const processedIds = new Set()

  // First pass: find all linked pairs (paid membership with linked_free_membership_id)
  for (const membership of activeMemberships.value) {
    if (membership.linked_free_membership_id) {
      const freeTrial = activeMemberships.value.find(m => m.id === membership.linked_free_membership_id)
      if (freeTrial) {
        result.push({
          ...freeTrial,
          linkedMembership: membership
        })
        processedIds.add(membership.id)
        processedIds.add(freeTrial.id)
      }
    }
  }

  // Second pass: add standalone memberships that weren't part of a linked pair
  for (const membership of activeMemberships.value) {
    if (!processedIds.has(membership.id)) {
      result.push(membership)
      processedIds.add(membership.id)
    }
  }

  return result
})

// Displayable past memberships - groups linked free periods with their memberships (same as active)
const displayablePastMemberships = computed(() => {
  if (!pastMemberships.value.length) return []

  const result = []
  const processedIds = new Set()

  // First pass: find all linked pairs (paid membership with linked_free_membership_id)
  for (const membership of pastMemberships.value) {
    if (membership.linked_free_membership_id) {
      const freeTrial = pastMemberships.value.find(m => m.id === membership.linked_free_membership_id)
      if (freeTrial) {
        result.push({
          ...freeTrial,
          linkedMembership: membership
        })
        processedIds.add(membership.id)
        processedIds.add(freeTrial.id)
      }
    }
  }

  // Second pass: add standalone memberships that weren't part of a linked pair
  for (const membership of pastMemberships.value) {
    if (!processedIds.has(membership.id)) {
      result.push(membership)
      processedIds.add(membership.id)
    }
  }

  return result
})

// Check if a membership is currently active (based on date)
const isCurrentlyActive = (membership) => {
  if (!membership) return false
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const startDate = new Date(membership.start_date)
  startDate.setHours(0, 0, 0, 0)
  const endDate = membership.end_date ? new Date(membership.end_date) : null
  if (endDate) endDate.setHours(23, 59, 59, 999)

  const isAfterStart = today >= startDate
  const isBeforeEnd = !endDate || today <= endDate

  return isAfterStart && isBeforeEnd && membership.status === 'active'
}

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
  return date.toLocaleDateString('de-DE', { timeZone: getDisplayTimezone(), day: '2-digit', month: '2-digit', year: 'numeric' })
}
</script>
