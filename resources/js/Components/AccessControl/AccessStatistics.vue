<template>
    <div class="space-y-6">
        <!-- Period Selector -->
        <div class="bg-surface shadow-sm rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-ink">Statistiken</h3>
                    <select
                        v-model="selectedPeriod"
                        aria-label="Zeitraum"
                        @change="loadStatistics"
                        class="field p-2"
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
            <div class="bg-surface shadow-sm rounded-lg p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 p-3 bg-brand-soft rounded-lg">
                        <Activity class="w-6 h-6 text-brand" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-ink-muted">Gesamt Scans</p>
                        <p class="text-2xl font-bold text-ink tabular-nums">{{ stats.total || 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-surface shadow-sm rounded-lg p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 p-3 bg-success-soft rounded-lg">
                        <CheckCircle class="w-6 h-6 text-success" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-ink-muted">Erfolgreich</p>
                        <p class="text-2xl font-bold text-success tabular-nums">{{ stats.granted || 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-surface shadow-sm rounded-lg p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 p-3 bg-danger-soft rounded-lg">
                        <XCircle class="w-6 h-6 text-danger" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-ink-muted">Abgelehnt</p>
                        <p class="text-2xl font-bold text-danger tabular-nums">{{ stats.denied || 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-surface shadow-sm rounded-lg p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 p-3 bg-info-soft rounded-lg">
                        <TrendingUp class="w-6 h-6 text-info" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-ink-muted">Erfolgsrate</p>
                        <p class="text-2xl font-bold text-info tabular-nums">{{ stats.success_rate || 0 }}%</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Hourly Distribution -->
            <div class="bg-surface shadow-sm rounded-lg p-6">
                <h4 class="text-base font-semibold text-ink mb-4">Scans pro Stunde</h4>
                <div class="h-48">
                    <div class="flex items-end justify-between h-full gap-1">
                        <div
                            v-for="(hour, index) in hourlyData"
                            :key="index"
                            class="flex-1 flex flex-col items-center"
                        >
                            <div class="w-full flex flex-col items-center" style="height: 140px;">
                                <div
                                    class="w-full bg-success-solid rounded-t transition-all duration-300"
                                    :style="{ height: getBarHeight(hour.granted, maxHourlyValue) + 'px' }"
                                    :title="`${hour.granted} erfolgreich`"
                                ></div>
                                <div
                                    class="w-full bg-danger-solid rounded-b transition-all duration-300"
                                    :style="{ height: getBarHeight(hour.denied, maxHourlyValue) + 'px' }"
                                    :title="`${hour.denied} abgelehnt`"
                                ></div>
                            </div>
                            <span
                                v-if="index % 2 === 0"
                                class="text-xs text-ink-muted mt-1"
                            >
                                {{ index }}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-center gap-4 mt-4 text-xs">
                    <div class="flex items-center gap-1">
                        <div class="w-3 h-3 bg-success-solid rounded"></div>
                        <span class="text-ink-secondary">Erfolgreich</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <div class="w-3 h-3 bg-danger-solid rounded"></div>
                        <span class="text-ink-secondary">Abgelehnt</span>
                    </div>
                </div>
            </div>

            <!-- Scan Types -->
            <div class="bg-surface shadow-sm rounded-lg p-6">
                <h4 class="text-base font-semibold text-ink mb-4">Nach Scan-Typ</h4>
                <div class="space-y-4">
                    <div>
                        <div class="flex items-center justify-between text-sm mb-1">
                            <div class="flex items-center gap-2">
                                <QrCode class="w-4 h-4 text-info" />
                                <span class="text-ink-secondary">QR-Code</span>
                            </div>
                            <span class="font-medium">{{ stats.by_scan_type?.qr_code || 0 }}</span>
                        </div>
                        <div class="w-full bg-fill-strong rounded-full h-2">
                            <div
                                class="bg-info-solid h-2 rounded-full transition-all duration-300"
                                :style="{ width: getPercentage(stats.by_scan_type?.qr_code || 0, stats.total || 1) + '%' }"
                            ></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between text-sm mb-1">
                            <div class="flex items-center gap-2">
                                <CreditCard class="w-4 h-4 text-purple-600" />
                                <span class="text-ink-secondary">NFC-Karte</span>
                            </div>
                            <span class="font-medium">{{ stats.by_scan_type?.nfc_card || 0 }}</span>
                        </div>
                        <div class="w-full bg-fill-strong rounded-full h-2">
                            <div
                                class="bg-purple-600 h-2 rounded-full transition-all duration-300"
                                :style="{ width: getPercentage(stats.by_scan_type?.nfc_card || 0, stats.total || 1) + '%' }"
                            ></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between text-sm mb-1">
                            <div class="flex items-center gap-2">
                                <QrCode class="w-4 h-4 text-info" />
                                <span class="text-ink-secondary">Rolling QR</span>
                            </div>
                            <span class="font-medium">{{ stats.by_scan_type?.rolling_qr || 0 }}</span>
                        </div>
                        <div class="w-full bg-fill-strong rounded-full h-2">
                            <div
                                class="bg-info-solid h-2 rounded-full transition-all duration-300"
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
            <div class="bg-surface shadow-sm rounded-lg p-6">
                <h4 class="text-base font-semibold text-ink mb-4">Scanner-Aktivität</h4>
                <div v-if="stats.by_device?.length > 0" class="space-y-3">
                    <div
                        v-for="device in stats.by_device"
                        :key="device.device"
                        class="flex items-center justify-between"
                    >
                        <div class="flex items-center gap-2">
                            <Scan class="w-4 h-4 text-ink-muted" />
                            <span class="text-sm text-ink-secondary">Scanner #{{ device.device }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-ink">{{ device.total }}</span>
                            <span class="text-xs text-ink-muted">({{ device.success_rate }}%)</span>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-8 text-ink-muted text-sm">
                    Keine Daten verfügbar
                </div>
            </div>

            <!-- Denial Reasons -->
            <div class="bg-surface shadow-sm rounded-lg p-6">
                <h4 class="text-base font-semibold text-ink mb-4">Ablehnungsgründe</h4>
                <div v-if="stats.denial_reasons?.length > 0" class="space-y-3">
                    <div
                        v-for="reason in stats.denial_reasons"
                        :key="reason.denial_reason"
                        class="flex items-center justify-between"
                    >
                        <div class="flex items-center gap-2">
                            <AlertTriangle class="w-4 h-4 text-danger" />
                            <span class="text-sm text-ink-secondary">{{ reason.denial_reason || 'Unbekannt' }}</span>
                        </div>
                        <span class="text-sm font-medium text-ink">{{ reason.count }}</span>
                    </div>
                </div>
                <div v-else class="text-center py-8 text-ink-muted text-sm">
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
} from '@lucide/vue'

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
