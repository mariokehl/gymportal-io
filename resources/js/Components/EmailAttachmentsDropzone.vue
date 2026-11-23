<template>
    <div class="space-y-4">
        <!-- Existing Attachments List -->
        <div v-if="props.attachments.length > 0" class="space-y-2">
            <div v-for="attachment in props.attachments" :key="attachment.id"
                class="flex items-center justify-between p-3 border border-line rounded-lg bg-surface-muted group hover:bg-fill transition-colors">
                <div class="flex items-center min-w-0 flex-1">
                    <div class="w-8 h-8 bg-brand-soft rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                        <component :is="FileText" class="w-4 h-4 text-brand-ink" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-ink truncate">{{ attachment.file_name }}</p>
                        <p class="text-xs text-ink-muted">{{ attachment.formatted_file_size }}</p>
                    </div>
                </div>
                <button @click="deleteAttachment(attachment)"
                    :disabled="deletingId === attachment.id"
                    class="p-2 text-danger hover:text-danger-strong transition-colors disabled:opacity-50 flex-shrink-0"
                    title="Anhang löschen">
                    <component :is="deletingId === attachment.id ? Loader2 : Trash2"
                        :class="['w-4 h-4', { 'animate-spin': deletingId === attachment.id }]" />
                </button>
            </div>
        </div>

        <!-- Upload Dropzone -->
        <div v-if="props.attachments.length < maxAttachments">
            <div
                @dragover.prevent="onDragOver"
                @dragleave.prevent="onDragLeave"
                @drop.prevent="onDrop"
                @click="triggerFileInput"
                :class="[
                    'border-2 border-dashed rounded-lg p-6 text-center transition-colors cursor-pointer',
                    isDragging
                        ? 'border-brand bg-brand-subtle'
                        : 'border-line-strong hover:border-brand'
                ]">
                <div v-if="isUploading" class="flex flex-col items-center">
                    <component :is="Loader2" class="w-8 h-8 text-brand-ink animate-spin" />
                    <p class="mt-2 text-sm font-medium text-ink-secondary">Wird hochgeladen...</p>
                </div>
                <div v-else>
                    <component :is="Upload" class="mx-auto w-8 h-8 text-ink-muted" />
                    <div class="mt-2">
                        <p class="text-sm font-medium text-ink-secondary">
                            <span class="text-brand-ink">Datei auswählen</span> oder per Drag & Drop
                        </p>
                        <p class="mt-1 text-xs text-ink-muted">
                            Nur PDF-Dateien, max. 10 MB ({{ props.attachments.length }}/{{ maxAttachments }} Anhänge)
                        </p>
                    </div>
                </div>
            </div>

            <input
                ref="fileInput"
                type="file"
                class="sr-only"
                accept="application/pdf"
                @change="onFileSelected" />
        </div>

        <!-- Max attachments reached info -->
        <div v-else class="flex items-center justify-center p-4 border border-line rounded-lg bg-surface-muted">
            <component :is="Paperclip" class="w-4 h-4 text-ink-muted mr-2" />
            <p class="text-sm text-ink-muted">Maximale Anzahl an Anhängen erreicht ({{ maxAttachments }}/{{ maxAttachments }})</p>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'
import { Paperclip, Upload, Trash2, FileText, Loader2 } from '@lucide/vue'
import { useToast } from '@/composables/useToast'

const props = defineProps({
    templateId: Number,
    attachments: {
        type: Array,
        default: () => []
    }
})

const emit = defineEmits(['attachments-updated'])

const { success: showSuccess, error: showError } = useToast()

const maxAttachments = 5
const maxFileSize = 10 * 1024 * 1024 // 10 MB

const fileInput = ref(null)
const isDragging = ref(false)
const isUploading = ref(false)
const deletingId = ref(null)

const triggerFileInput = () => {
    if (isUploading.value) return
    fileInput.value?.click()
}

const onDragOver = () => {
    isDragging.value = true
}

const onDragLeave = () => {
    isDragging.value = false
}

const onDrop = (event) => {
    isDragging.value = false
    const files = event.dataTransfer.files
    if (files.length > 0) {
        handleFile(files[0])
    }
}

const onFileSelected = (event) => {
    const file = event.target.files[0]
    if (file) {
        handleFile(file)
    }
    // Reset input so the same file can be selected again
    if (fileInput.value) {
        fileInput.value.value = ''
    }
}

const validateFile = (file) => {
    if (props.attachments.length >= maxAttachments) {
        showError(`Maximale Anzahl an Anhängen erreicht (${maxAttachments}).`)
        return false
    }

    if (file.type !== 'application/pdf') {
        showError('Nur PDF-Dateien sind erlaubt.')
        return false
    }

    if (file.size > maxFileSize) {
        showError('Die Datei ist zu groß. Maximal 10 MB sind erlaubt.')
        return false
    }

    return true
}

const handleFile = async (file) => {
    if (!validateFile(file)) {
        return
    }

    await uploadFile(file)
}

const uploadFile = async (file) => {
    isUploading.value = true

    const formData = new FormData()
    formData.append('file', file)

    try {
        await axios.post(
            `/settings/email-templates/${props.templateId}/attachments`,
            formData,
            {
                headers: {
                    'Content-Type': 'multipart/form-data'
                }
            }
        )

        showSuccess('Anhang erfolgreich hochgeladen.')
        emit('attachments-updated')
    } catch (error) {
        console.error('Fehler beim Hochladen des Anhangs:', error)

        const message = error.response?.data?.message
            || error.response?.data?.errors?.file?.[0]
            || 'Fehler beim Hochladen des Anhangs.'
        showError(message)
    } finally {
        isUploading.value = false
    }
}

const deleteAttachment = async (attachment) => {
    if (!confirm(`Möchten Sie den Anhang "${attachment.file_name}" wirklich löschen?`)) {
        return
    }

    deletingId.value = attachment.id

    try {
        await axios.delete(
            `/settings/email-templates/${props.templateId}/attachments/${attachment.id}`
        )

        showSuccess('Anhang erfolgreich gelöscht.')
        emit('attachments-updated')
    } catch (error) {
        console.error('Fehler beim Löschen des Anhangs:', error)

        const message = error.response?.data?.message || 'Fehler beim Löschen des Anhangs.'
        showError(message)
    } finally {
        deletingId.value = null
    }
}
</script>
