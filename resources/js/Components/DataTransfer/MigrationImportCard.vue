<template>
  <div class="bg-white shadow-sm rounded-lg overflow-hidden">
    <div class="px-4 py-5 sm:p-6">
      <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
        <FolderArchive class="w-5 h-5 mr-2 text-purple-500" />
        Migration aus einer anderen Studioverwaltung
      </h3>

      <!-- Format Description -->
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
        <div class="flex">
          <Info class="w-5 h-5 text-blue-400 mr-3 flex-shrink-0 mt-0.5" />
          <div>
            <h4 class="text-sm font-medium text-blue-800">Erwartete Struktur</h4>
            <p class="mt-1 text-sm text-blue-700">
              Übernehmen Sie Ihre Mitgliedsakten aus einer bestehenden Studioverwaltung. Erwartet wird
              je Mitglied ein Ordner mit einer <strong>master_data.xlsx</strong>. Laden Sie entweder
              einzelne Ordner oder alle Akten gesammelt als ZIP-Archiv hoch.
            </p>
            <div class="mt-2 text-sm text-blue-700">
              <p class="font-medium">Übernommen werden:</p>
              <ul class="list-disc list-inside mt-1 space-y-0.5">
                <li>Stammdaten inkl. gesetzlicher Vertreter bei Minderjährigen</li>
                <li>Tarif, Laufzeit, Kündigungsfrist und Verlängerung &mdash; fehlende Tarife werden angelegt</li>
                <li>Zusatzpakete wie eine Getränke-Flatrate als wiederkehrende Zusatzleistung</li>
                <li>SEPA-Mandat inkl. Mandatsreferenz &mdash; Ihre Mitglieder müssen nicht neu unterschreiben</li>
                <li>Guthaben und Verzehrguthaben als Guthabenbuchung</li>
                <li>Mitgliedskarten (NFC), damit der Check-in weiterhin funktioniert</li>
              </ul>
            </div>
            <p class="mt-2 text-sm text-blue-700">
              Die Abrechnung setzt automatisch dort an, wo Ihr bisheriges System aufgehört hat
              (Feld &bdquo;Bezahlt bis&ldquo;) &mdash; es entstehen keine doppelten Abbuchungen.
            </p>
          </div>
        </div>
      </div>

      <!-- Upload Area -->
      <div
        class="mt-2 flex justify-center rounded-lg border-2 border-dashed px-6 py-10 transition-colors"
        :class="archiveIsDragging ? 'border-purple-500 bg-purple-50' : 'border-gray-300'"
        @drop.prevent="handleArchiveDrop"
        @dragover.prevent="archiveIsDragging = true"
        @dragleave.prevent="archiveIsDragging = false"
      >
        <div class="text-center">
          <FolderArchive class="mx-auto h-12 w-12 text-gray-400" />
          <div class="mt-4 flex flex-wrap justify-center gap-x-1 text-sm text-gray-600">
            <label class="relative cursor-pointer rounded-md font-semibold text-purple-600 hover:text-purple-500">
              <span>ZIP-Archiv auswählen</span>
              <input
                type="file"
                class="sr-only"
                accept=".zip"
                @change="handleArchiveZipSelect"
                ref="archiveZipInput"
              >
            </label>
            <p>oder</p>
            <label class="relative cursor-pointer rounded-md font-semibold text-purple-600 hover:text-purple-500">
              <span>Ordner auswählen</span>
              <input
                type="file"
                class="sr-only"
                webkitdirectory
                directory
                multiple
                @change="handleArchiveFolderSelect"
                ref="archiveFolderInput"
              >
            </label>
          </div>
          <p class="text-xs text-gray-500 mt-1">ZIP-Archiv per Drag &amp; Drop oder Ordner mit den Mitgliedsakten</p>
        </div>
      </div>

      <!-- Selected Upload Info -->
      <div v-if="archiveFiles.length > 0" class="mt-4 p-4 bg-gray-50 rounded-lg">
        <div class="flex items-center justify-between">
          <div class="flex items-center">
            <FolderArchive class="w-8 h-8 text-purple-500 mr-3" />
            <div>
              <p class="text-sm font-medium text-gray-900">{{ archiveLabel }}</p>
              <p class="text-xs text-gray-500">
                {{ formatFileSize(archiveTotalSize) }}
                <span v-if="archiveSkippedFiles > 0">
                  &middot; {{ archiveSkippedFiles }} nicht benötigte Datei(en) werden nicht übertragen
                </span>
              </p>
            </div>
          </div>
          <button @click="clearArchiveSelection" class="text-gray-400 hover:text-gray-500">
            <X class="w-5 h-5" />
          </button>
        </div>
      </div>

      <!-- Validation in progress -->
      <div v-if="archiveIsValidating" class="mt-4 flex items-center text-sm text-gray-600">
        <Loader2 class="w-4 h-4 mr-2 animate-spin" />
        <span v-if="archiveUploadedCount < archiveFiles.length">
          Dateien werden übertragen &hellip; ({{ archiveUploadedCount }}/{{ archiveFiles.length }})
        </span>
        <span v-else>Archiv wird geprüft &hellip;</span>
      </div>

      <!-- Validation Results -->
      <div v-if="archiveValidationResult && !archiveIsValidating" class="mt-4">
        <div
          v-if="archiveValidationResult.valid"
          class="bg-green-50 border border-green-200 rounded-lg p-4"
        >
          <h4 class="text-sm font-medium text-green-800 flex items-center">
            <CheckCircle class="w-4 h-4 mr-2" />
            {{ archiveValidationResult.stats.members }} Mitgliedsakte(n) erkannt
          </h4>
          <dl class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
            <div>
              <dt class="text-green-700">Tarife zugeordnet</dt>
              <dd class="font-medium text-green-900">{{ archiveValidationResult.stats.plans_matched }}</dd>
            </div>
            <div>
              <dt class="text-green-700">Tarife neu</dt>
              <dd class="font-medium text-green-900">{{ archiveValidationResult.stats.plans_new }}</dd>
            </div>
            <div>
              <dt class="text-green-700">Zusatzpakete</dt>
              <dd class="font-medium text-green-900">{{ archiveValidationResult.stats.modules }}</dd>
            </div>
            <div>
              <dt class="text-green-700">SEPA-Mandate</dt>
              <dd class="font-medium text-green-900">{{ archiveValidationResult.stats.sepa_mandates }}</dd>
            </div>
            <div>
              <dt class="text-green-700">Guthaben</dt>
              <dd class="font-medium text-green-900">{{ archiveValidationResult.stats.credit_balances }}</dd>
            </div>
            <div>
              <dt class="text-green-700">Mitgliedskarten</dt>
              <dd class="font-medium text-green-900">{{ archiveValidationResult.stats.access_tags }}</dd>
            </div>
          </dl>

          <div v-if="archiveValidationResult.new_plans.length > 0" class="mt-3 text-sm text-green-800">
            <p class="font-medium">Diese Tarife werden neu angelegt:</p>
            <div class="flex flex-wrap gap-2 mt-1">
              <span
                v-for="name in archiveValidationResult.new_plans"
                :key="name"
                class="inline-flex items-center px-2 py-0.5 rounded bg-green-100 text-green-800 text-xs"
              >{{ name }}</span>
            </div>
          </div>
        </div>

        <div v-else class="bg-red-50 border border-red-200 rounded-lg p-4">
          <h4 class="text-sm font-medium text-red-800 flex items-center">
            <XCircle class="w-4 h-4 mr-2" />
            Archiv konnte nicht gelesen werden
          </h4>
          <ul class="mt-2 text-sm text-red-700 list-disc list-inside space-y-0.5">
            <li v-for="(error, index) in archiveValidationResult.errors" :key="index">{{ error }}</li>
          </ul>
        </div>

        <!-- Member Preview -->
        <div v-if="archiveValidationResult.members && archiveValidationResult.members.length > 0" class="mt-4 overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-3 py-2 text-left font-medium text-gray-700">Mitglied</th>
                <th class="px-3 py-2 text-left font-medium text-gray-700">Tarif</th>
                <th class="px-3 py-2 text-left font-medium text-gray-700">Zusatzpakete</th>
                <th class="px-3 py-2 text-left font-medium text-gray-700">SEPA</th>
                <th class="px-3 py-2 text-left font-medium text-gray-700">Guthaben</th>
                <th class="px-3 py-2 text-left font-medium text-gray-700">Erste Abrechnung</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
              <tr v-for="member in archiveValidationResult.members" :key="member.folder">
                <td class="px-3 py-2 text-gray-900">{{ member.name }}</td>
                <td class="px-3 py-2 text-gray-700">
                  {{ member.plan_name }}
                  <span
                    v-if="!member.plan_matched"
                    class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 text-xs"
                  >neu</span>
                </td>
                <td class="px-3 py-2 text-gray-700">{{ member.modules.join(', ') || '&mdash;' }}</td>
                <td class="px-3 py-2">
                  <CheckCircle v-if="member.has_sepa" class="w-4 h-4 text-green-500" />
                  <XCircle v-else class="w-4 h-4 text-gray-300" />
                </td>
                <td class="px-3 py-2 text-gray-700">{{ member.credit > 0 ? formatPrice(member.credit) : '&mdash;' }}</td>
                <td class="px-3 py-2 text-gray-700">
                  <span
                    v-if="member.membership_ended"
                    class="inline-flex items-center px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 text-xs"
                  >beendet</span>
                  <template v-else>{{ formatDate(member.next_charge) }}</template>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Warnings -->
        <div
          v-if="archiveValidationResult.warnings && archiveValidationResult.warnings.length > 0"
          class="mt-4 bg-yellow-50 border border-yellow-200 rounded-lg p-4"
        >
          <h4 class="text-sm font-medium text-yellow-800 flex items-center">
            <AlertTriangle class="w-4 h-4 mr-2" />
            Hinweise
          </h4>
          <ul class="mt-2 text-sm text-yellow-700 list-disc list-inside space-y-0.5">
            <li v-for="(warning, index) in archiveValidationResult.warnings" :key="index">{{ warning }}</li>
          </ul>
        </div>
      </div>

      <!-- Import Configuration -->
      <div v-if="archiveValidationResult && archiveValidationResult.valid && !archiveImportResult" class="mt-6 space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Stichtag für Akten ohne &bdquo;Bezahlt bis&ldquo;
          </label>
          <input
            v-model="archiveFallbackStartDate"
            type="date"
            class="block w-full max-w-xs rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm"
          >
          <p class="mt-1 text-xs text-gray-500">
            Wird nur verwendet, wenn eine Akte kein Enddatum des bereits abgerechneten Zeitraums enthält.
          </p>
        </div>

        <label class="flex items-start">
          <input
            v-model="archiveCreateMissingPlans"
            type="checkbox"
            class="mt-0.5 rounded border-gray-300 text-purple-600 focus:ring-purple-500"
          >
          <span class="ml-2 text-sm text-gray-700">
            Fehlende Tarife und Zusatzpakete automatisch anlegen
            <span class="block text-xs text-gray-500">
              Ohne diese Option werden Mitglieder ohne passenden Tarif übersprungen.
            </span>
          </span>
        </label>

        <button
          @click="startArchiveImport"
          :disabled="archiveIsImporting"
          class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 disabled:opacity-50 transition-colors"
        >
          <Loader2 v-if="archiveIsImporting" class="w-4 h-4 mr-2 animate-spin" />
          <Upload v-else class="w-4 h-4 mr-2" />
          {{ archiveIsImporting ? 'Import läuft …' : 'Mitgliedsakten importieren' }}
        </button>
      </div>

      <!-- Import Success -->
      <div
        v-if="archiveImportResult && archiveImportResult.success"
        class="mt-4 bg-green-50 border border-green-200 rounded-lg p-4"
      >
        <h4 class="text-sm font-medium text-green-800 flex items-center">
          <CheckCircle class="w-4 h-4 mr-2" />
          Import abgeschlossen
        </h4>
        <dl class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
          <div>
            <dt class="text-green-700">Mitglieder</dt>
            <dd class="font-medium text-green-900">{{ archiveImportResult.stats.members_created }}</dd>
          </div>
          <div>
            <dt class="text-green-700">Verträge</dt>
            <dd class="font-medium text-green-900">{{ archiveImportResult.stats.memberships_created }}</dd>
          </div>
          <div>
            <dt class="text-green-700">Tarife angelegt</dt>
            <dd class="font-medium text-green-900">{{ archiveImportResult.stats.plans_created }}</dd>
          </div>
          <div>
            <dt class="text-green-700">Zusatzpakete gebucht</dt>
            <dd class="font-medium text-green-900">{{ archiveImportResult.stats.addons_booked }}</dd>
          </div>
          <div>
            <dt class="text-green-700">SEPA-Mandate</dt>
            <dd class="font-medium text-green-900">{{ archiveImportResult.stats.payment_methods_created }}</dd>
          </div>
          <div>
            <dt class="text-green-700">Guthabenbuchungen</dt>
            <dd class="font-medium text-green-900">{{ archiveImportResult.stats.credit_entries_created }}</dd>
          </div>
          <div>
            <dt class="text-green-700">Mitgliedskarten</dt>
            <dd class="font-medium text-green-900">{{ archiveImportResult.stats.access_configs_created }}</dd>
          </div>
          <div>
            <dt class="text-green-700">Zahlungen vorgemerkt</dt>
            <dd class="font-medium text-green-900">{{ archiveImportResult.stats.payments_created }}</dd>
          </div>
          <div v-if="archiveImportResult.stats.skipped > 0">
            <dt class="text-green-700">Übersprungen</dt>
            <dd class="font-medium text-green-900">{{ archiveImportResult.stats.skipped }}</dd>
          </div>
        </dl>

        <div v-if="archiveImportResult.stats.errors.length > 0" class="mt-3">
          <p class="text-sm font-medium text-green-800">Nicht importierte Akten:</p>
          <ul class="mt-1 text-sm text-green-700 list-disc list-inside space-y-0.5">
            <li v-for="(error, index) in archiveImportResult.stats.errors" :key="index">{{ error }}</li>
          </ul>
        </div>
      </div>

      <!-- Import Error -->
      <div
        v-if="archiveImportResult && !archiveImportResult.success"
        class="mt-4 bg-red-50 border border-red-200 rounded-lg p-4"
      >
        <h4 class="text-sm font-medium text-red-800 flex items-center">
          <XCircle class="w-4 h-4 mr-2" />
          Import fehlgeschlagen
        </h4>
        <p class="mt-1 text-sm text-red-700">{{ archiveImportResult.error }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import {
  Upload,
  AlertTriangle,
  CheckCircle,
  XCircle,
  X,
  Loader2,
  Info,
  FolderArchive
} from 'lucide-vue-next'
import axios from 'axios'

// Fallback for archive records without a "Bezahlt bis" date: 1st of next month.
const nextMonth = new Date()
nextMonth.setMonth(nextMonth.getMonth() + 1)
nextMonth.setDate(1)
const archiveFallbackStartDate = ref(nextMonth.toISOString().slice(0, 10))

const archiveIsDragging = ref(false)
const archiveFiles = ref([])
const archiveIsZip = ref(false)
const archiveValidationResult = ref(null)
const archiveIsValidating = ref(false)
const archiveIsImporting = ref(false)
const archiveImportResult = ref(null)
const archiveToken = ref(null)
const archiveCreateMissingPlans = ref(true)
const archiveZipInput = ref(null)
const archiveFolderInput = ref(null)
const archiveSkippedFiles = ref(0)
const archiveUploadedCount = ref(0)

const archiveTotalSize = computed(() =>
  archiveFiles.value.reduce((total, file) => total + file.size, 0)
)

const archiveLabel = computed(() => {
  if (archiveFiles.value.length === 0) return ''
  if (archiveIsZip.value) return archiveFiles.value[0].name

  // Count the distinct top-level folders of a directory upload.
  const folders = new Set(
    archiveFiles.value
      .map((file) => (file.webkitRelativePath || file.name).split('/')[0])
      .filter(Boolean)
  )

  return `${archiveFiles.value.length} Datei(en) aus ${folders.size} Ordner(n)`
})

const clearArchiveSelection = () => {
  archiveFiles.value = []
  archiveIsZip.value = false
  archiveValidationResult.value = null
  archiveImportResult.value = null
  archiveToken.value = null
  archiveSkippedFiles.value = 0
  archiveUploadedCount.value = 0

  if (archiveZipInput.value) archiveZipInput.value.value = ''
  if (archiveFolderInput.value) archiveFolderInput.value.value = ''
}

const handleArchiveZipSelect = (event) => {
  const file = event.target.files?.[0]
  if (!file) return

  selectArchive([file], true)
}

const handleArchiveFolderSelect = (event) => {
  const files = Array.from(event.target.files ?? [])
  if (files.length === 0) return

  selectArchive(files, false)
}

const handleArchiveDrop = (event) => {
  archiveIsDragging.value = false

  const file = event.dataTransfer.files?.[0]
  if (!file) return

  if (!file.name.toLowerCase().endsWith('.zip')) {
    archiveValidationResult.value = {
      valid: false,
      errors: ['Per Drag & Drop wird nur ein ZIP-Archiv unterstützt. Ordner bitte über "Ordner auswählen" hochladen.'],
    }
    return
  }

  selectArchive([file], true)
}

// Only these files are read by the importer; everything else in a member
// folder (documents, messages, check-in history) is never uploaded.
const ARCHIVE_RELEVANT_FILES = [
  'master_data.xlsx',
  'access_identifications.xlsx',
  'account_data.xlsx',
  'benefit.xlsx',
  'customer.json',
  'customer_extended.json',
  'contact.json',
  'contracts.json',
  'bank_accounts.json',
  'liable_person.json',
]

// PHP caps the number of files per request (max_file_uploads, commonly 20),
// so a folder upload is sent in chunks that stay below that limit.
const ARCHIVE_CHUNK_SIZE = 15

const selectArchive = (files, isZip) => {
  const relevant = isZip
    ? files
    : files.filter((file) => ARCHIVE_RELEVANT_FILES.includes(file.name))

  archiveFiles.value = relevant
  archiveIsZip.value = isZip
  archiveValidationResult.value = null
  archiveImportResult.value = null
  archiveToken.value = null
  archiveSkippedFiles.value = files.length - relevant.length

  if (relevant.length === 0) {
    archiveValidationResult.value = {
      valid: false,
      errors: ['Im ausgewählten Ordner wurde keine master_data.xlsx gefunden. Bitte wählen Sie den Ordner mit den Mitgliedsakten aus.'],
    }
    return
  }

  validateArchive()
}

/**
 * Upload the selected files in chunks and return the staging token.
 */
const uploadArchiveFiles = async () => {
  let token = null
  archiveUploadedCount.value = 0

  for (let index = 0; index < archiveFiles.value.length; index += ARCHIVE_CHUNK_SIZE) {
    const chunk = archiveFiles.value.slice(index, index + ARCHIVE_CHUNK_SIZE)
    const formData = new FormData()

    chunk.forEach((file) => {
      // The relative path is required to rebuild one folder per member.
      formData.append('files[]', file, file.webkitRelativePath || file.name)
    })

    if (token) {
      formData.append('token', token)
    }

    const response = await axios.post(route('data-transfer.upload-archive-chunk'), formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    token = response.data.token
    archiveUploadedCount.value += chunk.length
  }

  return token
}

const validateArchive = async () => {
  archiveIsValidating.value = true
  archiveValidationResult.value = null

  try {
    const token = await uploadArchiveFiles()

    const response = await axios.post(route('data-transfer.validate-archive'), { token })

    archiveValidationResult.value = response.data
    archiveToken.value = response.data.token ?? null
  } catch (error) {
    archiveValidationResult.value = error.response?.data ?? {
      valid: false,
      errors: ['Das Archiv konnte nicht geprüft werden.'],
    }
  } finally {
    archiveIsValidating.value = false
  }
}

const startArchiveImport = async () => {
  archiveIsImporting.value = true
  archiveImportResult.value = null

  try {
    const response = await axios.post(route('data-transfer.import-archive'), {
      token: archiveToken.value,
      fallback_start_date: archiveFallbackStartDate.value,
      create_missing_plans: archiveCreateMissingPlans.value,
    })

    archiveImportResult.value = response.data
  } catch (error) {
    archiveImportResult.value = error.response?.data ?? {
      success: false,
      error: 'Der Import konnte nicht durchgeführt werden.',
    }
  } finally {
    archiveIsImporting.value = false
    // The staged upload is consumed by the import and cannot be reused.
    archiveToken.value = null
  }
}

const formatDate = (value) => {
  if (!value) return '—'

  return new Date(value).toLocaleDateString('de-DE')
}

const formatFileSize = (bytes) => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

const formatPrice = (price) => {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(price)
}
</script>
