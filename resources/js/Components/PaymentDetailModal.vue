<template>
  <div
    v-if="show"
    class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50"
    @click="close"
  >
    <div
      class="relative top-20 mx-auto p-5 border border-gray-50 w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white"
      @click.stop
    >
      <!-- Header -->
      <div class="flex items-start justify-between mb-5">
        <h3 class="text-lg font-medium text-gray-900">
          {{ payment && isCreditTopup(payment) ? 'Guthaben-Aufladung' : 'Zahlung' }} #{{ payment?.id }}
        </h3>
        <div class="flex items-center gap-2">
          <button
            v-if="payment?.status === 'pending' && showMarkAsPaid"
            @click="emit('mark-as-paid', payment)"
            class="inline-flex items-center gap-1.5 px-2 sm:px-3 py-1.5 text-sm font-medium text-green-700 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition-colors"
            title="Als bezahlt markieren"
          >
            <CheckCircle class="w-4 h-4" />
            <span class="hidden sm:inline">Bezahlt</span>
          </button>
          <button
            v-if="payment?.status === 'pending' && showCancelPayment"
            @click="emit('cancel-payment', payment)"
            class="inline-flex items-center gap-1.5 px-2 sm:px-3 py-1.5 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-colors"
            title="Zahlung abbrechen"
          >
            <Ban class="w-4 h-4" />
            <span class="hidden sm:inline">Abbrechen</span>
          </button>
          <button
            @click="close"
            class="ml-1 p-1.5 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition-colors"
          >
            <X class="w-5 h-5" />
          </button>
        </div>
      </div>

      <div v-if="payment" class="space-y-4">
        <!-- Kerndaten -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-500">Betrag</label>
            <div class="mt-1 flex flex-col items-start gap-1 sm:flex-row sm:items-center sm:gap-2">
              <span class="text-sm text-gray-900 font-semibold">{{ formatCurrency(payment.amount) }}</span>
              <PaymentStatusBadge :status="payment.status" />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-500">Zahlungsart</label>
            <p class="mt-1 text-sm text-gray-900 inline-flex items-center gap-1.5">
              <BanknoteArrowUp
                v-if="isCreditTopup(payment)"
                class="w-4 h-4 text-indigo-600 flex-none"
                title="Guthaben-Aufladung"
              />
              <BanknoteArrowDown
                v-else-if="hasCreditRedemption(payment)"
                class="w-4 h-4 text-indigo-600 flex-none"
                title="Guthaben-Einlösung"
              />
              {{ payment.payment_method_text }}
            </p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-500">Erstellt am</label>
            <p class="mt-1 text-sm text-gray-900">{{ formatDateTime(payment.created_at) }}</p>
          </div>
          <div v-if="payment.execution_date || canEditExecutionDate">
            <div class="flex items-center justify-between">
              <label class="block text-sm font-medium text-gray-500">Ausführungsdatum</label>
              <button
                v-if="canEditExecutionDate && !editingExecutionDate"
                @click="startEditingExecutionDate"
                class="text-gray-400 hover:text-indigo-600 transition-colors"
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
                class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                @keydown.enter="saveExecutionDate"
                @keydown.escape="cancelEditingExecutionDate"
              />
              <p v-if="executionDateError" class="mt-1 text-xs text-red-600">{{ executionDateError }}</p>
              <div class="flex items-center justify-end gap-2 mt-1.5">
                <button
                  @click="cancelEditingExecutionDate"
                  :disabled="savingExecutionDate"
                  class="inline-flex items-center px-2.5 py-1 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50"
                >
                  Abbrechen
                </button>
                <button
                  @click="saveExecutionDate"
                  :disabled="savingExecutionDate"
                  class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 disabled:opacity-50"
                >
                  <Loader2 v-if="savingExecutionDate" class="w-3 h-3 animate-spin" />
                  <Check v-else class="w-3 h-3" />
                  Speichern
                </button>
              </div>
            </div>
            <p v-else-if="payment.execution_date" class="mt-1 text-sm text-gray-900">{{ formatDate(payment.execution_date) }}</p>
            <p v-else class="mt-1 text-sm text-gray-400 italic cursor-pointer hover:text-indigo-600" @click="startEditingExecutionDate">Ausführungsdatum festlegen...</p>
          </div>
          <div v-if="payment.due_date">
            <label class="block text-sm font-medium text-gray-500">Fälligkeitsdatum</label>
            <p class="mt-1 text-sm text-gray-900">{{ formatDate(payment.due_date) }}</p>
          </div>
          <div v-if="payment.paid_date">
            <label class="block text-sm font-medium text-gray-500">Bezahlt am</label>
            <p class="mt-1 text-sm text-gray-900">{{ formatDate(payment.paid_date) }}</p>
          </div>
          <div v-if="payment.canceled_at">
            <label class="block text-sm font-medium text-gray-500">Abgebrochen am</label>
            <p class="mt-1 text-sm text-gray-900">{{ formatDateTime(payment.canceled_at) }}</p>
          </div>
        </div>

        <!-- Mitglied -->
        <div v-if="payment.membership?.member">
          <label class="block text-sm font-medium text-gray-500">Mitglied</label>
          <MemberIdentity
            :member="payment.membership.member"
            size="sm"
            :max-width="null"
            class="mt-1"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-500">Beschreibung</label>
          <p class="mt-1 text-sm text-gray-900">{{ payment.description }}</p>
        </div>

        <div v-if="isCreditTopup(payment)">
          <label class="block text-sm font-medium text-gray-500">Erfassung</label>
          <p class="mt-1 text-sm text-gray-900">{{ creditTopupSource(payment) }}</p>
        </div>

        <div v-else-if="payment.transaction_id">
          <label class="block text-sm font-medium text-gray-500">Transaktions-ID</label>
          <p class="mt-1 text-sm text-gray-900 font-mono">{{ payment.transaction_id }}</p>
        </div>

        <div v-if="payment.mollie_payment_id">
          <label class="block text-sm font-medium text-gray-500">Zahlungs-ID (Mollie)</label>
          <p class="mt-1 text-sm text-gray-900 font-mono">{{ payment.mollie_payment_id }}</p>
        </div>

        <div>
          <div class="flex items-center justify-between">
            <label class="block text-sm font-medium text-gray-500">Notizen</label>
            <button
              v-if="!editingNotes"
              @click="startEditingNotes"
              class="text-gray-400 hover:text-indigo-600 transition-colors"
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
              class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              placeholder="Notizen hinzufügen..."
              @keydown.ctrl.enter="saveNotes"
              @keydown.meta.enter="saveNotes"
              @keydown.escape="cancelEditingNotes"
            ></textarea>
            <div class="flex items-center justify-end gap-2 mt-1.5">
              <button
                @click="cancelEditingNotes"
                :disabled="savingNotes"
                class="inline-flex items-center px-2.5 py-1 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50"
              >
                Abbrechen
              </button>
              <button
                @click="saveNotes"
                :disabled="savingNotes"
                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 disabled:opacity-50"
              >
                <Loader2 v-if="savingNotes" class="w-3 h-3 animate-spin" />
                <Check v-else class="w-3 h-3" />
                Speichern
              </button>
            </div>
          </div>
          <p v-else-if="payment.notes" class="mt-1 text-sm text-gray-900 whitespace-pre-line">{{ payment.notes }}</p>
          <p v-else class="mt-1 text-sm text-gray-400 italic cursor-pointer hover:text-indigo-600" @click="startEditingNotes">Notiz hinzufügen...</p>
        </div>

        <!-- Mollie Zahlungslink -->
        <div
          v-if="payment.checkout_url || (payment.status === 'pending' && !payment.mollie_payment_id && page.props.mollie_configured)"
          class="border-t border-gray-200 pt-4"
        >
          <div v-if="payment.checkout_url" class="flex items-center justify-between gap-3 bg-indigo-50 border border-indigo-200 rounded-lg px-4 py-3">
            <div class="flex items-center gap-2 min-w-0">
              <Link2 class="w-4 h-4 text-indigo-500 shrink-0" />
              <span class="text-sm font-medium text-indigo-900 truncate">{{ payment.checkout_url }}</span>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
              <button
                @click="copyPaymentLink"
                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium text-indigo-700 bg-white border border-indigo-300 rounded-md hover:bg-indigo-50 transition-colors"
              >
                <component :is="paymentLinkCopied ? ClipboardCheck : Clipboard" class="w-3.5 h-3.5" />
                {{ paymentLinkCopied ? 'Kopiert!' : 'Kopieren' }}
              </button>
              <a
                :href="payment.checkout_url"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-indigo-700 bg-white border border-indigo-300 rounded-md hover:bg-indigo-50 transition-colors"
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
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <template v-if="creatingPaymentLink">
              <div class="w-4 h-4 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
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
} from 'lucide-vue-next'
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
