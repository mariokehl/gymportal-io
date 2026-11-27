<template>
  <div class="rounded-lg border border-line bg-surface shadow-sm overflow-hidden">
    <button
      type="button"
      @click="emit('toggle')"
      class="w-full flex items-center gap-3 p-4 text-left"
    >
      <div class="p-2 rounded-lg flex-none" :class="getPaymentMethodIconClass(paymentMethod.type)">
        <component :is="getPaymentMethodIcon(paymentMethod.type)" class="w-6 h-6" />
      </div>
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 flex-wrap">
          <h4 class="font-semibold text-ink">{{ paymentMethod.type_text }}</h4>
          <span v-if="paymentMethod.is_default" class="bg-info-soft text-info-strong text-xs px-2 py-0.5 rounded-full">
            Standard
          </span>
          <span :class="getPaymentMethodStatusClass(paymentMethod.status)" class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full">
            {{ getPaymentMethodStatusText(paymentMethod.status) }}
          </span>
        </div>
      </div>
      <ChevronDown
        class="w-5 h-5 text-ink-muted flex-none transition-transform"
        :class="expanded ? 'rotate-180' : ''"
      />
    </button>

    <!-- Expanded details -->
    <div v-show="expanded" class="px-4 pb-4 -mt-1">
      <div class="border-t border-line-subtle pt-3 space-y-3">
        <!-- SEPA Details -->
        <div v-if="isSepaType(paymentMethod.type)" class="grid gap-x-10 gap-y-1.5 text-sm sm:grid-cols-[max-content_minmax(0,1fr)]">
          <div class="space-y-1.5 min-w-0">
            <div class="flex items-center gap-1.5">
              <span class="text-ink-muted">IBAN:</span>
              <!-- Mollie holds the account data, so its IBAN is fetched lazily on expand. -->
              <template v-if="isMollieManaged(paymentMethod)">
                <span
                  v-if="mollieIban.status === 'loaded'"
                  class="font-mono text-ink"
                >{{ mollieIban.maskedIban }}</span>
                <span
                  v-else-if="mollieIban.status === 'error'"
                  class="font-mono text-ink"
                >{{ paymentMethod.masked_iban || '****' }}</span>
                <span
                  v-else
                  class="inline-flex items-center gap-1 text-ink-muted"
                  role="status"
                >
                  <RefreshCw class="w-3.5 h-3.5 animate-spin" aria-hidden="true" />
                  Wird von Mollie abgerufen …
                </span>
              </template>
              <span v-else class="font-mono text-ink">{{ paymentMethod.masked_iban || '****' }}</span>
              <Tooltip
                v-if="isMollieManaged(paymentMethod) && mollieIban.status === 'error'"
                position="top"
                teleport
                :text="mollieIban.message"
              >
                <button
                  type="button"
                  class="flex items-center text-danger hover:text-danger-strong"
                  :aria-label="mollieIban.message + ' Erneut versuchen'"
                  @click="loadMollieIban({ force: true })"
                >
                  <AlertCircle class="w-4 h-4" aria-hidden="true" />
                </button>
              </Tooltip>
              <Tooltip
                v-else-if="isMollieManaged(paymentMethod)"
                position="top"
                teleport
                text="IBAN wird von Mollie verwaltet"
              >
                <Link2
                  class="block w-4 h-4 text-brand-ink"
                  aria-label="IBAN wird von Mollie verwaltet"
                />
              </Tooltip>
              <Tooltip
                v-else-if="isMollieLinkBroken(paymentMethod)"
                position="top"
                teleport
                text="Keine Verknüpfung zu Mollie"
              >
                <Unlink
                  class="block w-4 h-4 text-danger"
                  aria-label="Keine Verknüpfung zu Mollie"
                />
              </Tooltip>
            </div>
            <div v-if="paymentMethod.sepa_mandate_reference" class="flex gap-1.5">
              <span class="text-ink-muted flex-none">Mandatsreferenz:</span>
              <span class="font-mono text-ink break-all">{{ paymentMethod.sepa_mandate_reference }}</span>
            </div>
            <div v-if="paymentMethod.sepa_mandate_status" class="flex items-center gap-2 flex-wrap">
              <span class="text-ink-muted">SEPA-Mandat:</span>
              <span
                :class="getSepaMandateStatusClass(paymentMethod.sepa_mandate_status)"
                class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full"
              >
                {{ getSepaMandateStatusText(paymentMethod.sepa_mandate_status) }}
              </span>
              <!-- Mollie holds the account data, so a locally corrected IBAN
                   only reaches it once the mandate is re-issued. -->
              <button
                v-if="canSyncMollieMandate(paymentMethod)"
                type="button"
                @click="emit('sync-mandate')"
                :disabled="busyAction === 'sync-mandate'"
                class="inline-flex items-center gap-1 text-xs font-semibold text-brand-ink hover:text-brand-ink-strong hover:underline disabled:opacity-50 disabled:cursor-not-allowed disabled:no-underline"
              >
                <RefreshCw
                  class="w-3.5 h-3.5"
                  :class="busyAction === 'sync-mandate' ? 'animate-spin' : ''"
                />
                {{ busyAction === 'sync-mandate' ? 'Wird übertragen …' : 'IBAN zu Mollie übertragen' }}
              </button>
            </div>
            <div v-if="paymentMethod.sepa_mandate_signed_at" class="flex gap-1.5">
              <span class="text-ink-muted">Unterschrieben am:</span>
              <span class="text-ink">{{ formatDate(paymentMethod.sepa_mandate_signed_at) }}</span>
            </div>
          </div>

          <!-- Mollie's own identifiers, kept in a second column so they do
               not push the account data apart. Wraps below it on mobile. -->
          <div v-if="hasMollieIds(paymentMethod)" class="space-y-1.5 min-w-0">
            <div v-if="paymentMethod.mollie_customer_id" class="flex gap-1.5">
              <span class="text-ink-muted flex-none">Mollie-Kunde:</span>
              <span class="font-mono text-ink break-all">{{ paymentMethod.mollie_customer_id }}</span>
            </div>
            <div v-if="paymentMethod.mollie_mandate_id" class="flex gap-1.5">
              <span class="text-ink-muted flex-none">Mollie-Mandat:</span>
              <span class="font-mono text-ink break-all">{{ paymentMethod.mollie_mandate_id }}</span>
            </div>
          </div>
        </div>

        <!-- Credit Card Details -->
        <div v-else-if="isCreditCardType(paymentMethod.type)" class="space-y-1.5 text-sm">
          <p class="text-ink-secondary font-mono">**** **** **** {{ paymentMethod.last_four }}</p>
          <p v-if="paymentMethod.cardholder_name" class="text-ink-secondary">{{ paymentMethod.cardholder_name }}</p>
          <p v-if="paymentMethod.expiry_date" class="text-ink-secondary">
            Gültig bis: {{ formatMonthYear(paymentMethod.expiry_date) }}
          </p>
        </div>

        <!-- Bank Transfer Details -->
        <div v-else-if="isBankTransferType(paymentMethod.type) && paymentMethod.bank_name" class="text-sm">
          <p class="text-ink-secondary">{{ paymentMethod.bank_name }}</p>
        </div>

        <!-- SEPA Mandate Actions -->
        <div v-if="paymentMethod.requires_mandate && paymentMethod.sepa_mandate_status === 'pending'" class="bg-warning-soft border border-warning-line rounded-xl p-3.5">
          <div class="flex gap-2.5">
            <AlertCircle class="w-[18px] h-[18px] text-warning flex-none mt-0.5" />
            <span class="text-compact font-semibold text-warning leading-snug">SEPA-Mandat muss noch unterschrieben werden</span>
          </div>
          <div class="flex flex-col gap-2 mt-3">
            <button
              type="button"
              @click="emit('send-mandate')"
              :disabled="busyAction === 'send-mandate'"
              class="inline-flex items-center justify-center gap-2 w-full min-h-11 px-3.5 py-2.5 rounded-[10px] bg-brand text-white text-sm font-semibold hover:bg-brand-strong transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <Send class="w-4 h-4" />
              {{ busyAction === 'send-mandate' ? 'Wird verarbeitet...' : 'Mandat versenden' }}
            </button>
            <button
              type="button"
              @click="emit('mark-signed')"
              :disabled="busyAction === 'mark-signed'"
              class="inline-flex items-center justify-center gap-2 w-full min-h-11 px-3.5 py-2.5 rounded-[10px] bg-surface border border-success text-success text-sm font-semibold hover:bg-success-subtle transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <Check class="w-4 h-4" />
              {{ busyAction === 'mark-signed' ? 'Wird markiert...' : 'Als unterschrieben markieren' }}
            </button>
          </div>
        </div>

        <!-- Signed mandate actions -->
        <div v-if="paymentMethod.requires_mandate && paymentMethod.sepa_mandate_status === 'signed'" class="p-3 bg-info-subtle rounded-md">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div class="flex items-center">
              <CheckCircle class="w-5 h-5 text-info mr-2 flex-none" />
              <span class="text-sm text-info-strong">SEPA-Mandat wurde unterschrieben und wartet auf Aktivierung</span>
            </div>
            <button
              type="button"
              @click="emit('activate-mandate')"
              :disabled="busyAction === 'activate-mandate'"
              class="bg-info-solid text-white px-3 py-1 rounded text-sm hover:bg-info-solid-strong disabled:opacity-50 disabled:cursor-not-allowed transition-colors whitespace-nowrap"
            >
              {{ busyAction === 'activate-mandate' ? 'Wird aktiviert...' : 'Mandat aktivieren' }}
            </button>
          </div>
        </div>

        <!-- Action links -->
        <div class="flex flex-wrap items-center gap-4 pt-1">
          <button
            v-if="!paymentMethod.is_default && paymentMethod.status === 'active'"
            @click="emit('set-default')"
            type="button"
            class="text-sm font-semibold text-brand-ink hover:text-brand-ink-strong"
            :disabled="busyAction === 'set-default'"
          >
            {{ busyAction === 'set-default' ? 'Wird gesetzt...' : 'Als Standard setzen' }}
          </button>
          <button
            @click="emit('edit')"
            type="button"
            class="text-sm font-semibold text-brand-ink hover:text-brand-ink-strong"
          >
            Bearbeiten
          </button>
          <button
            v-if="paymentMethod.status === 'active'"
            @click="emit('deactivate')"
            type="button"
            class="text-sm font-semibold text-danger hover:text-danger-strong"
            :disabled="busyAction === 'deactivate'"
          >
            {{ busyAction === 'deactivate' ? 'Deaktivieren...' : 'Deaktivieren' }}
          </button>
          <button
            v-if="paymentMethod.status === 'expired'"
            @click="emit('delete')"
            type="button"
            class="text-sm font-semibold text-danger hover:text-danger-strong"
            :disabled="busyAction === 'delete'"
          >
            {{ busyAction === 'delete' ? 'Löschen...' : 'Löschen' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, watch } from 'vue'
import axios from 'axios'
import {
  AlertCircle, Banknote, Building2, Check, CheckCircle, ChevronDown, CreditCard,
  FileText, Link2, RefreshCw, Send, Unlink, WalletCards
} from '@lucide/vue'
import Tooltip from '@/Components/Tooltip.vue'
import { formatDate, formatMonthYear } from '@/utils/formatters'
import {
  canSyncMollieMandate, getPaymentMethodIconClass, getPaymentMethodStatusClass,
  getPaymentMethodStatusText, getSepaMandateStatusClass, getSepaMandateStatusText,
  hasMollieIds, isBankTransferType, isCreditCardType, isMollieLinkBroken,
  isMollieManaged, isSepaType
} from '@/utils/paymentMethods'

const props = defineProps({
  paymentMethod: {
    type: Object,
    required: true
  },
  memberId: {
    type: [Number, String],
    required: true
  },
  expanded: {
    type: Boolean,
    default: false
  },
  // The action currently running for this payment method (e.g. 'set-default'),
  // so the matching button can show its progress. Null while idle.
  busyAction: {
    type: String,
    default: null
  }
})

const emit = defineEmits([
  'toggle',
  'set-default',
  'edit',
  'deactivate',
  'delete',
  'send-mandate',
  'mark-signed',
  'activate-mandate',
  'sync-mandate'
])

const PAYMENT_METHOD_ICONS = {
  sepa_direct_debit: Building2,
  creditcard: CreditCard,
  banktransfer: Building2,
  cash: Banknote,
  invoice: FileText,
  mollie_creditcard: CreditCard,
  mollie_directdebit: Building2,
  mollie_paypal: WalletCards,
  mollie_klarna: FileText
}

const getPaymentMethodIcon = type => PAYMENT_METHOD_ICONS[type] || CreditCard

// Mollie holds the account data of a linked direct debit, so its IBAN is only
// requested once the card is expanded. The result stays cached while the card
// is mounted, i.e. for as long as the payments tab is open.
const MOLLIE_IBAN_ERROR = 'Die IBAN konnte nicht von Mollie abgerufen werden.'
const mollieIban = reactive({ status: 'idle', maskedIban: null, message: null })

const setMollieIban = (status, maskedIban = null, message = null) => {
  Object.assign(mollieIban, { status, maskedIban, message })
}

const loadMollieIban = async ({ force = false } = {}) => {
  if (mollieIban.status === 'loading' || (mollieIban.status === 'loaded' && !force)) {
    return
  }

  setMollieIban('loading')

  try {
    const { data } = await axios.get(route('members.payment-methods.mollie-iban', {
      member: props.memberId,
      paymentMethod: props.paymentMethod.id
    }))
    setMollieIban('loaded', data.masked_iban)
  } catch (error) {
    setMollieIban('error', null, error.response?.data?.message || MOLLIE_IBAN_ERROR)
  }
}

watch(() => props.expanded, (expanded) => {
  if (expanded && isMollieManaged(props.paymentMethod)) {
    loadMollieIban()
  }
}, { immediate: true })

// A new mandate id means the mandate was re-issued (e.g. after transferring a
// corrected IBAN), so the cached IBAN is outdated.
watch(() => props.paymentMethod.mollie_mandate_id, () => {
  setMollieIban('idle')

  if (props.expanded && isMollieManaged(props.paymentMethod)) {
    loadMollieIban()
  }
})
</script>
