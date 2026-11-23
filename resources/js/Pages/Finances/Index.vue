<template>
  <AppLayout title="Finanzen">
    <template #header>
      Finanzen
    </template>

    <div class="space-y-6">
      <!-- Statistics Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-surface p-6 rounded-lg shadow-sm border border-line">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-ink-secondary">Gesamtumsatz</p>
              <p class="text-2xl font-bold text-ink tabular-nums">{{ formatCurrency(statistics.total_amount) }}</p>
            </div>
            <div class="p-3 bg-brand-soft rounded-full">
              <DollarSign class="w-6 h-6 text-brand" />
            </div>
          </div>
        </div>

        <div class="bg-surface p-6 rounded-lg shadow-sm border border-line">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-ink-secondary">Bezahlt</p>
              <p class="text-2xl font-bold text-success tabular-nums">{{ formatCurrency(statistics.paid_amount) }}</p>
              <p class="text-sm text-ink-muted">{{ statistics.paid_count }} Zahlungen</p>
            </div>
            <div class="p-3 bg-success-soft rounded-full">
              <CheckCircle class="w-6 h-6 text-success" />
            </div>
          </div>
        </div>

        <div class="bg-surface p-6 rounded-lg shadow-sm border border-line">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-ink-secondary">Ausstehend</p>
              <p class="text-2xl font-bold text-warning tabular-nums">{{ formatCurrency(statistics.pending_amount) }}</p>
              <p class="text-sm text-ink-muted">{{ statistics.pending_count }} Zahlungen</p>
            </div>
            <div class="p-3 bg-warning-soft rounded-full">
              <Clock class="w-6 h-6 text-warning" />
            </div>
          </div>
        </div>

        <div class="bg-surface p-6 rounded-lg shadow-sm border border-line">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-ink-secondary">Fehlgeschlagen</p>
              <p class="text-2xl font-bold text-danger tabular-nums">{{ formatCurrency(statistics.failed_amount) }}</p>
              <p class="text-sm text-ink-muted">{{ statistics.failed_count }} Zahlungen</p>
            </div>
            <div class="p-3 bg-danger-soft rounded-full">
              <XCircle class="w-6 h-6 text-danger" />
            </div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-surface p-6 rounded-lg shadow-sm border border-line">
        <div class="flex flex-wrap items-end gap-4 mb-4">
          <div class="flex-1 min-w-64">
            <label for="finances-local-filters-search" class="block text-sm font-medium text-ink-secondary mb-1">Suchen</label>
            <div class="relative">
              <Search class="absolute left-3 top-3 h-4 w-4 text-ink-muted" />
              <input id="finances-local-filters-search"
                v-model="localFilters.search"
                type="text"
                placeholder="Nach Mitglied, Beschreibung oder Transaktions-ID suchen..."
                class="field w-full pl-10 pr-4 py-2"
                @input="debouncedFilter"
              />
            </div>
          </div>

          <div class="w-48">
            <label for="finance-filter-status" class="block text-sm font-medium text-ink-secondary mb-1">Status</label>
            <select
              id="finance-filter-status"
              v-model="localFilters.status"
              class="field w-full px-3 py-2"
              @change="applyFilters"
            >
              <option value="">Alle Status</option>
              <option v-for="(label, value) in statusOptions" :key="value" :value="value">
                {{ label }}
              </option>
            </select>
          </div>

          <div class="w-48">
            <label for="finance-filter-payment-method" class="block text-sm font-medium text-ink-secondary mb-1">Zahlungsart</label>
            <select
              id="finance-filter-payment-method"
              v-model="localFilters.payment_method"
              class="field w-full px-3 py-2"
              @change="applyFilters"
            >
              <option value="">Alle Zahlungsarten</option>
              <option v-for="(label, value) in paymentMethodOptions" :key="value" :value="value">
                {{ label }}
              </option>
            </select>
          </div>

          <label class="flex h-[42px] cursor-pointer items-center gap-2 rounded-lg border border-line-strong bg-surface px-4 text-sm font-medium text-ink-secondary hover:bg-surface-muted">
            <input
              v-model="localFilters.due"
              type="checkbox"
              class="h-4 w-4 rounded border-line-strong text-brand"
              @change="applyFilters"
            />
            Nur fällige
          </label>

          <button
            @click="showAdvancedFilters = !showAdvancedFilters"
            class="flex h-[42px] items-center px-4 text-sm font-medium text-ink-secondary bg-surface border border-line-strong rounded-lg hover:bg-surface-muted"
          >
            <Filter class="w-4 h-4 mr-2" />
            Erweiterte Filter
            <ChevronDown :class="{ 'rotate-180': showAdvancedFilters }" class="w-4 h-4 ml-2 transform transition-transform" />
          </button>
        </div>

        <!-- Advanced Filters -->
        <div v-if="showAdvancedFilters" class="border-t border-line pt-4">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
              <label for="finances-local-filters-date-from" class="block text-sm font-medium text-ink-secondary mb-1">Datum von</label>
              <input id="finances-local-filters-date-from"
                v-model="localFilters.date_from"
                type="date"
                class="field w-full px-3 py-2"
                @change="applyFilters"
              />
            </div>

            <div>
              <label for="finances-local-filters-date-to" class="block text-sm font-medium text-ink-secondary mb-1">Datum bis</label>
              <input id="finances-local-filters-date-to"
                v-model="localFilters.date_to"
                type="date"
                class="field w-full px-3 py-2"
                @change="applyFilters"
              />
            </div>

            <div>
              <label for="finances-local-filters-amount-from" class="block text-sm font-medium text-ink-secondary mb-1">Betrag von</label>
              <input id="finances-local-filters-amount-from"
                v-model="localFilters.amount_from"
                type="number"
                step="0.01"
                placeholder="0.00"
                class="field w-full px-3 py-2"
                @change="applyFilters"
              />
            </div>

            <div>
              <label for="finances-local-filters-amount-to" class="block text-sm font-medium text-ink-secondary mb-1">Betrag bis</label>
              <input id="finances-local-filters-amount-to"
                v-model="localFilters.amount_to"
                type="number"
                step="0.01"
                placeholder="0.00"
                class="field w-full px-3 py-2"
                @change="applyFilters"
              />
            </div>
          </div>

          <div class="mt-4 flex gap-2">
            <button
              @click="clearFilters"
              class="px-4 py-2 text-sm font-medium text-ink-secondary bg-surface border border-line-strong rounded-lg hover:bg-surface-muted"
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
      >
        <template #toolbar>
          <label class="flex items-center gap-1.5 text-sm text-ink-muted">
            Pro Seite
            <select
              :value="perPage"
              class="field py-2 pl-3 pr-8"
              @change="changePerPage(Number($event.target.value))"
            >
              <option v-for="option in perPageOptions" :key="option" :value="option">{{ option }}</option>
            </select>
          </label>
        </template>
      </PaymentsTable>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppShell from '@/Layouts/AppShell.vue'
import PaymentsTable from '@/Components/PaymentsTable.vue'
import {
  DollarSign,
  CheckCircle,
  Clock,
  XCircle,
  Search,
  Filter,
  ChevronDown
} from '@lucide/vue'
import { debounce } from 'lodash'
import { formatCurrency } from '@/utils/formatters'

defineOptions({ layout: AppShell })

// Props
const props = defineProps({
  payments: Object,
  filters: Object,
  statusOptions: Object,
  paymentMethodOptions: Object,
  statistics: Object,
  perPage: Number,
  perPageOptions: Array
})

const DEFAULT_PER_PAGE = 25

// Reactive data
const selectedPayments = ref([])
const perPage = ref(props.perPage)
const perPageChosen = ref(false)
const showAdvancedFilters = ref(false)
const localFilters = ref({
  search: props.filters.search || '',
  status: props.filters.status || '',
  payment_method: props.filters.payment_method || '',
  date_from: props.filters.date_from || '',
  date_to: props.filters.date_to || '',
  amount_from: props.filters.amount_from || '',
  amount_to: props.filters.amount_to || '',
  // Pending payments whose execution or due date has been reached
  due: props.filters.due === true
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
  // Renders the due date with the execution date underneath; sorting uses the
  // execution date and falls back to the due date.
  { key: 'due_date', label: 'Fälligkeitsdatum', sortable: true, sortKey: 'scheduled_date', nowrap: true }
])

// Methods

// The server remembers the last chosen size in a cookie, so once the user
// picks one it is always sent — even the default, to overwrite the cookie.
const perPageParams = () =>
  perPageChosen.value || perPage.value !== DEFAULT_PER_PAGE ? { per_page: perPage.value } : {}

// The due flag is only sent when set; "false" would not pass validation.
const filterParams = () => ({ ...localFilters.value, due: localFilters.value.due ? 1 : undefined })

const applyFilters = () => {
  router.get(route('finances.index'), { ...filterParams(), ...perPageParams() }, {
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
    amount_to: '',
    due: false
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
    ...filterParams(),
    sort_by: column,
    sort_order: newOrder,
    ...perPageParams()
  }, {
    preserveState: true,
    preserveScroll: true
  })
}

// Keeps filters and sorting; a new page size starts again on page 1.
const changePerPage = value => {
  perPage.value = value
  perPageChosen.value = true
  selectedPayments.value = []

  router.get(route('finances.index'), {
    ...filterParams(),
    sort_by: props.filters.sort_by,
    sort_order: props.filters.sort_order,
    ...perPageParams()
  }, {
    preserveState: true,
    preserveScroll: true
  })
}
</script>
