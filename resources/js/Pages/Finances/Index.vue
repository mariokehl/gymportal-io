<template>
  <AppLayout title="Finanzen">
    <template #header>
      Finanzen
    </template>

    <div class="space-y-6">
      <!-- Statistics Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Gesamtumsatz</p>
              <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(statistics.total_amount) }}</p>
            </div>
            <div class="p-3 bg-blue-100 rounded-full">
              <DollarSign class="w-6 h-6 text-blue-600" />
            </div>
          </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Bezahlt</p>
              <p class="text-2xl font-bold text-green-600">{{ formatCurrency(statistics.paid_amount) }}</p>
              <p class="text-sm text-gray-500">{{ statistics.paid_count }} Zahlungen</p>
            </div>
            <div class="p-3 bg-green-100 rounded-full">
              <CheckCircle class="w-6 h-6 text-green-600" />
            </div>
          </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Ausstehend</p>
              <p class="text-2xl font-bold text-yellow-600">{{ formatCurrency(statistics.pending_amount) }}</p>
              <p class="text-sm text-gray-500">{{ statistics.pending_count }} Zahlungen</p>
            </div>
            <div class="p-3 bg-yellow-100 rounded-full">
              <Clock class="w-6 h-6 text-yellow-600" />
            </div>
          </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Fehlgeschlagen</p>
              <p class="text-2xl font-bold text-red-600">{{ formatCurrency(statistics.failed_amount) }}</p>
              <p class="text-sm text-gray-500">{{ statistics.failed_count }} Zahlungen</p>
            </div>
            <div class="p-3 bg-red-100 rounded-full">
              <XCircle class="w-6 h-6 text-red-600" />
            </div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
        <div class="flex flex-wrap gap-4 mb-4">
          <div class="flex-1 min-w-64">
            <label class="block text-sm font-medium text-gray-700 mb-1">Suchen</label>
            <div class="relative">
              <Search class="absolute left-3 top-3 h-4 w-4 text-gray-400" />
              <input
                v-model="localFilters.search"
                type="text"
                placeholder="Nach Mitglied, Beschreibung oder Transaktions-ID suchen..."
                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                @input="debouncedFilter"
              />
            </div>
          </div>

          <div class="w-48">
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select
              v-model="localFilters.status"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              @change="applyFilters"
            >
              <option value="">Alle Status</option>
              <option v-for="(label, value) in statusOptions" :key="value" :value="value">
                {{ label }}
              </option>
            </select>
          </div>

          <div class="w-48">
            <label class="block text-sm font-medium text-gray-700 mb-1">Zahlungsart</label>
            <select
              v-model="localFilters.payment_method"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              @change="applyFilters"
            >
              <option value="">Alle Zahlungsarten</option>
              <option v-for="(label, value) in paymentMethodOptions" :key="value" :value="value">
                {{ label }}
              </option>
            </select>
          </div>

          <button
            @click="showAdvancedFilters = !showAdvancedFilters"
            class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          >
            <Filter class="w-4 h-4 mr-2" />
            Erweiterte Filter
            <ChevronDown :class="{ 'rotate-180': showAdvancedFilters }" class="w-4 h-4 ml-2 transform transition-transform" />
          </button>
        </div>

        <!-- Advanced Filters -->
        <div v-if="showAdvancedFilters" class="border-t pt-4">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Datum von</label>
              <input
                v-model="localFilters.date_from"
                type="date"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                @change="applyFilters"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Datum bis</label>
              <input
                v-model="localFilters.date_to"
                type="date"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                @change="applyFilters"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Betrag von</label>
              <input
                v-model="localFilters.amount_from"
                type="number"
                step="0.01"
                placeholder="0.00"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                @change="applyFilters"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Betrag bis</label>
              <input
                v-model="localFilters.amount_to"
                type="number"
                step="0.01"
                placeholder="0.00"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                @change="applyFilters"
              />
            </div>
          </div>

          <div class="mt-4 flex gap-2">
            <button
              @click="clearFilters"
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              Filter zurücksetzen
            </button>
          </div>
        </div>
      </div>

      <!-- Actions Bar -->
      <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-4">
            <div class="flex items-center">
              <input
                id="select-all"
                type="checkbox"
                :checked="isAllSelected"
                @change="toggleSelectAll"
                class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
              />
              <label for="select-all" class="ml-2 text-sm font-medium text-gray-700">
                Alle auswählen
              </label>
            </div>

            <span v-if="selectedPayments.length > 0" class="text-sm text-gray-600">
              {{ selectedPayments.length }} von {{ payments.data.length }} ausgewählt
            </span>
          </div>

          <div class="flex items-center gap-2">
            <button
              v-if="selectedPayments.length > 0"
              @click="exportPayments('csv')"
              class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <Download class="w-4 h-4 mr-2" />
              CSV Export
            </button>

            <button
              v-if="selectedSepaPayments.length > 0"
              @click="exportPayments('pain008')"
              class="flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
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
                <th class="px-6 py-3 text-left">
                  <input
                    type="checkbox"
                    :checked="isAllSelected"
                    @change="toggleSelectAll"
                    class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                  />
                </th>
                <th
                  class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                  @click="sort('id')"
                >
                  <div class="flex items-center">
                    ID
                    <ArrowUpDown class="w-4 h-4 ml-1" />
                  </div>
                </th>
                <th
                  class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                  @click="sort('created_at')"
                >
                  <div class="flex items-center">
                    Datum
                    <ArrowUpDown class="w-4 h-4 ml-1" />
                  </div>
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Mitglied
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Beschreibung
                </th>
                <th
                  class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                  @click="sort('amount')"
                >
                  <div class="flex items-center">
                    Betrag
                    <ArrowUpDown class="w-4 h-4 ml-1" />
                  </div>
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Status
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Zahlungsart
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Fälligkeitsdatum
                </th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Aktionen
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr
                v-for="payment in payments.data"
                :key="payment.id"
                class="hover:bg-gray-50"
              >
                <td class="px-6 py-4 whitespace-nowrap">
                  <input
                    type="checkbox"
                    :value="payment.id"
                    v-model="selectedPayments"
                    class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                  />
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                  #{{ payment.id }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ formatDate(payment.created_at) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <div class="w-8 h-8 bg-blue-500 rounded-full text-white flex items-center justify-center text-xs font-semibold mr-3">
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
                </td>
                <td class="px-6 py-4">
                  <div class="text-sm text-gray-900">{{ payment.description }}</div>
                  <div v-if="payment.transaction_id" class="text-sm text-gray-500">
                    TXN: {{ payment.transaction_id }}
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold">
                  {{ formatCurrency(payment.amount) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span
                    :class="getStatusClasses(payment.status_color)"
                    class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                  >
                    {{ payment.status_text }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ payment.payment_method_text }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  <span v-if="payment.due_date">{{ formatDate(payment.due_date) }}</span>
                  <span v-else class="text-gray-400">-</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <div class="flex items-center justify-end space-x-2">
                    <button
                      @click="viewPayment(payment)"
                      class="text-blue-600 hover:text-blue-900"
                      title="Details anzeigen"
                    >
                      <Eye class="w-4 h-4" />
                    </button>
                    <button
                      v-if="payment.status === 'pending'"
                      @click="markAsPaid(payment)"
                      class="text-green-600 hover:text-green-900"
                      title="Als bezahlt markieren"
                    >
                      <CheckCircle class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
          <div class="flex-1 flex justify-between sm:hidden">
            <Link
              v-if="payments.prev_page_url"
              :href="payments.prev_page_url"
              class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
            >
              Zurück
            </Link>
            <Link
              v-if="payments.next_page_url"
              :href="payments.next_page_url"
              class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
            >
              Weiter
            </Link>
          </div>
          <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
              <p class="text-sm text-gray-700">
                Zeige
                <span class="font-medium">{{ payments.from || 0 }}</span>
                bis
                <span class="font-medium">{{ payments.to || 0 }}</span>
                von
                <span class="font-medium">{{ payments.total }}</span>
                Zahlungen
              </p>
            </div>
            <div>
              <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <Link
                  v-if="payments.prev_page_url"
                  :href="payments.prev_page_url"
                  class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
                >
                  <span class="sr-only">Zurück</span>
                  <ChevronLeft class="h-5 w-5" />
                </Link>

                <template v-for="link in payments.links" :key="link.label">
                  <Link
                    v-if="link.url && !link.label.includes('Previous') && !link.label.includes('Next')"
                    :href="link.url"
                    :class="[
                      'relative inline-flex items-center px-4 py-2 border text-sm font-medium',
                      link.active
                        ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
                        : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                    ]"
                    v-html="link.label"
                  />
                  <span
                    v-else-if="!link.url && !link.label.includes('Previous') && !link.label.includes('Next')"
                    class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700"
                  >
                    ...
                  </span>
                </template>

                <Link
                  v-if="payments.next_page_url"
                  :href="payments.next_page_url"
                  class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
                >
                  <span class="sr-only">Weiter</span>
                  <ChevronRight class="h-5 w-5" />
                </Link>
              </nav>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Payment Detail Modal -->
    <div
      v-if="showPaymentModal"
      class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
      @click="closePaymentModal"
    >
      <div
        class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white"
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
              <p class="mt-1 text-sm text-gray-900">{{ formatDate(selectedPayment.created_at) }}</p>
            </div>
          </div>

          <div v-if="selectedPayment.membership?.member">
            <label class="block text-sm font-medium text-gray-700">Mitglied</label>
            <div class="mt-1 flex items-center">
              <div class="w-8 h-8 bg-blue-500 rounded-full text-white flex items-center justify-center text-xs font-semibold mr-3">
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

          <div v-if="selectedPayment.notes">
            <label class="block text-sm font-medium text-gray-700">Notizen</label>
            <p class="mt-1 text-sm text-gray-900">{{ selectedPayment.notes }}</p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { router, usePage, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
  DollarSign,
  CheckCircle,
  Clock,
  XCircle,
  Search,
  Filter,
  ChevronDown,
  Download,
  ArrowUpDown,
  Eye,
  ChevronLeft,
  ChevronRight,
  X
} from 'lucide-vue-next'
import { debounce } from 'lodash'

// Props
const props = defineProps({
  payments: Object,
  filters: Object,
  statusOptions: Object,
  paymentMethodOptions: Object,
  statistics: Object
})

// Reactive data
const selectedPayments = ref([])
const showAdvancedFilters = ref(false)
const showPaymentModal = ref(false)
const selectedPayment = ref(null)
const localFilters = ref({
  search: props.filters.search || '',
  status: props.filters.status || '',
  payment_method: props.filters.payment_method || '',
  date_from: props.filters.date_from || '',
  date_to: props.filters.date_to || '',
  amount_from: props.filters.amount_from || '',
  amount_to: props.filters.amount_to || ''
})

// Computed properties
const isAllSelected = computed(() => {
  return props.payments.data.length > 0 && selectedPayments.value.length === props.payments.data.length
})

const selectedSepaPayments = computed(() => {
  return props.payments.data.filter(payment =>
    selectedPayments.value.includes(payment.id) && payment.payment_method === 'sepa'
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

const applyFilters = () => {
  router.get(route('finances.index'), localFilters.value, {
    preserveState: true,
    preserveScroll: true
  })
}

const debouncedFilter = debounce(() => {
  applyFilters()
}, 300)

const clearFilters = () => {
  localFilters.value = {
    search: '',
    status: '',
    payment_method: '',
    date_from: '',
    date_to: '',
    amount_from: '',
    amount_to: ''
  }
  applyFilters()
}

const sort = (column) => {
  const currentSort = props.filters.sort_by
  const currentOrder = props.filters.sort_order || 'desc'

  let newOrder = 'desc'
  if (currentSort === column && currentOrder === 'desc') {
    newOrder = 'asc'
  }

  router.get(route('finances.index'), {
    ...localFilters.value,
    sort_by: column,
    sort_order: newOrder
  }, {
    preserveState: true,
    preserveScroll: true
  })
}

const exportPayments = (type) => {
  if (selectedPayments.value.length === 0) {
    alert('Bitte wählen Sie mindestens eine Zahlung aus.')
    return
  }

  router.post(route('finances.export'), {
    payment_ids: selectedPayments.value,
    export_type: type
  })
}

const viewPayment = (payment) => {
  selectedPayment.value = payment
  showPaymentModal.value = true
}

const closePaymentModal = () => {
  showPaymentModal.value = false
  selectedPayment.value = null
}

const markAsPaid = (payment) => {
  if (confirm('Möchten Sie diese Zahlung als bezahlt markieren?')) {
    router.patch(route('payments.mark-paid', payment.id))
  }
}

// Utility functions
const formatCurrency = (amount) => {
  return new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency: 'EUR'
  }).format(amount)
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('de-DE', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}

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
    blue: 'bg-blue-100 text-blue-800',
    gray: 'bg-gray-100 text-gray-800'
  }
  return classes[color] || classes.gray
}
</script>
