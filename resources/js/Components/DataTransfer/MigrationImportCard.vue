<template>
  <div class="bg-surface shadow-sm rounded-lg overflow-hidden">
    <div class="px-4 py-5 sm:p-6">
      <h3 class="text-lg font-semibold text-ink mb-4 flex items-center">
        <FolderArchive class="w-5 h-5 mr-2 text-purple-500" />
        Migration aus einer anderen Studioverwaltung
      </h3>

      <!-- Format Description -->
      <div class="bg-info-subtle border border-info-line rounded-lg p-4 mb-4">
        <div class="flex">
          <Info class="w-5 h-5 text-info mr-3 flex-shrink-0 mt-0.5" />
          <div>
            <h4 class="text-sm font-medium text-info-strong">Erwartete Struktur</h4>
            <p class="mt-1 text-sm text-info">
              Übernehmen Sie Ihre Mitgliedsakten aus einer bestehenden Studioverwaltung. Erwartet wird
              je Mitglied ein Ordner mit einer <strong>master_data.xlsx</strong>. Laden Sie entweder
              einzelne Ordner oder alle Akten gesammelt als ZIP-Archiv hoch.
            </p>
            <div class="mt-2 text-sm text-info">
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
            <p class="mt-2 text-sm text-info">
              Die Abrechnung setzt automatisch dort an, wo Ihr bisheriges System aufgehört hat
              (Feld &bdquo;Bezahlt bis&ldquo;) &mdash; es entstehen keine doppelten Abbuchungen.
            </p>
          </div>
        </div>
      </div>

      <!-- Upload Area -->
      <div
        class="mt-2 flex justify-center rounded-lg border-2 border-dashed px-6 py-10 transition-colors"
        :class="archiveIsDragging ? 'border-purple-500 bg-purple-50' : 'border-line-strong'"
        @drop.prevent="handleArchiveDrop"
        @dragover.prevent="archiveIsDragging = true"
        @dragleave.prevent="archiveIsDragging = false"
      >
        <div class="text-center">
          <FolderArchive class="mx-auto h-12 w-12 text-ink-muted" />
          <div class="mt-4 flex flex-wrap justify-center gap-x-1 text-sm text-ink-secondary">
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
          <p class="text-xs text-ink-muted mt-1">ZIP-Archiv per Drag &amp; Drop oder Ordner mit den Mitgliedsakten</p>
          <p class="text-xs text-ink-muted mt-1">Maximal 100 MB pro Upload</p>
        </div>
      </div>

      <!-- Selected Upload Info -->
      <div v-if="archiveFiles.length > 0" class="mt-4 p-4 bg-surface-muted rounded-lg">
        <div class="flex items-center justify-between">
          <div class="flex items-center">
            <FolderArchive class="w-8 h-8 text-purple-500 mr-3" />
            <div>
              <p class="text-sm font-medium text-ink">{{ archiveLabel }}</p>
              <p class="text-xs text-ink-muted">
                {{ formatFileSize(archiveTotalSize) }}
                <span v-if="archiveSkippedFiles > 0">
                  &middot; {{ archiveSkippedFiles }} nicht benötigte Datei(en) werden nicht übertragen
                </span>
              </p>
            </div>
          </div>
          <button @click="clearArchiveSelection" class="text-ink-muted hover:text-ink-secondary">
            <X class="w-5 h-5" />
          </button>
        </div>
      </div>

      <!-- Validation in progress -->
      <div v-if="archiveIsValidating" class="mt-4 flex items-center text-sm text-ink-secondary">
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
          class="bg-success-subtle border border-success-line rounded-lg p-4"
        >
          <h4 class="text-sm font-medium text-success-strong flex items-center">
            <CheckCircle class="w-4 h-4 mr-2" />
            {{ archiveValidationResult.stats.members }} Mitgliedsakte(n) erkannt
          </h4>
          <dl class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
            <div>
              <dt class="text-success">Tarife zugeordnet</dt>
              <dd class="font-medium text-success-strong">{{ archiveValidationResult.stats.plans_matched }}</dd>
            </div>
            <div>
              <dt class="text-success">Tarife neu</dt>
              <dd class="font-medium text-success-strong">{{ archiveValidationResult.stats.plans_new }}</dd>
            </div>
            <div>
              <dt class="text-success">Zusatzpakete</dt>
              <dd class="font-medium text-success-strong">{{ archiveValidationResult.stats.modules }}</dd>
            </div>
            <div>
              <dt class="text-success">SEPA-Mandate</dt>
              <dd class="font-medium text-success-strong">{{ archiveValidationResult.stats.sepa_mandates }}</dd>
            </div>
            <div>
              <dt class="text-success">Guthaben</dt>
              <dd class="font-medium text-success-strong">{{ archiveValidationResult.stats.credit_balances }}</dd>
            </div>
            <div>
              <dt class="text-success">Mitgliedskarten</dt>
              <dd class="font-medium text-success-strong">{{ archiveValidationResult.stats.access_tags }}</dd>
            </div>
          </dl>

          <div v-if="archiveValidationResult.new_plans.length > 0" class="mt-3 text-sm text-success-strong">
            <p class="font-medium">Diese Tarife werden neu angelegt:</p>
            <div class="flex flex-wrap gap-2 mt-1">
              <span
                v-for="name in archiveValidationResult.new_plans"
                :key="name"
                class="inline-flex items-center px-2 py-0.5 rounded bg-success-soft text-success-strong text-xs"
              >{{ name }}</span>
            </div>
          </div>
        </div>

        <div v-else class="bg-danger-subtle border border-danger-line rounded-lg p-4">
          <h4 class="text-sm font-medium text-danger-strong flex items-center">
            <XCircle class="w-4 h-4 mr-2" />
            Archiv konnte nicht gelesen werden
          </h4>
          <ul class="mt-2 text-sm text-danger list-disc list-inside space-y-0.5">
            <li v-for="(error, index) in archiveValidationResult.errors" :key="index">{{ error }}</li>
          </ul>
        </div>

        <!-- Member Preview -->
        <div v-if="archiveValidationResult.members && archiveValidationResult.members.length > 0" class="mt-4 overflow-x-auto">
          <table class="min-w-full divide-y divide-line text-sm">
            <thead class="bg-surface-muted">
              <tr>
                <th class="px-3 py-2 text-left font-medium text-ink-secondary">Mitglied</th>
                <th class="px-3 py-2 text-left font-medium text-ink-secondary">Tarife</th>
                <th class="px-3 py-2 text-left font-medium text-ink-secondary">Zusatzpakete</th>
                <th class="px-3 py-2 text-left font-medium text-ink-secondary">SEPA</th>
                <th class="px-3 py-2 text-left font-medium text-ink-secondary">Guthaben</th>
                <th class="px-3 py-2 text-left font-medium text-ink-secondary">Erste Abrechnung</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line-subtle bg-surface">
              <tr v-for="member in archiveValidationResult.members" :key="member.folder">
                <td class="px-3 py-2 text-ink">{{ member.name }}</td>
                <td class="px-3 py-2 text-ink-secondary">
                  {{ member.plan_name }}
                  <span
                    v-if="!member.plan_matched"
                    class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded bg-warning-soft text-warning-strong text-xs"
                  >neu</span>
                </td>
                <td class="px-3 py-2 text-ink-secondary">{{ member.modules.join(', ') || '&mdash;' }}</td>
                <td class="px-3 py-2">
                  <CheckCircle v-if="member.has_sepa" class="w-4 h-4 text-success" />
                  <XCircle v-else class="w-4 h-4 text-ink-disabled" />
                </td>
                <td class="px-3 py-2 text-ink-secondary">{{ member.credit > 0 ? formatPrice(member.credit) : '&mdash;' }}</td>
                <td class="px-3 py-2 text-ink-secondary">
                  <span
                    v-if="member.membership_ended"
                    class="inline-flex items-center px-1.5 py-0.5 rounded bg-fill text-ink-secondary text-xs"
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
          class="mt-4 bg-warning-subtle border border-warning-line rounded-lg p-4"
        >
          <h4 class="text-sm font-medium text-warning-strong flex items-center">
            <AlertTriangle class="w-4 h-4 mr-2" />
            Hinweise
          </h4>
          <ul class="mt-2 text-sm text-warning list-disc list-inside space-y-0.5">
            <li v-for="(warning, index) in archiveValidationResult.warnings" :key="index">{{ warning }}</li>
          </ul>
        </div>
      </div>

      <!-- Import Configuration -->
      <div v-if="archiveValidationResult && archiveValidationResult.valid && !archiveImportResult" class="mt-6 space-y-4">
        <div>
          <label for="migration-import-card-archive-fallback-start-date" class="block text-sm font-medium text-ink-secondary mb-1">
            Stichtag für Akten ohne &bdquo;Bezahlt bis&ldquo;
          </label>
          <input id="migration-import-card-archive-fallback-start-date"
            v-model="archiveFallbackStartDate"
            type="date"
            class="field block w-full max-w-xs"
          >
          <p class="mt-1 text-xs text-ink-muted">
            Wird nur verwendet, wenn eine Akte kein Enddatum des bereits abgerechneten Zeitraums enthält.
          </p>
        </div>

        <label class="flex items-start">
          <input
            v-model="archiveCreateMissingPlans"
            type="checkbox"
            class="mt-0.5 rounded border-line-strong text-purple-600"
          >
          <span class="ml-2 text-sm text-ink-secondary">
            Fehlende Tarife und Zusatzpakete automatisch anlegen
            <span class="block text-xs text-ink-muted">
              Ohne diese Option werden Mitglieder ohne passenden Tarif übersprungen.
            </span>
          </span>
        </label>

        <!-- Delete existing data toggle -->
        <div>
          <div class="flex items-center justify-between">
            <div>
              <label class="block text-sm font-medium text-ink-secondary">Bestehende Daten vorher löschen</label>
              <p class="text-xs text-ink-muted mt-0.5">
                Alle Mitglieder, Mitgliedschaften, Zahlungen und Zahlungsarten werden unwiderruflich gelöscht.
              </p>
            </div>
            <button
              type="button"
              @click="archiveDeleteExisting = !archiveDeleteExisting; archiveConfirmDelete = false"
              :class="archiveDeleteExisting ? 'bg-danger-solid' : 'bg-fill-strong'"
              class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out"
            >
              <span
                :class="archiveDeleteExisting ? 'translate-x-5' : 'translate-x-0'"
                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-surface shadow ring-0 transition duration-200 ease-in-out"
              />
            </button>
          </div>

          <!-- Delete confirmation -->
          <div v-if="archiveDeleteExisting" class="mt-3 p-4 bg-danger-subtle border border-danger-line rounded-lg">
            <label class="flex items-start">
              <input
                type="checkbox"
                v-model="archiveConfirmDelete"
                class="mt-1 mr-3 text-danger rounded"
              >
              <span class="text-sm text-danger-strong">
                Ich verstehe, dass alle bestehenden Mitglieder ({{ exportStats?.members_count ?? 0 }}),
                Verträge ({{ exportStats?.memberships_count ?? 0 }}), Zahlungen ({{ exportStats?.payments_count ?? 0 }})
                und zugehörige Zahlungsarten unwiderruflich gelöscht werden.
              </span>
            </label>
            <p v-if="archiveValidationResult.stats.existing_members > 0" class="mt-2 text-xs text-danger">
              Bereits vorhandene Mitglieder werden dadurch nicht übersprungen, sondern aus der Akte neu angelegt.
            </p>
          </div>
        </div>

        <button
          @click="startArchiveImport"
          :disabled="!canArchiveImport"
          class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          <Loader2 v-if="archiveIsImporting" class="w-4 h-4 mr-2 animate-spin" />
          <Upload v-else class="w-4 h-4 mr-2" />
          {{ archiveIsImporting ? 'Import läuft …' : 'Mitgliedsakten importieren' }}
        </button>
      </div>

      <!-- Import Success -->
      <div
        v-if="archiveImportResult && archiveImportResult.success"
        class="mt-4 bg-success-subtle border border-success-line rounded-lg p-4"
      >
        <h4 class="text-sm font-medium text-success-strong flex items-center">
          <CheckCircle class="w-4 h-4 mr-2" />
          Import abgeschlossen
        </h4>
        <div v-if="archiveImportResult.stats.deleted && archiveImportResult.stats.deleted.members" class="mt-2 text-sm text-danger">
          Gelöscht: {{ archiveImportResult.stats.deleted.members }} Mitglieder,
          {{ archiveImportResult.stats.deleted.memberships }} Mitgliedschaften,
          {{ archiveImportResult.stats.deleted.payments }} Zahlungen,
          {{ archiveImportResult.stats.deleted.payment_methods }} Zahlungsarten<template v-if="archiveImportResult.stats.deleted.mollie_customers">,
          {{ archiveImportResult.stats.deleted.mollie_customers }} Mollie-Kunden</template>
        </div>
        <dl class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
          <div>
            <dt class="text-success">Mitglieder</dt>
            <dd class="font-medium text-success-strong">{{ archiveImportResult.stats.members_created }}</dd>
          </div>
          <div>
            <dt class="text-success">Verträge</dt>
            <dd class="font-medium text-success-strong">{{ archiveImportResult.stats.memberships_created }}</dd>
          </div>
          <div>
            <dt class="text-success">Tarife angelegt</dt>
            <dd class="font-medium text-success-strong">{{ archiveImportResult.stats.plans_created }}</dd>
          </div>
          <div>
            <dt class="text-success">Zusatzpakete gebucht</dt>
            <dd class="font-medium text-success-strong">{{ archiveImportResult.stats.addons_booked }}</dd>
          </div>
          <div>
            <dt class="text-success">SEPA-Mandate</dt>
            <dd class="font-medium text-success-strong">{{ archiveImportResult.stats.payment_methods_created }}</dd>
          </div>
          <div>
            <dt class="text-success">Guthabenbuchungen</dt>
            <dd class="font-medium text-success-strong">{{ archiveImportResult.stats.credit_entries_created }}</dd>
          </div>
          <div>
            <dt class="text-success">Mitgliedskarten</dt>
            <dd class="font-medium text-success-strong">{{ archiveImportResult.stats.access_configs_created }}</dd>
          </div>
          <div>
            <dt class="text-success">Zahlungen vorgemerkt</dt>
            <dd class="font-medium text-success-strong">{{ archiveImportResult.stats.payments_created }}</dd>
          </div>
          <div v-if="archiveImportResult.stats.skipped > 0">
            <dt class="text-success">Übersprungen</dt>
            <dd class="font-medium text-success-strong">{{ archiveImportResult.stats.skipped }}</dd>
          </div>
        </dl>

        <div v-if="archiveImportResult.stats.errors.length > 0" class="mt-3">
          <p class="text-sm font-medium text-success-strong">Nicht importierte Akten:</p>
          <ul class="mt-1 text-sm text-success list-disc list-inside space-y-0.5">
            <li v-for="(error, index) in archiveImportResult.stats.errors" :key="index">{{ error }}</li>
          </ul>
        </div>
      </div>

      <!-- Import Error -->
      <div
        v-if="archiveImportResult && !archiveImportResult.success"
        class="mt-4 bg-danger-subtle border border-danger-line rounded-lg p-4"
      >
        <h4 class="text-sm font-medium text-danger-strong flex items-center">
          <XCircle class="w-4 h-4 mr-2" />
          Import fehlgeschlagen
        </h4>
        <p class="mt-1 text-sm text-danger">{{ archiveImportResult.error }}</p>
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
} from '@lucide/vue'
import axios from 'axios'

defineProps({
  exportStats: Object,
})

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
const archiveDeleteExisting = ref(false)
const archiveConfirmDelete = ref(false)
const archiveZipInput = ref(null)
const archiveFolderInput = ref(null)
const archiveSkippedFiles = ref(0)
const archiveUploadedCount = ref(0)

const archiveTotalSize = computed(() =>
  archiveFiles.value.reduce((total, file) => total + file.size, 0)
)

const canArchiveImport = computed(() => {
  if (archiveIsImporting.value) return false
  if (!archiveToken.value) return false
  if (archiveDeleteExisting.value && !archiveConfirmDelete.value) return false
  return true
})

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
  archiveDeleteExisting.value = false
  archiveConfirmDelete.value = false

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

// Server-side limit for one staged upload; mirrors
// DataTransferController::ARCHIVE_MAX_TOTAL_SIZE_KB.
const ARCHIVE_MAX_TOTAL_SIZE = 100 * 1024 * 1024

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

  if (archiveTotalSize.value > ARCHIVE_MAX_TOTAL_SIZE) {
    archiveValidationResult.value = {
      valid: false,
      errors: [
        `Die Auswahl ist ${formatFileSize(archiveTotalSize.value)} groß. Pro Upload sind maximal 100 MB möglich. Bitte teilen Sie die Mitgliedsakten auf mehrere Uploads auf.`,
      ],
    }
    archiveFiles.value = []
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
    const data = error.response?.data

    // The chunk upload reports failures as a single "error" string, the
    // validation endpoint as an "errors" array — normalise both for display.
    archiveValidationResult.value = {
      valid: false,
      errors: data?.errors ?? (data?.error ? [data.error] : ['Das Archiv konnte nicht geprüft werden.']),
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
      delete_existing: archiveDeleteExisting.value,
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
