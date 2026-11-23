<template>
    <div class="bg-surface shadow-sm rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="text-lg leading-6 font-semibold text-ink">
                        Rechtliche Dokumente
                    </h3>
                    <p class="mt-1 text-sm text-ink-muted">
                        Hinterlegen Sie URLs zu Ihren rechtlichen Dokumenten. Diese können dann in der PWA und auf Vertragsformularen angezeigt werden.
                    </p>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="isLoading" class="flex justify-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-brand"></div>
            </div>

            <!-- Content -->
            <div v-else class="space-y-4">
                <!-- Existing URLs -->
                <div v-for="legalUrl in legalUrls" :key="legalUrl.id"
                    class="flex items-center justify-between p-4 border border-line rounded-lg bg-surface-muted group hover:bg-fill transition-colors">
                    <div class="flex items-center flex-1 min-w-0">
                        <div class="w-10 h-10 bg-brand-soft rounded-lg flex items-center justify-center mr-4 flex-shrink-0">
                            <component :is="getTypeIcon(legalUrl.type)" class="w-5 h-5 text-brand" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <h4 class="text-sm font-medium text-ink">{{ legalUrl.label }}</h4>
                            <a :href="legalUrl.url" target="_blank" rel="noopener noreferrer"
                                class="text-sm text-brand hover:text-brand-strong truncate block max-w-md">
                                {{ legalUrl.url }}
                            </a>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2 ml-4">
                        <button @click="editUrl(legalUrl)"
                            class="p-2 text-ink-muted hover:text-brand-strong transition-colors"
                            title="Bearbeiten">
                            <component :is="Pencil" class="w-4 h-4" />
                        </button>
                        <button @click="deleteUrl(legalUrl)"
                            :disabled="legalUrl.isDeleting"
                            class="p-2 text-ink-muted hover:text-danger-strong transition-colors disabled:opacity-50"
                            title="Löschen">
                            <component :is="Trash2" class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="legalUrls.length === 0 && !isAdding"
                    class="text-center py-8 border-2 border-dashed border-line-strong rounded-lg">
                    <component :is="FileText" class="mx-auto h-12 w-12 text-ink-muted" />
                    <h3 class="mt-2 text-sm font-medium text-ink">Keine URLs hinterlegt</h3>
                    <p class="mt-1 text-sm text-ink-muted">Fügen Sie URLs zu Ihren rechtlichen Dokumenten hinzu.</p>
                </div>

                <!-- Add New URL Section -->
                <div v-if="isAdding || isEditing" class="border border-brand-line rounded-lg p-4 bg-brand-subtle">
                    <h4 class="text-sm font-medium text-ink mb-4">
                        {{ isEditing ? 'URL bearbeiten' : 'Neue URL hinzufügen' }}
                    </h4>
                    <form @submit.prevent="saveUrl" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="legal-urls-manager-form-type" class="block text-sm font-medium text-ink-secondary mb-1">Dokumenttyp</label>
                                <select id="legal-urls-manager-form-type" v-model="form.type"
                                    :disabled="isEditing"
                                    class="field w-full p-2 disabled:bg-fill"
                                    required>
                                    <option value="" disabled>Bitte wählen...</option>
                                    <option v-for="(label, type) in availableTypesForSelect" :key="type" :value="type">
                                        {{ label }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label for="legal-urls-manager-form-url" class="block text-sm font-medium text-ink-secondary mb-1">URL</label>
                                <input id="legal-urls-manager-form-url" v-model="form.url" type="url" required
                                    placeholder="https://example.com/document.pdf"
                                    class="field block w-full px-3 py-1.5" />
                            </div>
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button type="button" @click="cancelForm"
                                class="px-4 py-2 text-sm font-medium text-ink-secondary bg-surface border border-line-strong rounded-md hover:bg-surface-muted">
                                Abbrechen
                            </button>
                            <button type="submit" :disabled="isSaving"
                                class="px-4 py-2 text-sm font-medium text-white bg-brand border border-transparent rounded-md hover:bg-brand-strong disabled:opacity-50">
                                {{ isSaving ? 'Speichern...' : 'Speichern' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Add Button -->
                <button v-if="!isAdding && !isEditing && hasAvailableTypes"
                    @click="startAdding"
                    class="w-full flex items-center justify-center px-4 py-3 border-2 border-dashed border-line-strong rounded-lg text-sm font-medium text-ink-secondary hover:border-brand hover:text-brand-strong transition-colors">
                    <component :is="Plus" class="w-5 h-5 mr-2" />
                    URL hinzufügen
                </button>

                <!-- All types used info -->
                <p v-if="!hasAvailableTypes && !isAdding && !isEditing" class="text-center text-sm text-ink-muted py-2">
                    Alle verfügbaren Dokumenttypen wurden bereits hinterlegt.
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Plus, Trash2, Pencil, FileText, Scale, Shield, BookOpen, CreditCard, FileSignature } from '@lucide/vue'

const props = defineProps({
    currentGym: {
        type: Object,
        required: true
    }
})

const emit = defineEmits(['success', 'error'])

// State
const isLoading = ref(true)
const isAdding = ref(false)
const isEditing = ref(false)
const isSaving = ref(false)
const legalUrls = ref([])
const availableTypes = ref({})
const editingId = ref(null)

const form = ref({
    type: '',
    url: ''
})

// Computed
const usedTypes = computed(() => legalUrls.value.map(u => u.type))

const availableTypesForSelect = computed(() => {
    if (isEditing.value) {
        // When editing, show only the current type
        return { [form.value.type]: availableTypes.value[form.value.type] }
    }
    // Filter out already used types
    return Object.fromEntries(
        Object.entries(availableTypes.value).filter(([type]) => !usedTypes.value.includes(type))
    )
})

const hasAvailableTypes = computed(() => {
    return Object.keys(availableTypesForSelect.value).length > 0
})

// Icon mapping
const typeIcons = {
    terms_and_conditions: Scale,
    cancellation_policy: FileSignature,
    privacy_policy: Shield,
    terms_of_use: BookOpen,
    pricing: CreditCard,
    contract_conclusion: FileText
}

const getTypeIcon = (type) => {
    return typeIcons[type] || FileText
}

// Methods
const loadLegalUrls = async () => {
    isLoading.value = true
    try {
        const response = await axios.get(route('settings.legal-urls.index'))
        legalUrls.value = response.data.legal_urls || []
        availableTypes.value = response.data.available_types || {}
    } catch (error) {
        console.error('Fehler beim Laden der URLs:', error)
        emit('error', 'Fehler beim Laden der rechtlichen URLs')
    } finally {
        isLoading.value = false
    }
}

const startAdding = () => {
    isAdding.value = true
    isEditing.value = false
    editingId.value = null
    form.value = { type: '', url: '' }
}

const editUrl = (legalUrl) => {
    isEditing.value = true
    isAdding.value = false
    editingId.value = legalUrl.id
    form.value = {
        type: legalUrl.type,
        url: legalUrl.url
    }
}

const cancelForm = () => {
    isAdding.value = false
    isEditing.value = false
    editingId.value = null
    form.value = { type: '', url: '' }
}

const saveUrl = async () => {
    isSaving.value = true
    try {
        const response = await axios.post(route('settings.legal-urls.store'), form.value)

        if (response.data.success) {
            const savedUrl = response.data.legal_url

            if (isEditing.value) {
                // Update existing
                const index = legalUrls.value.findIndex(u => u.id === editingId.value)
                if (index > -1) {
                    legalUrls.value[index] = savedUrl
                }
            } else {
                // Add new
                legalUrls.value.push(savedUrl)
            }

            cancelForm()
            emit('success', response.data.message)
        }
    } catch (error) {
        console.error('Fehler beim Speichern:', error)
        const message = error.response?.data?.message || 'Fehler beim Speichern der URL'
        emit('error', message)
    } finally {
        isSaving.value = false
    }
}

const deleteUrl = async (legalUrl) => {
    if (!confirm(`Möchten Sie die URL für "${legalUrl.label}" wirklich löschen?`)) {
        return
    }

    legalUrl.isDeleting = true
    try {
        await axios.delete(route('settings.legal-urls.destroy', legalUrl.id))

        const index = legalUrls.value.findIndex(u => u.id === legalUrl.id)
        if (index > -1) {
            legalUrls.value.splice(index, 1)
        }

        emit('success', 'URL wurde erfolgreich gelöscht.')
    } catch (error) {
        console.error('Fehler beim Löschen:', error)
        emit('error', 'Fehler beim Löschen der URL')
    } finally {
        legalUrl.isDeleting = false
    }
}

// Lifecycle
onMounted(() => {
    loadLegalUrls()
})
</script>
