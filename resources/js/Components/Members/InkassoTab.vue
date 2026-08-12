<template>
  <div class="space-y-5">
    <div v-if="loading" class="bg-white rounded-lg shadow-sm p-12 text-center text-gray-400 text-sm">
      Inkassodaten werden geladen …
    </div>

    <template v-else>
      <!-- Dunning levels -->
      <div class="bg-white rounded-lg shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
          <div class="text-lg font-semibold text-gray-900">Mahnstufe</div>
          <div class="text-sm text-gray-500">
            {{ activeCase
              ? 'Stufe 4 erreicht · Änderung während des Inkassos gesperrt'
              : `Aktuelle Stufe: ${currentLevel}` }}
          </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          <div
            v-for="level in levelCards"
            :key="level.number"
            :class="[
              level.current ? 'bg-indigo-50 border-indigo-500' : level.done ? 'bg-white border-gray-200' : 'bg-gray-50 border-gray-200',
            ]"
            class="rounded-lg px-4 py-3.5 border"
          >
            <div class="flex items-center gap-2">
              <span
                :class="level.current
                  ? 'bg-indigo-600 text-white'
                  : level.done ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-500'"
                class="w-[22px] h-[22px] rounded-full flex items-center justify-center text-xs font-semibold"
              >
                {{ level.number }}
              </span>
              <span
                :class="level.current ? 'text-indigo-800' : level.done ? 'text-gray-900' : 'text-gray-400'"
                class="text-sm font-semibold"
              >
                {{ level.name }}
              </span>
            </div>
            <div class="text-xs text-gray-500 mt-2">{{ level.meta }}</div>
            <div class="text-xs text-gray-400 mt-0.5">{{ level.date }}</div>
          </div>
        </div>
      </div>

      <!-- No case yet -->
      <div v-if="!activeCase" class="bg-white rounded-lg shadow-sm p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div>
            <div class="text-lg font-semibold text-gray-900">Kein aktiver Inkassofall</div>
            <div class="text-sm text-gray-500 mt-0.5">
              <template v-if="!settings.active">
                Es ist kein Inkassopartner aktiv. Aktiviere ihn in den Einstellungen.
              </template>
              <template v-else>
                Das Mitglied kann bei offenen Forderungen an den Inkassopartner übergeben werden.
              </template>
            </div>
          </div>
          <button
            type="button"
            :disabled="!settings.active"
            class="px-4 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed"
            @click="showHandover = true"
          >
            Zum Inkasso übergeben
          </button>
        </div>
      </div>

      <!-- Active case -->
      <template v-else>
        <div
          class="flex gap-3 px-4 py-3.5 rounded-lg bg-indigo-50 border border-indigo-200 text-indigo-800"
        >
          <Info class="w-5 h-5 flex-none text-indigo-400" />
          <div class="text-sm">
            <div class="font-semibold">Mitglied befindet sich im Inkasso</div>
            <div>
              Die Mahnstufe kann nicht verändert werden. Zahlungen, die direkt im Studio erfolgen,
              können hier nicht verbucht werden – informiere den Inkassopartner darüber.
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6 space-y-5">
          <div class="flex flex-wrap items-start justify-between gap-5">
            <div>
              <div class="flex flex-wrap items-center gap-2.5">
                <span class="text-lg font-semibold text-gray-900">
                  Aktenzeichen {{ activeCase.partner_reference || '—' }}
                </span>
                <button
                  type="button"
                  class="text-xs text-indigo-600 font-semibold hover:text-indigo-700"
                  @click="openReference"
                >
                  bearbeiten
                </button>
                <StatusPill :label="activeCase.status_text" :color="activeCase.status_color" />
              </div>
              <div class="text-sm text-gray-500 mt-1">
                Fall {{ activeCase.case_number }} · übergeben am {{ formatDate(activeCase.handed_over_at) }}
                <template v-if="activeCase.run"> mit Lauf
                  <Link
                    :href="route('finances.inkasso.show', activeCase.collection_run_id)"
                    class="text-indigo-600 font-semibold"
                  >{{ activeCase.run.run_number }}</Link>
                </template>
              </div>
            </div>
            <div class="flex flex-wrap gap-2.5 justify-end">
              <button type="button" class="btn-primary" @click="showPayment = true">Akte aktualisieren</button>
              <button type="button" class="btn-secondary" @click="showClose = true">Fall abschließen</button>
              <button type="button" class="btn-danger" @click="showCancel = true">Fall stornieren</button>
            </div>
          </div>

          <!-- Totals -->
          <div class="grid grid-cols-2 lg:grid-cols-5 gap-px bg-gray-200 border border-gray-200 rounded-lg overflow-hidden">
            <div class="bg-gray-50 p-4">
              <div class="text-xs uppercase tracking-wider text-gray-500">Hauptforderung</div>
              <div class="text-lg font-bold text-gray-900 mt-1">{{ formatCurrency(activeCase.principal_amount) }}</div>
            </div>
            <div class="bg-gray-50 p-4">
              <div class="text-xs uppercase tracking-wider text-gray-500">Mahngebühren</div>
              <div class="text-lg font-bold text-gray-900 mt-1">{{ formatCurrency(activeCase.dunning_amount) }}</div>
            </div>
            <div class="bg-gray-50 p-4">
              <div class="text-xs uppercase tracking-wider text-gray-500">Inkassokosten</div>
              <div class="text-lg font-bold text-gray-900 mt-1">{{ formatCurrency(activeCase.flat_amount) }}</div>
            </div>
            <div class="bg-gray-50 p-4">
              <div class="text-xs uppercase tracking-wider text-gray-500">Gezahlt</div>
              <div class="text-lg font-bold text-green-700 mt-1">{{ formatCurrency(activeCase.paid_amount) }}</div>
            </div>
            <div class="bg-white p-4">
              <div class="text-xs uppercase tracking-wider text-gray-500">Offen</div>
              <div class="text-lg font-bold text-red-700 mt-1">{{ formatCurrency(activeCase.open_amount) }}</div>
            </div>
          </div>

          <!-- Claims -->
          <div>
            <div class="text-sm font-semibold text-gray-900 mb-2.5">Forderungen im Fall</div>
            <div class="overflow-x-auto">
              <table class="w-full border border-gray-200 rounded-lg overflow-hidden">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider text-gray-500">Beschreibung</th>
                    <th class="text-left px-3 py-2.5 text-xs font-medium uppercase tracking-wider text-gray-500">Fällig</th>
                    <th class="text-right px-3 py-2.5 text-xs font-medium uppercase tracking-wider text-gray-500">Betrag</th>
                    <th class="text-right px-3 py-2.5 text-xs font-medium uppercase tracking-wider text-gray-500">Gezahlt</th>
                    <th class="text-right px-3 py-2.5 text-xs font-medium uppercase tracking-wider text-gray-500">Offen</th>
                    <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="claim in activeCase.claims" :key="claim.id" class="border-t border-gray-100">
                    <td class="px-4 py-3 text-sm text-gray-900">{{ claim.description }}</td>
                    <td class="px-3 py-3 text-sm text-gray-500">{{ formatDate(claim.due_date) }}</td>
                    <td class="px-3 py-3 text-sm text-gray-700 text-right">{{ formatCurrency(claim.amount) }}</td>
                    <td class="px-3 py-3 text-sm text-green-700 text-right">
                      {{ Number(claim.paid_amount) > 0 ? formatCurrency(claim.paid_amount) : '–' }}
                    </td>
                    <td class="px-3 py-3 text-sm font-semibold text-gray-900 text-right">{{ formatCurrency(claim.open_amount) }}</td>
                    <td class="px-4 py-3">
                      <StatusPill :label="claim.status_text" :color="claim.status_color" />
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Payments -->
          <div v-if="activeCase.payments?.length">
            <div class="text-sm font-semibold text-gray-900 mb-2.5">Zahlungen im Fall</div>
            <div class="overflow-x-auto">
              <table class="w-full border border-gray-200 rounded-lg overflow-hidden">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider text-gray-500">Buchungsdatum</th>
                    <th class="text-right px-3 py-2.5 text-xs font-medium uppercase tracking-wider text-gray-500">Betrag</th>
                    <th class="text-left px-3 py-2.5 text-xs font-medium uppercase tracking-wider text-gray-500">Verteilung</th>
                    <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider text-gray-500">Quelle</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="payment in activeCase.payments" :key="payment.id" class="border-t border-gray-100">
                    <td class="px-4 py-3 text-sm text-gray-900">{{ formatDate(payment.booked_at) }}</td>
                    <td class="px-3 py-3 text-sm font-semibold text-gray-900 text-right">{{ formatCurrency(payment.amount) }}</td>
                    <td class="px-3 py-3 text-sm text-gray-500">{{ payment.allocation_mode_text }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500">{{ payment.source }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </template>

      <!-- Closed cases -->
      <div v-if="closedCases.length" class="bg-white rounded-lg shadow-sm p-6">
        <div class="text-lg font-semibold text-gray-900 mb-3.5">Abgeschlossene Fälle</div>
        <div class="space-y-3">
          <div
            v-for="entry in closedCases"
            :key="entry.id"
            class="border border-gray-200 rounded-lg px-4 py-4 flex flex-wrap items-center justify-between gap-5"
          >
            <div>
              <div class="flex items-center gap-2.5">
                <span class="text-sm font-semibold text-gray-900">
                  Aktenzeichen {{ entry.partner_reference || '—' }}
                </span>
                <StatusPill :label="entry.status_text" :color="entry.status_color" />
              </div>
              <div class="text-sm text-gray-500 mt-0.5">
                Fall {{ entry.case_number }} · übergeben {{ formatDate(entry.handed_over_at) }}
                <template v-if="entry.closed_at"> · geschlossen {{ formatDate(entry.closed_at) }}</template>
                · {{ formatCurrency(entry.open_amount) }} offen
              </div>
            </div>
            <button
              v-if="entry.rejection_reason"
              type="button"
              class="btn-secondary whitespace-nowrap"
              @click="rejectionReason = entry.rejection_reason"
            >
              Ablehnungsgrund anzeigen
            </button>
          </div>
        </div>
      </div>
    </template>

    <!-- Modals -->
    <HandoverModal
      v-if="showHandover"
      :member="member"
      :settings="settings"
      @close="showHandover = false"
      @done="reload"
    />
    <BookPaymentModal
      v-if="showPayment && activeCase"
      :collection-case="activeCase"
      @close="showPayment = false"
      @done="reload"
    />
    <CloseCaseModal
      v-if="showClose && activeCase"
      :collection-case="activeCase"
      :settings="settings"
      @close="showClose = false"
      @done="reload"
    />
    <CancelCaseModal
      v-if="showCancel && activeCase"
      :collection-case="activeCase"
      @close="showCancel = false"
      @done="reload"
    />
    <ReferenceModal
      v-if="showReference && activeCase"
      :collection-case="activeCase"
      @close="showReference = false"
      @done="reload"
    />

    <!-- Rejection reason -->
    <teleport to="body">
      <div
        v-if="rejectionReason"
        class="fixed inset-0 bg-gray-500/75 flex items-center justify-center z-50 p-6"
        @click.self="rejectionReason = null"
      >
        <div class="bg-white rounded-lg shadow-lg w-full max-w-xl">
          <div class="px-6 pt-6 pb-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Ablehnung durch den Inkassopartner</h2>
          </div>
          <div class="p-6 space-y-4">
            <div class="border border-gray-200 rounded-lg p-4 text-sm text-gray-700">
              {{ rejectionReason }}
            </div>
            <p class="text-sm text-gray-700">
              Durch die Ablehnung wurde die Akte geschlossen und die Forderungen wurden an das Studio
              zurückübertragen. Prüfe die Adressdaten des Mitglieds, bevor du die Forderung erneut übergibst.
            </p>
          </div>
          <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end">
            <button type="button" class="btn-primary" @click="rejectionReason = null">Schließen</button>
          </div>
        </div>
      </div>
    </teleport>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Info } from 'lucide-vue-next'
import StatusPill from '@/Components/Inkasso/StatusPill.vue'
import HandoverModal from '@/Components/Inkasso/HandoverModal.vue'
import BookPaymentModal from '@/Components/Inkasso/BookPaymentModal.vue'
import CloseCaseModal from '@/Components/Inkasso/CloseCaseModal.vue'
import CancelCaseModal from '@/Components/Inkasso/CancelCaseModal.vue'
import ReferenceModal from '@/Components/Inkasso/ReferenceModal.vue'
import { formatCurrency, formatDate } from '@/utils/formatters'

const props = defineProps({
  member: { type: Object, required: true },
})

const emit = defineEmits(['case-count'])

const OPEN_STATUSES = ['in_progress', 'partial_payment']

const LEVEL_NAMES = {
  1: 'Zahlungserinnerung',
  2: '1. Mahnung',
  3: '2. Mahnung',
  4: 'Inkasso',
}

const loading = ref(true)
const cases = ref([])
const notices = ref([])
const currentLevel = ref(0)
const settings = ref({ active: false })
const rejectionReason = ref(null)

const showHandover = ref(false)
const showPayment = ref(false)
const showClose = ref(false)
const showCancel = ref(false)
const showReference = ref(false)

const activeCase = computed(() => cases.value.find(entry => OPEN_STATUSES.includes(entry.status)) ?? null)
const closedCases = computed(() => cases.value.filter(entry => !OPEN_STATUSES.includes(entry.status)))

const levelCards = computed(() =>
  [1, 2, 3, 4].map(number => {
    const notice = notices.value.find(entry => entry.level === number)
    const isCollection = number === 4

    return {
      number,
      name: LEVEL_NAMES[number],
      done: Boolean(notice) && !(isCollection && activeCase.value),
      current: isCollection ? Boolean(activeCase.value) : false,
      meta: isCollection
        ? 'Übergabe an den Inkassopartner'
        : notice
          ? `${formatCurrency(notice.fee)} Mahngebühr`
          : 'noch nicht erreicht',
      date: notice ? `erreicht ${formatDate(notice.triggered_at)}` : '—',
    }
  })
)

const load = async () => {
  loading.value = true

  try {
    const { data } = await axios.get(route('members.inkasso.show', props.member.id))
    cases.value = data.cases ?? []
    notices.value = data.dunning_notices ?? []
    currentLevel.value = data.current_level ?? 0
    settings.value = data.settings ?? { active: false }
    emit('case-count', cases.value.length)
  } finally {
    loading.value = false
  }
}

const reload = () => {
  closeAll()
  load()
}

const closeAll = () => {
  showHandover.value = false
  showPayment.value = false
  showClose.value = false
  showCancel.value = false
  showReference.value = false
}

const openReference = () => {
  showReference.value = true
}

onMounted(load)

defineExpose({ reload })
</script>

<style scoped>
@reference "tailwindcss";

.btn-primary {
  @apply px-4 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors;
}

.btn-secondary {
  @apply px-4 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-50 transition-colors;
}

.btn-danger {
  @apply px-4 py-2.5 bg-white border border-red-300 text-red-700 rounded-md text-sm font-medium hover:bg-red-50 transition-colors;
}
</style>
