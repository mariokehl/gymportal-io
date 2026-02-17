<template>
    <div class="space-y-6">
        <!-- Header mit Template-Auswahl -->
        <div class="bg-white shadow-sm rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        E-Mail-Vorlagen verwalten
                    </h3>
                    <button @click="showCreateTemplate = true"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-md flex items-center">
                        <component :is="Plus" class="w-4 h-4 mr-2" />
                        Neue Vorlage
                    </button>
                </div>

                <!-- Template Selection -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Vorlage auswählen</label>
                    <select v-model="selectedTemplateId" @change="loadSelectedTemplate"
                        class="w-full p-2 border border-gray-300 rounded-md bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Bitte wählen Sie eine Vorlage aus</option>
                        <option v-for="template in templates" :key="template.id" :value="template.id">
                            {{ template.name }} ({{ template.type }})
                        </option>
                    </select>
                </div>

                <!-- Success/Error Messages -->
                <div v-if="successMessage"
                    class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
                    {{ successMessage }}
                </div>
                <div v-if="errorMessage" class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                    {{ errorMessage }}
                </div>
            </div>
        </div>

        <!-- Template Editor (nur wenn Template ausgewählt) -->
        <div v-if="currentTemplate" class="bg-white shadow-sm rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex justify-between items-center mb-6">
                    <h4 class="text-lg font-medium text-gray-900">{{ currentTemplate.name }} bearbeiten</h4>
                    <div class="flex space-x-2">
                        <button @click="toggleEditMode"
                            :class="editMode === 'preview' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700'"
                            class="hover:bg-indigo-700 hover:text-white font-medium py-2 px-4 rounded-md transition-colors">
                            <component :is="Eye" class="w-4 h-4 mr-2 inline" />
                            {{ editMode === 'preview' ? 'Bearbeiten' : 'Vorschau' }}
                        </button>
                        <button @click="previewTemplate"
                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded-md">
                            <component :is="Monitor" class="w-4 h-4 mr-2 inline" />
                            Vollbild-Vorschau
                        </button>
                        <button @click="saveTemplate" :disabled="isSaving"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-md disabled:opacity-50">
                            <component :is="Save" class="w-4 h-4 mr-2 inline" />
                            {{ isSaving ? 'Speichert...' : 'Speichern' }}
                        </button>
                    </div>
                </div>

                <form @submit.prevent="saveTemplate" class="space-y-6">
                    <!-- Template Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Vorlagen-Name</label>
                        <input v-model="currentTemplate.name" type="text"
                            class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6"
                            required />
                    </div>

                    <!-- Template Type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Template-Typ</label>
                        <select v-model="currentTemplate.type"
                            class="w-full p-2 border border-gray-300 rounded-md bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="welcome">Willkommen</option>
                            <option value="confirmation">Bestätigung</option>
                            <option value="reminder">Erinnerung</option>
                            <option value="cancellation">Kündigung</option>
                            <option value="invoice">Rechnung</option>
                            <option value="payment_failed">Zahlung fehlgeschlagen</option>
                            <option value="general">Allgemein</option>
                        </select>
                    </div>

                    <!-- Subject -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">E-Mail-Betreff</label>
                        <input v-model="currentTemplate.subject" type="text"
                            class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6"
                            placeholder="z.B. Herzlich willkommen, [Vorname]!"
                            required />
                    </div>

                    <!-- Placeholder Helper -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h5 class="text-sm font-medium text-blue-800 mb-2">Verfügbare Platzhalter:</h5>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-xs">
                            <span v-for="placeholder in availablePlaceholders" :key="placeholder.key"
                                @click="insertPlaceholder(placeholder.key)"
                                class="bg-white border border-blue-300 rounded px-2 py-1 cursor-pointer hover:bg-blue-100 transition-colors">
                                {{ placeholder.key }} - {{ placeholder.description }}
                            </span>
                        </div>
                        <p class="text-xs text-blue-600 mt-2">Klicken Sie auf einen Platzhalter, um ihn in den Editor einzufügen.</p>
                    </div>

                    <!-- Editor Section -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">E-Mail-Inhalt</label>

                        <!-- Edit/Preview Toggle -->
                        <div class="mb-4">
                            <div class="flex border-b border-gray-200">
                                <button type="button"
                                    @click="editMode = 'edit'"
                                    :class="editMode === 'edit' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                                    class="px-4 py-2 text-sm font-medium border-b-2 focus:outline-none">
                                    HTML Editor
                                </button>
                                <button type="button"
                                    @click="editMode = 'preview'"
                                    :class="editMode === 'preview' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                                    class="px-4 py-2 text-sm font-medium border-b-2 focus:outline-none">
                                    Live-Vorschau
                                </button>
                            </div>
                        </div>

                        <!-- Editor Content -->
                        <div class="border border-gray-300 rounded-lg overflow-hidden">
                            <!-- Formatting Toolbar (nur im Edit-Modus) -->
                            <div v-if="editMode === 'edit'" class="bg-gray-50 border-b border-gray-300 p-2 flex flex-wrap gap-1">
                                <button type="button" @click="insertHtmlTag('strong')"
                                    class="p-2 hover:bg-gray-200 rounded border border-gray-300 bg-white text-sm font-bold">
                                    B
                                </button>
                                <button type="button" @click="insertHtmlTag('em')"
                                    class="p-2 hover:bg-gray-200 rounded border border-gray-300 bg-white text-sm italic">
                                    I
                                </button>
                                <button type="button" @click="insertHtmlTag('u')"
                                    class="p-2 hover:bg-gray-200 rounded border border-gray-300 bg-white text-sm underline">
                                    U
                                </button>
                                <div class="w-px bg-gray-300 mx-1"></div>
                                <button type="button" @click="insertHtmlTag('h2')"
                                    class="p-2 hover:bg-gray-200 rounded border border-gray-300 bg-white text-sm font-bold">
                                    H2
                                </button>
                                <button type="button" @click="insertHtmlTag('h3')"
                                    class="p-2 hover:bg-gray-200 rounded border border-gray-300 bg-white text-sm font-bold">
                                    H3
                                </button>
                                <div class="w-px bg-gray-300 mx-1"></div>
                                <button type="button" @click="insertList('ul')"
                                    class="p-2 hover:bg-gray-200 rounded border border-gray-300 bg-white text-sm">
                                    <component :is="List" class="w-4 h-4" />
                                </button>
                                <button type="button" @click="insertList('ol')"
                                    class="p-2 hover:bg-gray-200 rounded border border-gray-300 bg-white text-sm">
                                    <component :is="ListOrdered" class="w-4 h-4" />
                                </button>
                                <div class="w-px bg-gray-300 mx-1"></div>
                                <button type="button" @click="insertLink"
                                    class="p-2 hover:bg-gray-200 rounded border border-gray-300 bg-white text-sm">
                                    <component :is="Link" class="w-4 h-4" />
                                </button>
                                <div class="w-px bg-gray-300 mx-1"></div>
                                <button type="button" @click="insertParagraph"
                                    class="p-2 hover:bg-gray-200 rounded border border-gray-300 bg-white text-xs">
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
                                    class="w-full h-96 p-4 font-mono text-sm border-none resize-none focus:outline-none focus:ring-0"
                                    placeholder="<p>Beginnen Sie hier mit dem Schreiben...</p>"
                                    spellcheck="false">
                                </textarea>
                                <div class="absolute bottom-2 right-2 text-xs text-gray-500 bg-white px-2 py-1 rounded shadow">
                                    HTML-Modus
                                </div>
                            </div>

                            <!-- Live Preview -->
                            <div v-else-if="editMode === 'preview'" class="min-h-96 p-4 bg-white">
                                <div class="prose max-w-none"
                                     v-html="replacePlaceholders(currentTemplate.body)">
                                </div>
                                <div class="mt-4 text-xs text-gray-500 border-t pt-2">
                                    Vorschau-Modus - Platzhalter werden durch Beispieldaten ersetzt
                                </div>
                            </div>
                        </div>

                        <div class="mt-2 text-xs text-gray-500 space-y-1">
                            <p><strong>Tipp:</strong> Verwenden Sie HTML-Tags für Formatierung (z.B. &lt;strong&gt;fett&lt;/strong&gt;, &lt;em&gt;kursiv&lt;/em&gt;)</p>
                            <p><strong>Tastenkürzel:</strong> Ctrl+B = Fett, Ctrl+I = Kursiv, Ctrl+Enter = Neuer Absatz</p>
                        </div>
                    </div>

                    <!-- Template Settings -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" v-model="currentTemplate.is_active"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                <span class="ml-2 text-sm text-gray-700">Template aktiv</span>
                            </label>
                        </div>
                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" v-model="currentTemplate.is_default"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                <span class="ml-2 text-sm text-gray-700">Als Standard verwenden</span>
                            </label>
                        </div>
                    </div>

                    <!-- Anhänge -->
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <h5 class="text-sm font-medium text-gray-900 mb-3">Anhänge (z.B. AGB, Widerrufsbelehrung)</h5>
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
        <div class="bg-white shadow-sm rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h4 class="text-lg font-medium text-gray-900 mb-4">Alle Vorlagen</h4>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Name
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Typ
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Erstellt am
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Aktionen
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="template in templates" :key="template.id">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ template.name }}</div>
                                    <div class="text-sm text-gray-500 truncate max-w-xs">{{ template.subject }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                                        :class="getTypeColor(template.type)">
                                        {{ getTypeLabel(template.type) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <span :class="template.is_active ? 'text-green-600' : 'text-gray-400'"
                                            class="text-xs font-medium">
                                            {{ template.is_active ? 'Aktiv' : 'Inaktiv' }}
                                        </span>
                                        <div class="ml-2 w-2 h-2 rounded-full"
                                            :class="template.is_active ? 'bg-green-400' : 'bg-gray-300'"></div>
                                        <span v-if="template.is_default"
                                            class="ml-2 inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                            Standard
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ formatDate(template.created_at) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                    <button @click="editTemplate(template)"
                                        class="text-indigo-600 hover:text-indigo-900">
                                        <component :is="Edit" class="w-4 h-4" />
                                    </button>
                                    <button @click="duplicateTemplate(template)"
                                        class="text-gray-600 hover:text-gray-900">
                                        <component :is="Copy" class="w-4 h-4" />
                                    </button>
                                    <button @click="deleteTemplate(template)"
                                        class="text-red-600 hover:text-red-900">
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
            <div class="relative top-20 mx-auto p-5 border border-gray-50 w-11/12 md:w-3/4 lg:w-2/3 shadow-lg rounded-md bg-white">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">E-Mail-Vollbild-Vorschau</h3>
                    <button @click="showPreview = false" class="text-gray-400 hover:text-gray-600">
                        <component :is="X" class="w-5 h-5" />
                    </button>
                </div>

                <div class="border border-gray-200 rounded-lg overflow-hidden">
                    <div class="bg-gray-50 px-4 py-2 border-b border-gray-200">
                        <div class="text-sm text-gray-600">
                            <strong>Betreff:</strong> {{ replacePlaceholders(currentTemplate.subject) }}
                        </div>
                    </div>
                    <div class="p-4 bg-white prose max-w-none" v-html="replacePlaceholders(currentTemplate.body)">
                    </div>
                </div>

                <div class="flex justify-end mt-4">
                    <button @click="showPreview = false"
                        class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-md">
                        Schließen
                    </button>
                </div>
            </div>
        </div>

        <!-- Create Template Modal -->
        <div v-if="showCreateTemplate" class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border border-gray-50 w-96 shadow-lg rounded-md bg-white">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Neue E-Mail-Vorlage</h3>
                    <button @click="showCreateTemplate = false" class="text-gray-400 hover:text-gray-600">
                        <component :is="X" class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="createTemplate" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Vorlagen-Name</label>
                        <input v-model="newTemplate.name" type="text"
                            class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6"
                            required />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Template-Typ</label>
                        <select v-model="newTemplate.type"
                            class="w-full p-2 border border-gray-300 rounded-md bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="welcome">Willkommen</option>
                            <option value="confirmation">Bestätigung</option>
                            <option value="reminder">Erinnerung</option>
                            <option value="cancellation">Kündigung</option>
                            <option value="invoice">Rechnung</option>
                            <option value="payment_failed">Zahlung fehlgeschlagen</option>
                            <option value="general">Allgemein</option>
                        </select>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" @click="showCreateTemplate = false"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 border border-gray-300 rounded-md hover:bg-gray-300">
                            Abbrechen
                        </button>
                        <button type="submit" :disabled="isCreating"
                            class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 disabled:opacity-50">
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
    Plus, Eye, Save, Edit, Copy, Trash2, X, List, ListOrdered, Link, Monitor
} from 'lucide-vue-next'
import { formatDate } from '@/utils/formatters'
import EmailAttachmentsDropzone from '@/Components/EmailAttachmentsDropzone.vue'

// Props
const props = defineProps({
    currentGym: Object
})

// Reactive data
const templates = ref([])
const selectedTemplateId = ref('')
const currentTemplate = ref(null)
const showPreview = ref(false)
const showCreateTemplate = ref(false)
const isSaving = ref(false)
const isCreating = ref(false)
const successMessage = ref('')
const errorMessage = ref('')
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
    { key: '[Website]', description: 'Website-URL' },
    { key: '[Vertragslaufzeit]', description: 'Laufzeit des Vertrags' },
    { key: '[Monatsbeitrag]', description: 'Monatlicher Beitrag' },
    { key: '[Startdatum]', description: 'Vertragsbeginn' },
    { key: '[QR-Code-Link]', description: 'Link zum QR-Code' },
    { key: '[Mitgliederbereich-Link]', description: 'Link zum Mitgliederbereich' },
    { key: '[Datum]', description: 'Aktuelles Datum' }
])

// Methods
const loadTemplates = async () => {
    try {
        const response = await axios.get(route('settings.email-templates.index'))
        templates.value = response.data.templates
    } catch (error) {
        console.error('Fehler beim Laden der E-Mail-Vorlagen:', error)
        errorMessage.value = 'Fehler beim Laden der E-Mail-Vorlagen'
        setTimeout(() => errorMessage.value = '', 3000)
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
        errorMessage.value = 'Fehler beim Laden der Vorlage'
        setTimeout(() => errorMessage.value = '', 3000)
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

        successMessage.value = 'E-Mail-Vorlage erfolgreich gespeichert!'
        setTimeout(() => successMessage.value = '', 3000)
    } catch (error) {
        console.error('Fehler beim Speichern der Vorlage:', error)
        errorMessage.value = 'Fehler beim Speichern der Vorlage'
        setTimeout(() => errorMessage.value = '', 3000)
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

            successMessage.value = 'E-Mail-Vorlage erfolgreich erstellt!'
            setTimeout(() => successMessage.value = '', 3000)
        }
    } catch (error) {
        console.error('Fehler beim Erstellen der Vorlage:', error)
        errorMessage.value = 'Fehler beim Erstellen der Vorlage'
        setTimeout(() => errorMessage.value = '', 3000)
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
            successMessage.value = 'Vorlage erfolgreich dupliziert!'
            setTimeout(() => successMessage.value = '', 3000)
        }
    } catch (error) {
        console.error('Fehler beim Duplizieren der Vorlage:', error)
        errorMessage.value = 'Fehler beim Duplizieren der Vorlage'
        setTimeout(() => errorMessage.value = '', 3000)
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

        successMessage.value = 'E-Mail-Vorlage erfolgreich gelöscht!'
        setTimeout(() => successMessage.value = '', 3000)
    } catch (error) {
        console.error('Fehler beim Löschen der Vorlage:', error)
        errorMessage.value = 'Fehler beim Löschen der Vorlage'
        setTimeout(() => errorMessage.value = '', 3000)
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
        '[Website]': props.currentGym?.website || 'https://ihr-studio.de',
        '[Vertragslaufzeit]': '12 Monate',
        '[Monatsbeitrag]': '49,90',
        '[Startdatum]': new Date().toLocaleDateString('de-DE'),
        '[QR-Code-Link]': 'https://members.gymportal.io/qr-code',
        '[Mitgliederbereich-Link]': 'https://members.gymportal.io',
        '[Datum]': new Date().toLocaleDateString('de-DE')
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
        general: 'Allgemein'
    }
    return labels[type] || type
}

const getTypeColor = (type) => {
    const colors = {
        welcome: 'bg-green-100 text-green-800',
        confirmation: 'bg-blue-100 text-blue-800',
        reminder: 'bg-yellow-100 text-yellow-800',
        cancellation: 'bg-red-100 text-red-800',
        invoice: 'bg-purple-100 text-purple-800',
        payment_failed: 'bg-red-100 text-red-800',
        general: 'bg-gray-100 text-gray-800'
    }
    return colors[type] || 'bg-gray-100 text-gray-800'
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
