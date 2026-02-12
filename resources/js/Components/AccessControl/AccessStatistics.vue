<template>
    <div class="space-y-6">
        <!-- Period Selector -->
        <div class="bg-white shadow-sm rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900">Statistiken</h3>
                    <select
                        v-model="selectedPeriod"
                        @change="loadStatistics"
                        class="p-2 border border-gray-300 rounded-md bg-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    >
                        <option value="today">Heute</option>
                        <option value="week">Diese Woche</option>
                        <option value="month">Dieser Monat</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 p-3 bg-indigo-100 rounded-lg">
                        <Activity class="w-6 h-6 text-indigo-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Gesamt Scans</p>
                        <p class="text-2xl font-bold text-gray-900">{{ stats.total || 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 p-3 bg-green-100 rounded-lg">
                        <CheckCircle class="w-6 h-6 text-green-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Erfolgreich</p>
                        <p class="text-2xl font-bold text-green-600">{{ stats.granted || 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 p-3 bg-red-100 rounded-lg">
                        <XCircle class="w-6 h-6 text-red-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Abgelehnt</p>
                        <p class="text-2xl font-bold text-red-600">{{ stats.denied || 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 p-3 bg-blue-100 rounded-lg">
                        <TrendingUp class="w-6 h-6 text-blue-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Erfolgsrate</p>
                        <p class="text-2xl font-bold text-blue-600">{{ stats.success_rate || 0 }}%</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Hourly Distribution -->
            <div class="bg-white shadow-sm rounded-lg p-6">
                <h4 class="text-base font-medium text-gray-900 mb-4">Scans pro Stunde</h4>
                <div class="h-48">
                    <div class="flex items-end justify-between h-full gap-1">
                        <div
                            v-for="(hour, index) in hourlyData"
                            :key="index"
                            class="flex-1 flex flex-col items-center"
                        >
                            <div class="w-full flex flex-col items-center" style="height: 140px;">
                                <div
                                    class="w-full bg-green-400 rounded-t transition-all duration-300"
                                    :style="{ height: getBarHeight(hour.granted, maxHourlyValue) + 'px' }"
                                    :title="`${hour.granted} erfolgreich`"
                                ></div>
                                <div
                                    class="w-full bg-red-400 rounded-b transition-all duration-300"
                                    :style="{ height: getBarHeight(hour.denied, maxHourlyValue) + 'px' }"
                                    :title="`${hour.denied} abgelehnt`"
                                ></div>
                            </div>
                            <span
                                v-if="index % 2 === 0"
                                class="text-xs text-gray-500 mt-1"
                            >
                                {{ index }}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-center gap-4 mt-4 text-xs">
                    <div class="flex items-center gap-1">
                        <div class="w-3 h-3 bg-green-400 rounded"></div>
                        <span class="text-gray-600">Erfolgreich</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <div class="w-3 h-3 bg-red-400 rounded"></div>
                        <span class="text-gray-600">Abgelehnt</span>
                    </div>
                </div>
            </div>

            <!-- Scan Types -->
            <div class="bg-white shadow-sm rounded-lg p-6">
                <h4 class="text-base font-medium text-gray-900 mb-4">Nach Scan-Typ</h4>
                <div class="space-y-4">
                    <div>
                        <div class="flex items-center justify-between text-sm mb-1">
                            <div class="flex items-center gap-2">
                                <QrCode class="w-4 h-4 text-blue-600" />
                                <span class="text-gray-700">QR-Code</span>
                            </div>
                            <span class="font-medium">{{ stats.by_scan_type?.qr_code || 0 }}</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div
                                class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                                :style="{ width: getPercentage(stats.by_scan_type?.qr_code || 0, stats.total || 1) + '%' }"
                            ></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between text-sm mb-1">
                            <div class="flex items-center gap-2">
                                <CreditCard class="w-4 h-4 text-purple-600" />
                                <span class="text-gray-700">NFC-Karte</span>
                            </div>
                            <span class="font-medium">{{ stats.by_scan_type?.nfc_card || 0 }}</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div
                                class="bg-purple-600 h-2 rounded-full transition-all duration-300"
                                :style="{ width: getPercentage(stats.by_scan_type?.nfc_card || 0, stats.total || 1) + '%' }"
                            ></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between text-sm mb-1">
                            <div class="flex items-center gap-2">
                                <QrCode class="w-4 h-4 text-blue-600" />
                                <span class="text-gray-700">Rolling QR</span>
                            </div>
                            <span class="font-medium">{{ stats.by_scan_type?.rolling_qr || 0 }}</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div
                                class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                                :style="{ width: getPercentage(stats.by_scan_type?.rolling_qr || 0, stats.total || 1) + '%' }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scanner Activity & Denial Reasons -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Scanner Activity -->
            <div class="bg-white shadow-sm rounded-lg p-6">
                <h4 class="text-base font-medium text-gray-900 mb-4">Scanner-Aktivität</h4>
                <div v-if="stats.by_device?.length > 0" class="space-y-3">
                    <div
                        v-for="device in stats.by_device"
                        :key="device.device"
                        class="flex items-center justify-between"
                    >
                        <div class="flex items-center gap-2">
                            <Scan class="w-4 h-4 text-gray-400" />
                            <span class="text-sm text-gray-700">Scanner #{{ device.device }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-gray-900">{{ device.total }}</span>
                            <span class="text-xs text-gray-500">({{ device.success_rate }}%)</span>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-8 text-gray-500 text-sm">
                    Keine Daten verfügbar
                </div>
            </div>

            <!-- Denial Reasons -->
            <div class="bg-white shadow-sm rounded-lg p-6">
                <h4 class="text-base font-medium text-gray-900 mb-4">Ablehnungsgründe</h4>
                <div v-if="stats.denial_reasons?.length > 0" class="space-y-3">
                    <div
                        v-for="reason in stats.denial_reasons"
                        :key="reason.denial_reason"
                        class="flex items-center justify-between"
                    >
                        <div class="flex items-center gap-2">
                            <AlertTriangle class="w-4 h-4 text-red-400" />
                            <span class="text-sm text-gray-700">{{ reason.denial_reason || 'Unbekannt' }}</span>
                        </div>
                        <span class="text-sm font-medium text-gray-900">{{ reason.count }}</span>
                    </div>
                </div>
                <div v-else class="text-center py-8 text-gray-500 text-sm">
                    Keine Ablehnungen im Zeitraum
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import {
    Activity, CheckCircle, XCircle, TrendingUp,
    QrCode, CreditCard, Scan, AlertTriangle
} from 'lucide-vue-next'

const props = defineProps({
    initialStatistics: {
        type: Object,
        default: () => ({})
    },
    gymId: {
        type: Number,
        required: true
    }
})

const selectedPeriod = ref('today')
const stats = ref(props.initialStatistics)
const isLoading = ref(false)

const hourlyData = computed(() => {
    if (!stats.value.hourly_distribution) {
        // Generate empty data for all 24 hours
        return Array.from({ length: 24 }, (_, i) => ({
            hour: i,
            total: 0,
            granted: 0,
            denied: 0
        }))
    }
    return stats.value.hourly_distribution
})

const maxHourlyValue = computed(() => {
    const max = Math.max(...hourlyData.value.map(h => h.total))
    return max > 0 ? max : 1
})

const loadStatistics = async () => {
    isLoading.value = true

    try {
        const response = await axios.get(route('access-control.statistics'), {
            params: { period: selectedPeriod.value }
        })
        stats.value = response.data
    } catch (error) {
        console.error('Failed to load statistics:', error)
    } finally {
        isLoading.value = false
    }
}

const getBarHeight = (value, max) => {
    if (max === 0 || value === 0) return 0
    return Math.max(2, (value / max) * 120)
}

const getPercentage = (value, total) => {
    if (total === 0) return 0
    return Math.round((value / total) * 100)
}

onMounted(() => {
    // Load fresh statistics on mount
    loadStatistics()
})
</script>
