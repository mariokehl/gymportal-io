<template>
  <AppLayout title="Mitglieder">
    <template #header>
      Mitglieder
    </template>

    <div class="space-y-5">
      <FilterPanel
        v-model="definition"
        v-model:active-saved-id="activeSavedId"
        :fields="filterFields"
        :saved-filters="savedFilters"
        context="members"
        subject="Mitglieder"
      />

      <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-3.5">
          <div class="flex items-baseline gap-2">
            <template v-if="canViewStatistics">
              <span class="text-2xl font-bold text-gray-900">{{ members.total }}</span>
              <span v-if="activeCount > 0" class="text-sm text-gray-500">von {{ memberCount }} Mitgliedern entsprechen dem Filter</span>
              <span v-else class="text-sm text-gray-500">Mitglieder</span>
            </template>
            <span v-else class="text-sm text-gray-500">{{ activeCount > 0 ? 'Gefilterte Mitglieder' : 'Alle Mitglieder' }}</span>
            <Loader2 v-if="isLoading" class="h-4 w-4 animate-spin self-center text-gray-400" />
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <label class="flex items-center gap-1.5 text-[13px] text-gray-500">
              Pro Seite
              <select
                :value="perPage"
                class="h-[34px] rounded-md border border-gray-300 bg-white py-0 pl-2 pr-7 text-[13px] text-gray-900"
                @change="changePerPage(Number($event.target.value))"
              >
                <option v-for="option in perPageOptions" :key="option" :value="option">{{ option }}</option>
              </select>
            </label>
            <button
              v-if="canExport"
              type="button"
              :disabled="members.total === 0 || isExporting"
              class="inline-flex h-9 items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
              @click="exportMembers(false)"
            >
              <Download class="h-4 w-4" />
              CSV exportieren<template v-if="canViewStatistics"> ({{ members.total }})</template>
            </button>
            <Link
              :href="route('members.create')"
              class="inline-flex h-9 items-center whitespace-nowrap rounded-lg bg-indigo-600 px-4 text-sm font-medium text-white transition-colors hover:bg-indigo-700"
            >
              <Plus class="mr-2 h-4 w-4" />
              Neues Mitglied
            </Link>
          </div>
        </div>

        <div v-if="selectedCount > 0" class="flex flex-wrap items-center gap-3 border-b border-indigo-200 bg-indigo-50 px-5 py-2.5">
          <span class="text-sm font-semibold text-indigo-800">{{ selectionLabel }}</span>
          <button
            v-if="offerSelectAll"
            type="button"
            class="px-1 py-0.5 text-sm font-medium text-indigo-600 underline underline-offset-[3px]"
            @click="selectAllMatching"
          >
            Alle {{ members.total }} gefilterten Mitglieder auswählen
          </button>
          <button type="button" class="px-1 py-0.5 text-sm text-indigo-700" @click="clearSelection">Auswahl aufheben</button>
          <div class="ml-auto flex flex-wrap items-center gap-2">
            <button
              type="button"
              :disabled="isExporting"
              class="inline-flex h-8 items-center gap-1.5 rounded-md border border-indigo-200 bg-white px-3 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
              @click="exportMembers(true)"
            >
              <Download class="h-[15px] w-[15px]" />
              Auswahl exportieren
            </button>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-[1180px] w-full">
            <thead class="border-b border-gray-200 bg-gray-50">
              <tr>
                <th v-if="canExport" class="relative w-[52px] pl-4">
                  <div ref="selectMenuWrapper" class="flex items-center">
                    <button type="button" title="Aktuelle Seite auswählen" class="flex" @click="togglePage">
                      <SelectionBox :state="pageSelectionState" />
                    </button>
                    <button type="button" title="Auswahloptionen" class="flex rounded p-0.5 text-gray-500 hover:bg-gray-200" @click="selectMenuOpen = !selectMenuOpen">
                      <ChevronDown class="h-3.5 w-3.5" />
                    </button>
                    <div v-if="selectMenuOpen" class="absolute left-2 top-full z-40 mt-0.5 w-[260px] rounded-lg border border-gray-200 bg-white py-1 text-left font-normal normal-case tracking-normal shadow-lg">
                      <button type="button" class="flex w-full justify-between px-3 py-2 text-sm text-gray-900 hover:bg-indigo-50" @click="selectPage">
                        <span>Aktuelle Seite auswählen</span><span class="text-xs text-gray-400">{{ members.data.length }}</span>
                      </button>
                      <button type="button" class="flex w-full justify-between px-3 py-2 text-sm text-gray-900 hover:bg-indigo-50" @click="selectAllMatching">
                        <span>Alle gefilterten Mitglieder</span><span v-if="canViewStatistics" class="text-xs text-gray-400">{{ members.total }}</span>
                      </button>
                      <div class="my-1 h-px bg-gray-100" />
                      <button type="button" class="flex w-full px-3 py-2 text-sm text-gray-700 hover:bg-gray-100" @click="clearSelection">Auswahl aufheben</button>
                    </div>
                  </div>
                </th>
                <th
                  v-for="column in columns"
                  :key="column.label"
                  :class="[
                    'whitespace-nowrap px-3 py-[11px] text-[11px] font-medium uppercase tracking-wider text-gray-500',
                    column.align === 'right' ? 'text-right' : 'text-left',
                    !canExport && column.label === 'Mitglied' && 'pl-5',
                  ]"
                  :title="column.title"
                  :aria-sort="column.sort && sortBy === column.sort ? (sortDirection === 'asc' ? 'ascending' : 'descending') : undefined"
                >
                  <button
                    v-if="column.sort"
                    type="button"
                    :class="['inline-flex items-center gap-1 uppercase hover:text-gray-900', sortBy === column.sort && 'text-gray-900']"
                    @click="changeSort(column)"
                  >
                    {{ column.label }}
                    <component :is="sortIcon(column.sort)" class="h-3.5 w-3.5" />
                  </button>
                  <template v-else>{{ column.label }}</template>
                </th>
                <th class="w-12 pr-5"><span class="sr-only">Aktionen</span></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr
                v-for="member in members.data"
                :key="member.id"
                :class="isSelected(member.id) ? 'bg-indigo-50/60' : 'hover:bg-gray-50'"
              >
                <td v-if="canExport" class="pl-4">
                  <button type="button" title="Auswählen" class="-ml-1 flex p-1" @click="toggleRow(member.id)">
                    <SelectionBox :state="isSelected(member.id) ? 'all' : 'none'" />
                  </button>
                </td>
                <td :class="['py-2.5 pr-3', canExport ? 'pl-1' : 'pl-5']">
                  <MemberIdentity :member="member" size="md" :show-email="false" max-width="220px">
                    <template #meta>
                      <p class="flex items-center gap-1.5 text-xs text-gray-500">
                        <span class="truncate">{{ member.member_number }}<template v-if="member.age !== null"> · {{ member.age }} J.</template></span>
                        <AggregatorBadge v-if="member.aggregator" :aggregator="member.aggregator.key" :status="member.aggregator.status" />
                      </p>
                    </template>
                  </MemberIdentity>
                </td>
                <td class="px-3 py-2.5">
                  <MemberStatusBadge :status="member.status" :outstanding-balance="member.outstanding_balance" />
                </td>
                <td class="max-w-[220px] px-3 py-2.5 text-[13px] text-gray-700">{{ member.plans.length ? member.plans.join(', ') : '–' }}</td>
                <td class="px-3 py-2.5 text-[13px] text-gray-700">{{ member.payment_method ?? '–' }}</td>
                <td class="whitespace-nowrap px-3 py-2.5 text-right text-[13px]">
                  <span v-if="member.outstanding_balance" class="font-medium text-amber-600">{{ formatCurrency(member.outstanding_balance) }}</span>
                  <span v-else class="text-gray-400">–</span>
                  <span v-if="member.failed_payment_count > 0" class="block text-[11px] text-red-700">{{ member.failed_payment_count }}× fehlgeschlagen</span>
                </td>
                <td class="px-3 py-2.5 text-right text-[13px] tabular-nums text-gray-700">{{ member.check_in_count }}</td>
                <td class="whitespace-nowrap px-3 py-2.5 text-[13px] text-gray-500">{{ member.last_check_in_at ? formatDate(member.last_check_in_at) : 'nie' }}</td>
                <td class="whitespace-nowrap px-3 py-2.5 text-[13px] text-gray-700">{{ [member.postal_code, member.city].filter(Boolean).join(' ') || '–' }}</td>
                <td class="whitespace-nowrap px-3 py-2.5 font-mono text-xs text-gray-700">
                  {{ member.nfc_tag ?? '–' }}<span v-if="member.nfc_tag_count > 1" class="ml-1 font-sans text-gray-400">+{{ member.nfc_tag_count - 1 }}</span>
                </td>
                <td class="pr-5 text-right">
                  <button
                    v-if="member.can_delete"
                    type="button"
                    class="rounded p-1 text-red-600 transition-colors hover:text-red-900"
                    title="Löschen"
                    @click="confirmDelete(member)"
                  >
                    <Trash2 class="h-4 w-4" />
                  </button>
                  <!-- Teleported: the table scrolls horizontally, which would
                       clip the panel and widen the scroll area. -->
                  <Tooltip v-else position="left" teleport>
                    <template #default>
                      <button disabled class="cursor-not-allowed rounded p-1 text-gray-300 opacity-50">
                        <Trash2 class="h-4 w-4" />
                      </button>
                    </template>
                    <template #content>
                      <div class="mb-1 flex items-center font-semibold">
                        <AlertCircle class="mr-1 h-3 w-3" />
                        Löschen nicht möglich
                      </div>
                      <div class="text-gray-300">{{ member.delete_block_reason }}</div>
                      <div v-if="member.status !== 'inactive'" class="mt-2 border-t border-gray-700 pt-2 text-gray-400">
                        Tipp: Mitglied muss erst inaktiviert werden
                      </div>
                    </template>
                  </Tooltip>
                </td>
              </tr>
            </tbody>
          </table>

          <div v-if="members.data.length === 0" class="px-6 py-12 text-center">
            <Users class="mx-auto h-12 w-12 text-gray-400" />
            <h3 class="mt-2 text-sm font-semibold text-gray-900">Keine Mitglieder gefunden</h3>
            <p class="mt-1 text-sm text-gray-500">
              {{ activeCount > 0
                ? 'Keine Mitglieder entsprechen den aktuellen Filtern. Lockern Sie Bedingungen oder verknüpfen Sie sie mit ODER.'
                : 'Beginnen Sie mit dem Hinzufügen Ihres ersten Mitglieds.' }}
            </p>
          </div>
        </div>

        <Pagination
          :data="members"
          item-label="Mitglieder"
          :hide-total="!canViewStatistics"
          :is-loading="isLoading"
          @navigate="handlePaginationEvent"
        />
      </section>
    </div>

    <!-- Erweitertes Lösch-Modal -->
    <div v-if="showDeleteModal" class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50">
      <div class="relative top-20 mx-auto p-5 border border-gray-50 w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
          <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
            <AlertTriangle class="h-6 w-6 text-red-600" />
          </div>
          <h3 class="text-lg font-medium text-gray-900 mt-2">Mitglied löschen</h3>

          <div class="mt-4 px-7 py-3">
            <!-- Mitgliedsinfo -->
            <div class="bg-gray-50 rounded-lg p-3 mb-4">
              <div class="font-medium text-gray-900">
                {{ memberToDelete?.first_name }} {{ memberToDelete?.last_name }}
              </div>
              <div class="text-sm text-gray-500">
                {{ memberToDelete?.member_number }} • {{ memberToDelete?.email }}
              </div>
            </div>

            <!-- Prüfungs-Checkliste -->
            <div class="text-left space-y-2 mb-4">
              <div class="flex items-center text-sm">
                <CheckCircle class="w-4 h-4 text-green-500 mr-2" />
                <span class="text-gray-600">Status: Inaktiv</span>
              </div>
              <div class="flex items-center text-sm">
                <CheckCircle class="w-4 h-4 text-green-500 mr-2" />
                <span class="text-gray-600">Keine aktiven Mitgliedschaften</span>
              </div>
              <div class="flex items-center text-sm">
                <CheckCircle class="w-4 h-4 text-green-500 mr-2" />
                <span class="text-gray-600">Keine offenen Zahlungen</span>
              </div>
            </div>

            <!-- Warnung -->
            <div class="bg-red-50 border border-red-200 rounded-md p-3">
              <p class="text-sm text-red-800">
                <strong>Achtung:</strong> Diese Aktion kann nicht rückgängig gemacht werden.
                Alle Daten des Mitglieds werden unwiderruflich gelöscht.
              </p>
            </div>
          </div>

          <div class="items-center px-4 py-3 space-y-2">
            <button
              @click="deleteMember"
              :disabled="isDeleting"
              class="px-4 py-2 bg-red-600 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              <span v-if="!isDeleting">Endgültig löschen</span>
              <span v-else class="flex items-center justify-center">
                <Loader2 class="w-4 h-4 mr-2 animate-spin" />
                Wird gelöscht...
              </span>
            </button>
            <button
              @click="closeDeleteModal"
              :disabled="isDeleting"
              class="px-4 py-2 bg-white text-gray-700 text-base font-medium rounded-md w-full shadow-sm border border-gray-300 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 disabled:opacity-50 transition-colors"
            >
              Abbrechen
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import axios from 'axios'
import { debounce } from 'lodash'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import MemberStatusBadge from '@/Components/MemberStatusBadge.vue'
import MemberIdentity from '@/Components/Members/MemberIdentity.vue'
import Tooltip from '@/Components/Tooltip.vue'
import AggregatorBadge from '@/Components/Aggregators/AggregatorBadge.vue'
import FilterPanel from '@/Components/Filters/FilterPanel.vue'
import SelectionBox from '@/Components/Filters/SelectionBox.vue'
import {
  Users, Plus, Trash2, AlertTriangle, AlertCircle, CheckCircle, Loader2, ArrowUpDown, ArrowUp, ArrowDown, ChevronDown, Download
} from 'lucide-vue-next'
import { formatCurrency, formatDate } from '@/utils/formatters'
import { activeConditionCount, effectiveKey, normalizeDefinition, serializeDefinition } from '@/utils/filterBuilder'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  members: Object,
  filter: Object,
  sorting: Object,
  perPage: Number,
  perPageOptions: Array,
  filterFields: Array,
  savedFilters: Array,
  memberCount: Number,
  canViewStatistics: Boolean,
  canExport: Boolean
})

const { success: toastSuccess, error: toastError } = useToast()

// Sortable columns default to the direction that is useful first.
const columns = [
  { label: 'Mitglied', sort: 'name', firstDirection: 'asc' },
  { label: 'Status' },
  { label: 'Tarife', title: 'Laufende Mitgliedschaften' },
  { label: 'Zahlungsart' },
  { label: 'OPOS', title: 'Offene Posten', sort: 'outstanding_balance', firstDirection: 'desc', align: 'right' },
  { label: 'Check-Ins', sort: 'check_in_count', firstDirection: 'desc', align: 'right' },
  { label: 'Zuletzt', title: 'Letzter Check-In', sort: 'last_check_in', firstDirection: 'desc' },
  { label: 'PLZ / Ort' },
  { label: 'NFC-Tag' }
]

// Mirror the defaults of App\Services\MemberListService.
const DEFAULT_SORT = { sortBy: 'member_number', sortDirection: 'asc' }
const DEFAULT_PER_PAGE = 25

const initialSavedId = Number(new URLSearchParams(window.location.search).get('saved')) || null

const definition = ref(normalizeDefinition(props.filter))
const activeSavedId = ref(props.savedFilters.some(saved => saved.id === initialSavedId) ? initialSavedId : null)
const sortBy = ref(props.sorting.sortBy)
const sortDirection = ref(props.sorting.sortDirection)
const perPage = ref(props.perPage)
const isLoading = ref(false)
const isExporting = ref(false)

const fieldsByKey = computed(() => Object.fromEntries(props.filterFields.map(field => [field.key, field])))
const activeCount = computed(() => activeConditionCount(definition.value, fieldsByKey.value))

// Query string of the list; defaults stay out of the URL.
const listParams = () => {
  const params = {}
  const filter = serializeDefinition(definition.value)

  if (filter) params.filter = filter
  if (activeSavedId.value) params.saved = activeSavedId.value
  if (sortBy.value !== DEFAULT_SORT.sortBy || sortDirection.value !== DEFAULT_SORT.sortDirection) {
    params.sortBy = sortBy.value
    params.sortDirection = sortDirection.value
  }
  if (perPage.value !== DEFAULT_PER_PAGE) params.perPage = perPage.value

  return params
}

const reload = () => {
  router.get(route('members.index'), listParams(), {
    preserveState: true,
    preserveScroll: true,
    replace: true,
    only: ['members', 'filter'],
    onStart: () => { isLoading.value = true },
    onFinish: () => { isLoading.value = false }
  })
}

const debouncedReload = debounce(reload, 350)

// Only changes that alter the result trigger a request; a condition that
// is still being filled in (incomplete) does not.
watch(
  () => [effectiveKey(definition.value, fieldsByKey.value), activeSavedId.value],
  () => {
    clearSelection()
    debouncedReload()
  }
)

const changeSort = column => {
  if (sortBy.value === column.sort) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortBy.value = column.sort
    sortDirection.value = column.firstDirection
  }
  reload()
}

const sortIcon = column => {
  if (sortBy.value !== column) return ArrowUpDown
  return sortDirection.value === 'asc' ? ArrowUp : ArrowDown
}

const changePerPage = value => {
  perPage.value = value
  reload()
}

const handlePaginationEvent = event => {
  isLoading.value = event.type === 'start'
}

// Selection: explicit ids, or every match minus deselected ones after
// "select all" — the latter works across pages without loading all ids.
const selectAll = ref(false)
const selectedIds = ref(new Set())
const excludedIds = ref(new Set())
const selectMenuOpen = ref(false)
const selectMenuWrapper = ref(null)

const isSelected = id => (selectAll.value ? !excludedIds.value.has(id) : selectedIds.value.has(id))

const selectedCount = computed(() => (selectAll.value
  ? Math.max(0, props.members.total - excludedIds.value.size)
  : selectedIds.value.size))

const pageSelectedCount = computed(() => props.members.data.filter(member => isSelected(member.id)).length)

const pageSelectionState = computed(() => {
  if (pageSelectedCount.value === 0) return 'none'
  return pageSelectedCount.value === props.members.data.length ? 'all' : 'some'
})

const offerSelectAll = computed(() => !selectAll.value
  && pageSelectedCount.value === props.members.data.length
  && props.members.total > props.members.data.length)

const selectionLabel = computed(() => {
  if (selectAll.value && excludedIds.value.size === 0) {
    return props.canViewStatistics ? `Alle ${props.members.total} gefilterten Mitglieder ausgewählt` : 'Alle gefilterten Mitglieder ausgewählt'
  }
  return selectedCount.value === 1 ? '1 Mitglied ausgewählt' : `${selectedCount.value} Mitglieder ausgewählt`
})

const setSelected = (ids, selected) => {
  const target = new Set(selectAll.value ? excludedIds.value : selectedIds.value)
  const add = selectAll.value ? !selected : selected

  ids.forEach(id => (add ? target.add(id) : target.delete(id)))

  if (selectAll.value) excludedIds.value = target
  else selectedIds.value = target
}

const toggleRow = id => setSelected([id], !isSelected(id))

const togglePage = () => setSelected(props.members.data.map(member => member.id), pageSelectionState.value !== 'all')

const selectPage = () => {
  setSelected(props.members.data.map(member => member.id), true)
  selectMenuOpen.value = false
}

const selectAllMatching = () => {
  selectAll.value = true
  selectedIds.value = new Set()
  excludedIds.value = new Set()
  selectMenuOpen.value = false
}

function clearSelection() {
  selectAll.value = false
  selectedIds.value = new Set()
  excludedIds.value = new Set()
  selectMenuOpen.value = false
}

const closeSelectMenu = event => {
  if (selectMenuOpen.value && !selectMenuWrapper.value?.contains(event.target)) selectMenuOpen.value = false
}

onMounted(() => document.addEventListener('mousedown', closeSelectMenu))
onBeforeUnmount(() => {
  document.removeEventListener('mousedown', closeSelectMenu)
  debouncedReload.cancel()
})

const exportMembers = async selectionOnly => {
  const payload = { filter: serializeDefinition(definition.value) }

  if (selectionOnly) {
    if (selectAll.value) payload.excluded_ids = [...excludedIds.value]
    else payload.member_ids = [...selectedIds.value]
  }

  isExporting.value = true

  try {
    const response = await axios.post(route('members.export'), payload, { responseType: 'blob' })
    const filename = response.headers['content-disposition']?.match(/filename="?([^";]+)"?/)?.[1] ?? 'mitglieder.csv'
    const url = window.URL.createObjectURL(new Blob([response.data], { type: 'text/csv;charset=utf-8' }))
    const link = document.createElement('a')

    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => window.URL.revokeObjectURL(url), 1000)

    toastSuccess('Export erstellt', filename)
  } catch {
    toastError('Export fehlgeschlagen', 'Die Mitgliederliste konnte nicht exportiert werden.')
  } finally {
    isExporting.value = false
  }
}

// Delete
const showDeleteModal = ref(false)
const memberToDelete = ref(null)
const isDeleting = ref(false)

const confirmDelete = (member) => {
  memberToDelete.value = member
  showDeleteModal.value = true
}

const deleteMember = () => {
  if (memberToDelete.value && !isDeleting.value) {
    isDeleting.value = true

    router.delete(route('members.destroy', memberToDelete.value.id), {
      onSuccess: () => {
        showDeleteModal.value = false
        memberToDelete.value = null
        isDeleting.value = false
      },
      onError: () => {
        isDeleting.value = false
      }
    })
  }
}

const closeDeleteModal = () => {
  if (!isDeleting.value) {
    showDeleteModal.value = false
    memberToDelete.value = null
  }
}
</script>
