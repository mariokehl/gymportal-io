<template>
  <div>
    <!-- Actions Bar -->
    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
          <div class="flex items-center">
            <input
              id="select-all"
              type="checkbox"
              :checked="isAllSelected"
              @change="toggleSelectAll"
              :disabled="batchExecutingPayments"
              class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 disabled:opacity-50"
            />
            <label for="select-all" class="ml-2 text-sm font-medium text-gray-700">
              Alle auswählen
            </label>
          </div>

          <span v-if="selectedPayments.length > 0" class="text-sm text-gray-600">
            {{ selectedPayments.length }} von {{ payments.data.length }} ausgewählt
          </span>

          <!-- Batch Progress Indicator -->
          <div v-if="batchExecutingPayments" class="flex items-center text-sm text-indigo-600">
            <div class="w-4 h-4 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin mr-2"></div>
            Zahlungen werden ausgeführt...
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button
            v-if="selectedPayments.length > 0 && showCsvExport"
            @click="handleExport('csv')"
            :disabled="batchExecutingPayments"
            class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <Download class="w-4 h-4 mr-2" />
            CSV Export
          </button>

          <button
            v-if="selectedSepaPayments.length > 0 && showSepaExport"
            @click="handleExport('pain008')"
            :disabled="batchExecutingPayments"
            class="flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-lg hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <Download class="w-4 h-4 mr-2" />
            PAIN.008 Export ({{ selectedSepaPayments.length }})
          </button>
        </div>
      </div>
    </div>

    <!-- Payments Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left" v-if="showCheckboxes">
                <input
                  type="checkbox"
                  :checked="isAllSelected"
                  @change="toggleSelectAll"
                  class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                />
              </th>
              <th
                v-for="column in visibleColumns"
                :key="column.key"
                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                :class="{
                  'cursor-pointer hover:bg-gray-100': column.sortable,
                  'text-right': column.align === 'right'
                }"
                @click="column.sortable && handleSort(column.key)"
              >
                <div class="flex items-center" :class="{ 'justify-end': column.align === 'right' }">
                  {{ column.label }}
                  <ArrowUpDown v-if="column.sortable" class="w-4 h-4 ml-1" />
                </div>
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider" v-if="showActions">
                Aktionen
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr
              v-for="payment in payments.data"
              :key="payment.id"
              class="hover:bg-gray-50"
              :class="{ 'opacity-60': executingPaymentId === payment.id }"
            >
              <td class="px-6 py-4 whitespace-nowrap" v-if="showCheckboxes">
                <input
                  type="checkbox"
                  :value="payment.id"
                  v-model="selectedPayments"
                  :disabled="executingPaymentId === payment.id || batchExecutingPayments"
                  class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 disabled:opacity-50"
                />
              </td>
              <td
                v-for="column in visibleColumns"
                :key="column.key"
                class="px-6 py-4"
                :class="{
                  'whitespace-nowrap': column.nowrap,
                  'text-right': column.align === 'right'
                }"
              >
                <!-- ID Column -->
                <template v-if="column.key === 'id'">
                  <span class="text-sm font-medium text-gray-900">#{{ payment.id }}</span>
                </template>

                <!-- Date Column -->
                <template v-else-if="column.key === 'created_at'">
                  <span class="text-sm text-gray-900">{{ formatDate(payment.created_at) }}</span>
                </template>

                <!-- Member Column -->
                <template v-else-if="column.key === 'member'">
                  <div class="flex items-center">
                    <div class="w-8 h-8 bg-indigo-500 rounded-full text-white flex items-center justify-center text-xs font-semibold mr-3">
                      {{ getMemberInitials(payment.membership?.member) }}
                    </div>
                    <div>
                      <div class="text-sm font-medium text-gray-900">
                        {{ payment.membership?.member?.first_name }} {{ payment.membership?.member?.last_name }}
                      </div>
                      <div class="text-sm text-gray-500">
                        {{ payment.membership?.member?.email }}
                      </div>
                    </div>
                  </div>
                </template>

                <!-- Description Column -->
                <template v-else-if="column.key === 'description'">
                  <div class="text-sm text-gray-900 text-nowrap">{{ payment.description }}</div>
                  <div v-if="payment.transaction_id" class="text-sm text-gray-500 text-nowrap">
                    Tx: {{ payment.transaction_id }}
                  </div>
                  <div v-else-if="payment.mollie_payment_id" class="text-sm text-gray-500 text-nowrap">
                    Tx: {{ payment.mollie_payment_id }}
                  </div>
                </template>

                <!-- Amount Column -->
                <template v-else-if="column.key === 'amount'">
                  <span class="text-sm font-semibold">{{ formatCurrency(payment.amount) }}</span>
                </template>

                <!-- Status Column -->
                <template v-else-if="column.key === 'status'">
                  <div class="flex items-center">
                    <span
                      :class="getStatusClasses(payment.status_color)"
                      class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                    >
                      {{ payment.status_text }}
                    </span>
                    <div
                      v-if="executingPaymentId === payment.id"
                      class="ml-2 w-4 h-4 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin"
                      title="Zahlung wird ausgeführt..."
                    ></div>
                  </div>
                </template>

                <!-- Payment Method Column -->
                <template v-else-if="column.key === 'payment_method'">
                  <span class="text-sm text-gray-900">{{ payment.payment_method_text }}</span>
                </template>

                <!-- Due Date Column -->
                <template v-else-if="column.key === 'due_date'">
                  <span v-if="payment.due_date" class="text-sm text-gray-900">{{ formatDate(payment.due_date) }}</span>
                  <span v-else class="text-sm text-gray-400">-</span>
                </template>

                <!-- Custom Column Slot -->
                <template v-else>
                  <slot :name="`column-${column.key}`" :payment="payment" :value="payment[column.key]">
                    {{ payment[column.key] }}
                  </slot>
                </template>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium" v-if="showActions">
                <div class="flex items-center justify-end space-x-2">
                  <button
                    v-if="showViewDetails"
                    @click="viewPayment(payment)"
                    class="text-gray-700 hover:text-gray-900"
                    title="Details anzeigen"
                  >
                    <Eye class="w-4 h-4" />
                  </button>
                  <button
                    v-if="payment.status === 'pending' && showMarkAsPaid"
                    @click="markAsPaid(payment)"
                    class="text-green-600 hover:text-green-900"
                    title="Als bezahlt markieren"
                  >
                    <CheckCircle class="w-4 h-4" />
                  </button>
                  <button
                    v-if="payment.status === 'pending' && showCancelPayment"
                    @click="cancelPayment(payment)"
                    class="text-red-600 hover:text-red-900"
                    title="Zahlung abbrechen"
                  >
                    <XCircle class="w-4 h-4" />
                  </button>
                  <slot name="actions" :payment="payment" :is-executing="executingPaymentId === payment.id"></slot>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination with Pagination Component -->
      <Pagination
        v-if="showPagination"
        :data="payments"
        item-label="Zahlungen"
        :is-loading="isProcessing"
        @navigate="handlePaginationEvent"
      />
    </div>

    <!-- Payment Detail Modal -->
    <div
      v-if="showPaymentModal"
      class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50"
      @click="closePaymentModal"
    >
      <div
        class="relative top-20 mx-auto p-5 border border-gray-50 w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white"
        @click.stop
      >
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-medium text-gray-900">
            Zahlungsdetails #{{ selectedPayment?.id }}
          </h3>
          <button
            @click="closePaymentModal"
            class="text-gray-400 hover:text-gray-600"
          >
            <X class="w-6 h-6" />
          </button>
        </div>

        <div v-if="selectedPayment" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Betrag</label>
              <p class="mt-1 text-sm text-gray-900 font-semibold">{{ formatCurrency(selectedPayment.amount) }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Status</label>
              <span
                :class="getStatusClasses(selectedPayment.status_color)"
                class="mt-1 inline-flex px-2 py-1 text-xs font-semibold rounded-full"
              >
                {{ selectedPayment.status_text }}
              </span>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Zahlungsart</label>
              <p class="mt-1 text-sm text-gray-900">{{ selectedPayment.payment_method_text }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Erstellt am</label>
              <p class="mt-1 text-sm text-gray-900">{{ formatDateTime(selectedPayment.created_at) }}</p>
            </div>
            <div v-if="selectedPayment.execution_date">
              <label class="block text-sm font-medium text-gray-700">Ausführungsdatum</label>
              <p class="mt-1 text-sm text-gray-900">{{ formatDate(selectedPayment.execution_date) }}</p>
            </div>
            <div v-if="selectedPayment.due_date">
              <label class="block text-sm font-medium text-gray-700">Fälligkeitsdatum</label>
              <p class="mt-1 text-sm text-gray-900">{{ formatDate(selectedPayment.due_date) }}</p>
            </div>
            <div v-if="selectedPayment.paid_at">
              <label class="block text-sm font-medium text-gray-700">Bezahlt am</label>
              <p class="mt-1 text-sm text-gray-900">{{ formatDateTime(selectedPayment.paid_at) }}</p>
            </div>
            <div v-if="selectedPayment.canceled_at">
              <label class="block text-sm font-medium text-gray-700">Abgebrochen am</label>
              <p class="mt-1 text-sm text-gray-900">{{ formatDateTime(selectedPayment.canceled_at) }}</p>
            </div>
          </div>

          <div v-if="selectedPayment.membership?.member">
            <label class="block text-sm font-medium text-gray-700">Mitglied</label>
            <div class="mt-1 flex items-center">
              <div class="w-8 h-8 bg-indigo-500 rounded-full text-white flex items-center justify-center text-xs font-semibold mr-3">
                {{ getMemberInitials(selectedPayment.membership.member) }}
              </div>
              <div>
                <p class="text-sm font-medium text-gray-900">
                  {{ selectedPayment.membership.member.first_name }} {{ selectedPayment.membership.member.last_name }}
                </p>
                <p class="text-sm text-gray-500">{{ selectedPayment.membership.member.email }}</p>
              </div>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Beschreibung</label>
            <p class="mt-1 text-sm text-gray-900">{{ selectedPayment.description }}</p>
          </div>

          <div v-if="selectedPayment.transaction_id">
            <label class="block text-sm font-medium text-gray-700">Transaktions-ID</label>
            <p class="mt-1 text-sm text-gray-900 font-mono">{{ selectedPayment.transaction_id }}</p>
          </div>

          <div v-if="selectedPayment.mollie_payment_id">
            <label class="block text-sm font-medium text-gray-700">Zahlungs-ID (Mollie)</label>
            <p class="mt-1 text-sm text-gray-900 font-mono">{{ selectedPayment.mollie_payment_id }}</p>
          </div>

          <div v-if="selectedPayment.notes">
            <label class="block text-sm font-medium text-gray-700">Notizen</label>
            <p class="mt-1 text-sm text-gray-900">{{ selectedPayment.notes }}</p>
          </div>

          <!-- Custom detail fields slot -->
          <slot name="payment-details" :payment="selectedPayment"></slot>
        </div>

        <div class="mt-6 flex justify-end space-x-3">
          <button
            @click="closePaymentModal"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
          >
            Schließen
          </button>
          <button
            v-if="selectedPayment?.status === 'pending' && showCancelPayment"
            @click="cancelPaymentFromModal"
            class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-lg hover:bg-red-700 focus:ring-2 focus:ring-red-500 focus:border-red-500"
          >
            Zahlung abbrechen
          </button>
          <button
            v-if="selectedPayment?.status === 'pending' && showMarkAsPaid"
            @click="markAsPaidFromModal"
            class="px-4 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-lg hover:bg-green-700 focus:ring-2 focus:ring-green-500 focus:border-green-500"
          >
            Als bezahlt markieren
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import Pagination from '@/Components/Pagination.vue'
import {
  Download,
  ArrowUpDown,
  Eye,
  CheckCircle,
  XCircle,
  X
} from 'lucide-vue-next'
import { formatCurrency, formatDate, formatDateTime } from '@/utils/formatters'

// Props
const props = defineProps({
  payments: {
    type: Object,
    required: true
  },
  executingPaymentId: {
    type: [Number, String, null],
    default: null
  },
  batchExecutingPayments: {
    type: Boolean,
    default: false
  },
  columns: {
    type: Array,
    default: () => [
      { key: 'id', label: 'ID', sortable: true, nowrap: true },
      { key: 'created_at', label: 'Datum', sortable: true, nowrap: true },
      { key: 'member', label: 'Mitglied', sortable: false },
      { key: 'description', label: 'Beschreibung', sortable: false },
      { key: 'amount', label: 'Betrag', sortable: true, nowrap: true },
      { key: 'status', label: 'Status', sortable: false, nowrap: true },
      { key: 'payment_method', label: 'Zahlungsart', sortable: false, nowrap: true },
      { key: 'due_date', label: 'Fälligkeitsdatum', sortable: false, nowrap: true }
    ]
  },
  showCheckboxes: {
    type: Boolean,
    default: true
  },
  showActions: {
    type: Boolean,
    default: true
  },
  showViewDetails: {
    type: Boolean,
    default: true
  },
  showMarkAsPaid: {
    type: Boolean,
    default: true
  },
  showCancelPayment: {
    type: Boolean,
    default: true
  },
  showPagination: {
    type: Boolean,
    default: true
  },
  showCsvExport: {
    type: Boolean,
    default: true
  },
  showSepaExport: {
    type: Boolean,
    default: true
  },
  selectedIds: {
    type: Array,
    default: () => []
  },
  markPaidRoute: {
    type: String,
    default: 'payments.mark-paid'
  },
  cancelRoute: {
    type: String,
    default: 'payments.cancel'
  },
  exportRoute: {
    type: String,
    default: 'finances.export'
  },
  confirmMarkPaidMessage: {
    type: String,
    default: 'Möchten Sie diese Zahlung als bezahlt markieren?'
  },
  confirmCancelMessage: {
    type: String,
    default: 'Möchten Sie diese Zahlung wirklich abbrechen? Diese Aktion kann nicht rückgängig gemacht werden.'
  }
})

// Emits
const emit = defineEmits([
  'sort',
  'export',
  'paginate',
  'update:selectedIds',
  'payment-viewed',
  'payment-marked-paid',
  'payment-canceled',
  'before-mark-paid',
  'before-cancel',
  'before-export'
])

// Local state
const selectedPayments = ref(props.selectedIds)
const showPaymentModal = ref(false)
const selectedPayment = ref(null)
const isProcessing = ref(false)

// Watch for external changes to selectedIds
watch(() => props.selectedIds, (newVal) => {
  selectedPayments.value = newVal
})

// Watch for internal changes and emit them
watch(selectedPayments, (newVal) => {
  emit('update:selectedIds', newVal)
})

// Computed
const visibleColumns = computed(() => {
  return props.columns.filter(col => col.visible !== false)
})

const isAllSelected = computed(() => {
  return props.payments.data.length > 0 && selectedPayments.value.length === props.payments.data.length
})

const selectedSepaPayments = computed(() => {
  return props.payments.data.filter(payment =>
    selectedPayments.value.includes(payment.id) && payment.payment_method === 'sepa_direct_debit'
  )
})

// Methods
const toggleSelectAll = () => {
  if (isAllSelected.value) {
    selectedPayments.value = []
  } else {
    selectedPayments.value = props.payments.data.map(payment => payment.id)
  }
}

const handleSort = (column) => {
  emit('sort', column)
}

const handleExport = async (type) => {
  if (selectedPayments.value.length === 0) {
    alert('Bitte wählen Sie mindestens eine Zahlung aus.')
    return
  }

  // Allow parent to handle or prevent export
  const beforeExportEvent = {
    type,
    paymentIds: selectedPayments.value,
    preventDefault: false
  }

  emit('before-export', beforeExportEvent)

  if (!beforeExportEvent.preventDefault) {
    // If parent doesn't handle it, use default behavior
    if (props.exportRoute && window.route) {
      try {
        // Use axios to download the file as a blob
        const response = await axios.post(route(props.exportRoute), {
          payment_ids: selectedPayments.value,
          export_type: type
        }, {
          responseType: 'blob'
        })

        // Extract filename from Content-Disposition header or create a default one
        const contentDisposition = response.headers['content-disposition']
        let filename = `export_${type}_${new Date().toISOString().slice(0, 10)}`

        if (contentDisposition) {
          const filenameMatch = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/)
          if (filenameMatch && filenameMatch[1]) {
            filename = filenameMatch[1].replace(/['"]/g, '')
          }
        } else {
          // Add appropriate file extension based on type
          if (type === 'csv') filename += '.csv'
          else if (type === 'pain008') filename += '.xml'
          else if (type === 'pdf') filename += '.pdf'
        }

        // Create blob URL and trigger download
        const blob = new Blob([response.data], { type: response.headers['content-type'] })
        const url = window.URL.createObjectURL(blob)
        const link = document.createElement('a')
        link.href = url
        link.download = filename
        document.body.appendChild(link)
        link.click()

        // Cleanup
        document.body.removeChild(link)
        window.URL.revokeObjectURL(url)
      } catch (error) {
        console.error('Export failed:', error)
        alert('Export fehlgeschlagen. Bitte versuchen Sie es erneut.')
      }
    } else {
      // Emit for parent to handle
      emit('export', {
        type,
        paymentIds: selectedPayments.value
      })
    }
  }
}

const handlePaginationEvent = (event) => {
  // Handle loading states from Pagination component
  if (event.type === 'start') {
    isProcessing.value = true
  } else if (event.type === 'finish') {
    isProcessing.value = false
  }

  // Emit event for parent component if needed
  emit('paginate', event)
}

const viewPayment = (payment) => {
  selectedPayment.value = payment
  showPaymentModal.value = true
  emit('payment-viewed', payment)
}

const closePaymentModal = () => {
  showPaymentModal.value = false
  selectedPayment.value = null
}

const markAsPaid = async (payment) => {
  if (confirm(props.confirmMarkPaidMessage)) {
    await performMarkAsPaid(payment)
  }
}

const markAsPaidFromModal = async () => {
  if (selectedPayment.value && confirm(props.confirmMarkPaidMessage)) {
    await performMarkAsPaid(selectedPayment.value)
    closePaymentModal()
  }
}

const performMarkAsPaid = async (payment) => {
  // Allow parent to handle or prevent marking as paid
  const beforeMarkPaidEvent = {
    payment,
    preventDefault: false
  }

  emit('before-mark-paid', beforeMarkPaidEvent)

  if (!beforeMarkPaidEvent.preventDefault) {
    // If parent doesn't handle it, use default behavior
    if (props.markPaidRoute && window.route && router) {
      router.patch(route(props.markPaidRoute, payment.id), {}, {
        onSuccess: () => {
          emit('payment-marked-paid', payment)
        }
      })
    } else {
      // Emit for parent to handle
      emit('payment-marked-paid', payment)
    }
  }
}

const cancelPayment = async (payment) => {
  if (confirm(props.confirmCancelMessage)) {
    await performCancelPayment(payment)
  }
}

const cancelPaymentFromModal = async () => {
  if (selectedPayment.value && confirm(props.confirmCancelMessage)) {
    await performCancelPayment(selectedPayment.value)
    closePaymentModal()
  }
}

const performCancelPayment = async (payment) => {
  // Allow parent to handle or prevent cancellation
  const beforeCancelEvent = {
    payment,
    preventDefault: false
  }

  emit('before-cancel', beforeCancelEvent)

  if (!beforeCancelEvent.preventDefault) {
    // If parent doesn't handle it, use default behavior
    if (props.cancelRoute && window.route && router) {
      router.delete(route(props.cancelRoute, payment.id), {
        onSuccess: () => {
          emit('payment-canceled', payment)
        }
      })
    } else {
      // Emit for parent to handle
      emit('payment-canceled', payment)
    }
  }
}

// Utility functions
const getMemberInitials = (member) => {
  if (!member) return '??'
  const first = member.first_name?.charAt(0) || ''
  const last = member.last_name?.charAt(0) || ''
  return (first + last).toUpperCase()
}

const getStatusClasses = (color) => {
  const classes = {
    green: 'bg-green-100 text-green-800',
    yellow: 'bg-yellow-100 text-yellow-800',
    red: 'bg-red-100 text-red-800',
    blue: 'bg-indigo-100 text-indigo-800',
    gray: 'bg-gray-100 text-gray-800'
  }
  return classes[color] || classes.gray
}

// Expose methods for parent component if needed
defineExpose({
  viewPayment,
  closePaymentModal,
  selectedPayments
})
</script>
