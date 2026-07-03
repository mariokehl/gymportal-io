<template>
    <div class="space-y-6">
        <div class="bg-white shadow-sm rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                    Google-Sheet-Verknüpfung
                </h3>
                <p class="text-sm text-gray-600 mb-6">
                    Spiegelt automatisch täglich um 00:05 Uhr alle Check-Ins des Vortages in ein Google Sheet.
                    Dies ermöglicht einem Dienstleister die Prüfung der Check-In-Zeiten anhand der Kamerabilder.
                </p>

                <!-- Status: aktiv verknüpft -->
                <div
                    v-if="state.configured"
                    class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6"
                >
                    <div class="flex items-start">
                        <CheckCircle2 class="w-5 h-5 text-green-600 mt-0.5 mr-2 flex-shrink-0" />
                        <div class="text-sm text-green-800">
                            <p class="font-medium">Verknüpfung aktiv</p>
                            <p v-if="state.last_synced_at" class="text-green-700 mt-1">
                                Zuletzt synchronisiert: {{ formatDateTime(state.last_synced_at) }}
                            </p>
                            <p v-else class="text-green-700 mt-1">
                                Noch keine Synchronisierung durchgeführt.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Anleitung -->
                <div class="border border-gray-200 rounded-lg mb-6">
                    <button
                        type="button"
                        class="w-full flex items-center justify-between px-4 py-3 text-left"
                        @click="showGuide = !showGuide"
                    >
                        <span class="text-sm font-medium text-gray-700 flex items-center">
                            <HelpCircle class="w-4 h-4 mr-2 text-gray-400" />
                            So richten Sie den Zugang ein
                        </span>
                        <ChevronDown
                            class="w-4 h-4 text-gray-400 transition-transform"
                            :class="{ 'rotate-180': showGuide }"
                        />
                    </button>
                    <div v-if="showGuide" class="px-4 pb-4 text-sm text-gray-600 space-y-2">
                        <ol class="list-decimal list-inside space-y-2">
                            <li>Öffnen Sie die <a href="https://console.cloud.google.com/" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline">Google Cloud Console</a> und legen Sie ein Projekt an.</li>
                            <li>Aktivieren Sie darin die <strong>Google Sheets API</strong>.</li>
                            <li>Erstellen Sie einen <strong>Dienstkonto (Service Account)</strong> und laden Sie dessen <strong>JSON-Schlüssel</strong> herunter.</li>
                            <li>Laden Sie den JSON-Schlüssel unten hoch.</li>
                            <li>Erstellen Sie ein Google Sheet und teilen Sie es mit der angezeigten Dienstkonto-E-Mail-Adresse als <strong>Bearbeiter</strong>.</li>
                            <li>Fügen Sie die Sheet-URL unten ein und speichern Sie.</li>
                        </ol>
                    </div>
                </div>

                <form @submit.prevent="saveSettings" class="space-y-6">
                    <!-- Toggle -->
                    <div class="flex items-start">
                        <div class="flex items-center h-7">
                            <input
                                id="google_sheet_enabled"
                                v-model="form.enabled"
                                type="checkbox"
                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                            />
                        </div>
                        <div class="ml-3">
                            <label for="google_sheet_enabled" class="text-sm font-medium text-gray-700">
                                Google-Sheet-Spiegelung aktivieren
                            </label>
                            <p class="text-xs text-gray-500 mt-1">
                                Wenn aktiviert, werden die Check-Ins des Vortages täglich angehängt.
                            </p>
                        </div>
                    </div>

                    <div v-if="form.enabled" class="ml-7 space-y-4">
                        <!-- JSON-Key Upload -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Service-Account-Schlüssel (JSON)
                            </label>
                            <input
                                ref="fileInput"
                                type="file"
                                accept="application/json,.json"
                                class="block w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                                @change="onFileChange"
                            />
                            <p v-if="state.service_account_email && !selectedFileName" class="mt-1 text-xs text-gray-500">
                                Ein Schlüssel ist bereits hinterlegt. Laden Sie nur bei Bedarf einen neuen hoch.
                            </p>
                            <p v-if="selectedFileName" class="mt-1 text-xs text-gray-500">
                                Ausgewählt: {{ selectedFileName }}
                            </p>
                        </div>

                        <!-- Dienstkonto-E-Mail -->
                        <div v-if="displayedServiceAccountEmail" class="bg-indigo-50 border border-indigo-200 rounded-lg p-3">
                            <p class="text-xs font-medium text-indigo-800 mb-1">
                                Teilen Sie Ihr Sheet mit dieser Adresse (als Bearbeiter):
                            </p>
                            <div class="flex items-center space-x-2">
                                <code class="text-xs text-indigo-900 break-all">{{ displayedServiceAccountEmail }}</code>
                                <button
                                    type="button"
                                    class="text-indigo-600 hover:text-indigo-800 flex-shrink-0"
                                    title="Kopieren"
                                    @click="copyEmail"
                                >
                                    <Copy class="w-4 h-4" />
                                </button>
                            </div>
                            <p v-if="emailCopied" class="text-xs text-green-600 mt-1">Kopiert!</p>
                        </div>

                        <!-- Sheet-URL -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Google-Sheet-URL
                            </label>
                            <input
                                v-model="form.sheet_url"
                                type="url"
                                placeholder="https://docs.google.com/spreadsheets/d/..."
                                class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6"
                            />
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-between gap-3">
                        <div>
                            <button
                                v-if="state.configured"
                                type="button"
                                :disabled="isBusy"
                                class="text-sm text-gray-600 hover:text-red-600 disabled:opacity-50"
                                @click="removeLink"
                            >
                                Verknüpfung entfernen
                            </button>
                        </div>
                        <div class="flex gap-3">
                            <button
                                v-if="state.configured"
                                type="button"
                                :disabled="isBusy"
                                class="bg-white hover:bg-gray-50 text-gray-700 font-medium py-2 px-4 rounded-md border border-gray-300 disabled:opacity-50"
                                @click="runTest"
                                title="Hängt die Check-Ins des Vortages erneut an"
                            >
                                Testlauf jetzt
                            </button>
                            <button
                                type="submit"
                                :disabled="isBusy"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-md disabled:opacity-50"
                            >
                                {{ isSaving ? 'Speichern...' : 'Speichern' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { CheckCircle2, ChevronDown, Copy, HelpCircle } from 'lucide-vue-next'

const props = defineProps({
    googleSheet: {
        type: Object,
        default: () => ({
            enabled: false,
            configured: false,
            sheet_url: null,
            service_account_email: null,
            last_synced_at: null,
        }),
    },
})

const emit = defineEmits(['success', 'error'])

const state = ref({ ...props.googleSheet })

const form = ref({
    enabled: props.googleSheet.enabled,
    sheet_url: props.googleSheet.sheet_url ?? '',
})

const fileInput = ref(null)
const selectedFile = ref(null)
const selectedFileName = ref('')
const fileServiceAccountEmail = ref(null)
const showGuide = ref(false)
const isSaving = ref(false)
const isBusy = ref(false)
const emailCopied = ref(false)

// Prefer the email read from a freshly selected file, else the stored one.
const displayedServiceAccountEmail = computed(
    () => fileServiceAccountEmail.value || state.value.service_account_email,
)

const onFileChange = async (event) => {
    const file = event.target.files?.[0]
    selectedFile.value = file ?? null
    selectedFileName.value = file?.name ?? ''
    fileServiceAccountEmail.value = null

    if (!file) {
        return
    }

    // Read the client_email locally so the user immediately sees which address
    // to share the sheet with, before saving.
    try {
        const text = await file.text()
        const parsed = JSON.parse(text)
        fileServiceAccountEmail.value = parsed.client_email ?? null
    } catch (e) {
        emit('error', 'Die ausgewählte Datei ist keine gültige JSON-Datei.')
    }
}

const copyEmail = async () => {
    if (!displayedServiceAccountEmail.value) {
        return
    }
    try {
        await navigator.clipboard.writeText(displayedServiceAccountEmail.value)
        emailCopied.value = true
        setTimeout(() => (emailCopied.value = false), 2000)
    } catch (e) {
        // Clipboard access can be denied; silently ignore.
    }
}

const formatDateTime = (value) => {
    if (!value) {
        return ''
    }
    return new Date(value).toLocaleString('de-DE')
}

const saveSettings = async () => {
    isSaving.value = true
    isBusy.value = true

    try {
        const data = new FormData()
        data.append('enabled', form.value.enabled ? '1' : '0')
        data.append('sheet_url', form.value.sheet_url ?? '')
        if (selectedFile.value) {
            data.append('credentials_file', selectedFile.value)
        }

        const response = await axios.post(
            route('access-control.google-sheet-settings.update'),
            data,
            { headers: { 'Content-Type': 'multipart/form-data' } },
        )

        if (response.data.googleSheet) {
            state.value = response.data.googleSheet
            form.value.sheet_url = response.data.googleSheet.sheet_url ?? ''
            form.value.enabled = response.data.googleSheet.enabled
        }
        resetFileSelection()
        emit('success', response.data.message || 'Einstellungen wurden gespeichert.')
    } catch (error) {
        emit('error', error.response?.data?.message || 'Fehler beim Speichern der Einstellungen.')
    } finally {
        isSaving.value = false
        isBusy.value = false
    }
}

const removeLink = async () => {
    isBusy.value = true
    try {
        const response = await axios.delete(route('access-control.google-sheet-settings.destroy'))
        state.value = response.data.googleSheet
        form.value.enabled = false
        form.value.sheet_url = ''
        resetFileSelection()
        emit('success', response.data.message || 'Verknüpfung wurde entfernt.')
    } catch (error) {
        emit('error', error.response?.data?.message || 'Fehler beim Entfernen der Verknüpfung.')
    } finally {
        isBusy.value = false
    }
}

const runTest = async () => {
    isBusy.value = true
    try {
        const response = await axios.post(route('access-control.google-sheet-settings.test'))
        emit('success', response.data.message || 'Testlauf gestartet.')
    } catch (error) {
        emit('error', error.response?.data?.message || 'Fehler beim Starten des Testlaufs.')
    } finally {
        isBusy.value = false
    }
}

const resetFileSelection = () => {
    selectedFile.value = null
    selectedFileName.value = ''
    fileServiceAccountEmail.value = null
    if (fileInput.value) {
        fileInput.value.value = ''
    }
}
</script>
