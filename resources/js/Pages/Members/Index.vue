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

      <!-- scroll-mt clears the sticky app header when paginating scrolls
           the list back up; tabindex lets keyboard focus follow. -->
      <section
        ref="listSection"
        tabindex="-1"
        class="@container scroll-mt-24 overflow-clip rounded-lg border border-line bg-surface shadow-sm focus:outline-none"
        :aria-busy="showSkeleton"
      >
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-3.5">
          <div class="flex items-baseline gap-2">
            <template v-if="canViewStatistics">
              <span class="text-2xl font-bold text-ink tabular-nums">{{ members.total }}</span>
              <span v-if="activeCount > 0" class="text-sm text-ink-muted">von {{ memberCount }} Mitgliedern entsprechen dem Filter</span>
              <span v-else class="text-sm text-ink-muted">Mitglieder</span>
            </template>
            <span v-else class="text-sm text-ink-muted">{{ activeCount > 0 ? 'Gefilterte Mitglieder' : 'Alle Mitglieder' }}</span>
            <Loader2 v-if="isLoading" class="h-4 w-4 animate-spin self-center text-ink-muted" />
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <label class="hidden @2xl:flex items-center gap-1.5 text-compact text-ink-muted">
              Pro Seite
              <select
                :value="perPage"
                class="field h-[34px] py-0 pl-2 pr-7 text-compact pointer-coarse:text-base"
                @change="changePerPage(Number($event.target.value))"
              >
                <option v-for="option in perPageOptions" :key="option" :value="option">{{ option }}</option>
              </select>
            </label>
            <button
              v-if="canExport"
              type="button"
              :disabled="members.total === 0 || isExporting"
              class="inline-flex h-9 pointer-coarse:h-11 items-center gap-2 rounded-lg border border-line-strong bg-surface px-4 text-sm font-medium text-ink-secondary hover:bg-surface-muted disabled:cursor-not-allowed disabled:opacity-50"
              @click="exportMembers(false)"
            >
              <Download class="h-4 w-4" />
              <span>CSV<span class="hidden @2xl:inline"> exportieren</span><template v-if="canViewStatistics"> ({{ members.total }})</template></span>
            </button>
            <Link
              :href="route('members.create')"
              class="inline-flex h-9 pointer-coarse:h-11 items-center whitespace-nowrap rounded-lg bg-brand px-4 text-sm font-medium text-white transition-colors hover:bg-brand-strong"
            >
              <Plus class="mr-2 h-4 w-4" />
              Neues Mitglied
            </Link>
          </div>
        </div>

        <div v-if="selectedCount > 0" class="flex flex-wrap items-center gap-3 border-b border-brand-line bg-brand-subtle px-5 py-2.5">
          <span class="text-sm font-semibold text-brand-ink-strong">{{ selectionLabel }}</span>
          <button
            v-if="offerSelectAll"
            type="button"
            class="px-1 py-0.5 text-sm font-medium text-brand-ink underline underline-offset-[3px]"
            @click="selectAllMatching"
          >
            Alle {{ members.total }} gefilterten Mitglieder auswählen
          </button>
          <button type="button" class="px-1 py-0.5 text-sm text-brand-ink-strong" @click="clearSelection">Auswahl aufheben</button>
          <div class="ml-auto flex flex-wrap items-center gap-2">
            <button
              type="button"
              :disabled="isExporting"
              class="inline-flex h-8 items-center gap-1.5 rounded-md border border-brand-line bg-surface px-3 text-sm font-medium text-ink-secondary hover:bg-surface-muted disabled:opacity-50"
              @click="exportMembers(true)"
            >
              <Download class="h-[15px] w-[15px]" />
              Auswahl exportieren
            </button>
          </div>
        </div>

        <!-- relative: absolutely positioned descendants (e.g. the sr-only
             "Aktionen" heading) must be clipped by this scroll container,
             otherwise they are placed against the page and widen the window. -->
        <!-- Dimmed right away while loading, so a click has visible effect
             even when the response is too fast for the skeleton. -->
        <HorizontalScroller
          class="relative transition-opacity duration-100"
          :class="isLoading && !showSkeleton ? 'pointer-events-none opacity-50' : ''"
        >
          <!-- Narrow cards (phones): one row per member with what identifies
               them at a glance; the full table needs ~1180px. -->
          <ul ref="cardList" class="@2xl:hidden divide-y divide-line-subtle">
            <template v-if="showSkeleton">
              <li
                v-for="row in skeletonRowCount"
                :key="`skeleton-${row}`"
                class="flex items-center gap-3 px-4 py-2"
                :style="cardRowHeight ? { height: `${cardRowHeight}px` } : null"
                aria-hidden="true"
              >
                <div class="size-9 shrink-0 rounded-full bg-fill motion-safe:animate-pulse" />
                <div class="min-w-0 flex-1 space-y-2">
                  <div class="h-3.5 w-2/5 rounded bg-fill motion-safe:animate-pulse" />
                  <div class="h-3 w-3/5 rounded bg-fill motion-safe:animate-pulse" />
                </div>
                <div class="h-5 w-16 shrink-0 rounded-full bg-fill motion-safe:animate-pulse" />
              </li>
            </template>
            <li
              v-for="member in (showSkeleton ? [] : members.data)"
              :key="member.id"
              class="flex items-center gap-2 py-2.5 pr-4"
              :class="[isSelected(member.id) ? 'bg-brand-subtle/60' : '', canExport ? 'pl-2' : 'pl-4']"
            >
              <button
                v-if="canExport"
                type="button"
                role="checkbox"
                :aria-label="`${member.first_name} ${member.last_name} auswählen`"
                :aria-checked="isSelected(member.id)"
                class="flex size-11 shrink-0 items-center justify-center"
                @click="toggleRow(member.id)"
              >
                <SelectionBox :state="isSelected(member.id) ? 'all' : 'none'" />
              </button>
              <div class="min-w-0 flex-1">
                <MemberIdentity :member="member" size="md" :show-email="false" max-width="100%">
                  <template #meta>
                    <p class="flex items-center gap-1.5 text-xs text-ink-muted">
                      <span class="truncate">
                        {{ member.member_number }}<template v-if="member.plans.length"> · {{ member.plans[0] }}</template>
                      </span>
                      <AggregatorBadge v-if="member.aggregator" :aggregator="member.aggregator.key" :status="member.aggregator.status" />
                    </p>
                  </template>
                </MemberIdentity>
              </div>
              <MemberStatusBadge class="shrink-0" :status="member.status" :outstanding-balance="member.outstanding_balance" />
            </li>
          </ul>

          <table class="hidden @2xl:table min-w-[1180px] w-full">
            <thead class="border-b border-line bg-surface-muted">
              <tr>
                <th v-if="canExport" class="relative w-[52px] pl-4">
                  <div ref="selectMenuWrapper" class="flex items-center pointer-coarse:gap-6">
                    <button
                      type="button"
                      role="checkbox"
                      title="Aktuelle Seite auswählen"
                      aria-label="Alle Mitglieder dieser Seite auswählen"
                      :aria-checked="pageSelectionState === 'some' ? 'mixed' : pageSelectionState === 'all'"
                      class="touch-target relative flex"
                      @click="togglePage"
                    >
                      <SelectionBox :state="pageSelectionState" />
                    </button>
                    <button
                      type="button"
                      title="Auswahloptionen"
                      aria-label="Auswahloptionen"
                      aria-haspopup="menu"
                      :aria-expanded="selectMenuOpen"
                      class="touch-target relative flex rounded p-0.5 text-ink-muted hover:bg-fill-strong"
                      @click="selectMenuOpen = !selectMenuOpen"
                    >
                      <ChevronDown class="h-3.5 w-3.5" />
                    </button>
                    <div v-if="selectMenuOpen" class="absolute left-2 top-full z-40 mt-0.5 w-[260px] rounded-lg border border-line bg-surface py-1 text-left font-normal normal-case tracking-normal shadow-lg">
                      <button type="button" class="flex w-full justify-between px-3 py-2 text-sm text-ink hover:bg-brand-subtle" @click="selectPage">
                        <span>Aktuelle Seite auswählen</span><span class="text-xs text-ink-muted">{{ members.data.length }}</span>
                      </button>
                      <button type="button" class="flex w-full justify-between px-3 py-2 text-sm text-ink hover:bg-brand-subtle" @click="selectAllMatching">
                        <span>Alle gefilterten Mitglieder</span><span v-if="canViewStatistics" class="text-xs text-ink-muted">{{ members.total }}</span>
                      </button>
                      <div class="my-1 h-px bg-fill" />
                      <button type="button" class="flex w-full px-3 py-2 text-sm text-ink-secondary hover:bg-fill" @click="clearSelection">Auswahl aufheben</button>
                    </div>
                  </div>
                </th>
                <th
                  v-for="column in columns"
                  :key="column.label"
                  :class="[
                    'whitespace-nowrap px-3 py-[11px] text-2xs font-medium uppercase tracking-wider text-ink-muted',
                    column.align === 'right' ? 'text-right' : 'text-left',
                    !canExport && column.label === 'Mitglied' && 'pl-5',
                  ]"
                  :title="column.title"
                  :aria-sort="column.sort && sortBy === column.sort ? (sortDirection === 'asc' ? 'ascending' : 'descending') : undefined"
                >
                  <button
                    v-if="column.sort"
                    type="button"
                    :class="['touch-target relative inline-flex items-center gap-1 uppercase hover:text-ink', sortBy === column.sort && 'text-ink']"
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
            <tbody ref="tableBody" class="divide-y divide-line-subtle">
              <TableSkeletonRows
                v-if="showSkeleton"
                :rows="skeletonRowCount"
                :columns="columns.length + (canExport ? 2 : 1)"
                :row-height="tableRowHeight"
                cell-class="px-3 py-3 first:pl-5"
              />
              <tr
                v-for="member in (showSkeleton ? [] : members.data)"
                :key="member.id"
                :class="isSelected(member.id) ? 'bg-brand-subtle/60' : 'hover:bg-surface-muted'"
              >
                <td v-if="canExport" class="pl-4">
                  <button
                    type="button"
                    role="checkbox"
                    title="Auswählen"
                    :aria-label="`${member.first_name} ${member.last_name} auswählen`"
                    :aria-checked="isSelected(member.id)"
                    class="touch-target relative -ml-1 flex p-1"
                    @click="toggleRow(member.id)"
                  >
                    <SelectionBox :state="isSelected(member.id) ? 'all' : 'none'" />
                  </button>
                </td>
                <td :class="['py-2.5 pr-3', canExport ? 'pl-1' : 'pl-5']">
                  <MemberIdentity :member="member" size="md" :show-email="false" max-width="220px">
                    <template #meta>
                      <p class="flex items-center gap-1.5 text-xs text-ink-muted">
                        <span class="truncate">{{ member.member_number }}<template v-if="member.age !== null"> · {{ member.age }} J.</template></span>
                        <AggregatorBadge v-if="member.aggregator" :aggregator="member.aggregator.key" :status="member.aggregator.status" />
                      </p>
                    </template>
                  </MemberIdentity>
                </td>
                <td class="whitespace-nowrap px-3 py-2.5">
                  <MemberStatusBadge :status="member.status" :outstanding-balance="member.outstanding_balance" />
                </td>
                <!-- Data cells stay on one line so every row has the same height;
                     what does not fit is truncated and shown in full as a tooltip. -->
                <td class="whitespace-nowrap px-3 py-2.5 text-compact text-ink-secondary">
                  <template v-if="member.plans.length">
                    <Tooltip position="top" teleport only-when-truncated class="align-middle">
                      <span class="block max-w-[160px] truncate">{{ member.plans[0] }}</span>
                      <template #content>{{ member.plans[0] }}</template>
                    </Tooltip>
                    <Tooltip v-if="member.plans.length > 1" position="top" teleport class="ml-1 align-middle">
                      <span class="cursor-default rounded-full bg-fill px-1.5 py-0.5 text-2xs font-medium text-ink-secondary">
                        +{{ member.plans.length - 1 }}
                      </span>
                      <template #content>
                        <div class="mb-1 font-semibold">Laufende Tarife</div>
                        <ul class="space-y-0.5">
                          <li v-for="plan in member.plans" :key="plan">{{ plan }}</li>
                        </ul>
                      </template>
                    </Tooltip>
                  </template>
                  <span v-else class="text-ink-muted">–</span>
                </td>
                <td class="whitespace-nowrap px-3 py-2.5 text-compact text-ink-secondary">
                  <Tooltip v-if="member.payment_method" position="top" teleport only-when-truncated>
                    <span class="flex items-center">
                      <span
                        v-if="member.payment_method.provider"
                        :class="['mr-1.5 rounded px-1.5 py-0.5 text-2xs font-medium', providerBadgeClass(member.payment_method.provider)]"
                      >{{ member.payment_method.provider }}</span>
                      <span class="max-w-[150px] truncate">{{ member.payment_method.label }}</span>
                    </span>
                    <template #content>
                      <div class="font-semibold">{{ member.payment_method.label }}</div>
                      <div v-if="member.payment_method.provider" class="text-ink-disabled">über {{ member.payment_method.provider }}</div>
                    </template>
                  </Tooltip>
                  <span v-else class="text-ink-muted">–</span>
                </td>
                <td class="whitespace-nowrap px-3 py-2.5 text-right text-compact">
                  <span v-if="member.outstanding_balance" class="font-medium text-warning">{{ formatCurrency(member.outstanding_balance) }}</span>
                  <span v-else class="text-ink-muted">–</span>
                  <span v-if="member.failed_payment_count > 0" class="block text-2xs text-danger">{{ member.failed_payment_count }}× fehlgeschlagen</span>
                </td>
                <td class="whitespace-nowrap px-3 py-2.5 text-right text-compact tabular-nums text-ink-secondary">{{ member.check_in_count }}</td>
                <td class="whitespace-nowrap px-3 py-2.5 text-compact text-ink-muted">{{ member.last_check_in_at ? formatDate(member.last_check_in_at) : 'nie' }}</td>
                <td class="whitespace-nowrap px-3 py-2.5 text-compact text-ink-secondary">
                  <Tooltip v-if="member.postal_code || member.city" position="top" teleport only-when-truncated>
                    <span class="block max-w-[170px] truncate">{{ [member.postal_code, member.city].filter(Boolean).join(' ') }}</span>
                    <template #content>{{ [member.postal_code, member.city].filter(Boolean).join(' ') }}</template>
                  </Tooltip>
                  <span v-else class="text-ink-muted">–</span>
                </td>
                <td class="whitespace-nowrap px-3 py-2.5 font-mono text-xs text-ink-secondary">
                  {{ member.nfc_tag ?? '–' }}<span v-if="member.nfc_tag_count > 1" class="ml-1 font-sans text-ink-muted">+{{ member.nfc_tag_count - 1 }}</span>
                </td>
                <td class="pr-5 text-right">
                  <button
                    v-if="member.can_delete"
                    type="button"
                    class="rounded p-1 text-danger transition-colors hover:text-danger-strong"
                    title="Löschen"
                    :aria-label="`${member.first_name} ${member.last_name} löschen`"
                    @click="confirmDelete(member)"
                  >
                    <Trash2 class="h-4 w-4" />
                  </button>
                  <!-- Teleported: the table scrolls horizontally, which would
                       clip the panel and widen the scroll area. -->
                  <Tooltip v-else position="left" teleport>
                    <template #default>
                      <!-- aria-disabled instead of disabled keeps the button
                           focusable, so keyboard users reach the tooltip. -->
                      <button
                        type="button"
                        aria-disabled="true"
                        :aria-label="`${member.first_name} ${member.last_name} kann nicht gelöscht werden: ${member.delete_block_reason}`"
                        class="cursor-not-allowed rounded p-1 text-ink-disabled opacity-50"
                        @click.prevent
                      >
                        <Trash2 class="h-4 w-4" />
                      </button>
                    </template>
                    <template #content>
                      <div class="mb-1 flex items-center font-semibold">
                        <AlertCircle class="mr-1 h-3 w-3" />
                        Löschen nicht möglich
                      </div>
                      <div class="text-ink-disabled">{{ member.delete_block_reason }}</div>
                      <div v-if="member.status !== 'inactive'" class="mt-2 border-t border-gray-700 pt-2 text-ink-muted">
                        Tipp: Mitglied muss erst inaktiviert werden
                      </div>
                    </template>
                  </Tooltip>
                </td>
              </tr>
            </tbody>
          </table>

          <div v-if="members.data.length === 0 && !showSkeleton" class="px-6 py-12 text-center">
            <Users class="mx-auto h-12 w-12 text-ink-muted" />
            <h3 class="mt-2 text-sm font-semibold text-ink">Keine Mitglieder gefunden</h3>
            <p class="mt-1 text-sm text-ink-muted">
              {{ activeCount > 0
                ? 'Keine Mitglieder entsprechen den aktuellen Filtern. Lockern Sie Bedingungen oder verknüpfen Sie sie mit ODER.'
                : 'Beginnen Sie mit dem Hinzufügen Ihres ersten Mitglieds.' }}
            </p>
          </div>
        </HorizontalScroller>

        <Pagination
          :data="members"
          item-label="Mitglieder"
          :hide-total="!canViewStatistics"
          :is-loading="isLoading"
          :scroll-target="listSection"
          @navigate="handlePaginationEvent"
        />
      </section>
    </div>

    <!-- Erweitertes Lösch-Modal -->
    <div v-if="showDeleteModal" role="dialog" aria-modal="true" aria-label="Mitglied löschen" class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50">
      <div class="relative top-20 mx-auto p-5 border border-line-subtle w-96 shadow-lg rounded-md bg-surface">
        <div class="mt-3 text-center">
          <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-danger-soft">
            <AlertTriangle class="h-6 w-6 text-danger" />
          </div>
          <h3 class="text-lg font-semibold text-ink mt-2">Mitglied löschen</h3>

          <div class="mt-4 px-7 py-3">
            <!-- Mitgliedsinfo -->
            <div class="bg-surface-muted rounded-lg p-3 mb-4">
              <div class="font-medium text-ink">
                {{ memberToDelete?.first_name }} {{ memberToDelete?.last_name }}
              </div>
              <div class="text-sm text-ink-muted">
                {{ memberToDelete?.member_number }} • {{ memberToDelete?.email }}
              </div>
            </div>

            <!-- Prüfungs-Checkliste -->
            <div class="text-left space-y-2 mb-4">
              <div class="flex items-center text-sm">
                <CheckCircle class="w-4 h-4 text-success mr-2" />
                <span class="text-ink-secondary">Status: Inaktiv</span>
              </div>
              <div class="flex items-center text-sm">
                <CheckCircle class="w-4 h-4 text-success mr-2" />
                <span class="text-ink-secondary">Keine aktiven Mitgliedschaften</span>
              </div>
              <div class="flex items-center text-sm">
                <CheckCircle class="w-4 h-4 text-success mr-2" />
                <span class="text-ink-secondary">Keine offenen Zahlungen</span>
              </div>
            </div>

            <!-- Warnung -->
            <div class="bg-danger-subtle border border-danger-line rounded-md p-3">
              <p class="text-sm text-danger-strong">
                <strong>Achtung:</strong> Diese Aktion kann nicht rückgängig gemacht werden.
                Alle Daten des Mitglieds werden unwiderruflich gelöscht.
              </p>
            </div>
          </div>

          <div class="items-center px-4 py-3 space-y-2">
            <button
              @click="deleteMember"
              :disabled="isDeleting"
              class="px-4 py-2 bg-danger-solid text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-danger-solid-strong disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
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
              class="px-4 py-2 bg-surface text-ink-secondary text-base font-medium rounded-md w-full shadow-sm border border-line-strong hover:bg-surface-muted disabled:opacity-50 transition-colors"
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
import AppShell from '@/Layouts/AppShell.vue'
import Pagination from '@/Components/Pagination.vue'
import TableSkeletonRows from '@/Components/Common/TableSkeletonRows.vue'
import HorizontalScroller from '@/Components/Common/HorizontalScroller.vue'
import MemberStatusBadge from '@/Components/MemberStatusBadge.vue'
import MemberIdentity from '@/Components/Members/MemberIdentity.vue'
import Tooltip from '@/Components/Tooltip.vue'
import AggregatorBadge from '@/Components/Aggregators/AggregatorBadge.vue'
import FilterPanel from '@/Components/Filters/FilterPanel.vue'
import SelectionBox from '@/Components/Filters/SelectionBox.vue'
import {
  Users, Plus, Trash2, AlertTriangle, AlertCircle, CheckCircle, Loader2, ArrowUpDown, ArrowUp, ArrowDown, ChevronDown, Download
} from '@lucide/vue'
import { formatCurrency, formatDate } from '@/utils/formatters'
import { activeConditionCount, effectiveKey, normalizeDefinition, serializeDefinition } from '@/utils/filterBuilder'
import { useToast } from '@/composables/useToast'
import { useDelayedFlag } from '@/composables/useDelayedFlag'
import { averageRowHeight } from '@/utils/skeletonSize'

defineOptions({ layout: AppShell })

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

// Payment providers in their brand colours (Mollie: black logo, white type);
// unknown providers stay neutral.
const PROVIDER_BADGE_CLASSES = { Mollie: 'bg-black text-white' }

const providerBadgeClass = provider => PROVIDER_BADGE_CLASSES[provider] ?? 'bg-fill text-ink-muted'

// Mirror the defaults of App\Services\MemberListService.
const DEFAULT_SORT = { sortBy: 'member_number', sortDirection: 'asc' }
const DEFAULT_PER_PAGE = 25

const initialSavedId = Number(new URLSearchParams(window.location.search).get('saved')) || null

const definition = ref(normalizeDefinition(props.filter))
const activeSavedId = ref(props.savedFilters.some(saved => saved.id === initialSavedId) ? initialSavedId : null)
const sortBy = ref(props.sorting.sortBy)
const sortDirection = ref(props.sorting.sortDirection)
const perPage = ref(props.perPage)
const perPageChosen = ref(false)
const isLoading = ref(false)
const listSection = ref(null)

// Skeleton rows replace the list while a page, filter or sort loads; as many
// as are shown now, so the layout height stays stable.
const showSkeleton = useDelayedFlag(isLoading)
const skeletonRowCount = computed(() => Math.min(props.members.data.length || perPage.value, perPage.value))

// Measured while the real rows are still rendered, right when loading
// starts; only the visible layout (table or cards) yields a height.
const tableBody = ref(null)
const cardList = ref(null)
const tableRowHeight = ref(null)
const cardRowHeight = ref(null)

watch(isLoading, loading => {
  if (!loading || showSkeleton.value) return

  const rows = props.members.data.length
  tableRowHeight.value = averageRowHeight(tableBody.value, rows) ?? tableRowHeight.value
  cardRowHeight.value = averageRowHeight(cardList.value, rows) ?? cardRowHeight.value
}, { flush: 'sync' })
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
  // The server remembers the last chosen size in a cookie, so once the user
  // picks one it is always sent — even the default, to overwrite the cookie.
  if (perPageChosen.value || perPage.value !== DEFAULT_PER_PAGE) params.perPage = perPage.value

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
// is still being filled in (incomplete) does not. Separate sources are
// compared by value; a getter returning a new array would fire on every
// prop reload (e.g. paginating) and reset the list to page 1.
watch(
  [() => effectiveKey(definition.value, fieldsByKey.value), activeSavedId],
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
  perPageChosen.value = true
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
