<template>
  <div
    v-if="show"
    class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50"
    @click="close"
  >
    <div
      class="relative top-20 mx-auto p-5 border border-line-subtle w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-surface"
      @click.stop
    >
      <!-- Header -->
      <div class="flex items-start justify-between mb-5">
        <h3 class="text-lg font-semibold text-ink">
          {{ payment && isCreditTopup(payment) ? 'Guthaben-Aufladung' : 'Zahlung' }} #{{ payment?.id }}
        </h3>
        <div class="flex items-center gap-2">
          <button
            v-if="payment?.status === 'pending' && showMarkAsPaid"
            @click="emit('mark-as-paid', payment)"
            class="inline-flex items-center gap-1.5 px-2 sm:px-3 py-1.5 text-sm font-medium text-success bg-success-subtle border border-success-line rounded-lg hover:bg-success-soft transition-colors"
            title="Als bezahlt markieren"
          >
            <CheckCircle class="w-4 h-4" />
            <span class="hidden sm:inline">Bezahlt</span>
          </button>
          <button
            v-if="payment?.status === 'pending' && showCancelPayment"
            @click="emit('cancel-payment', payment)"
            class="inline-flex items-center gap-1.5 px-2 sm:px-3 py-1.5 text-sm font-medium text-danger bg-danger-subtle border border-danger-line rounded-lg hover:bg-danger-soft transition-colors"
            title="Zahlung abbrechen"
          >
            <Ban class="w-4 h-4" />
            <span class="hidden sm:inline">Abbrechen</span>
          </button>
          <button
            @click="close"
            class="ml-1 p-1.5 text-ink-muted hover:text-ink rounded-lg hover:bg-fill transition-colors"
          >
            <X class="w-5 h-5" />
          </button>
        </div>
      </div>

      <div v-if="payment" class="space-y-4">
        <!-- Kerndaten -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-ink-muted">Betrag</label>
            <div class="mt-1 flex flex-col items-start gap-1 sm:flex-row sm:items-center sm:gap-2">
              <span class="text-sm text-ink font-semibold">{{ formatCurrency(payment.amount) }}</span>
              <DiscountBadge :discount="payment.metadata?.discount" />
              <PaymentStatusBadge :status="payment.status" />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-ink-muted">Zahlungsart</label>
            <p class="mt-1 text-sm text-ink inline-flex items-center gap-1.5">
              <BanknoteArrowUp
                v-if="isCreditTopup(payment)"
                class="w-4 h-4 text-brand-ink flex-none"
                title="Guthaben-Aufladung"
              />
              <BanknoteArrowDown
                v-else-if="hasCreditRedemption(payment)"
                class="w-4 h-4 text-brand-ink flex-none"
                title="Guthaben-Einlösung"
              />
              {{ payment.payment_method_text }}
            </p>
          </div>
          <div v-if="payment.metadata?.discount" class="col-span-2">
            <label class="block text-sm font-medium text-ink-muted">Rabatt</label>
            <dl class="mt-1 rounded-lg border border-success-line bg-success-subtle px-3 py-2.5 text-sm">
              <div class="flex justify-between">
                <dt class="text-ink-secondary">Regulärer Beitrag</dt>
                <dd class="text-ink line-through">
                  {{ formatCurrency(payment.metadata.discount.regular_price) }}
                </dd>
              </div>
              <div class="mt-1 flex justify-between">
                <dt class="text-ink-secondary">Aktionspreis</dt>
                <dd class="font-semibold text-ink">
                  {{ formatCurrency(payment.metadata.discount.discounted_price) }}
                </dd>
              </div>
              <div class="mt-1 flex justify-between">
                <dt class="text-ink-secondary">Ersparnis</dt>
                <dd class="font-semibold text-success">
                  {{ formatCurrency(payment.metadata.discount.savings) }}
                </dd>
              </div>
              <div class="mt-2 border-t border-success-line pt-2 text-xs text-ink-secondary">
                {{ discountPhaseLabel }} · Rabattstaffel vom Vertragsabschluss
              </div>
            </dl>
          </div>
          <div>
            <label class="block text-sm font-medium text-ink-muted">Erstellt am</label>
            <p class="mt-1 text-sm text-ink">{{ formatDateTime(payment.created_at) }}</p>
          </div>
          <div v-if="payment.execution_date || canEditExecutionDate">
            <div class="flex items-center justify-between">
              <label class="block text-sm font-medium text-ink-muted">Ausführungsdatum</label>
              <button
                v-if="canEditExecutionDate && !editingExecutionDate"
                @click="startEditingExecutionDate"
                class="text-ink-muted hover:text-brand-ink-strong transition-colors"
                title="Ausführungsdatum bearbeiten"
              >
                <Pencil class="w-3.5 h-3.5" />
              </button>
            </div>
            <div v-if="editingExecutionDate" class="mt-1">
              <input
                ref="executionDateInput"
                v-model="executionDateForm"
                type="date"
                :min="today"
                class="field w-full"
                @keydown.enter="saveExecutionDate"
                @keydown.escape="cancelEditingExecutionDate"
              />
              <p v-if="executionDateError" class="mt-1 text-xs text-danger">{{ executionDateError }}</p>
              <div class="flex items-center justify-end gap-2 mt-1.5">
                <button
                  @click="cancelEditingExecutionDate"
                  :disabled="savingExecutionDate"
                  class="inline-flex items-center px-2.5 py-1 text-xs font-medium text-ink-secondary bg-surface border border-line-strong rounded-md hover:bg-surface-muted disabled:opacity-50"
                >
                  Abbrechen
                </button>
                <button
                  @click="saveExecutionDate"
                  :disabled="savingExecutionDate"
                  class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-white bg-brand border border-transparent rounded-md hover:bg-brand-strong disabled:opacity-50"
                >
                  <Loader2 v-if="savingExecutionDate" class="w-3 h-3 animate-spin" />
                  <Check v-else class="w-3 h-3" />
                  Speichern
                </button>
              </div>
            </div>
            <p v-else-if="payment.execution_date" class="mt-1 text-sm text-ink">{{ formatDate(payment.execution_date) }}</p>
            <p v-else class="mt-1 text-sm text-ink-muted italic cursor-pointer hover:text-brand-ink-strong" @click="startEditingExecutionDate">Ausführungsdatum festlegen...</p>
          </div>
          <div v-if="payment.due_date">
            <label class="block text-sm font-medium text-ink-muted">Fälligkeitsdatum</label>
            <p class="mt-1 text-sm text-ink">{{ formatDate(payment.due_date) }}</p>
          </div>
          <div v-if="payment.paid_date">
            <label class="block text-sm font-medium text-ink-muted">Bezahlt am</label>
            <p class="mt-1 text-sm text-ink">{{ formatDate(payment.paid_date) }}</p>
          </div>
          <div v-if="payment.canceled_at">
            <label class="block text-sm font-medium text-ink-muted">Abgebrochen am</label>
            <p class="mt-1 text-sm text-ink">{{ formatDateTime(payment.canceled_at) }}</p>
          </div>
        </div>

        <!-- Mitglied -->
        <div v-if="payment.member">
          <label class="block text-sm font-medium text-ink-muted">Mitglied</label>
          <MemberIdentity
            :member="payment.member"
            size="sm"
            :max-width="null"
            class="mt-1"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-ink-muted">Beschreibung</label>
          <p class="mt-1 text-sm text-ink">{{ payment.description }}</p>
        </div>

        <div v-if="isCreditTopup(payment)">
          <label class="block text-sm font-medium text-ink-muted">Erfassung</label>
          <p class="mt-1 text-sm text-ink">{{ creditTopupSource(payment) }}</p>
        </div>

        <div v-else-if="payment.transaction_id">
          <label class="block text-sm font-medium text-ink-muted">Transaktions-ID</label>
          <p class="mt-1 text-sm text-ink font-mono">{{ payment.transaction_id }}</p>
        </div>

        <div v-if="payment.mollie_payment_id">
          <label class="block text-sm font-medium text-ink-muted">Zahlungs-ID (Mollie)</label>
          <p class="mt-1 text-sm text-ink font-mono">{{ payment.mollie_payment_id }}</p>
        </div>

        <div>
          <div class="flex items-center justify-between">
            <label class="block text-sm font-medium text-ink-muted">Notizen</label>
            <button
              v-if="!editingNotes"
              @click="startEditingNotes"
              class="text-ink-muted hover:text-brand-ink-strong transition-colors"
              title="Notizen bearbeiten"
            >
              <Pencil class="w-3.5 h-3.5" />
            </button>
          </div>
          <div v-if="editingNotes" class="mt-1">
            <textarea
              ref="notesTextarea"
              v-model="notesForm"
              rows="3"
              class="field w-full"
              placeholder="Notizen hinzufügen..."
              @keydown.ctrl.enter="saveNotes"
              @keydown.meta.enter="saveNotes"
              @keydown.escape="cancelEditingNotes"
            ></textarea>
            <div class="flex items-center justify-end gap-2 mt-1.5">
              <button
                @click="cancelEditingNotes"
                :disabled="savingNotes"
                class="inline-flex items-center px-2.5 py-1 text-xs font-medium text-ink-secondary bg-surface border border-line-strong rounded-md hover:bg-surface-muted disabled:opacity-50"
              >
                Abbrechen
              </button>
              <button
                @click="saveNotes"
                :disabled="savingNotes"
                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-white bg-brand border border-transparent rounded-md hover:bg-brand-strong disabled:opacity-50"
              >
                <Loader2 v-if="savingNotes" class="w-3 h-3 animate-spin" />
                <Check v-else class="w-3 h-3" />
                Speichern
              </button>
            </div>
          </div>
          <p v-else-if="payment.notes" class="mt-1 text-sm text-ink whitespace-pre-line">{{ payment.notes }}</p>
          <p v-else class="mt-1 text-sm text-ink-muted italic cursor-pointer hover:text-brand-ink-strong" @click="startEditingNotes">Notiz hinzufügen...</p>
        </div>

        <!-- Mollie Zahlungslink -->
        <div
          v-if="payment.checkout_url || (payment.status === 'pending' && !payment.mollie_payment_id && page.props.mollie_configured)"
          class="border-t border-line pt-4"
        >
          <div v-if="payment.checkout_url" class="flex items-center justify-between gap-3 bg-brand-subtle border border-brand-line rounded-lg px-4 py-3">
            <div class="flex items-center gap-2 min-w-0">
              <Link2 class="w-4 h-4 text-brand-ink shrink-0" />
              <span class="text-sm font-medium text-brand-ink-strong truncate">{{ payment.checkout_url }}</span>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
              <button
                @click="copyPaymentLink"
                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium text-brand-ink-strong bg-surface border border-brand-line rounded-md hover:bg-brand-subtle transition-colors"
              >
                <component :is="paymentLinkCopied ? ClipboardCheck : Clipboard" class="w-3.5 h-3.5" />
                {{ paymentLinkCopied ? 'Kopiert!' : 'Kopieren' }}
              </button>
              <a
                :href="payment.checkout_url"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-brand-ink-strong bg-surface border border-brand-line rounded-md hover:bg-brand-subtle transition-colors"
                title="Link extern öffnen"
              >
                <ExternalLink class="w-3.5 h-3.5" />
              </a>
            </div>
          </div>
          <button
            v-else
            @click="createPaymentLink"
            :disabled="creatingPaymentLink"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-brand-ink-strong bg-brand-subtle border border-brand-line rounded-lg hover:bg-brand-soft transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <template v-if="creatingPaymentLink">
              <div class="w-4 h-4 border-2 border-brand border-t-transparent rounded-full animate-spin"></div>
              Wird erstellt...
            </template>
            <template v-else>
              <Link2 class="w-4 h-4" />
              Mollie Zahlungslink erstellen
            </template>
          </button>
        </div>

        <!-- Custom detail fields slot -->
        <slot name="payment-details" :payment="payment"></slot>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'
import PaymentStatusBadge from '@/Components/PaymentStatusBadge.vue'
import MemberIdentity from '@/Components/Members/MemberIdentity.vue'
import DiscountBadge from '@/Components/DiscountBadge.vue'
import {
  CheckCircle,
  X,
  Clipboard,
  ClipboardCheck,
  ExternalLink,
  Ban,
  Link2,
  Pencil,
  Check,
  Loader2,
  BanknoteArrowUp,
  BanknoteArrowDown
} from '@lucide/vue'
import { formatCurrency, formatDate, formatDateTime } from '@/utils/formatters'
import {
  hasCreditRedemption,
  isCreditTopup,
  creditTopupSource,
  todayAsIsoDate
} from '@/utils/payments'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  payment: {
    type: Object,
    default: null
  },
  showMarkAsPaid: {
    type: Boolean,
    default: true
  },
  showCancelPayment: {
    type: Boolean,
    default: true
  }
})

/**
 * The months of the contract this discount phase covers.
 */
const discountPhaseLabel = computed(() => {
  const discount = props.payment?.metadata?.discount

  if (!discount) {
    return ''
  }

  return discount.period_start_month === discount.period_end_month
    ? `Monat ${discount.period_start_month}`
    : `Monat ${discount.period_start_month}–${discount.period_end_month}`
})

const emit = defineEmits(['close', 'mark-as-paid', 'cancel-payment', 'payment-updated'])

const page = usePage()
const creatingPaymentLink = ref(false)
const paymentLinkCopied = ref(false)
const editingNotes = ref(false)
const notesForm = ref('')
const savingNotes = ref(false)
const notesTextarea = ref(null)
const editingExecutionDate = ref(false)
const executionDateForm = ref('')
const savingExecutionDate = ref(false)
const executionDateError = ref('')
const executionDateInput = ref(null)

// Lower bound for the date input.
const today = computed(() => todayAsIsoDate())

// The execution date may only be changed for pending payments (no credit
// top-ups) that have not been handed over to a payment provider yet.
const canEditExecutionDate = computed(() => {
  const payment = props.payment
  if (!payment) return false

  return payment.status === 'pending'
    && !isCreditTopup(payment)
    && !payment.mollie_payment_id
    && !payment.transaction_id
})

// Reset any open inline editor whenever the modal is closed or another payment
// is shown, so no stale draft leaks into the next payment.
watch(() => [props.show, props.payment?.id], () => {
  cancelEditingNotes()
  cancelEditingExecutionDate()
})

const close = () => {
  emit('close')
}

const createPaymentLink = async () => {
  if (!props.payment || creatingPaymentLink.value) return

  creatingPaymentLink.value = true
  try {
    const response = await axios.post(route('payments.create-payment-link', props.payment.id))
    emit('payment-updated', {
      checkout_url: response.data.checkout_url,
      mollie_payment_id: response.data.mollie_payment_id,
      payment_method: response.data.payment_method,
      payment_method_text: response.data.payment_method_text
    })
  } catch (error) {
    const message = error.response?.data?.error || 'Zahlungslink konnte nicht erstellt werden.'
    alert(message)
  } finally {
    creatingPaymentLink.value = false
  }
}

const startEditingNotes = () => {
  notesForm.value = props.payment?.notes || ''
  editingNotes.value = true
  nextTick(() => {
    notesTextarea.value?.focus()
  })
}

const cancelEditingNotes = () => {
  editingNotes.value = false
  notesForm.value = ''
}

const saveNotes = async () => {
  savingNotes.value = true
  try {
    const response = await axios.patch(route('payments.update-notes', props.payment.id), {
      notes: notesForm.value || null,
    })
    emit('payment-updated', { notes: response.data.notes })
    editingNotes.value = false
  } catch (error) {
    const message = error.response?.data?.message || 'Notizen konnten nicht gespeichert werden.'
    alert(message)
  } finally {
    savingNotes.value = false
  }
}

const startEditingExecutionDate = () => {
  if (!canEditExecutionDate.value) return

  executionDateForm.value = props.payment.execution_date
    ? String(props.payment.execution_date).slice(0, 10)
    : today.value
  executionDateError.value = ''
  editingExecutionDate.value = true
  nextTick(() => {
    executionDateInput.value?.focus()
  })
}

const cancelEditingExecutionDate = () => {
  editingExecutionDate.value = false
  executionDateForm.value = ''
  executionDateError.value = ''
}

const saveExecutionDate = async () => {
  if (savingExecutionDate.value) return

  if (!executionDateForm.value) {
    executionDateError.value = 'Bitte ein Ausführungsdatum wählen.'
    return
  }

  if (executionDateForm.value < today.value) {
    executionDateError.value = 'Das Ausführungsdatum darf nicht in der Vergangenheit liegen.'
    return
  }

  savingExecutionDate.value = true
  executionDateError.value = ''
  try {
    const response = await axios.patch(route('payments.update-execution-date', props.payment.id), {
      execution_date: executionDateForm.value,
    })
    emit('payment-updated', { execution_date: response.data.execution_date })
    editingExecutionDate.value = false
  } catch (error) {
    executionDateError.value = error.response?.data?.errors?.execution_date?.[0]
      || error.response?.data?.message
      || 'Ausführungsdatum konnte nicht gespeichert werden.'
  } finally {
    savingExecutionDate.value = false
  }
}

const copyPaymentLink = async () => {
  if (!props.payment?.checkout_url) return

  try {
    await navigator.clipboard.writeText(props.payment.checkout_url)
    paymentLinkCopied.value = true
    setTimeout(() => {
      paymentLinkCopied.value = false
    }, 2000)
  } catch {
    // Fallback for older browsers
    const textArea = document.createElement('textarea')
    textArea.value = props.payment.checkout_url
    document.body.appendChild(textArea)
    textArea.select()
    document.execCommand('copy')
    document.body.removeChild(textArea)
    paymentLinkCopied.value = true
    setTimeout(() => {
      paymentLinkCopied.value = false
    }, 2000)
  }
}
</script>
