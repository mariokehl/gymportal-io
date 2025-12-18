<template>
    <div class="space-y-6">
        <!-- Live Status & Filters -->
        <div class="bg-white shadow-sm rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <!-- Live Indicator -->
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center space-x-2">
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

                    <!-- Filters -->
                    <div class="flex flex-wrap items-center gap-3">
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
                </div>
            </div>
        </div>

        <!-- Log List -->
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <div v-if="logs.length === 0" class="text-center py-12">
                <Radio class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-sm font-medium text-gray-900">Keine Einträge</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Warten auf Scan-Ereignisse...
                </p>
            </div>

            <div v-else class="divide-y divide-gray-200">
                <TransitionGroup name="log-list">
                    <div
                        v-for="log in logs"
                        :key="log.id"
                        :class="[
                            'px-4 py-4 sm:px-6 transition-all duration-500',
                            isNewEntry(log) ? 'bg-yellow-50' : 'bg-white hover:bg-gray-50',
                            !log.access_granted && isNewEntry(log) ? 'bg-red-50' : ''
                        ]"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center min-w-0 gap-4">
                                <!-- Status Icon -->
                                <div
                                    :class="[
                                        'flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center',
                                        log.access_granted ? 'bg-green-100' : 'bg-red-100'
                                    ]"
                                >
                                    <CheckCircle v-if="log.access_granted" class="w-5 h-5 text-green-600" />
                                    <Tooltip v-else :text="log.denial_reason || 'Unbekannter Grund'" position="right">
                                        <XCircle class="w-5 h-5 text-red-600 cursor-help" />
                                    </Tooltip>
                                </div>

                                <!-- Main Info -->
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-gray-900">
                                            {{ log.scanner_name }}
                                        </span>
                                        <span
                                            :class="[
                                                'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                                                log.scan_type === 'nfc_card'
                                                    ? 'bg-purple-100 text-purple-800'
                                                    : 'bg-blue-100 text-blue-800'
                                            ]"
                                        >
                                            <CreditCard v-if="log.scan_type === 'nfc_card'" class="w-3 h-3 mr-1" />
                                            <QrCode v-else class="w-3 h-3 mr-1" />
                                            {{ log.scan_type_label }}
                                        </span>
                                        <span v-if="isNewEntry(log)" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                            NEU
                                        </span>
                                    </div>
                                    <div class="mt-1 text-sm text-gray-500">
                                        <span v-if="log.member_name">
                                            <a
                                                v-if="log.member_url"
                                                :href="log.member_url"
                                                class="text-indigo-600 hover:text-indigo-800"
                                            >
                                                {{ log.member_name }}
                                            </a>
                                            <span v-else>{{ log.member_name }}</span>
                                            <span v-if="log.member_number" class="text-gray-400">
                                                (#{{ log.member_number }})
                                            </span>
                                        </span>
                                        <span v-else-if="log.nfc_card_id" class="text-orange-600">
                                            Unbekannte NFC-Karte
                                        </span>
                                        <span v-else-if="!log.access_granted" class="text-red-600">
                                            {{ log.denial_reason || 'Zugang verweigert' }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Side: Time & Actions -->
                            <div class="flex items-center gap-4 ml-4">
                                <div class="text-right">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ formatTime(log.created_at) }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ log.time_ago }}
                                    </div>
                                </div>

                                <!-- NFC Copy Button -->
                                <div v-if="log.nfc_card_id && !log.access_granted" class="flex-shrink-0">
                                    <button
                                        @click="copyNfcId(log)"
                                        class="inline-flex items-center px-3 py-1.5 border border-orange-300 rounded-md text-sm font-medium text-orange-700 bg-orange-50 hover:bg-orange-100 transition-colors"
                                    >
                                        <Copy class="w-4 h-4 mr-1" />
                                        ID kopieren
                                    </button>
                                </div>

                                <!-- Member Link -->
                                <div v-else-if="log.member_url" class="flex-shrink-0">
                                    <a
                                        :href="log.member_url"
                                        class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors"
                                    >
                                        <User class="w-4 h-4 mr-1" />
                                        Profil
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- NFC Card Detail Box -->
                        <div
                            v-if="log.nfc_card_id && !log.access_granted"
                            class="mt-3 ml-14 p-3 bg-orange-50 border border-orange-200 rounded-lg"
                        >
                            <div class="flex items-start">
                                <AlertTriangle class="w-5 h-5 text-orange-500 mt-0.5 mr-2 flex-shrink-0" />
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-orange-800">
                                        NFC-Karten-ID
                                    </p>
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
                                        Kopieren Sie diese ID und hinterlegen Sie sie im Mitgliederprofil unter "Zugangsverwaltung".
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </TransitionGroup>

                <!-- Load More -->
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
            </div>
        </div>

        <!-- Copy Success Toast -->
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
import { ref, computed, onMounted, watch } from 'vue'
import {
    Play, Pause, RotateCcw, Radio, CheckCircle, XCircle,
    CreditCard, QrCode, Copy, User, AlertTriangle, Loader2
} from 'lucide-vue-next'
import Tooltip from '@/Components/Tooltip.vue'
import { useScannerAccessLogs } from '@/composables/useScannerAccessLogs'
import { format } from 'date-fns'

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
    }
})

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
const newEntryIds = ref(new Set())

// Initialize with server-provided logs
onMounted(() => {
    setInitialLogs(props.initialLogs)
})

const hasActiveFilters = computed(() => {
    return filters.scanner || filters.type || filters.status
})

const applyFilters = () => {
    applyComposableFilters(filters)
}

const clearFilters = () => {
    clearComposableFilters()
}

const isNewEntry = (log) => {
    // Consider entries new if created in last 10 seconds
    const tenSecondsAgo = new Date(Date.now() - 10000)
    return new Date(log.created_at) > tenSecondsAgo
}

const formatTime = (dateString) => {
    try {
        return format(new Date(dateString), 'HH:mm:ss')
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
