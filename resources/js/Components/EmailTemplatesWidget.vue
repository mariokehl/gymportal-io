<template>
    <div class="space-y-6">
        <!-- Header mit Template-Auswahl -->
        <div class="bg-surface shadow-sm rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg leading-6 font-semibold text-ink">
                        E-Mail-Vorlagen verwalten
                    </h3>
                    <button @click="showCreateTemplate = true"
                        class="bg-brand hover:bg-brand-strong text-white font-medium py-2 px-4 rounded-md flex items-center">
                        <component :is="Plus" class="w-4 h-4 mr-2" />
                        Neue Vorlage
                    </button>
                </div>

                <!-- Template Selection -->
                <div class="mb-6">
                    <label for="email-templates-widget-selected-template-id" class="block text-sm font-medium text-ink-secondary mb-2">Vorlage auswählen</label>
                    <select id="email-templates-widget-selected-template-id" v-model="selectedTemplateId" @change="loadSelectedTemplate"
                        class="field w-full p-2">
                        <option value="">Bitte wählen Sie eine Vorlage aus</option>
                        <option v-for="template in templates" :key="template.id" :value="template.id">
                            {{ template.name }} ({{ template.type }})
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Template Editor (nur wenn Template ausgewählt) -->
        <div v-if="currentTemplate" class="bg-surface shadow-sm rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex justify-between items-center mb-6">
                    <h4 class="text-lg font-semibold text-ink">{{ currentTemplate.name }} bearbeiten</h4>
                    <div class="flex space-x-2">
                        <button @click="toggleEditMode"
                            :class="editMode === 'preview' ? 'bg-brand text-white' : 'bg-fill text-ink-secondary'"
                            class="hover:bg-brand-strong hover:text-white font-medium py-2 px-4 rounded-md transition-colors">
                            <component :is="Eye" class="w-4 h-4 mr-2 inline" />
                            {{ editMode === 'preview' ? 'Bearbeiten' : 'Vorschau' }}
                        </button>
                        <button @click="previewTemplate"
                            class="bg-fill hover:bg-fill-strong text-ink-secondary font-medium py-2 px-4 rounded-md">
                            <component :is="Monitor" class="w-4 h-4 mr-2 inline" />
                            Vollbild-Vorschau
                        </button>
                        <button @click="saveTemplate" :disabled="isSaving"
                            class="bg-brand hover:bg-brand-strong text-white font-medium py-2 px-4 rounded-md disabled:opacity-50">
                            <component :is="Save" class="w-4 h-4 mr-2 inline" />
                            {{ isSaving ? 'Speichert...' : 'Speichern' }}
                        </button>
                    </div>
                </div>

                <form @submit.prevent="saveTemplate" class="space-y-6">
                    <!-- Template Name -->
                    <div>
                        <label for="email-templates-widget-current-template-name" class="block text-sm font-medium text-ink-secondary mb-1">Vorlagen-Name</label>
                        <input id="email-templates-widget-current-template-name" v-model="currentTemplate.name" type="text"
                            class="field block w-full px-3 py-1.5"
                            required />
                    </div>

                    <!-- Template Type -->
                    <div>
                        <label for="email-templates-widget-current-template-type" class="block text-sm font-medium text-ink-secondary mb-1">Template-Typ</label>
                        <select id="email-templates-widget-current-template-type" v-model="currentTemplate.type"
                            class="field w-full p-2">
                            <option value="welcome">Willkommen</option>
                            <!-- Not wired to any mailer yet, hidden until a send flow uses these types -->
                            <!-- <option value="confirmation">Bestätigung</option> -->
                            <!-- <option value="reminder">Erinnerung</option> -->
                            <option value="cancellation">Kündigung</option>
                            <!-- <option value="invoice">Rechnung</option> -->
                            <option value="payment_failed">Zahlung fehlgeschlagen</option>
                            <option value="login_code">Anmeldecode</option>
                            <option value="member_app_access">App-Zugangslink</option>
                            <!-- <option value="general">Allgemein</option> -->
                        </select>
                    </div>

                    <!-- Subject -->
                    <div>
                        <label for="email-templates-widget-current-template-subject" class="block text-sm font-medium text-ink-secondary mb-1">E-Mail-Betreff</label>
                        <input id="email-templates-widget-current-template-subject" v-model="currentTemplate.subject" type="text"
                            class="field block w-full px-3 py-1.5"
                            placeholder="z.B. Herzlich willkommen, [Vorname]!"
                            required />
                    </div>

                    <!-- Placeholder Helper -->
                    <div class="bg-info-subtle border border-info-line rounded-lg p-4">
                        <h5 class="text-sm font-medium text-info-strong mb-2">Verfügbare Platzhalter:</h5>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-xs">
                            <span v-for="placeholder in availablePlaceholders" :key="placeholder.key"
                                @click="insertPlaceholder(placeholder.key)"
                                class="bg-surface border border-info rounded px-2 py-1 cursor-pointer hover:bg-info-soft transition-colors">
                                {{ placeholder.key }} - {{ placeholder.description }}
                                <Tooltip v-if="placeholder.hint" :text="placeholder.hint" max-width="none" class="align-middle ml-0.5">
                                    <Info class="w-3.5 h-3.5 text-info" :aria-label="placeholder.hint" />
                                </Tooltip>
                            </span>
                        </div>
                        <p class="text-xs text-info mt-2">Klicken Sie auf einen Platzhalter, um ihn in den Editor einzufügen.</p>
                    </div>

                    <!-- Editor Section -->
                    <div>
                        <label class="block text-sm font-medium text-ink-secondary mb-1">E-Mail-Inhalt</label>

                        <!-- Edit/Preview Toggle -->
                        <div class="mb-4">
                            <div class="flex border-b border-line">
                                <button type="button"
                                    @click="editMode = 'edit'"
                                    :class="editMode === 'edit' ? 'border-brand text-brand' : 'border-transparent text-ink-muted hover:text-ink'"
                                    class="px-4 py-2 text-sm font-medium border-b-2">
                                    HTML Editor
                                </button>
                                <button type="button"
                                    @click="editMode = 'preview'"
                                    :class="editMode === 'preview' ? 'border-brand text-brand' : 'border-transparent text-ink-muted hover:text-ink'"
                                    class="px-4 py-2 text-sm font-medium border-b-2">
                                    Live-Vorschau
                                </button>
                            </div>
                        </div>

                        <!-- Editor Content -->
                        <div class="border border-line-strong rounded-lg overflow-hidden">
                            <!-- Formatting Toolbar (nur im Edit-Modus) -->
                            <div v-if="editMode === 'edit'" class="bg-surface-muted border-b border-line-strong p-2 flex flex-wrap gap-1">
                                <button type="button" @click="insertHtmlTag('strong')"
                                    class="p-2 hover:bg-fill-strong rounded border border-line-strong bg-surface text-sm font-bold">
                                    B
                                </button>
                                <button type="button" @click="insertHtmlTag('em')"
                                    class="p-2 hover:bg-fill-strong rounded border border-line-strong bg-surface text-sm italic">
                                    I
                                </button>
                                <button type="button" @click="insertHtmlTag('u')"
                                    class="p-2 hover:bg-fill-strong rounded border border-line-strong bg-surface text-sm underline">
                                    U
                                </button>
                                <div class="w-px bg-line-strong mx-1"></div>
                                <button type="button" @click="insertHtmlTag('h2')"
                                    class="p-2 hover:bg-fill-strong rounded border border-line-strong bg-surface text-sm font-bold">
                                    H2
                                </button>
                                <button type="button" @click="insertHtmlTag('h3')"
                                    class="p-2 hover:bg-fill-strong rounded border border-line-strong bg-surface text-sm font-bold">
                                    H3
                                </button>
                                <div class="w-px bg-line-strong mx-1"></div>
                                <button type="button" @click="insertList('ul')"
                                    class="p-2 hover:bg-fill-strong rounded border border-line-strong bg-surface text-sm">
                                    <component :is="List" class="w-4 h-4" />
                                </button>
                                <button type="button" @click="insertList('ol')"
                                    class="p-2 hover:bg-fill-strong rounded border border-line-strong bg-surface text-sm">
                                    <component :is="ListOrdered" class="w-4 h-4" />
                                </button>
                                <div class="w-px bg-line-strong mx-1"></div>
                                <button type="button" @click="insertLink"
                                    class="p-2 hover:bg-fill-strong rounded border border-line-strong bg-surface text-sm">
                                    <component :is="Link" class="w-4 h-4" />
                                </button>
                                <div class="w-px bg-line-strong mx-1"></div>
                                <button type="button" @click="insertParagraph"
                                    class="p-2 hover:bg-fill-strong rounded border border-line-strong bg-surface text-xs">
                                    <p>¶</p>
                                </button>
                            </div>

                            <!-- HTML Editor -->
                            <div v-if="editMode === 'edit'" class="relative">
                                <textarea
                                    ref="htmlEditor"
                                    v-model="currentTemplate.body"
                                    @keydown="handleKeyDown"
                                    @input="updateContent"
                                    class="w-full h-96 p-4 font-mono text-sm border-none resize-none"
                                    placeholder="<p>Beginnen Sie hier mit dem Schreiben...</p>"
                                    spellcheck="false">
                                </textarea>
                                <div class="absolute bottom-2 right-2 text-xs text-ink-muted bg-surface px-2 py-1 rounded shadow">
                                    HTML-Modus
                                </div>
                            </div>

                            <!-- Live Preview -->
                            <div v-else-if="editMode === 'preview'" class="min-h-96 p-4 bg-surface">
                                <div class="prose max-w-none"
                                     v-html="replacePlaceholders(currentTemplate.body)">
                                </div>
                                <div class="mt-4 text-xs text-ink-muted border-t pt-2">
                                    Vorschau-Modus - Platzhalter werden durch Beispieldaten ersetzt
                                </div>
                            </div>
                        </div>

                        <div class="mt-2 text-xs text-ink-muted space-y-1">
                            <p><strong>Tipp:</strong> Verwenden Sie HTML-Tags für Formatierung (z.B. &lt;strong&gt;fett&lt;/strong&gt;, &lt;em&gt;kursiv&lt;/em&gt;)</p>
                            <p><strong>Tastenkürzel:</strong> Ctrl+B = Fett, Ctrl+I = Kursiv, Ctrl+Enter = Neuer Absatz</p>
                        </div>
                    </div>

                    <!-- Template Settings -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" v-model="currentTemplate.is_active"
                                    class="rounded border-line-strong text-brand" />
                                <span class="ml-2 text-sm text-ink-secondary">Template aktiv</span>
                            </label>
                        </div>
                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" v-model="currentTemplate.is_default"
                                    class="rounded border-line-strong text-brand" />
                                <span class="ml-2 text-sm text-ink-secondary">Als Standard verwenden</span>
                            </label>
                        </div>
                    </div>

                    <!-- Anhänge -->
                    <div class="mt-6 pt-6 border-t border-line">
                        <h5 class="text-sm font-medium text-ink mb-3">Anhänge (z.B. AGB, Widerrufsbelehrung)</h5>
                        <EmailAttachmentsDropzone
                            :template-id="currentTemplate.id"
                            :attachments="currentTemplate.file_attachments || []"
                            @attachments-updated="reloadCurrentTemplate"
                        />
                    </div>
                </form>
            </div>
        </div>

        <!-- Template List -->
        <div class="bg-surface shadow-sm rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h4 class="text-lg font-semibold text-ink mb-4">Alle Vorlagen</h4>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line">
                        <thead class="bg-surface-muted">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">
                                    Name
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">
                                    Typ
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">
                                    Erstellt am
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider">
                                    Aktionen
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-surface divide-y divide-line">
                            <tr v-for="template in templates" :key="template.id">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-ink">{{ template.name }}</div>
                                    <div class="text-sm text-ink-muted truncate max-w-xs">{{ template.subject }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                                        :class="getTypeColor(template.type)">
                                        {{ getTypeLabel(template.type) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <span :class="template.is_active ? 'text-success' : 'text-ink-muted'"
                                            class="text-xs font-medium">
                                            {{ template.is_active ? 'Aktiv' : 'Inaktiv' }}
                                        </span>
                                        <div class="ml-2 w-2 h-2 rounded-full"
                                            :class="template.is_active ? 'bg-success-solid' : 'bg-line-strong'"></div>
                                        <span v-if="template.is_default"
                                            class="ml-2 inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-info-soft text-info-strong">
                                            Standard
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-ink-muted">
                                    {{ formatDate(template.created_at) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                    <button @click="editTemplate(template)"
                                        class="text-brand hover:text-brand-strong">
                                        <component :is="Edit" class="w-4 h-4" />
                                    </button>
                                    <button @click="duplicateTemplate(template)"
                                        class="text-ink-secondary hover:text-ink">
                                        <component :is="Copy" class="w-4 h-4" />
                                    </button>
                                    <button @click="deleteTemplate(template)"
                                        class="text-danger hover:text-danger-strong">
                                        <component :is="Trash2" class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Preview Modal -->
        <div v-if="showPreview" class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border border-line-subtle w-11/12 md:w-3/4 lg:w-2/3 shadow-lg rounded-md bg-surface">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-ink">E-Mail-Vollbild-Vorschau</h3>
                    <button @click="showPreview = false" class="text-ink-muted hover:text-ink">
                        <component :is="X" class="w-5 h-5" />
                    </button>
                </div>

                <div class="border border-line rounded-lg overflow-hidden">
                    <div class="bg-surface-muted px-4 py-2 border-b border-line">
                        <div class="text-sm text-ink-secondary">
                            <strong>Betreff:</strong> {{ replacePlaceholders(currentTemplate.subject) }}
                        </div>
                    </div>
                    <div class="p-4 bg-surface prose max-w-none" v-html="replacePlaceholders(currentTemplate.body)">
                    </div>
                </div>

                <div class="flex justify-end mt-4">
                    <button @click="showPreview = false"
                        class="bg-fill-strong hover:bg-line-strong text-ink-secondary font-medium py-2 px-4 rounded-md">
                        Schließen
                    </button>
                </div>
            </div>
        </div>

        <!-- Create Template Modal -->
        <div v-if="showCreateTemplate" class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border border-line-subtle w-96 shadow-lg rounded-md bg-surface">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-ink">Neue E-Mail-Vorlage</h3>
                    <button @click="showCreateTemplate = false" class="text-ink-muted hover:text-ink">
                        <component :is="X" class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="createTemplate" class="space-y-4">
                    <div>
                        <label for="email-templates-widget-new-template-name" class="block text-sm font-medium text-ink-secondary mb-1">Vorlagen-Name</label>
                        <input id="email-templates-widget-new-template-name" v-model="newTemplate.name" type="text"
                            class="field block w-full px-3 py-1.5"
                            required />
                    </div>

                    <div>
                        <label for="email-templates-widget-new-template-type" class="block text-sm font-medium text-ink-secondary mb-1">Template-Typ</label>
                        <select id="email-templates-widget-new-template-type" v-model="newTemplate.type"
                            class="field w-full p-2">
                            <option value="welcome">Willkommen</option>
                            <!-- Not wired to any mailer yet, hidden until a send flow uses these types -->
                            <!-- <option value="confirmation">Bestätigung</option> -->
                            <!-- <option value="reminder">Erinnerung</option> -->
                            <option value="cancellation">Kündigung</option>
                            <!-- <option value="invoice">Rechnung</option> -->
                            <option value="payment_failed">Zahlung fehlgeschlagen</option>
                            <option value="login_code">Anmeldecode</option>
                            <option value="member_app_access">App-Zugangslink</option>
                            <!-- <option value="general">Allgemein</option> -->
                        </select>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" @click="showCreateTemplate = false"
                            class="px-4 py-2 text-sm font-medium text-ink-secondary bg-fill-strong border border-line-strong rounded-md hover:bg-line-strong">
                            Abbrechen
                        </button>
                        <button type="submit" :disabled="isCreating"
                            class="px-4 py-2 text-sm font-medium text-white bg-brand border border-transparent rounded-md hover:bg-brand-strong disabled:opacity-50">
                            {{ isCreating ? 'Erstellt...' : 'Erstellen' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue'
import {
    Plus, Eye, Save, Edit, Copy, Trash2, X, List, ListOrdered, Link, Monitor, Info
} from '@lucide/vue'
import { usePage } from '@inertiajs/vue3'
import { formatDate } from '@/utils/formatters'
import EmailAttachmentsDropzone from '@/Components/EmailAttachmentsDropzone.vue'
import Tooltip from '@/Components/Tooltip.vue'
import { useToast } from '@/composables/useToast'

const { success, error: toastError } = useToast()

// Props
const props = defineProps({
    currentGym: Object
})

const page = usePage()

// Reactive data
const templates = ref([])
const selectedTemplateId = ref('')
const currentTemplate = ref(null)
const showPreview = ref(false)
const showCreateTemplate = ref(false)
const isSaving = ref(false)
const isCreating = ref(false)
const editMode = ref('edit') // 'edit' oder 'preview'
const htmlEditor = ref(null)

const newTemplate = ref({
    name: '',
    type: 'general',
    subject: '',
    body: '<p>Beginnen Sie hier mit dem Schreiben...</p>',
    is_active: true,
    is_default: false,
    useStandardTemplate: true
})

// Available placeholders
const availablePlaceholders = ref([
    { key: '[Vorname]', description: 'Vorname des Mitglieds' },
    { key: '[Nachname]', description: 'Nachname des Mitglieds' },
    { key: '[Anrede]', description: 'Anrede (Herr/Frau)' },
    { key: '[E-Mail]', description: 'E-Mail-Adresse' },
    { key: '[Mitgliedsnummer]', description: 'Mitgliedsnummer' },
    { key: '[Fitnessstudio-Name]', description: 'Name des Fitnessstudios' },
    { key: '[Adresse]', description: 'Adresse des Studios' },
    { key: '[Telefon]', description: 'Telefonnummer' },
    { key: '[Studio-E-Mail]', description: 'E-Mail-Adresse des Studios' },
    { key: '[Website]', description: 'Website-URL' },
    { key: '[Vertragslaufzeit]', description: 'Laufzeit des Vertrags' },
    { key: '[Monatsbeitrag]', description: 'Monatlicher Beitrag' },
    { key: '[Startdatum]', description: 'Vertragsbeginn' },
    { key: '[Enddatum]', description: 'Vertragsende' },
    { key: '[Mitgliederbereich-Link]', description: 'Link zum Mitgliederbereich' },
    { key: '[Datum]', description: 'Aktuelles Datum' },
    { key: '[Uhrzeit]', description: 'Aktuelle Uhrzeit' },
    { key: '[Anmeldecode]', description: 'Einmaliger Anmeldecode' },
    ...[
        { key: '[Kuendigungseingang]', description: 'Datum und Uhrzeit des Kündigungseingangs' },
        { key: '[Kuendigungstermin]', description: 'Beendigungszeitpunkt der Mitgliedschaft' },
        { key: '[Kuendigungsgrund]', description: 'Angegebener Kündigungsgrund' },
        { key: '[Tarif-Name]', description: 'Name des gekündigten Tarifs' },
        { key: '[Vertragsnummer]', description: 'Nummer des gekündigten Vertrags' }
    ].map(placeholder => ({ ...placeholder, hint: 'Funktioniert nur im Template „Kündigung“.' })),
    {
        key: '[Mollie-Zahlungslink]',
        description: 'Link zur Zahlung über Mollie',
        hint: 'Funktioniert nur im Template „Zahlung fehlgeschlagen“.'
    }
])

// Methods
const loadTemplates = async () => {
    try {
        const response = await axios.get(route('settings.email-templates.index'))
        templates.value = response.data.templates
    } catch (error) {
        console.error('Fehler beim Laden der E-Mail-Vorlagen:', error)
        toastError('Fehler beim Laden der E-Mail-Vorlagen')
    }
}

const loadSelectedTemplate = async () => {
    if (!selectedTemplateId.value) {
        currentTemplate.value = null
        return
    }

    try {
        const response = await axios.get(route('settings.email-templates.show', selectedTemplateId.value))
        currentTemplate.value = response.data.template
        editMode.value = 'edit'
    } catch (error) {
        console.error('Fehler beim Laden der Vorlage:', error)
        toastError('Fehler beim Laden der Vorlage')
    }
}

const reloadCurrentTemplate = async () => {
    if (!currentTemplate.value) return
    try {
        const response = await axios.get(route('settings.email-templates.show', currentTemplate.value.id))
        currentTemplate.value = response.data.template
    } catch (error) {
        console.error('Fehler beim Neuladen der Vorlage:', error)
    }
}

const saveTemplate = async () => {
    if (!currentTemplate.value) return

    isSaving.value = true

    try {
        const response = await axios.put(
            route('settings.email-templates.update', currentTemplate.value.id),
            currentTemplate.value
        )

        if (response.data.template) {
            currentTemplate.value = response.data.template

            // Update template in list
            const index = templates.value.findIndex(t => t.id === currentTemplate.value.id)
            if (index !== -1) {
                templates.value[index] = { ...currentTemplate.value }
            }
        }

        success('E-Mail-Vorlage erfolgreich gespeichert!')
    } catch (error) {
        console.error('Fehler beim Speichern der Vorlage:', error)
        toastError('Fehler beim Speichern der Vorlage')
    } finally {
        isSaving.value = false
    }
}

const createTemplate = async () => {
    isCreating.value = true

    try {
        const templateData = {
            ...newTemplate.value,
            gym_id: props.currentGym.id,
            use_template_definition: newTemplate.value.useStandardTemplate
        }

        // Remove frontend-only properties
        delete templateData.useStandardTemplate

        const response = await axios.post(route('settings.email-templates.store'), templateData)

        if (response.data.template) {
            templates.value.push(response.data.template)
            showCreateTemplate.value = false

            // Reset form
            newTemplate.value = {
                name: '',
                type: 'general',
                subject: '',
                body: '<p>Beginnen Sie hier mit dem Schreiben...</p>',
                is_active: true,
                is_default: false,
                useStandardTemplate: true
            }

            success('E-Mail-Vorlage erfolgreich erstellt!')
        }
    } catch (error) {
        console.error('Fehler beim Erstellen der Vorlage:', error)
        toastError(error.response?.data?.message || 'Fehler beim Erstellen der Vorlage')
    } finally {
        isCreating.value = false
    }
}

// Template selection handlers
const onTemplateTypeChange = () => {
    // Update the preview when template type changes
    if (newTemplate.value.useStandardTemplate) {
        // The computed property selectedTemplateDefinition will automatically update
    }
}

const onUseStandardTemplateChange = () => {
    // If user disables standard template, clear the content
    if (!newTemplate.value.useStandardTemplate) {
        newTemplate.value.subject = ''
        newTemplate.value.body = '<p>Beginnen Sie hier mit dem Schreiben...</p>'
    }
}

const editTemplate = (template) => {
    selectedTemplateId.value = template.id
    loadSelectedTemplate()
}

const duplicateTemplate = async (template) => {
    try {
        const response = await axios.post(route('settings.email-templates.duplicate', template.id))

        if (response.data.template) {
            templates.value.push(response.data.template)
            success('Vorlage erfolgreich dupliziert!')
        }
    } catch (error) {
        console.error('Fehler beim Duplizieren der Vorlage:', error)
        toastError('Fehler beim Duplizieren der Vorlage')
    }
}

const deleteTemplate = async (template) => {
    if (!confirm('Möchten Sie diese E-Mail-Vorlage wirklich löschen?')) {
        return
    }

    try {
        await axios.delete(route('settings.email-templates.destroy', template.id))

        const index = templates.value.findIndex(t => t.id === template.id)
        if (index !== -1) {
            templates.value.splice(index, 1)
        }

        if (currentTemplate.value && currentTemplate.value.id === template.id) {
            currentTemplate.value = null
            selectedTemplateId.value = ''
        }

        success('E-Mail-Vorlage erfolgreich gelöscht!')
    } catch (error) {
        console.error('Fehler beim Löschen der Vorlage:', error)
        toastError('Fehler beim Löschen der Vorlage')
    }
}

const previewTemplate = () => {
    if (!currentTemplate.value) return
    showPreview.value = true
}

const toggleEditMode = () => {
    editMode.value = editMode.value === 'edit' ? 'preview' : 'edit'
}

const updateContent = () => {
    // Reactivity wird automatisch durch v-model gehandhabt
}

// Editor-spezifische Funktionen
const insertPlaceholder = (placeholder) => {
    if (editMode.value === 'preview') {
        editMode.value = 'edit'
        nextTick(() => insertPlaceholder(placeholder))
        return
    }

    if (!htmlEditor.value) return

    const textarea = htmlEditor.value
    const start = textarea.selectionStart
    const end = textarea.selectionEnd
    const text = textarea.value

    // Insert placeholder at cursor position
    const newText = text.substring(0, start) + placeholder + text.substring(end)
    currentTemplate.value.body = newText

    // Move cursor after inserted placeholder
    nextTick(() => {
        textarea.focus()
        textarea.setSelectionRange(start + placeholder.length, start + placeholder.length)
    })
}

const insertHtmlTag = (tag) => {
    if (!htmlEditor.value) return

    const textarea = htmlEditor.value
    const start = textarea.selectionStart
    const end = textarea.selectionEnd
    const selectedText = textarea.value.substring(start, end)

    let replacement
    if (selectedText) {
        replacement = `<${tag}>${selectedText}</${tag}>`
    } else {
        replacement = `<${tag}></${tag}>`
    }

    const text = textarea.value
    const newText = text.substring(0, start) + replacement + text.substring(end)
    currentTemplate.value.body = newText

    nextTick(() => {
        textarea.focus()
        if (selectedText) {
            textarea.setSelectionRange(start + replacement.length, start + replacement.length)
        } else {
            textarea.setSelectionRange(start + tag.length + 2, start + tag.length + 2)
        }
    })
}

const insertList = (listType) => {
    if (!htmlEditor.value) return

    const textarea = htmlEditor.value
    const start = textarea.selectionStart
    const listHtml = listType === 'ul'
        ? '<ul>\n  <li>Element 1</li>\n  <li>Element 2</li>\n</ul>'
        : '<ol>\n  <li>Element 1</li>\n  <li>Element 2</li>\n</ol>'

    const text = textarea.value
    const newText = text.substring(0, start) + listHtml + text.substring(start)
    currentTemplate.value.body = newText

    nextTick(() => {
        textarea.focus()
        textarea.setSelectionRange(start + listHtml.length, start + listHtml.length)
    })
}

const insertLink = () => {
    const url = prompt('URL eingeben:')
    const text = prompt('Link-Text eingeben:') || url

    if (!url || !htmlEditor.value) return

    const textarea = htmlEditor.value
    const start = textarea.selectionStart
    const linkHtml = `<a href="${url}">${text}</a>`

    const textValue = textarea.value
    const newText = textValue.substring(0, start) + linkHtml + textValue.substring(start)
    currentTemplate.value.body = newText

    nextTick(() => {
        textarea.focus()
        textarea.setSelectionRange(start + linkHtml.length, start + linkHtml.length)
    })
}

const insertParagraph = () => {
    if (!htmlEditor.value) return

    const textarea = htmlEditor.value
    const start = textarea.selectionStart
    const paragraphHtml = '<p></p>'

    const text = textarea.value
    const newText = text.substring(0, start) + paragraphHtml + text.substring(start)
    currentTemplate.value.body = newText

    nextTick(() => {
        textarea.focus()
        textarea.setSelectionRange(start + 3, start + 3) // Cursor zwischen <p> und </p>
    })
}

const handleKeyDown = (event) => {
    if (event.ctrlKey || event.metaKey) {
        switch (event.key) {
            case 'b':
                event.preventDefault()
                insertHtmlTag('strong')
                break
            case 'i':
                event.preventDefault()
                insertHtmlTag('em')
                break
            case 'Enter':
                event.preventDefault()
                insertParagraph()
                break
        }
    }
}

const replacePlaceholders = (content) => {
    if (!content) return ''

    // Sample data for preview
    const sampleData = {
        '[Vorname]': 'Max',
        '[Nachname]': 'Mustermann',
        '[Anrede]': 'Herr',
        '[E-Mail]': 'max.mustermann@example.com',
        '[Mitgliedsnummer]': 'GM-2024-001',
        '[Fitnessstudio-Name]': props.currentGym?.name || 'Ihr Fitnessstudio',
        '[Adresse]': props.currentGym?.address || 'Musterstraße 123, 12345 Musterstadt',
        '[Telefon]': props.currentGym?.phone || '+49 123 456789',
        '[Studio-E-Mail]': props.currentGym?.email || 'info@ihr-studio.de',
        '[Website]': props.currentGym?.website || 'https://ihr-studio.de',
        '[Vertragslaufzeit]': '12 Monate',
        '[Monatsbeitrag]': '49,90',
        '[Startdatum]': new Date().toLocaleDateString('de-DE'),
        '[Enddatum]': new Date(new Date().setFullYear(new Date().getFullYear() + 1)).toLocaleDateString('de-DE'),
        '[Mitgliederbereich-Link]': page.props.pwaUrl,
        '[Datum]': new Date().toLocaleDateString('de-DE'),
        '[Uhrzeit]': new Date().toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' }),
        '[Anmeldecode]': '482916',
        '[Kuendigungseingang]': new Date().toLocaleDateString('de-DE') + ', '
            + new Date().toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' }) + ' Uhr',
        '[Kuendigungstermin]': new Date(new Date().setFullYear(new Date().getFullYear() + 1)).toLocaleDateString('de-DE'),
        '[Kuendigungsgrund]': 'Umzug',
        '[Tarif-Name]': 'Premium Mitgliedschaft',
        '[Vertragsnummer]': '12345',
        '[Mollie-Zahlungslink]': 'https://www.mollie.com/checkout/beispiel'
    }

    let replacedContent = content
    Object.entries(sampleData).forEach(([placeholder, value]) => {
        replacedContent = replacedContent.replace(new RegExp(placeholder.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'), value)
    })

    return replacedContent
}

const getTypeLabel = (type) => {
    const labels = {
        welcome: 'Willkommen',
        confirmation: 'Bestätigung',
        reminder: 'Erinnerung',
        cancellation: 'Kündigung',
        invoice: 'Rechnung',
        payment_failed: 'Mahnung',
        login_code: 'Anmeldecode',
        member_app_access: 'App-Zugangslink',
        general: 'Allgemein'
    }
    return labels[type] || type
}

const getTypeColor = (type) => {
    const colors = {
        welcome: 'bg-success-soft text-success-strong',
        confirmation: 'bg-info-soft text-info-strong',
        reminder: 'bg-warning-soft text-warning-strong',
        cancellation: 'bg-danger-soft text-danger-strong',
        invoice: 'bg-purple-100 text-purple-800',
        payment_failed: 'bg-danger-soft text-danger-strong',
        login_code: 'bg-cyan-100 text-cyan-800',
        member_app_access: 'bg-brand-soft text-brand-strong',
        general: 'bg-fill text-ink'
    }
    return colors[type] || 'bg-fill text-ink'
}

// Lifecycle
onMounted(async () => {
    await loadTemplates()
})
</script>

<style scoped>
.prose {
    max-width: none;
}

.prose :deep(p) {
    margin-bottom: 1em;
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
</style>
