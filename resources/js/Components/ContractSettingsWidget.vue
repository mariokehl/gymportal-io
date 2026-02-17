<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="bg-white shadow-sm rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center mb-4">
                    <component :is="FileSignature" class="w-6 h-6 text-indigo-600 mr-3" />
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        Vertragseinstellungen
                    </h3>
                </div>
                <p class="text-sm text-gray-500">
                    Konfiguriere die Vertragsunterzeichnung und die Vertragsvorlage für automatisch generierte Online-Verträge.
                </p>

                <!-- Success/Error Messages -->
                <div v-if="successMessage"
                    class="mt-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
                    {{ successMessage }}
                </div>
                <div v-if="errorMessage"
                    class="mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                    {{ errorMessage }}
                </div>
            </div>
        </div>

        <!-- Allgemeine Vertragseinstellungen -->
        <div class="bg-white shadow-sm rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h4 class="text-lg font-medium text-gray-900 mb-2">Allgemeine Einstellungen</h4>
                <p class="text-sm text-gray-600 mb-6">
                    Konfiguriere, wie neue Mitgliedschaftsverträge erstellt werden.
                </p>

                <div class="space-y-4">
                    <!-- Toggle: Verträge zum 1. des Monats starten -->
                    <div class="flex items-start">
                        <div class="flex items-center h-7">
                            <input
                                id="contracts_start_first_of_month"
                                v-model="contractsStartFirstOfMonth"
                                type="checkbox"
                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded" />
                        </div>
                        <div class="ml-3">
                            <label for="contracts_start_first_of_month" class="text-sm font-medium text-gray-700">
                                Verträge immer zum 1. des Monats starten
                            </label>
                            <p class="text-xs text-gray-500 mt-1">
                                Wenn aktiviert, starten zahlungspflichtige Verträge immer zum 1. des Folgemonats.
                                Für die Zeit vom Anlagedatum bis Monatsende wird automatisch eine kostenlose Mitgliedschaft erstellt.
                            </p>
                        </div>
                    </div>

                    <!-- Textfeld: Name der Gratis-Mitgliedschaft (nur sichtbar wenn Toggle aktiv) -->
                    <div v-if="contractsStartFirstOfMonth" class="ml-7">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Name der Gratis-Mitgliedschaft
                        </label>
                        <input
                            v-model="freeTrialMembershipName"
                            type="text"
                            placeholder="Gratis-Testzeitraum"
                            class="block w-full max-w-md rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6" />
                        <p class="mt-1 text-xs text-gray-500">
                            Dieser Name wird für die automatisch erstellte Gratis-Mitgliedschaft verwendet.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Signing Method -->
        <div class="bg-white shadow-sm rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h4 class="text-lg font-medium text-gray-900 mb-4">Unterzeichnungsmethode</h4>

                <div class="space-y-3">
                    <label class="flex items-center p-4 bg-gray-50 rounded-lg cursor-pointer"
                        :class="{ 'ring-2 ring-indigo-500 bg-indigo-50': signingMethod === 'offline' }">
                        <input
                            type="radio"
                            v-model="signingMethod"
                            value="offline"
                            class="rounded-full border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        >
                        <div class="ml-3">
                            <span class="text-sm font-medium text-gray-900">Offline unterzeichnet</span>
                            <p class="text-xs text-gray-500">Der Vertrag wird vor Ort im Studio unterzeichnet.</p>
                        </div>
                    </label>

                    <label class="flex items-center p-4 bg-gray-50 rounded-lg cursor-pointer"
                        :class="{ 'ring-2 ring-indigo-500 bg-indigo-50': signingMethod === 'online' }">
                        <input
                            type="radio"
                            v-model="signingMethod"
                            value="online"
                            class="rounded-full border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        >
                        <div class="ml-3">
                            <span class="text-sm font-medium text-gray-900">Automatisch generierter Online-Vertrag per E-Mail</span>
                            <p class="text-xs text-gray-500">Der Vertrag wird automatisch generiert und dem Mitglied per E-Mail zugesendet.</p>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        <!-- Online Contract Template (only visible when signing method is 'online') -->
        <div v-if="signingMethod === 'online'" class="bg-white shadow-sm rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h4 class="text-lg font-medium text-gray-900 mb-6">Vertragsvorlage</h4>

                <div class="space-y-6">
                    <!-- Subject -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Vertrags-Betreff</label>
                        <input
                            v-model="contractTemplateSubject"
                            type="text"
                            class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6"
                            placeholder="z.B. Mitgliedschaftsvertrag"
                        />
                    </div>

                    <!-- Placeholder Helper -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h5 class="text-sm font-medium text-blue-800 mb-3 flex items-center">
                            <component :is="Info" class="w-4 h-4 mr-2" />
                            Verfügbare Platzhalter
                        </h5>
                        <p class="text-xs text-blue-600 mb-3">Klicken Sie auf einen Platzhalter, um ihn an der Cursorposition im Textfeld einzufügen.</p>

                        <!-- Member Placeholders -->
                        <div class="mb-3">
                            <h6 class="text-xs font-semibold text-blue-900 mb-1.5">Mitglied</h6>
                            <div class="flex flex-wrap gap-1.5">
                                <span v-for="placeholder in placeholderGroups.member" :key="placeholder.key"
                                    @click="insertPlaceholder(placeholder.key)"
                                    class="bg-white border border-blue-300 rounded px-2 py-1 text-xs cursor-pointer hover:bg-blue-100 transition-colors"
                                    :title="placeholder.description">
                                    {{ placeholder.key }}
                                </span>
                            </div>
                        </div>

                        <!-- Gym Placeholders -->
                        <div class="mb-3">
                            <h6 class="text-xs font-semibold text-blue-900 mb-1.5">Fitnessstudio</h6>
                            <div class="flex flex-wrap gap-1.5">
                                <span v-for="placeholder in placeholderGroups.gym" :key="placeholder.key"
                                    @click="insertPlaceholder(placeholder.key)"
                                    class="bg-white border border-blue-300 rounded px-2 py-1 text-xs cursor-pointer hover:bg-blue-100 transition-colors"
                                    :title="placeholder.description">
                                    {{ placeholder.key }}
                                </span>
                            </div>
                        </div>

                        <!-- Contract Placeholders -->
                        <div class="mb-3">
                            <h6 class="text-xs font-semibold text-blue-900 mb-1.5">Vertrag</h6>
                            <div class="flex flex-wrap gap-1.5">
                                <span v-for="placeholder in placeholderGroups.contract" :key="placeholder.key"
                                    @click="insertPlaceholder(placeholder.key)"
                                    class="bg-white border border-blue-300 rounded px-2 py-1 text-xs cursor-pointer hover:bg-blue-100 transition-colors"
                                    :title="placeholder.description">
                                    {{ placeholder.key }}
                                </span>
                            </div>
                        </div>

                        <!-- System Placeholders -->
                        <div>
                            <h6 class="text-xs font-semibold text-blue-900 mb-1.5">System</h6>
                            <div class="flex flex-wrap gap-1.5">
                                <span v-for="placeholder in placeholderGroups.system" :key="placeholder.key"
                                    @click="insertPlaceholder(placeholder.key)"
                                    class="bg-white border border-blue-300 rounded px-2 py-1 text-xs cursor-pointer hover:bg-blue-100 transition-colors"
                                    :title="placeholder.description">
                                    {{ placeholder.key }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Body Textarea -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Vertragsinhalt (HTML)</label>
                        <textarea
                            ref="bodyTextarea"
                            v-model="contractTemplateBody"
                            rows="20"
                            class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 font-mono"
                            placeholder="<p>Vertragsinhalt hier eingeben...</p>"
                            spellcheck="false"
                        ></textarea>
                        <p class="mt-1 text-xs text-gray-500">
                            Verwenden Sie HTML-Tags für die Formatierung des Vertragstextes.
                        </p>
                    </div>

                    <!-- Preview Button -->
                    <div>
                        <button
                            type="button"
                            @click="previewTemplate"
                            :disabled="isPreviewing"
                            class="flex items-center bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-md disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            <component :is="Eye" class="w-4 h-4 mr-2" />
                            {{ isPreviewing ? 'Vorschau wird geladen...' : 'Vorschau anzeigen' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Save Button -->
        <div class="bg-white shadow-sm rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <button
                    type="button"
                    @click="saveSettings"
                    :disabled="isSaving"
                    class="w-full flex items-center justify-center bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-md disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                    <component :is="Save" class="w-4 h-4 mr-2" />
                    {{ isSaving ? 'Speichert...' : 'Einstellungen speichern' }}
                </button>
            </div>
        </div>

        <!-- Preview Modal -->
        <div v-if="showPreviewModal" class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border border-gray-50 w-11/12 md:w-3/4 lg:w-2/3 shadow-lg rounded-md bg-white">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Vertragsvorschau</h3>
                    <button @click="showPreviewModal = false" class="text-gray-400 hover:text-gray-600">
                        <span class="text-2xl">&times;</span>
                    </button>
                </div>

                <div class="border border-gray-200 rounded-lg overflow-hidden">
                    <div class="bg-gray-50 px-4 py-2 border-b border-gray-200">
                        <div class="text-sm text-gray-600">
                            <strong>Betreff:</strong> {{ contractTemplateSubject }}
                        </div>
                    </div>
                    <div class="p-6 bg-white prose max-w-none" v-html="previewHtml"></div>
                </div>

                <div class="flex justify-end mt-4">
                    <button @click="showPreviewModal = false"
                        class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-md">
                        Schließen
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, nextTick } from 'vue'
import axios from 'axios'
import { FileSignature, Save, Eye, Info } from 'lucide-vue-next'

// Props
const props = defineProps({
    currentGym: Object
})

// Reactive data
const isSaving = ref(false)
const isPreviewing = ref(false)
const successMessage = ref('')
const errorMessage = ref('')
const showPreviewModal = ref(false)
const previewHtml = ref('')
const bodyTextarea = ref(null)

// Form data - load from currentGym.contract_settings with defaults
const contractSettings = props.currentGym?.contract_settings || {}
const signingMethod = ref(contractSettings.signing_method || 'offline')
const contractTemplateSubject = ref(contractSettings.contract_template_subject || 'Mitgliedschaftsvertrag')
const defaultContractTemplate = `<div class="section">
    <h2>Vertragsparteien</h2>

    <strong>[Fitnessstudio-Name]</strong><br>
    [Adresse]<br>

    <br>

    <strong>[Vorname] [Nachname]</strong><br>
    [Strasse], [PLZ] [Ort]<br>
    Geburtsdatum: [Geburtsdatum]<br>
</div>

<hr>

<div class="section">
    <h2>1. Vertragsabschluss</h2>
    <p>
        Dieser Vertrag wurde am [Vertragsdatum] um [Uhrzeit] Uhr
        im elektronischen Geschäftsverkehr über die Website [Website] abgeschlossen.
    </p>
</div>

<div class="section">
    <h2>2. Vertragsgegenstand</h2>
    <p>Tarif: <strong>[Tarif-Name]</strong></p>
</div>

<div class="section">
    <h2>3. Vertragsbeginn und Laufzeit</h2>
    <p>
        Vertragsbeginn: [Startdatum]<br>
        Erstlaufzeit: [Vertragslaufzeit]<br>
        Kündigungsfrist: [Kuendigungsfrist]<br>
    </p>

    <p>
        Der Vertrag verlängert sich nach Ablauf der Erstlaufzeit auf unbestimmte Zeit
        und ist monatlich kündbar.
    </p>
</div>

<div class="section">
    <h2>4. Beiträge und Zahlungsbedingungen</h2>

    <table>
        <tr>
            <td>Monatsbeitrag:</td>
            <td>[Monatsbeitrag] €</td>
        </tr>
        <tr>
            <td>Aufnahmegebühr:</td>
            <td>[Aufnahmegebuehr]</td>
        </tr>
        <tr>
            <td>Abrechnungszyklus:</td>
            <td>[Abrechnungszyklus]</td>
        </tr>
    </table>
</div>

<div class="section">
    <h2>5. Widerrufsrecht</h2>

    <p>
        Sie haben das Recht, binnen vierzehn Tagen ohne Angabe von Gründen diesen Vertrag zu widerrufen.
    </p>
</div>

<div class="section">
    <h2>6. Kündigung</h2>
    <p>
        Die Kündigung bedarf der Textform (z. B. E-Mail).
    </p>
</div>

<div class="section small">
    <p>
        Stand: [Datum]
    </p>
</div>`

const contractTemplateBody = ref(contractSettings.contract_template_body || defaultContractTemplate)

// Gym-level contract fields
const contractsStartFirstOfMonth = ref(props.currentGym?.contracts_start_first_of_month || false)
const freeTrialMembershipName = ref(props.currentGym?.free_trial_membership_name || 'Gratis-Testzeitraum')

// Placeholder groups
const placeholderGroups = {
    member: [
        { key: '[Vorname]', description: 'Vorname des Mitglieds' },
        { key: '[Nachname]', description: 'Nachname des Mitglieds' },
        { key: '[Anrede]', description: 'Anrede (Herr/Frau)' },
        { key: '[E-Mail]', description: 'E-Mail-Adresse' },
        { key: '[Mitgliedsnummer]', description: 'Mitgliedsnummer' },
        { key: '[Geburtsdatum]', description: 'Geburtsdatum des Mitglieds' },
        { key: '[Strasse]', description: 'Straße und Hausnummer' },
        { key: '[PLZ]', description: 'Postleitzahl des Mitglieds' },
        { key: '[Ort]', description: 'Wohnort des Mitglieds' },
    ],
    gym: [
        { key: '[Fitnessstudio-Name]', description: 'Name des Fitnessstudios' },
        { key: '[Adresse]', description: 'Vollständige Adresse des Studios' },
        { key: '[Telefon]', description: 'Telefonnummer' },
        { key: '[Website]', description: 'Website-URL' },
    ],
    contract: [
        { key: '[Vertragslaufzeit]', description: 'Laufzeit des Vertrags in Monaten' },
        { key: '[Monatsbeitrag]', description: 'Monatlicher Beitrag' },
        { key: '[Startdatum]', description: 'Vertragsbeginn' },
        { key: '[Enddatum]', description: 'Vertragsende' },
        { key: '[Vertragsnummer]', description: 'Eindeutige Vertragsnummer' },
        { key: '[Vertragsdatum]', description: 'Datum der Vertragserstellung' },
        { key: '[Tarif-Name]', description: 'Name des gewählten Tarifs' },
        { key: '[Abrechnungszyklus]', description: 'Abrechnungszyklus' },
        { key: '[Kuendigungsfrist]', description: 'Kündigungsfrist' },
        { key: '[Aufnahmegebuehr]', description: 'Aufnahmegebühr' },
        { key: '[Einrichtungsgebuehr]', description: 'Einmalige Einrichtungsgebühr' },
    ],
    system: [
        { key: '[Datum]', description: 'Aktuelles Datum' },
        { key: '[Uhrzeit]', description: 'Aktuelle Uhrzeit' },
    ]
}

// Methods
const insertPlaceholder = (placeholder) => {
    if (!bodyTextarea.value) return

    const textarea = bodyTextarea.value
    const start = textarea.selectionStart
    const end = textarea.selectionEnd
    const text = contractTemplateBody.value || ''

    // Insert placeholder at cursor position
    contractTemplateBody.value = text.substring(0, start) + placeholder + text.substring(end)

    // Move cursor after inserted placeholder
    nextTick(() => {
        textarea.focus()
        textarea.setSelectionRange(start + placeholder.length, start + placeholder.length)
    })
}

const saveSettings = async () => {
    isSaving.value = true
    successMessage.value = ''
    errorMessage.value = ''

    try {
        await axios.put('/settings/contract-settings', {
            signing_method: signingMethod.value,
            contract_template_subject: contractTemplateSubject.value,
            contract_template_body: contractTemplateBody.value,
            contracts_start_first_of_month: contractsStartFirstOfMonth.value,
            free_trial_membership_name: freeTrialMembershipName.value,
        })

        successMessage.value = 'Vertragseinstellungen erfolgreich gespeichert!'
        setTimeout(() => successMessage.value = '', 3000)
    } catch (error) {
        console.error('Fehler beim Speichern:', error)
        errorMessage.value = error.response?.data?.message || 'Fehler beim Speichern der Vertragseinstellungen'
        setTimeout(() => errorMessage.value = '', 5000)
    } finally {
        isSaving.value = false
    }
}

const previewTemplate = async () => {
    isPreviewing.value = true
    errorMessage.value = ''

    try {
        const response = await axios.post('/settings/contract-template/preview', {
            contract_template_body: contractTemplateBody.value,
            contract_template_subject: contractTemplateSubject.value,
        })

        previewHtml.value = response.data.preview
        showPreviewModal.value = true
    } catch (error) {
        console.error('Fehler bei der Vorschau:', error)
        errorMessage.value = error.response?.data?.message || 'Fehler beim Generieren der Vorschau'
        setTimeout(() => errorMessage.value = '', 5000)
    } finally {
        isPreviewing.value = false
    }
}
</script>

<style scoped>
.prose :deep(p) {
    margin-bottom: 1em;
}

.prose :deep(h1) {
    font-size: 2em;
    font-weight: 700;
    margin: 1em 0 0.5em 0;
}

.prose :deep(h2) {
    font-size: 1.5em;
    font-weight: 600;
    margin: 1.5em 0 0.75em 0;
}

.prose :deep(h3) {
    font-size: 1.25em;
    font-weight: 600;
    margin: 1.25em 0 0.5em 0;
}

.prose :deep(ul), .prose :deep(ol) {
    margin: 1em 0;
    padding-left: 2em;
}

.prose :deep(ul) {
    list-style-type: disc;
}

.prose :deep(ol) {
    list-style-type: decimal;
}

.prose :deep(li) {
    margin-bottom: 0.5em;
}

.prose :deep(a) {
    color: #2563eb;
    text-decoration: underline;
}

.prose :deep(strong) {
    font-weight: 600;
}

.prose :deep(em) {
    font-style: italic;
}

.prose :deep(table) {
    width: 100%;
    border-collapse: collapse;
    margin: 1em 0;
}

.prose :deep(th), .prose :deep(td) {
    border: 1px solid #d1d5db;
    padding: 0.5em 0.75em;
    text-align: left;
}

.prose :deep(th) {
    background-color: #f9fafb;
    font-weight: 600;
}
</style>
