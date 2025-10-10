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
            <div class="p-3 bg-indigo-100 rounded-full">
              <DollarSign class="w-6 h-6 text-indigo-600" />
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
                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                @input="debouncedFilter"
              />
            </div>
          </div>

          <div class="w-48">
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select
              v-model="localFilters.status"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
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
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
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
            class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
          >
            <Filter class="w-4 h-4 mr-2" />
            Erweiterte Filter
            <ChevronDown :class="{ 'rotate-180': showAdvancedFilters }" class="w-4 h-4 ml-2 transform transition-transform" />
          </button>
        </div>

        <!-- Advanced Filters -->
        <div v-if="showAdvancedFilters" class="border-t border-gray-200 pt-4">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Datum von</label>
              <input
                v-model="localFilters.date_from"
                type="date"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                @change="applyFilters"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Datum bis</label>
              <input
                v-model="localFilters.date_to"
                type="date"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
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
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
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
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                @change="applyFilters"
              />
            </div>
          </div>

          <div class="mt-4 flex gap-2">
            <button
              @click="clearFilters"
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            >
              Filter zurücksetzen
            </button>
          </div>
        </div>
      </div>

      <!-- Payments Table Component -->
      <PaymentsTable
        :payments="payments"
        :columns="tableColumns"
        v-model:selectedIds="selectedPayments"
        @sort="handleSort"
      />
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PaymentsTable from '@/Components/PaymentsTable.vue'
import {
  DollarSign,
  CheckCircle,
  Clock,
  XCircle,
  Search,
  Filter,
  ChevronDown
} from 'lucide-vue-next'
import { debounce } from 'lodash'
import { formatCurrency } from '@/utils/formatters'

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
const localFilters = ref({
  search: props.filters.search || '',
  status: props.filters.status || '',
  payment_method: props.filters.payment_method || '',
  date_from: props.filters.date_from || '',
  date_to: props.filters.date_to || '',
  amount_from: props.filters.amount_from || '',
  amount_to: props.filters.amount_to || ''
})

// Table columns configuration
const tableColumns = ref([
  { key: 'id', label: 'ID', sortable: true, nowrap: true },
  { key: 'created_at', label: 'Datum', sortable: true, nowrap: true },
  { key: 'member', label: 'Mitglied', sortable: false },
  { key: 'description', label: 'Beschreibung', sortable: false },
  { key: 'amount', label: 'Betrag', sortable: true, nowrap: true },
  { key: 'status', label: 'Status', sortable: false, nowrap: true },
  { key: 'payment_method', label: 'Zahlungsart', sortable: false, nowrap: true },
  { key: 'due_date', label: 'Fälligkeitsdatum', sortable: false, nowrap: true }
])

// Methods
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

const handleSort = (column) => {
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
</script>
