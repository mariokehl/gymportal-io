<template>
    <div class="space-y-4">
        <!-- Kopfbereich: Verbindung, Filter, Kennzahlen -->
        <div class="bg-white shadow-sm rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <!-- Verbindungsstatus -->
                    <div class="flex items-center gap-3 flex-wrap">
                        <div class="flex items-center gap-2">
                            <span
                                :class="[
                                    'relative flex h-3 w-3',
                                    isConnected ? 'text-green-500' : 'text-red-500'
                                ]"
                            >
                                <span
                                    v-if="isConnected && !isPaused"
                                    class="animate-ping absolute inline-flex h-full w-full rounded-full bg-current opacity-75"
                                ></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-current"></span>
                            </span>
                            <span class="text-sm font-medium" :class="isConnected ? 'text-green-600' : 'text-red-600'">
                                {{ isConnected ? (isPaused ? 'PAUSIERT' : 'LIVE') : 'OFFLINE' }}
                            </span>
                        </div>

                        <button
                            @click="toggleLive"
                            :class="[
                                'inline-flex items-center px-3 py-1.5 border rounded-md text-sm font-medium transition-colors',
                                isPaused
                                    ? 'border-green-300 text-green-700 hover:bg-green-50'
                                    : 'border-yellow-300 text-yellow-700 hover:bg-yellow-50'
                            ]"
                        >
                            <Play v-if="isPaused" class="w-4 h-4 mr-1" />
                            <Pause v-else class="w-4 h-4 mr-1" />
                            {{ isPaused ? 'Fortsetzen' : 'Pausieren' }}
                        </button>

                        <span v-if="newEntriesCount > 0" class="text-sm text-orange-600 font-medium">
                            {{ newEntriesCount }} neue Einträge
                        </span>
                    </div>

                    <!-- Aktive Filter als Chips + Filter-Umschalter -->
                    <div class="flex items-center gap-2 flex-wrap">
                        <button
                            v-for="chip in activeChips"
                            :key="chip.key"
                            type="button"
                            @click="chip.clear()"
                            class="inline-flex items-center gap-1.5 pl-3 pr-2.5 py-1 rounded-full border border-indigo-200 bg-indigo-50 text-indigo-800 text-xs font-medium hover:bg-indigo-100"
                        >
                            {{ chip.label }}
                            <X class="w-3 h-3" />
                        </button>

                        <button
                            type="button"
                            @click="showFilters = !showFilters"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-md border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            <SlidersHorizontal class="w-4 h-4" />
                            Filter
                        </button>
                    </div>
                </div>

                <!-- Ausklappbare Filter -->
                <div v-if="showFilters" class="mt-5 pt-5 border-t border-gray-200 flex flex-wrap gap-3">
                    <select
                        v-model="filters.scanner"
                        @change="applyFilters"
                        class="p-2 border border-gray-300 rounded-md bg-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    >
                        <option :value="null">Alle Scanner</option>
                        <option v-for="scanner in scanners" :key="scanner.device_number" :value="scanner.device_number">
                            {{ scanner.device_name }} (#{{ scanner.device_number }})
                        </option>
                    </select>

                    <select
                        v-model="filters.type"
                        @change="applyFilters"
                        class="p-2 border border-gray-300 rounded-md bg-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    >
                        <option :value="null">Alle Typen</option>
                        <option value="qr_code">QR-Code</option>
                        <option value="nfc_card">NFC-Karte</option>
                    </select>

                    <select
                        v-model="filters.task"
                        @change="applyFilters"
                        class="p-2 border border-gray-300 rounded-md bg-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    >
                        <option :value="null">Alle Aufgaben</option>
                        <option v-for="task in DEVICE_TASKS" :key="task.value" :value="task.value">
                            {{ task.label }}
                        </option>
                    </select>

                    <select
                        v-if="hasSiblingLocations"
                        v-model="filters.origin"
                        @change="applyFilters"
                        class="p-2 border border-gray-300 rounded-md bg-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    >
                        <option :value="null">Alle Herkünfte</option>
                        <option value="home">Eigene Mitglieder</option>
                        <option value="guest">Fremd-Check-ins</option>
                    </select>

                    <select
                        v-model="filters.status"
                        @change="applyFilters"
                        class="p-2 border border-gray-300 rounded-md bg-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    >
                        <option :value="null">Alle Status</option>
                        <option value="granted">Gewährt</option>
                        <option value="denied">Verweigert</option>
                    </select>

                    <button
                        v-if="hasActiveFilters"
                        @click="clearFilters"
                        class="p-2 text-gray-500 hover:text-gray-700"
                        title="Filter zurücksetzen"
                    >
                        <RotateCcw class="w-4 h-4" />
                    </button>
                </div>

                <!-- Kennzahlen: klickbar, setzen den passenden Filter -->
                <div class="mt-5 pt-5 border-t border-gray-200 grid gap-4 grid-cols-2 lg:grid-cols-4">
                    <button
                        type="button"
                        @click="clearFilters"
                        class="text-left border border-gray-200 rounded-lg px-4 py-3.5 bg-white hover:bg-gray-50"
                    >
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Check-ins heute</div>
                        <div class="mt-1.5 text-2xl font-bold text-gray-900">{{ summary.checkins }}</div>
                    </button>

                    <button
                        v-if="hasSiblingLocations"
                        type="button"
                        @click="filterOrigin('guest')"
                        class="text-left border border-gray-200 rounded-lg px-4 py-3.5 bg-white hover:bg-gray-50"
                    >
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Fremd-Check-ins</div>
                        <div class="mt-1.5 text-2xl font-bold text-indigo-600">{{ summary.guests }}</div>
                    </button>

                    <button
                        type="button"
                        @click="filterStatus('denied')"
                        class="text-left border border-gray-200 rounded-lg px-4 py-3.5 bg-white hover:bg-gray-50"
                    >
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Ablehnungen heute</div>
                        <div class="mt-1.5 text-2xl font-bold text-red-600">{{ summary.denied }}</div>
                    </button>

                    <div v-if="hasSiblingLocations" class="border border-gray-200 rounded-lg px-4 py-3.5">
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Gäste eingelassen</div>
                        <div class="mt-1.5 text-2xl font-bold text-gray-900">{{ summary.guests_granted }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Aufmerksamkeit erforderlich -->
        <div
            v-if="attentionRows.length > 0"
            class="bg-white border border-red-200 rounded-lg shadow-sm overflow-hidden"
        >
            <div class="bg-red-50 border-b border-red-200 px-4 py-3.5 sm:px-6 flex items-center gap-2.5 flex-wrap">
                <AlertTriangle class="w-4 h-4 text-red-600 flex-shrink-0" />
                <span class="text-sm font-semibold text-red-900">Aufmerksamkeit erforderlich</span>
                <span class="bg-red-100 text-red-700 rounded-full px-2.5 py-0.5 text-xs font-medium">
                    {{ attentionRows.length }} in den letzten 30 Minuten
                </span>
            </div>

            <div
                v-for="log in attentionRows"
                :key="log.id"
                class="px-4 py-4 sm:px-6 border-b border-gray-100 last:border-b-0 flex items-center justify-between gap-4 flex-wrap"
            >
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-medium text-gray-900">
                            {{ log.member_name || 'Unbekanntes Mitglied' }}
                        </span>
                        <span v-if="log.member_number" class="text-sm text-gray-400">
                            (#{{ log.member_number }})
                        </span>
                        <span
                            v-if="log.is_cross_location"
                            class="inline-flex items-center gap-1 bg-amber-100 text-amber-800 rounded px-2 py-0.5 text-xs font-medium"
                        >
                            <MapPin class="w-3 h-3 flex-shrink-0" />
                            {{ log.home_gym_name }}
                        </span>
                    </div>
                    <div class="mt-1 text-sm text-red-600">
                        {{ log.denial_reason || 'Zugang verweigert' }}
                    </div>
                    <div class="mt-0.5 text-xs text-gray-500">
                        {{ formatTime(log.created_at) }} · {{ log.time_ago }}
                    </div>
                </div>

                <div class="flex items-center gap-2 flex-shrink-0">
                    <!-- Ein fremder Vertrag wird in der anderen Organisation
                         verwaltet, daher erst der Wechsel-Dialog. -->
                    <button
                        v-if="log.denial_kind === 'contract' && log.is_cross_location"
                        type="button"
                        @click="openSwitch(log, 'contract')"
                        class="px-3.5 py-1.5 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700"
                    >
                        Vertrag prüfen
                    </button>
                    <Link
                        v-else-if="log.denial_kind === 'contract'"
                        :href="route('contracts.index')"
                        class="px-3.5 py-1.5 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700"
                    >
                        Vertrag prüfen
                    </Link>
                    <button
                        v-else-if="log.denial_kind === 'location'"
                        type="button"
                        @click="emit('open-config')"
                        class="px-3.5 py-1.5 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700"
                    >
                        Standort freigeben
                    </button>
                    <a
                        v-if="log.member_url"
                        :href="log.member_url"
                        class="px-3.5 py-1.5 rounded-md border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Profil öffnen
                    </a>
                    <button
                        v-else-if="log.is_cross_location && log.member_id"
                        type="button"
                        @click="openSwitch(log, 'member')"
                        class="px-3.5 py-1.5 rounded-md border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Profil öffnen
                    </button>
                </div>
            </div>
        </div>

        <div class="flex gap-4 items-start flex-col xl:flex-row">
            <!-- Protokoll, nach Tag gruppiert -->
            <div class="flex-1 min-w-0 w-full bg-white shadow-sm rounded-lg overflow-hidden">
                <div v-if="logs.length === 0" class="text-center py-12">
                    <Radio class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900">Keine Einträge</h3>
                    <p class="mt-1 text-sm text-gray-500">Warten auf Scan-Ereignisse...</p>
                </div>

                <template v-else>
                    <div v-for="group in logGroups" :key="group.day">
                        <div class="bg-gray-50 border-y border-gray-200 px-4 py-2 sm:px-6 text-xs font-medium uppercase tracking-wide text-gray-500">
                            {{ group.day }}
                        </div>

                        <TransitionGroup name="log-list">
                            <div
                                v-for="log in group.rows"
                                :key="log.id"
                                :class="[
                                    'px-4 py-3.5 sm:px-6 border-b border-gray-100 transition-all duration-500',
                                    isNewEntry(log) ? 'bg-yellow-50' : 'bg-white hover:bg-gray-50'
                                ]"
                            >
                                <div class="flex items-center justify-between gap-4">
                                    <div class="flex items-center gap-3.5 min-w-0">
                                        <span
                                            :class="[
                                                'flex-shrink-0 w-2 h-2 rounded-full',
                                                log.access_granted ? 'bg-green-500' : 'bg-red-500'
                                            ]"
                                        ></span>

                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <a
                                                    v-if="log.member_url"
                                                    :href="log.member_url"
                                                    class="text-sm font-medium text-gray-900 hover:text-indigo-600"
                                                >
                                                    {{ log.member_name }}
                                                </a>
                                                <!-- Fremdes Profil: erst wechseln, dann öffnen. -->
                                                <button
                                                    v-else-if="log.member_name && log.is_cross_location && log.member_id"
                                                    type="button"
                                                    @click="openSwitch(log, 'member')"
                                                    class="text-sm font-medium text-gray-900 hover:text-indigo-600"
                                                >
                                                    {{ log.member_name }}
                                                </button>
                                                <span v-else-if="log.member_name" class="text-sm font-medium text-gray-900">
                                                    {{ log.member_name }}
                                                </span>
                                                <span v-else class="text-sm font-medium text-gray-500">
                                                    Unbekanntes Mitglied
                                                </span>

                                                <span v-if="log.member_number" class="text-sm text-gray-400">
                                                    (#{{ log.member_number }})
                                                </span>

                                                <span
                                                    v-if="log.is_cross_location"
                                                    class="inline-flex items-center gap-1 bg-amber-100 text-amber-800 rounded px-2 py-0.5 text-xs font-medium"
                                                >
                                                    <MapPin class="w-3 h-3 flex-shrink-0" />
                                                    {{ log.home_gym_name }}
                                                </span>

                                                <span v-if="isNewEntry(log)" class="bg-yellow-100 text-yellow-800 rounded px-2 py-0.5 text-xs font-medium">
                                                    NEU
                                                </span>
                                            </div>

                                            <div class="mt-0.5 text-xs text-gray-500">
                                                <template v-if="log.scanner_name">{{ log.scanner_name }} · </template>{{ log.scan_type_label }}
                                                <template v-if="log.device_task">
                                                    · {{ deviceTaskLabel(log.device_task) }}
                                                </template>
                                            </div>

                                            <!-- Ablehnungsgrund mit passender Abkürzung -->
                                            <div v-if="!log.access_granted" class="mt-1.5 flex items-center gap-2 flex-wrap">
                                                <span class="text-sm text-red-600">
                                                    {{ log.denial_reason || 'Zugang verweigert' }}
                                                </span>

                                                <button
                                                    v-if="log.denial_kind === 'contract' && log.is_cross_location"
                                                    type="button"
                                                    @click="openSwitch(log, 'contract')"
                                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full border border-indigo-200 bg-indigo-50 text-indigo-800 text-xs font-medium hover:bg-indigo-100"
                                                >
                                                    Vertrag prüfen
                                                    <ChevronRight class="w-3 h-3" />
                                                </button>

                                                <Link
                                                    v-else-if="log.denial_kind === 'contract'"
                                                    :href="route('contracts.index')"
                                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full border border-indigo-200 bg-indigo-50 text-indigo-800 text-xs font-medium hover:bg-indigo-100"
                                                >
                                                    Vertrag prüfen
                                                    <ChevronRight class="w-3 h-3" />
                                                </Link>

                                                <button
                                                    v-else-if="log.denial_kind === 'location'"
                                                    type="button"
                                                    @click="emit('open-config')"
                                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full border border-indigo-200 bg-indigo-50 text-indigo-800 text-xs font-medium hover:bg-indigo-100"
                                                >
                                                    Standortfreigabe prüfen
                                                    <ChevronRight class="w-3 h-3" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-4 flex-shrink-0">
                                        <div class="text-right">
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ formatTime(log.created_at) }}
                                            </div>
                                            <div class="text-xs text-gray-500">{{ log.time_ago }}</div>
                                        </div>

                                        <button
                                            v-if="log.nfc_card_id && !log.access_granted"
                                            @click="copyNfcId(log)"
                                            class="inline-flex items-center px-3 py-1.5 border border-orange-300 rounded-md text-sm font-medium text-orange-700 bg-orange-50 hover:bg-orange-100"
                                        >
                                            <Copy class="w-4 h-4 mr-1" />
                                            ID kopieren
                                        </button>
                                    </div>
                                </div>

                                <!-- NFC-Karten-Detail -->
                                <div
                                    v-if="log.nfc_card_id && !log.access_granted"
                                    class="mt-3 ml-6 p-3 bg-orange-50 border border-orange-200 rounded-lg"
                                >
                                    <div class="flex items-start">
                                        <AlertTriangle class="w-5 h-5 text-orange-500 mt-0.5 mr-2 flex-shrink-0" />
                                        <div class="flex-1">
                                            <p class="text-sm font-medium text-orange-800">NFC-Karten-ID</p>
                                            <div class="mt-1 flex items-center gap-2">
                                                <code class="text-sm font-mono bg-white px-2 py-1 rounded border border-orange-200">
                                                    {{ log.nfc_card_id }}
                                                </code>
                                                <button
                                                    @click="copyNfcId(log)"
                                                    class="p-1 text-orange-600 hover:text-orange-800"
                                                    title="Kopieren"
                                                >
                                                    <Copy class="w-4 h-4" />
                                                </button>
                                            </div>
                                            <p class="mt-2 text-xs text-orange-600">
                                                Kopieren Sie diese ID und hinterlegen Sie sie im Mitgliederprofil unter „Zugänge“.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </TransitionGroup>
                    </div>

                    <div v-if="hasMore" class="px-4 py-4 text-center">
                        <button
                            @click="loadMore"
                            :disabled="isLoading"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
                        >
                            <Loader2 v-if="isLoading" class="w-4 h-4 mr-2 animate-spin" />
                            {{ isLoading ? 'Laden...' : 'Weitere laden' }}
                        </button>
                    </div>
                </template>
            </div>

            <!-- Fremd-Check-ins nach Heimatstandort -->
            <div
                v-if="hasSiblingLocations"
                class="w-full xl:w-80 xl:flex-shrink-0 bg-white shadow-sm rounded-lg p-6"
            >
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-1">Fremd-Check-ins heute</h3>
                <p class="text-xs text-gray-500 mb-4">
                    Eingelassene von insgesamt versuchten Fremd-Check-ins, nach Heimatstandort.
                </p>

                <div
                    v-for="location in summary.breakdown"
                    :key="location.name"
                    class="py-2.5 border-b border-gray-100 last:border-b-0"
                >
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="text-sm text-gray-700">{{ location.name }}</span>
                        <span class="text-sm font-medium text-gray-900 whitespace-nowrap">
                            {{ location.granted }} von {{ location.total }}
                        </span>
                    </div>
                    <div class="mt-1.5 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                        <div
                            class="h-full rounded-full bg-indigo-600"
                            :style="{ width: barWidth(location) }"
                        ></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Standort wechseln -->
        <SwitchLocationModal
            :log="switchLog"
            :target="switchTarget"
            :current-gym-name="currentGymName"
            @close="switchLog = null"
        />

        <!-- Kopier-Bestätigung -->
        <Transition
            enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-y-2 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="showCopyToast"
                class="fixed bottom-4 right-4 bg-green-600 text-white px-4 py-2 rounded-lg shadow-lg flex items-center"
            >
                <CheckCircle class="w-5 h-5 mr-2" />
                {{ copyToastMessage }}
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import {
    Play, Pause, RotateCcw, Radio, CheckCircle, Copy, AlertTriangle,
    Loader2, MapPin, SlidersHorizontal, X, ChevronRight
} from 'lucide-vue-next'
import SwitchLocationModal from '@/Components/AccessControl/SwitchLocationModal.vue'
import { useScannerAccessLogs } from '@/composables/useScannerAccessLogs'
import { getDisplayTimezone } from '@/utils/formatters'
import { DEVICE_TASKS, deviceTaskLabel } from '@/utils/deviceTasks'

const props = defineProps({
    initialLogs: {
        type: Array,
        default: () => []
    },
    scanners: {
        type: Array,
        default: () => []
    },
    gymId: {
        type: Number,
        required: true
    },
    summary: {
        type: Object,
        default: () => ({
            checkins: 0,
            guests: 0,
            denied: 0,
            guests_granted: 0,
            breakdown: []
        })
    },
    hasSiblingLocations: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['open-config'])

const {
    logs,
    isConnected,
    isPaused,
    newEntriesCount,
    isLoading,
    hasMore,
    filters,
    toggleLive,
    loadMore,
    applyFilters: applyComposableFilters,
    clearFilters: clearComposableFilters,
    setInitialLogs
} = useScannerAccessLogs(props.gymId)

const showCopyToast = ref(false)
const copyToastMessage = ref('')
const showFilters = ref(false)

// Log entry whose member or contract lives in another location, shown in the
// switch dialog. Null while the dialog is closed.
const switchLog = ref(null)
const switchTarget = ref('member')

// Shared globally by HandleInertiaRequests, so the dialog can name the
// organisation the operator is leaving.
const currentGymName = computed(() => usePage().props.auth?.user?.current_gym?.name ?? '')

const openSwitch = (log, target) => {
    switchTarget.value = target
    switchLog.value = log
}

onMounted(() => {
    setInitialLogs(props.initialLogs)
})

const hasActiveFilters = computed(
    () => Boolean(filters.scanner || filters.type || filters.task || filters.status || filters.origin)
)

const applyFilters = () => applyComposableFilters(filters)
const clearFilters = () => clearComposableFilters()

const filterOrigin = (origin) => {
    filters.origin = filters.origin === origin ? null : origin
    applyFilters()
}

const filterStatus = (status) => {
    filters.status = filters.status === status ? null : status
    applyFilters()
}

const activeChips = computed(() => {
    const chips = []

    if (filters.origin === 'guest') {
        chips.push({ key: 'origin', label: 'Nur Fremd-Check-ins', clear: () => filterOrigin('guest') })
    }
    if (filters.origin === 'home') {
        chips.push({ key: 'origin', label: 'Nur eigene Mitglieder', clear: () => filterOrigin('home') })
    }
    if (filters.status === 'denied') {
        chips.push({ key: 'status', label: 'Nur Ablehnungen', clear: () => filterStatus('denied') })
    }
    if (filters.status === 'granted') {
        chips.push({ key: 'status', label: 'Nur gewährte Zugänge', clear: () => filterStatus('granted') })
    }

    return chips
})

/**
 * Denials from the last 30 minutes the operator can act on. Anything older is
 * history and belongs in the log below, not in the alert panel.
 */
const attentionRows = computed(() => {
    const cutoff = Date.now() - 30 * 60 * 1000

    return logs.value.filter(
        log => !log.access_granted
            && log.denial_kind
            && new Date(log.created_at).getTime() >= cutoff
    )
})

const dayLabel = (dateString) => {
    const date = new Date(dateString)
    const timeZone = getDisplayTimezone()
    const asDay = value => value.toLocaleDateString('en-CA', { timeZone })

    const today = new Date()
    const yesterday = new Date(today.getTime() - 24 * 60 * 60 * 1000)

    if (asDay(date) === asDay(today)) return 'Heute'
    if (asDay(date) === asDay(yesterday)) return 'Gestern'

    return date.toLocaleDateString('de-DE', {
        timeZone,
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    })
}

/**
 * Groups the log by day, keeping the order the entries arrive in — the list is
 * already sorted newest first.
 */
const logGroups = computed(() => {
    const groups = []

    logs.value.forEach((log) => {
        const day = dayLabel(log.created_at)
        const last = groups[groups.length - 1]

        if (last && last.day === day) {
            last.rows.push(log)
        } else {
            groups.push({ day, rows: [log] })
        }
    })

    return groups
})

const barWidth = (location) => {
    const max = Math.max(...props.summary.breakdown.map(l => l.total), 1)

    return `${Math.round((location.granted / max) * 100)}%`
}

const isNewEntry = (log) => new Date(log.created_at) > new Date(Date.now() - 10000)

const formatTime = (dateString) => {
    if (!dateString) return '-'

    try {
        return new Date(dateString).toLocaleTimeString('de-DE', {
            timeZone: getDisplayTimezone(),
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        })
    } catch {
        return '-'
    }
}

const copyNfcId = async (log) => {
    try {
        await navigator.clipboard.writeText(log.nfc_card_id)
        copyToastMessage.value = 'NFC-ID in Zwischenablage kopiert!'
        showCopyToast.value = true
        setTimeout(() => {
            showCopyToast.value = false
        }, 3000)
    } catch (error) {
        console.error('Copy failed:', error)
    }
}
</script>

<style scoped>
.log-list-enter-active {
    transition: all 0.5s ease-out;
}

.log-list-enter-from {
    opacity: 0;
    transform: translateY(-20px);
}

.log-list-leave-active {
    transition: all 0.3s ease-in;
}

.log-list-leave-to {
    opacity: 0;
}

.log-list-move {
    transition: transform 0.3s ease;
}
</style>
