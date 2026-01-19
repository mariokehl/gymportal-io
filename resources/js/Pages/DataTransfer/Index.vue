<template>
  <AppLayout title="Import/Export">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Datenimport/-export
      </h2>
    </template>

    <div class="py-6">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Warning Banner -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
          <div class="flex">
            <AlertTriangle class="w-5 h-5 text-yellow-400 mr-3 flex-shrink-0 mt-0.5" />
            <div>
              <h3 class="text-sm font-medium text-yellow-800">Wichtige Hinweise</h3>
              <ul class="mt-2 text-sm text-yellow-700 list-disc list-inside space-y-1">
                <li v-for="item in sensitiveDataWarning.excluded" :key="item">
                  {{ item }} werden <strong>nicht</strong> exportiert
                </li>
                <li>Der "Ersetzen"-Modus löscht alle bestehenden Daten unwiderruflich</li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Export Section -->
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
          <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
              <Download class="w-5 h-5 mr-2 text-indigo-500" />
              Daten exportieren
            </h3>

            <p class="text-sm text-gray-600 mb-4">
              Exportieren Sie alle Daten Ihrer Organisation als JSON-Datei.
              Dies umfasst Mitglieder, Verträge, Kurse, Zahlungen und Einstellungen.
            </p>

            <!-- Export Statistics Preview -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
              <div class="bg-gray-50 p-3 rounded-lg">
                <p class="text-sm text-gray-500">Mitglieder</p>
                <p class="text-xl font-semibold">{{ exportStats.members_count }}</p>
              </div>
              <div class="bg-gray-50 p-3 rounded-lg">
                <p class="text-sm text-gray-500">Verträge</p>
                <p class="text-xl font-semibold">{{ exportStats.memberships_count }}</p>
              </div>
              <div class="bg-gray-50 p-3 rounded-lg">
                <p class="text-sm text-gray-500">Zahlungen</p>
                <p class="text-xl font-semibold">{{ exportStats.payments_count }}</p>
              </div>
              <div class="bg-gray-50 p-3 rounded-lg">
                <p class="text-sm text-gray-500">Kurse</p>
                <p class="text-xl font-semibold">{{ exportStats.courses_count }}</p>
              </div>
            </div>

            <button
              @click="handleExport"
              :disabled="isExporting"
              class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50"
            >
              <Download class="w-4 h-4 mr-2" />
              {{ isExporting ? 'Wird exportiert...' : 'Als JSON exportieren' }}
            </button>
          </div>
        </div>

        <!-- Import Section -->
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
          <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
              <Upload class="w-5 h-5 mr-2 text-green-500" />
              Daten importieren
            </h3>

            <!-- File Upload Area -->
            <div
              class="mt-2 flex justify-center rounded-lg border-2 border-dashed px-6 py-10 transition-colors"
              :class="isDragging ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300'"
              @drop.prevent="handleDrop"
              @dragover.prevent="isDragging = true"
              @dragleave.prevent="isDragging = false"
            >
              <div class="text-center">
                <FileJson class="mx-auto h-12 w-12 text-gray-400" />
                <div class="mt-4 flex text-sm text-gray-600">
                  <label class="relative cursor-pointer rounded-md font-semibold text-indigo-600 hover:text-indigo-500">
                    <span>Datei auswählen</span>
                    <input
                      type="file"
                      class="sr-only"
                      accept=".json"
                      @change="handleFileSelect"
                      ref="fileInput"
                    >
                  </label>
                  <p class="pl-1">oder per Drag & Drop</p>
                </div>
                <p class="text-xs text-gray-500 mt-1">JSON-Datei bis zu 100 MB</p>
              </div>
            </div>

            <!-- Selected File Info -->
            <div v-if="selectedFile" class="mt-4 p-4 bg-gray-50 rounded-lg">
              <div class="flex items-center justify-between">
                <div class="flex items-center">
                  <FileJson class="w-8 h-8 text-indigo-500 mr-3" />
                  <div>
                    <p class="font-medium text-gray-900">{{ selectedFile.name }}</p>
                    <p class="text-sm text-gray-500">{{ formatFileSize(selectedFile.size) }}</p>
                  </div>
                </div>
                <button
                  @click="clearFile"
                  class="text-gray-400 hover:text-gray-600"
                >
                  <X class="w-5 h-5" />
                </button>
              </div>
            </div>

            <!-- Validation in progress -->
            <div v-if="isValidating" class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
              <div class="flex items-center">
                <Loader2 class="w-5 h-5 text-blue-500 mr-3 animate-spin" />
                <span class="text-sm text-blue-700">Datei wird validiert...</span>
              </div>
            </div>

            <!-- Validation Results -->
            <div v-if="validationResult && !isValidating" class="mt-4">
              <div v-if="validationResult.valid" class="bg-green-50 border border-green-200 rounded-lg p-4">
                <h4 class="text-sm font-medium text-green-800 flex items-center">
                  <CheckCircle class="w-4 h-4 mr-2" />
                  Datei ist gültig
                </h4>
                <div class="mt-2 grid grid-cols-2 md:grid-cols-4 gap-2 text-sm text-green-700">
                  <span>Mitglieder: {{ validationResult.stats.members }}</span>
                  <span>Verträge: {{ validationResult.stats.memberships }}</span>
                  <span>Zahlungen: {{ validationResult.stats.payments }}</span>
                  <span>Kurse: {{ validationResult.stats.courses }}</span>
                </div>
                <div v-if="validationResult.warnings && validationResult.warnings.length > 0" class="mt-3 text-sm text-yellow-700">
                  <p class="font-medium">Warnungen:</p>
                  <ul class="list-disc list-inside">
                    <li v-for="warning in validationResult.warnings" :key="warning">{{ warning }}</li>
                  </ul>
                </div>
              </div>
              <div v-else class="bg-red-50 border border-red-200 rounded-lg p-4">
                <h4 class="text-sm font-medium text-red-800 flex items-center">
                  <XCircle class="w-4 h-4 mr-2" />
                  Validierungsfehler
                </h4>
                <ul class="mt-2 text-sm text-red-700 list-disc list-inside">
                  <li v-for="error in validationResult.errors" :key="error">{{ error }}</li>
                </ul>
              </div>
            </div>

            <!-- Import Mode Selection -->
            <div v-if="validationResult?.valid" class="mt-6">
              <label class="block text-sm font-medium text-gray-700 mb-3">Import-Modus</label>
              <div class="space-y-3">
                <label
                  class="flex items-start p-4 border rounded-lg cursor-pointer transition-colors"
                  :class="importMode === 'append' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300'"
                >
                  <input
                    type="radio"
                    v-model="importMode"
                    value="append"
                    class="mt-1 mr-3 text-indigo-600 focus:ring-indigo-500"
                  >
                  <div>
                    <span class="font-medium text-gray-900">Hinzufügen (Append)</span>
                    <p class="text-sm text-gray-500">
                      Neue Datensätze werden hinzugefügt. Bestehende Daten bleiben erhalten.
                      Duplikate (gleiche E-Mail) werden übersprungen.
                    </p>
                  </div>
                </label>
                <label
                  class="flex items-start p-4 border rounded-lg cursor-pointer transition-colors"
                  :class="importMode === 'replace' ? 'border-red-500 bg-red-50' : 'border-gray-200 hover:border-gray-300'"
                >
                  <input
                    type="radio"
                    v-model="importMode"
                    value="replace"
                    class="mt-1 mr-3 text-red-600 focus:ring-red-500"
                  >
                  <div>
                    <span class="font-medium text-gray-900">Ersetzen (Replace)</span>
                    <p class="text-sm text-red-600">
                      ACHTUNG: Alle bestehenden Daten werden gelöscht und durch die importierten Daten ersetzt!
                    </p>
                  </div>
                </label>
              </div>

              <!-- Replace Confirmation -->
              <div v-if="importMode === 'replace'" class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                <label class="flex items-start">
                  <input
                    type="checkbox"
                    v-model="confirmReplace"
                    class="mt-1 mr-3 text-red-600 focus:ring-red-500 rounded"
                  >
                  <span class="text-sm text-red-800">
                    Ich verstehe, dass alle bestehenden Daten ({{ exportStats.members_count }} Mitglieder,
                    {{ exportStats.payments_count }} Zahlungen, etc.) unwiderruflich gelöscht werden.
                  </span>
                </label>
              </div>

              <!-- Import Button -->
              <button
                @click="handleImport"
                :disabled="!canImport"
                class="mt-6 w-full inline-flex items-center justify-center px-4 py-3 bg-green-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <Upload class="w-4 h-4 mr-2" />
                {{ isImporting ? 'Wird importiert...' : 'Daten importieren' }}
              </button>
            </div>

            <!-- Import Success -->
            <div v-if="importResult && importResult.success" class="mt-4 p-4 bg-green-50 border border-green-200 rounded-lg">
              <h4 class="text-sm font-medium text-green-800 flex items-center">
                <CheckCircle class="w-4 h-4 mr-2" />
                Import erfolgreich!
              </h4>
              <div class="mt-2 grid grid-cols-2 md:grid-cols-3 gap-2 text-sm text-green-700">
                <span>Mitglieder: {{ importResult.stats.members }}</span>
                <span>Verträge: {{ importResult.stats.membership_plans }}</span>
                <span>Mitgliedschaften: {{ importResult.stats.memberships }}</span>
                <span>Zahlungen: {{ importResult.stats.payments }}</span>
                <span>Kurse: {{ importResult.stats.courses }}</span>
                <span>Check-Ins: {{ importResult.stats.check_ins }}</span>
              </div>
            </div>

            <!-- Import Error -->
            <div v-if="importResult && !importResult.success" class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
              <h4 class="text-sm font-medium text-red-800 flex items-center">
                <XCircle class="w-4 h-4 mr-2" />
                Import fehlgeschlagen
              </h4>
              <p class="mt-1 text-sm text-red-700">{{ importResult.error }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import {
  Download,
  Upload,
  FileJson,
  AlertTriangle,
  CheckCircle,
  XCircle,
  X,
  Loader2
} from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const props = defineProps({
  currentGym: Object,
  exportStats: Object,
  sensitiveDataWarning: Object,
})

// State
const isDragging = ref(false)
const selectedFile = ref(null)
const validationResult = ref(null)
const importMode = ref('append')
const confirmReplace = ref(false)
const isExporting = ref(false)
const isImporting = ref(false)
const isValidating = ref(false)
const importResult = ref(null)
const fileInput = ref(null)

// Computed
const canImport = computed(() => {
  if (!validationResult.value?.valid) return false
  if (isImporting.value) return false
  if (importMode.value === 'replace' && !confirmReplace.value) return false
  return true
})

// Methods
const handleExport = async () => {
  isExporting.value = true
  try {
    const response = await axios.get(route('data-transfer.export'), {
      responseType: 'blob'
    })

    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    const filename = `gym_export_${props.currentGym.slug}_${new Date().toISOString().slice(0, 10)}.json`
    link.setAttribute('download', filename)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (error) {
    console.error('Export failed:', error)
    alert('Export fehlgeschlagen: ' + (error.response?.data?.message || error.message))
  } finally {
    isExporting.value = false
  }
}

const handleFileSelect = (event) => {
  const file = event.target.files[0]
  if (file) processFile(file)
}

const handleDrop = (event) => {
  isDragging.value = false
  const file = event.dataTransfer.files[0]
  if (file) processFile(file)
}

const processFile = async (file) => {
  if (!file.name.endsWith('.json')) {
    alert('Bitte wählen Sie eine JSON-Datei aus.')
    return
  }

  selectedFile.value = file
  validationResult.value = null
  confirmReplace.value = false
  importResult.value = null
  isValidating.value = true

  // Validate file
  const formData = new FormData()
  formData.append('file', file)

  try {
    const response = await axios.post(route('data-transfer.validate'), formData)
    validationResult.value = response.data
  } catch (error) {
    validationResult.value = {
      valid: false,
      errors: [error.response?.data?.error || 'Validierung fehlgeschlagen']
    }
  } finally {
    isValidating.value = false
  }
}

const clearFile = () => {
  selectedFile.value = null
  validationResult.value = null
  confirmReplace.value = false
  importResult.value = null
  if (fileInput.value) fileInput.value.value = ''
}

const handleImport = async () => {
  if (!canImport.value) return

  isImporting.value = true
  importResult.value = null

  const formData = new FormData()
  formData.append('file', selectedFile.value)
  formData.append('mode', importMode.value)
  formData.append('confirm_replace', confirmReplace.value ? '1' : '0')

  try {
    const response = await axios.post(route('data-transfer.import'), formData)
    importResult.value = response.data

    if (response.data.success) {
      // Clear the form after successful import
      selectedFile.value = null
      validationResult.value = null
      confirmReplace.value = false
      if (fileInput.value) fileInput.value.value = ''

      // Reload the page to update statistics
      setTimeout(() => {
        router.reload()
      }, 2000)
    }
  } catch (error) {
    importResult.value = {
      success: false,
      error: error.response?.data?.error || error.message
    }
  } finally {
    isImporting.value = false
  }
}

const formatFileSize = (bytes) => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}
</script>
