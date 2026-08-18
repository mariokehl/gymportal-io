<template>
  <div class="flex flex-col h-full">
    <!-- Body -->
    <div class="p-4 flex-1">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <span v-if="membership.is_free_trial" :class="['flex', isSecondary ? 'text-gray-400' : 'text-green-700']">
              <Gift class="w-[18px] h-[18px]" />
            </span>
            <h4 :class="['text-lg font-bold leading-tight', isSecondary ? 'text-gray-600' : 'text-gray-900']">
              <span v-if="membership.is_free_trial">{{ membership.membership_plan?.name }}</span>
              <template v-else>
                <span v-if="membership.membership_plan?.deleted_at" class="text-red-600">Gelöschter Vertrag: </span>
                {{ membership.membership_plan?.name || 'Unbekannter Vertrag' }}
              </template>
            </h4>
            <MembershipStatusEditor
              :membership="membership"
              :forcing-membership-status="forcingMembershipStatus"
              @force-status="(m, s) => $emit('force-status', m, s)"
            />
          </div>
          <p :class="['text-sm mt-2.5', isSecondary ? 'text-gray-500' : 'text-gray-700']">
            {{ membership.is_free_trial ? 'Kostenloser Testzeitraum' : (membership.membership_plan?.description || 'Keine Beschreibung verfügbar') }}
          </p>
        </div>

        <!-- Price -->
        <div class="text-right flex-none">
          <p :class="['text-xl font-bold leading-tight', priceColorClass]">
            {{ membership.is_free_trial ? 'Gratis' : formatCurrency(membership.membership_plan?.price || 0) }}
          </p>
          <p v-if="!membership.is_free_trial" class="text-xs text-gray-500">
            pro {{ getBillingCycleText(membership.membership_plan?.billing_cycle || 'monthly') }}
          </p>
        </div>
      </div>

      <!-- Detail rows -->
      <div class="flex flex-col gap-[5px] mt-3.5">
        <div class="flex gap-1.5 text-sm">
          <span class="text-gray-500">Laufzeit:</span>
          <span :class="['font-medium', isSecondary ? 'text-gray-600' : 'text-gray-900']">
            {{ formatDate(membership.start_date) }} - {{ membership.end_date ? formatDate(membership.end_date) : (membership.cancellation_date ? formatDate(membership.cancellation_date) : 'unbefristet') }}
          </span>
        </div>
        <div v-if="membership.membership_plan?.commitment_months" class="flex gap-1.5 text-sm">
          <span class="text-gray-500">Mindestlaufzeit:</span>
          <span :class="['font-medium', isSecondary ? 'text-gray-600' : 'text-gray-900']">{{ membership.membership_plan.commitment_months }} Monate</span>
        </div>
        <div v-if="membership.membership_plan?.cancellation_period" class="flex gap-1.5 text-sm">
          <span class="text-gray-500">Kündigungsfrist:</span>
          <span :class="['font-medium', isSecondary ? 'text-gray-600' : 'text-gray-900']">{{ membership.membership_plan.formatted_cancellation_period }}</span>
        </div>
      </div>

      <!-- Notice banners -->
      <div v-if="membership.membership_plan?.deleted_at" class="mt-3.5 flex items-center gap-2 px-3 py-2.5 rounded-md bg-red-50">
        <AlertCircle class="w-[17px] h-[17px] text-red-500 flex-none" />
        <span class="text-[13.5px] font-medium text-red-700">Der Vertragsplan wurde gelöscht. Die Mitgliedschaft bleibt jedoch bestehen.</span>
      </div>

      <div v-if="membership.status === 'pending'" class="mt-3.5 flex items-center gap-2 px-3 py-2.5 rounded-md bg-orange-100">
        <AlertCircle class="w-[17px] h-[17px] text-orange-500 flex-none" />
        <span class="text-[13.5px] font-medium text-orange-700">Diese Mitgliedschaft wartet auf Aktivierung</span>
      </div>

      <div v-if="membership.pause_start_date" class="mt-3.5 flex items-center gap-2 px-3 py-2.5 rounded-md bg-yellow-100">
        <Clock class="w-[17px] h-[17px] text-yellow-700 flex-none" />
        <span class="text-[13.5px] font-medium text-yellow-800">
          Pausiert vom {{ formatDate(membership.pause_start_date) }} bis {{ formatDate(membership.pause_end_date) }}
        </span>
      </div>

      <div v-if="membership.cancellation_date" class="mt-3.5 flex items-start gap-2 px-3 py-2.5 rounded-md bg-red-50">
        <AlertCircle class="w-[17px] h-[17px] text-red-500 flex-none mt-px" />
        <span class="text-[13.5px] font-medium text-red-700">
          Kündigung wirksam zum {{ formatDate(membership.cancellation_date) }}
          <span v-if="membership.cancellation_reason" class="block mt-1 font-normal">
            Grund: {{ membership.cancellation_reason }}
          </span>
        </span>
      </div>
    </div>

    <!-- Action footer bar -->
    <div
      v-if="hasActions"
      class="flex border-t border-gray-100"
    >
      <template v-for="(action, index) in actions" :key="action.key">
        <div v-if="index > 0" class="w-px bg-gray-100 flex-none"></div>
        <button
          @click="action.handler"
          type="button"
          :disabled="action.loading"
          :title="action.title"
          :class="[
            'flex-1 inline-flex items-center justify-center gap-1.5 p-3 text-sm font-semibold transition-colors active:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed',
            action.colorClass,
          ]"
        >
          <component :is="action.icon" class="w-4 h-4" />
          <span>{{ action.loading ? action.loadingLabel : action.label }}</span>
        </button>
      </template>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import {
  Clock, CheckCircle, XCircle, PlayCircle, StopCircle,
  RotateCcw, AlertCircle, Gift, Undo2
} from 'lucide-vue-next'
import { formatCurrency, formatDate } from '@/utils/formatters'
import MembershipStatusEditor from '@/Components/Members/MembershipStatusEditor.vue'

const props = defineProps({
  membership: {
    type: Object,
    required: true
  },
  isSecondary: {
    type: Boolean,
    default: false
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

// Helper functions
const getBillingCycleText = (cycle) => {
  const cycles = {
    'monthly': 'Monat',
    'quarterly': 'Quartal',
    'biannual': 'Halbjahr',
    'yearly': 'Jahr'
  }
  return cycles[cycle] || cycle
}

const priceColorClass = computed(() => {
  if (props.membership.is_free_trial) {
    return props.isSecondary ? 'text-gray-500' : 'text-indigo-600'
  }
  return props.isSecondary ? 'text-gray-500' : 'text-gray-900'
})

// Action bar: build the list of available actions for this membership.
const actions = computed(() => {
  const m = props.membership
  const list = []

  // Activate pending membership
  if (m.status === 'pending') {
    list.push({
      key: 'activate',
      label: 'Aktivieren',
      loadingLabel: 'Wird aktiviert...',
      loading: props.activatingMembership === m.id,
      icon: CheckCircle,
      colorClass: 'text-green-700',
      handler: () => emit('activate', m),
    })
  }

  // Pause (not for free trials, not once cancelled)
  if (m.status === 'active' && !m.cancellation_date && !m.is_free_trial) {
    list.push({
      key: 'pause',
      label: 'Stilllegen',
      icon: Clock,
      colorClass: 'text-amber-600',
      handler: () => emit('pause', m),
    })
  }

  // Resume
  if (m.status === 'paused') {
    list.push({
      key: 'resume',
      label: 'Fortsetzen',
      loadingLabel: 'Wird aktiviert...',
      loading: props.resumingMembership === m.id,
      icon: PlayCircle,
      colorClass: 'text-green-700',
      handler: () => emit('resume', m),
    })
  }

  // Cancel (not for free trials, not pending, not once cancelled)
  if (!m.cancellation_date && m.status !== 'pending' && !m.is_free_trial) {
    list.push({
      key: 'cancel',
      label: 'Kündigen',
      icon: XCircle,
      colorClass: 'text-red-600',
      handler: () => emit('cancel', m),
    })
  }

  // Withdraw (only within the 14-day window)
  if (!m.cancellation_date && m.withdrawal_eligible && !m.is_free_trial) {
    list.push({
      key: 'withdraw',
      label: 'Widerrufen',
      loadingLabel: 'Wird widerrufen...',
      loading: props.withdrawingMembership === m.id,
      icon: Undo2,
      colorClass: 'text-purple-600',
      title: 'Widerruf möglich bis ' + formatDate(m.withdrawal_deadline),
      handler: () => emit('withdraw', m),
    })
  }

  // Stop (only for active free trials)
  if (m.is_free_trial && m.status === 'active') {
    list.push({
      key: 'abort',
      label: 'Stoppen',
      loadingLabel: 'Wird gestoppt...',
      loading: props.abortingMembership === m.id,
      icon: StopCircle,
      colorClass: 'text-red-600',
      handler: () => emit('abort', m),
    })
  }

  // Revoke cancellation
  if (m.cancellation_date) {
    list.push({
      key: 'revoke-cancellation',
      label: 'Kündigung zurücknehmen',
      loadingLabel: 'Wird zurückgenommen...',
      loading: props.revokingCancellation === m.id,
      icon: RotateCcw,
      colorClass: 'text-blue-600',
      handler: () => emit('revoke-cancellation', m),
    })
  }

  return list
})

const hasActions = computed(() =>
  ['active', 'paused', 'pending'].includes(props.membership.status) && actions.value.length > 0
)
</script>
