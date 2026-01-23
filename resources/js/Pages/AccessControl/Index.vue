<template>
    <AppLayout title="Zugangskontrolle">
        <template #header>
            Zugangskontrolle
        </template>

        <div class="space-y-6">
            <!-- Tabs -->
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        @click="activeTab = tab.key"
                        :class="[
                            'py-2 px-1 border-b-2 font-medium text-sm flex items-center',
                            activeTab === tab.key
                                ? 'border-indigo-500 text-indigo-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                        ]"
                    >
                        <component :is="tab.icon" class="w-4 h-4 mr-2" />
                        {{ tab.label }}
                    </button>
                </nav>
            </div>

            <!-- Success/Error Messages -->
            <div v-if="successMessage" class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
                {{ successMessage }}
            </div>
            <div v-if="errorMessage" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                {{ errorMessage }}
            </div>

            <!-- Scanner Tab (nur für Owner/Admin) -->
            <div v-if="activeTab === 'scanners' && isOwnerOrAdmin" class="space-y-6">
                <ScannerManagement
                    :scanners="scannersData"
                    :gym-id="gymId"
                    :initial-secret-key="scannerSecretKey"
                    @scanner-created="handleScannerCreated"
                    @scanner-updated="handleScannerUpdated"
                    @scanner-deleted="handleScannerDeleted"
                    @success="handleSuccess"
                    @error="handleError"
                />
            </div>

            <!-- Live Log Tab (für alle Benutzer) -->
            <div v-if="activeTab === 'live-log'" class="space-y-6">
                <AccessLogLive
                    :initial-logs="recentLogs"
                    :scanners="scannersData"
                    :gym-id="gymId"
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
import { Scan, Radio, BarChart3 } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import ScannerManagement from '@/Components/AccessControl/ScannerManagement.vue'
import AccessLogLive from '@/Components/AccessControl/AccessLogLive.vue'
import AccessStatistics from '@/Components/AccessControl/AccessStatistics.vue'

const page = usePage()

const props = defineProps({
    scanners: {
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
    }
})

// Prüfe ob Benutzer Owner oder Admin ist (role_id 1 oder 2)
const isOwnerOrAdmin = computed(() => {
    const roleId = page.props.auth.user?.role_id
    return roleId === 1 || roleId === 2
})

// Alle verfügbaren Tabs
const allTabs = [
    { key: 'scanners', label: 'Scanner', icon: Scan, requiresAdmin: true },
    { key: 'live-log', label: 'Live-Protokoll', icon: Radio, requiresAdmin: false },
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

// Standard-Tab: 'scanners' für Admin/Owner, 'live-log' für Mitarbeiter
const activeTab = ref(isOwnerOrAdmin.value ? 'scanners' : 'live-log')
const successMessage = ref('')
const errorMessage = ref('')
const scannersData = ref([...props.scanners])

const handleSuccess = (message) => {
    successMessage.value = message
    setTimeout(() => successMessage.value = '', 4000)
}

const handleError = (message) => {
    errorMessage.value = message
    setTimeout(() => errorMessage.value = '', 4000)
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
