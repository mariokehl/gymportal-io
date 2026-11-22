<template>
  <div>
    <!-- Actions Bar -->
    <div class="bg-surface p-4 rounded-lg shadow-sm border border-line mb-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
          <div class="flex items-center">
            <input
              id="select-all"
              type="checkbox"
              :checked="isAllSelected"
              @change="toggleSelectAll"
              :disabled="batchExecutingPayments"
              class="h-4 w-4 text-brand border-line-strong rounded disabled:opacity-50"
            />
            <label for="select-all" class="ml-2 text-sm font-medium text-ink-secondary">
              Alle auswählen
            </label>
          </div>

          <span v-if="selectedPayments.length > 0" class="text-sm text-ink-secondary">
            {{ selectedPayments.length }} von {{ payments.data.length }} ausgewählt
          </span>

          <!-- Batch Progress Indicator -->
          <div v-if="batchExecutingPayments" class="flex items-center text-sm text-brand">
            <div class="w-4 h-4 border-2 border-brand border-t-transparent rounded-full animate-spin mr-2"></div>
            Zahlungen werden ausgeführt...
          </div>
        </div>

        <div class="flex items-center gap-2">
          <!-- Extra controls of the parent page, e.g. the page size -->
          <slot name="toolbar" />

          <button
            v-if="selectedPayments.length > 0 && showCsvExport"
            @click="handleExport('csv')"
            :disabled="batchExecutingPayments"
            class="flex items-center px-4 py-2 text-sm font-medium text-ink-secondary bg-surface border border-line-strong rounded-lg hover:bg-surface-muted disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <Download class="w-4 h-4 mr-2" />
            CSV Export
          </button>

          <button
            v-if="selectedSepaPayments.length > 0 && showSepaExport"
            @click="handleExport('pain008')"
            :disabled="batchExecutingPayments"
            class="flex items-center px-4 py-2 text-sm font-medium text-white bg-brand border border-transparent rounded-lg hover:bg-brand-strong disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <Download class="w-4 h-4 mr-2" />
            PAIN.008 Export ({{ selectedSepaPayments.length }})
          </button>
        </div>
      </div>
    </div>

    <!-- Payments Table -->
    <div class="bg-surface rounded-lg shadow-sm border border-line overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-line">
          <thead class="bg-surface-muted">
            <tr>
              <th class="px-4 2xl:px-6 py-3 text-left" v-if="showCheckboxes">
                <input
                  type="checkbox"
                  :checked="isAllSelected"
                  @change="toggleSelectAll"
                  class="h-4 w-4 text-brand border-line-strong rounded"
                />
              </th>
              <th
                v-for="column in visibleColumns"
                :key="column.key"
                class="px-4 2xl:px-6 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider"
                :class="{
                  'cursor-pointer hover:bg-fill': column.sortable,
                  'text-right': column.align === 'right'
                }"
                @click="column.sortable && handleSort(column.sortKey || column.key)"
              >
                <div class="flex items-center" :class="{ 'justify-end': column.align === 'right' }">
                  {{ column.label }}
                  <ArrowUpDown v-if="column.sortable" class="w-4 h-4 ml-1" />
                </div>
              </th>
              <th class="px-4 2xl:px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider" v-if="showActions">
                Aktionen
              </th>
            </tr>
          </thead>
          <tbody class="bg-surface divide-y divide-line">
            <template v-for="payment in payments.data" :key="payment.id">
            <tr
              :class="[
                rowBackgroundClass(payment),
                { 'opacity-60': executingPaymentId === payment.id }
              ]"
            >
              <td class="px-4 2xl:px-6 py-4 whitespace-nowrap" v-if="showCheckboxes">
                <input
                  type="checkbox"
                  :value="payment.id"
                  v-model="selectedPayments"
                  :disabled="executingPaymentId === payment.id || batchExecutingPayments"
                  class="h-4 w-4 text-brand border-line-strong rounded disabled:opacity-50"
                />
              </td>
              <td
                v-for="column in visibleColumns"
                :key="column.key"
                class="px-4 2xl:px-6 py-4"
                :class="{
                  'whitespace-nowrap': column.nowrap,
                  'text-right': column.align === 'right'
                }"
              >
                <!-- ID Column -->
                <template v-if="column.key === 'id'">
                  <span class="text-sm font-medium text-ink">#{{ payment.id }}</span>
                </template>

                <!-- Date Column -->
                <template v-else-if="column.key === 'created_at'">
                  <span class="text-sm text-ink">{{ formatDate(payment.created_at) }}</span>
                </template>

                <!-- Member Column -->
                <template v-else-if="column.key === 'member'">
                  <MemberIdentity
                    :member="payment.member"
                    size="sm"
                    max-width="250px"
                  />
                </template>

                <!-- Description Column -->
                <template v-else-if="column.key === 'description'">
                  <!-- Wraps (within a readable width) so the table fits narrower screens -->
                  <div class="min-w-48 max-w-sm text-sm text-ink">{{ payment.description }}</div>
                  <div v-if="isCreditTopup(payment)" class="max-w-sm truncate text-xs text-ink-muted font-mono">
                    {{ creditTopupSource(payment) }}
                  </div>
                  <div v-else-if="payment.transaction_id" class="max-w-sm truncate text-xs text-ink-muted font-mono" :title="payment.transaction_id">
                    Tx: {{ payment.transaction_id }}
                  </div>
                  <div v-else-if="payment.mollie_payment_id" class="max-w-sm truncate text-xs text-ink-muted font-mono" :title="payment.mollie_payment_id">
                    Tx: {{ payment.mollie_payment_id }}
                  </div>
                </template>

                <!-- Amount Column -->
                <template v-else-if="column.key === 'amount'">
                  <span class="inline-flex items-center gap-1.5">
                    <span class="text-sm font-semibold">{{ formatCurrency(payment.amount) }}</span>
                    <DiscountBadge :discount="payment.metadata?.discount" />
                  </span>
                </template>

                <!-- Status Column -->
                <template v-else-if="column.key === 'status'">
                  <div class="flex items-center">
                    <PaymentStatusBadge :status="payment.status" />
                    <!-- Chargeback/Refund Badge -->
                    <button
                      v-if="getChargebackRefundCount(payment) > 0"
                      @click.stop="toggleRowExpansion(payment.id)"
                      class="ml-2 inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-orange-100 text-orange-800 hover:bg-orange-200 transition-colors cursor-pointer"
                      :title="isRowExpanded(payment.id) ? 'Details ausblenden' : 'Details anzeigen'"
                    >
                      <component
                        :is="isRowExpanded(payment.id) ? ChevronDown : ChevronRight"
                        class="w-3 h-3 mr-1"
                      />
                      {{ getChargebackRefundCount(payment) }}
                    </button>
                    <!-- Credit redemption badge -->
                    <button
                      v-if="hasCreditRedemption(payment)"
                      @click.stop="toggleRowExpansion(payment.id)"
                      class="ml-2 inline-flex items-center gap-1 px-2 py-0.5 text-xs font-semibold rounded-full bg-brand-subtle text-brand hover:bg-brand-soft transition-colors cursor-pointer"
                      :title="isRowExpanded(payment.id) ? 'Guthaben-Einlösung ausblenden' : 'Guthaben-Einlösung anzeigen'"
                    >
                      1
                      <component
                        :is="isRowExpanded(payment.id) ? ChevronDown : ChevronRight"
                        class="w-3 h-3"
                      />
                    </button>
                    <div
                      v-if="executingPaymentId === payment.id"
                      class="ml-2 w-4 h-4 border-2 border-brand border-t-transparent rounded-full animate-spin"
                      title="Zahlung wird ausgeführt..."
                    ></div>
                  </div>
                </template>

                <!-- Payment Method Column -->
                <template v-else-if="column.key === 'payment_method'">
                  <span class="text-sm text-ink inline-flex items-center gap-1.5">
                    <BanknoteArrowUp
                      v-if="isCreditTopup(payment)"
                      class="w-4 h-4 text-brand flex-none"
                      title="Guthaben-Aufladung"
                    />
                    <BanknoteArrowDown
                      v-else-if="hasCreditRedemption(payment)"
                      class="w-4 h-4 text-brand flex-none"
                      title="Guthaben-Einlösung"
                    />
                    <component
                      :is="getPaymentMethodIcon(payment)"
                      v-else-if="getPaymentMethodIcon(payment)"
                      class="w-4 h-4 text-ink-muted flex-none"
                    />
                    {{ getPaymentMethodLabel(payment) }}
                  </span>
                </template>

                <!-- Due Date Column -->
                <template v-else-if="column.key === 'due_date'">
                  <div v-if="payment.due_date" class="text-sm text-ink">{{ formatDate(payment.due_date) }}</div>
                  <span v-else class="text-sm text-ink-muted">-</span>
                  <div v-if="payment.execution_date" class="text-sm text-ink-muted">{{ formatDate(payment.execution_date) }}</div>
                </template>

                <!-- Custom Column Slot -->
                <template v-else>
                  <slot :name="`column-${column.key}`" :payment="payment" :value="payment[column.key]">
                    {{ payment[column.key] }}
                  </slot>
                </template>
              </td>
              <td class="px-4 2xl:px-6 py-4 whitespace-nowrap text-right text-sm font-medium" v-if="showActions">
                <!-- Touch: spread icons so their 44px hit areas do not overlap -->
                <div class="flex items-center justify-end space-x-2 pointer-coarse:space-x-7">
                  <button
                    v-if="showViewDetails"
                    @click="viewPayment(payment)"
                    class="touch-target relative text-ink-secondary hover:text-ink"
                    title="Details anzeigen"
                  >
                    <Eye class="w-4 h-4" />
                  </button>
                  <button
                    v-if="payment.status === 'pending' && showMarkAsPaid"
                    @click="markAsPaid(payment)"
                    class="touch-target relative text-success hover:text-success-strong"
                    title="Als bezahlt markieren"
                  >
                    <CheckCircle class="w-4 h-4" />
                  </button>
                  <button
                    v-if="payment.status === 'pending' && showCancelPayment"
                    @click="cancelPayment(payment)"
                    class="touch-target relative text-danger hover:text-danger-strong"
                    title="Zahlung abbrechen"
                  >
                    <XCircle class="w-4 h-4" />
                  </button>
                  <slot name="actions" :payment="payment" :is-executing="executingPaymentId === payment.id"></slot>
                </div>
              </td>
            </tr>

            <!-- Expanded Row for Chargebacks/Refunds/Credit -->
            <tr v-if="isRowExpanded(payment.id) && (getChargebackRefundCount(payment) > 0 || hasCreditRedemption(payment))">
              <td
                :colspan="visibleColumns.length + (showCheckboxes ? 1 : 0) + (showActions ? 1 : 0)"
                class="px-6 py-4"
                :class="expandedBackgroundClass(payment)"
              >
                <div class="space-y-4">
                  <!-- Credit redemption -->
                  <div v-if="getCreditRedemption(payment)">
                    <h4 class="text-sm font-semibold text-ink flex items-center mb-2">
                      <RotateCcw class="w-4 h-4 mr-2 text-brand" />
                      Guthaben-Einlösung (1)
                    </h4>
                    <div class="bg-surface rounded-lg border border-line overflow-hidden">
                      <table class="min-w-full divide-y divide-line">
                        <thead class="bg-fill">
                          <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-ink-muted uppercase">ID</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-ink-muted uppercase">Datum</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-ink-muted uppercase">Betrag</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-ink-muted uppercase">Status</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-ink-muted uppercase">Beschreibung</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-ink-muted uppercase">Restguthaben</th>
                          </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                          <tr class="hover:bg-surface-muted">
                            <td class="px-4 py-2 text-sm text-ink font-mono">{{ getCreditRedemption(payment).evId }}</td>
                            <td class="px-4 py-2 text-sm text-ink-secondary">{{ formatDate(payment.paid_date || payment.due_date || payment.created_at) }}</td>
                            <td class="px-4 py-2 text-sm font-semibold text-brand">− {{ formatCentsEuro(getCreditRedemption(payment).redeemedCents) }}</td>
                            <td class="px-4 py-2">
                              <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-success-soft text-success">
                                <span class="w-1.5 h-1.5 rounded-full bg-success-solid"></span>
                                Eingelöst
                              </span>
                            </td>
                            <td class="px-4 py-2 text-sm text-ink-secondary">{{ payment.description }}</td>
                            <td class="px-4 py-2 text-sm text-ink-secondary">{{ formatCentsEuro(getCreditRedemption(payment).balanceAfterCents) }}</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>

                  <!-- Chargebacks -->
                  <div v-if="payment.chargebacks?.length > 0">
                    <h4 class="text-sm font-semibold text-ink flex items-center mb-2">
                      <AlertTriangle class="w-4 h-4 mr-2 text-danger" />
                      Rückbuchungen ({{ payment.chargebacks.length }})
                    </h4>
                    <div class="bg-surface rounded-lg border border-line overflow-hidden">
                      <table class="min-w-full divide-y divide-line">
                        <thead class="bg-fill">
                          <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-ink-muted uppercase">ID</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-ink-muted uppercase">Datum</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-ink-muted uppercase">Betrag</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-ink-muted uppercase">Status</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-ink-muted uppercase">Grund</th>
                          </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                          <tr v-for="chargeback in payment.chargebacks" :key="chargeback.id" class="hover:bg-surface-muted">
                            <td class="px-4 py-2 text-sm text-ink font-mono">{{ chargeback.mollie_chargeback_id }}</td>
                            <td class="px-4 py-2 text-sm text-ink">{{ formatDate(chargeback.chargeback_date) }}</td>
                            <td class="px-4 py-2 text-sm font-semibold text-danger">-{{ formatCurrency(chargeback.amount) }}</td>
                            <td class="px-4 py-2">
                              <PaymentStatusBadge :status="chargeback.status" type="chargeback" />
                            </td>
                            <td class="px-4 py-2 text-sm text-ink-secondary">{{ chargeback.reason || '-' }}</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>

                  <!-- Refunds -->
                  <div v-if="payment.refunds?.length > 0">
                    <h4 class="text-sm font-semibold text-ink flex items-center mb-2">
                      <RotateCcw class="w-4 h-4 mr-2 text-info" />
                      Erstattungen ({{ payment.refunds.length }})
                    </h4>
                    <div class="bg-surface rounded-lg border border-line overflow-hidden">
                      <table class="min-w-full divide-y divide-line">
                        <thead class="bg-fill">
                          <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-ink-muted uppercase">ID</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-ink-muted uppercase">Datum</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-ink-muted uppercase">Betrag</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-ink-muted uppercase">Status</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-ink-muted uppercase">Beschreibung</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-ink-muted uppercase">Grund</th>
                          </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                          <tr v-for="refund in payment.refunds" :key="refund.id" class="hover:bg-surface-muted">
                            <td class="px-4 py-2 text-sm text-ink font-mono">{{ refund.mollie_refund_id }}</td>
                            <td class="px-4 py-2 text-sm text-ink">{{ formatDate(refund.created_at) }}</td>
                            <td class="px-4 py-2 text-sm font-semibold text-info">-{{ formatCurrency(refund.amount) }}</td>
                            <td class="px-4 py-2">
                              <PaymentStatusBadge :status="refund.status" type="refund" />
                            </td>
                            <td class="px-4 py-2 text-sm text-ink-secondary">{{ refund.description || '-' }}</td>
                            <td class="px-4 py-2 text-sm text-ink-secondary">{{ refund.reason || '-' }}</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </td>
            </tr>
            </template>
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

    <PaymentDetailModal
      :show="showPaymentModal"
      :payment="selectedPayment"
      :show-mark-as-paid="showMarkAsPaid"
      :show-cancel-payment="showCancelPayment"
      @close="closePaymentModal"
      @mark-as-paid="markAsPaidFromModal"
      @cancel-payment="cancelPaymentFromModal"
      @payment-updated="applyPaymentUpdate"
    >
      <template #payment-details="slotProps">
        <slot name="payment-details" v-bind="slotProps"></slot>
      </template>
    </PaymentDetailModal>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import Pagination from '@/Components/Pagination.vue'
import PaymentStatusBadge from '@/Components/PaymentStatusBadge.vue'
import PaymentDetailModal from '@/Components/PaymentDetailModal.vue'
import MemberIdentity from '@/Components/Members/MemberIdentity.vue'
import DiscountBadge from '@/Components/DiscountBadge.vue'
import {
  Download,
  ArrowUpDown,
  Eye,
  CheckCircle,
  XCircle,
  ChevronDown,
  ChevronRight,
  RotateCcw,
  AlertTriangle,
  Link2,
  BanknoteArrowUp,
  BanknoteArrowDown,
  Building2,
  CreditCard,
  Banknote,
  FileText,
  WalletCards,
  Wallet
} from '@lucide/vue'
import { formatCurrency, formatDate } from '@/utils/formatters'
import {
  getCreditRedemption,
  hasCreditRedemption,
  isCreditTopup,
  creditTopupSource,
  isScheduledInFuture,
  isWithinPause
} from '@/utils/payments'

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
const expandedRows = ref(new Set())

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
/**
 * Background of a payment row. A payment inside its membership's pause period
 * is blue, which takes precedence over the amber of a future-dated one: the
 * pause is the more specific reason the charge is not collected as usual.
 */
const rowBackgroundClass = (payment) => {
  if (isWithinPause(payment)) return 'bg-info-subtle hover:bg-info-soft'
  if (isScheduledInFuture(payment)) return 'bg-warning-subtle hover:bg-warning-soft'

  return 'hover:bg-surface-muted'
}

/**
 * Matching background for the expanded chargeback/refund/credit sub-row.
 */
const expandedBackgroundClass = (payment) => {
  if (isWithinPause(payment)) return 'bg-info-subtle'
  if (isScheduledInFuture(payment)) return 'bg-warning-subtle'

  return 'bg-surface-muted'
}

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

// Apply a partial update coming from the detail modal to both the selected
// payment and its row in the table, so the list reflects the change instantly.
const applyPaymentUpdate = (changes) => {
  if (!selectedPayment.value) return

  Object.assign(selectedPayment.value, changes)

  const payment = props.payments.data.find(p => p.id === selectedPayment.value.id)
  if (payment && payment !== selectedPayment.value) {
    Object.assign(payment, changes)
  }
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
const getChargebackRefundCount = (payment) => {
  const chargebackCount = payment.chargebacks?.length || 0
  const refundCount = payment.refunds?.length || 0
  return chargebackCount + refundCount
}

const formatCentsEuro = (cents) =>
  (cents / 100).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €'

// Payment method label. A leading wallet icon (rendered separately) marks a
// credit top-up or redemption, so the credit part is not repeated in the text.
const getPaymentMethodLabel = (payment) => payment.payment_method_text

// Icon for a payment method. Credit top-ups/redemptions are handled separately
// with directional banknote icons; this covers the regular methods.
const paymentMethodIcons = {
  sepa_direct_debit: Building2,
  banktransfer: Building2,
  mollie_directdebit: Building2,
  mollie_banktransfer: Building2,
  creditcard: CreditCard,
  mollie_creditcard: CreditCard,
  cash: Banknote,
  invoice: FileText,
  mollie_klarna: FileText,
  mollie_paymentlink: Link2,
  mollie_paypal: WalletCards,
  credit: Wallet,
}

const getPaymentMethodIcon = (payment) => paymentMethodIcons[payment?.payment_method] || null

const toggleRowExpansion = (paymentId) => {
  if (expandedRows.value.has(paymentId)) {
    expandedRows.value.delete(paymentId)
  } else {
    expandedRows.value.add(paymentId)
  }
  // Trigger reactivity
  expandedRows.value = new Set(expandedRows.value)
}

const isRowExpanded = (paymentId) => {
  return expandedRows.value.has(paymentId)
}


// Expose methods for parent component if needed
defineExpose({
  viewPayment,
  closePaymentModal,
  selectedPayments
})
</script>
