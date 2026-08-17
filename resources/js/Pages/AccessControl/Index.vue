<template>
    <AppLayout title="Zugangskontrolle">
        <template #header>
            Zugangskontrolle
        </template>

        <div class="space-y-6">
            <!-- Tabs -->
            <TabRail v-model="activeTab" :tabs="tabs" />

            <!-- Live Log Tab (für alle Benutzer) -->
            <div v-if="activeTab === 'live-log'" class="space-y-6">
                <AccessLogLive
                    :initial-logs="recentLogs"
                    :scanners="scannersData"
                    :gym-id="gymId"
                    :summary="crossLocationSummary"
                    :has-sibling-locations="crossLocation.has_siblings"
                    @open-config="activeTab = 'config'"
                />
            </div>

            <!-- Scanner Tab (nur für Owner/Admin) -->
            <div v-if="activeTab === 'scanners' && isOwnerOrAdmin" class="space-y-6">
                <ScannerManagement
                    :scanners="scannersData"
                    :usage-addons="usageAddons"
                    :gym-id="gymId"
                    :initial-secret-key="scannerSecretKey"
                    @scanner-created="handleScannerCreated"
                    @scanner-updated="handleScannerUpdated"
                    @scanner-deleted="handleScannerDeleted"
                    @success="handleSuccess"
                    @error="handleError"
                />
            </div>

            <!-- Konfiguration Tab (nur für Owner/Admin) -->
            <div v-if="activeTab === 'config' && isOwnerOrAdmin" class="space-y-6">
                <CrossLocationSettings
                    :cross-location="crossLocation"
                    @success="handleSuccess"
                    @error="handleError"
                />
                <RollingQrSettings
                    :rolling-qr-enabled="rollingQrEnabled"
                    :rolling-qr-interval="rollingQrInterval"
                    :rolling-qr-tolerance-windows="rollingQrToleranceWindows"
                    @success="handleSuccess"
                    @error="handleError"
                />
                <GoogleSheetSettings
                    :google-sheet="googleSheet"
                    @success="handleSuccess"
                    @error="handleError"
                />
            </div>

            <!-- Statistics Tab (nur für Owner/Admin) -->
            <div v-if="activeTab === 'statistics' && isOwnerOrAdmin" class="space-y-6">
                <AccessStatistics
                    :initial-statistics="statistics"
                    :gym-id="gymId"
                />
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { ScanLine, Radio, BarChart3, Settings } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import TabRail from '@/Components/TabRail.vue'
import ScannerManagement from '@/Components/AccessControl/ScannerManagement.vue'
import AccessLogLive from '@/Components/AccessControl/AccessLogLive.vue'
import AccessStatistics from '@/Components/AccessControl/AccessStatistics.vue'
import RollingQrSettings from '@/Components/AccessControl/RollingQrSettings.vue'
import GoogleSheetSettings from '@/Components/AccessControl/GoogleSheetSettings.vue'
import CrossLocationSettings from '@/Components/AccessControl/CrossLocationSettings.vue'
import { useToast } from '@/composables/useToast'

const { success, error: toastError } = useToast()

const page = usePage()

const props = defineProps({
    scanners: {
        type: Array,
        default: () => []
    },
    usageAddons: {
        type: Array,
        default: () => []
    },
    recentLogs: {
        type: Array,
        default: () => []
    },
    statistics: {
        type: Object,
        default: () => ({})
    },
    gymId: {
        type: Number,
        required: true
    },
    scannerSecretKey: {
        type: String,
        default: null
    },
    rollingQrEnabled: {
        type: Boolean,
        default: false
    },
    rollingQrInterval: {
        type: Number,
        default: 3
    },
    rollingQrToleranceWindows: {
        type: Number,
        default: 1
    },
    googleSheet: {
        type: Object,
        default: () => ({
            enabled: false,
            configured: false,
            sheet_url: null,
            service_account_email: null,
            last_synced_at: null
        })
    },
    crossLocation: {
        type: Object,
        default: () => ({
            rule: 'own',
            allowed_gym_ids: [],
            locations: [],
            has_siblings: false
        })
    },
    crossLocationSummary: {
        type: Object,
        default: () => ({
            checkins: 0,
            guests: 0,
            denied: 0,
            guests_granted: 0,
            breakdown: []
        })
    }
})

// Prüfe ob Benutzer Owner oder Admin ist (role_id 1 oder 2)
const isOwnerOrAdmin = computed(() => {
    const roleId = page.props.auth.user?.role_id
    return roleId === 1 || roleId === 2
})

// Alle verfügbaren Tabs
const allTabs = [
    { key: 'live-log', label: 'Live-Protokoll', icon: Radio, requiresAdmin: false },
    { key: 'scanners', label: 'Geräte', icon: ScanLine, requiresAdmin: true },
    { key: 'config', label: 'Konfiguration', icon: Settings, requiresAdmin: true },
    { key: 'statistics', label: 'Statistiken', icon: BarChart3, requiresAdmin: true },
]

// Gefilterte Tabs basierend auf Benutzerrolle
const tabs = computed(() => {
    if (isOwnerOrAdmin.value) {
        return allTabs
    }
    // Mitarbeiter sehen nur Tabs ohne requiresAdmin
    return allTabs.filter(tab => !tab.requiresAdmin)
})

const activeTab = ref('live-log')
const scannersData = ref([...props.scanners])

const handleSuccess = (message) => {
    success(message)
}

const handleError = (message) => {
    toastError(message)
}

const handleScannerCreated = (scanner) => {
    scannersData.value.push(scanner)
}

const handleScannerUpdated = (updatedScanner) => {
    const index = scannersData.value.findIndex(s => s.id === updatedScanner.id)
    if (index !== -1) {
        scannersData.value[index] = updatedScanner
    }
}

const handleScannerDeleted = (scannerId) => {
    scannersData.value = scannersData.value.filter(s => s.id !== scannerId)
}
</script>
