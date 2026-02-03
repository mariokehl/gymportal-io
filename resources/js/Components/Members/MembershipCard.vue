<template>
  <div>
    <div class="flex justify-between items-start">
      <div>
        <h4 :class="['text-lg font-semibold', isSecondary ? 'text-gray-700' : '']">
          <span v-if="membership.is_free_trial" class="inline-flex items-center gap-1">
            <Gift class="w-4 h-4 text-green-600" />
            Gratis-Zeitraum
          </span>
          <template v-else>
            <span v-if="membership.membership_plan?.deleted_at" class="text-red-600">Gelöschter Vertrag: </span>
            {{ membership.membership_plan?.name || 'Unbekannter Vertrag' }}
          </template>
          <span :class="getStatusBadgeClass(membership.status)" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ml-1">
            {{ getStatusText(membership.status) }}
          </span>
        </h4>
        <p :class="isSecondary ? 'text-gray-500' : 'text-gray-600'">
          {{ membership.is_free_trial ? 'Kostenloser Testzeitraum' : (membership.membership_plan?.description || 'Keine Beschreibung verfügbar') }}
        </p>
        <div class="mt-2 space-y-1">
          <p class="text-sm"><span class="font-medium">Laufzeit:</span> {{ formatDate(membership.start_date) }} - {{ formatDate(membership.end_date) }}</p>
          <p v-if="membership.membership_plan?.commitment_months" class="text-sm">
            <span class="font-medium">Mindestlaufzeit:</span> {{ membership.membership_plan.commitment_months }} Monate
          </p>
          <p v-if="membership.membership_plan?.cancellation_period" class="text-sm">
            <span class="font-medium">Kündigungsfrist:</span> {{ membership.membership_plan.formatted_cancellation_period }}
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

          <!-- Withdraw button (only for eligible memberships within 14-day period) -->
          <button
            v-if="membership.withdrawal_eligible && !membership.is_free_trial"
            @click="$emit('withdraw', membership)"
            type="button"
            class="text-sm text-purple-600 hover:text-purple-800 font-medium flex items-center gap-1 transition-colors"
            :disabled="withdrawingMembership === membership.id"
            :title="'Widerruf möglich bis ' + formatDate(membership.withdrawal_deadline)"
          >
            <Undo2 class="w-4 h-4" />
            <span class="hidden sm:inline">{{ withdrawingMembership === membership.id ? 'Wird widerrufen...' : 'Widerrufen' }}</span>
            <span class="sm:hidden">{{ withdrawingMembership === membership.id ? '...' : 'Widerruf' }}</span>
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
          <p :class="['text-2xl font-bold', isSecondary ? 'text-gray-500' : 'text-indigo-600']">
            {{ membership.is_free_trial ? 'Gratis' : formatCurrency(membership.membership_plan?.price || 0) }}
          </p>
          <p v-if="!membership.is_free_trial" :class="['text-sm', isSecondary ? 'text-gray-400' : 'text-gray-500']">
            pro {{ getBillingCycleText(membership.membership_plan?.billing_cycle || 'monthly') }}
          </p>
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
</template>

<script setup>
import {
  Clock, CheckCircle, XCircle, PlayCircle, StopCircle,
  RotateCcw, AlertCircle, Gift, Undo2
} from 'lucide-vue-next'
import { formatCurrency, formatDate } from '@/utils/formatters'

defineProps({
  membership: {
    type: Object,
    required: true
  },
  isSecondary: {
    type: Boolean,
    default: false
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
  },
  withdrawingMembership: {
    type: [Number, null],
    default: null
  }
})

defineEmits(['activate', 'pause', 'resume', 'cancel', 'revoke-cancellation', 'abort', 'withdraw'])

// Helper functions
const getStatusBadgeClass = (status) => {
  const classes = {
    'active': 'bg-green-100 text-green-800',
    'pending': 'bg-orange-100 text-orange-800',
    'paused': 'bg-yellow-100 text-yellow-800',
    'cancelled': 'bg-red-100 text-red-800',
    'expired': 'bg-gray-100 text-gray-800',
    'withdrawn': 'bg-purple-100 text-purple-800'
  }
  return classes[status] || 'bg-gray-100 text-gray-800'
}

const getStatusText = (status) => {
  const texts = {
    'active': 'Aktiv',
    'pending': 'Ausstehend',
    'paused': 'Pausiert',
    'cancelled': 'Gekündigt',
    'expired': 'Abgelaufen',
    'withdrawn': 'Widerrufen'
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
