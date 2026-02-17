<template>
    <div class="space-y-4">
        <!-- Existing Attachments List -->
        <div v-if="props.attachments.length > 0" class="space-y-2">
            <div v-for="attachment in props.attachments" :key="attachment.id"
                class="flex items-center justify-between p-3 border border-gray-200 rounded-lg bg-gray-50 group hover:bg-gray-100 transition-colors">
                <div class="flex items-center min-w-0 flex-1">
                    <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                        <component :is="FileText" class="w-4 h-4 text-indigo-600" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-900 truncate">{{ attachment.file_name }}</p>
                        <p class="text-xs text-gray-500">{{ attachment.formatted_file_size }}</p>
                    </div>
                </div>
                <button @click="deleteAttachment(attachment)"
                    :disabled="deletingId === attachment.id"
                    class="p-2 text-red-600 hover:text-red-800 transition-colors disabled:opacity-50 flex-shrink-0"
                    title="Anhang löschen">
                    <component :is="deletingId === attachment.id ? Loader2 : Trash2"
                        :class="['w-4 h-4', { 'animate-spin': deletingId === attachment.id }]" />
                </button>
            </div>
        </div>

        <!-- Error Message -->
        <div v-if="errorMessage" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded text-sm">
            {{ errorMessage }}
        </div>

        <!-- Success Message -->
        <div v-if="successMessage" class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded text-sm">
            {{ successMessage }}
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
                        ? 'border-indigo-500 bg-indigo-50'
                        : 'border-gray-300 hover:border-indigo-400'
                ]">
                <div v-if="isUploading" class="flex flex-col items-center">
                    <component :is="Loader2" class="w-8 h-8 text-indigo-500 animate-spin" />
                    <p class="mt-2 text-sm font-medium text-gray-700">Wird hochgeladen...</p>
                </div>
                <div v-else>
                    <component :is="Upload" class="mx-auto w-8 h-8 text-gray-400" />
                    <div class="mt-2">
                        <p class="text-sm font-medium text-gray-700">
                            <span class="text-indigo-600">Datei auswählen</span> oder per Drag & Drop
                        </p>
                        <p class="mt-1 text-xs text-gray-500">
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
        <div v-else class="flex items-center justify-center p-4 border border-gray-200 rounded-lg bg-gray-50">
            <component :is="Paperclip" class="w-4 h-4 text-gray-400 mr-2" />
            <p class="text-sm text-gray-500">Maximale Anzahl an Anhängen erreicht ({{ maxAttachments }}/{{ maxAttachments }})</p>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'
import { Paperclip, Upload, Trash2, FileText, Loader2 } from 'lucide-vue-next'

const props = defineProps({
    templateId: Number,
    attachments: {
        type: Array,
        default: () => []
    }
})

const emit = defineEmits(['attachments-updated'])

const maxAttachments = 5
const maxFileSize = 10 * 1024 * 1024 // 10 MB

const fileInput = ref(null)
const isDragging = ref(false)
const isUploading = ref(false)
const deletingId = ref(null)
const errorMessage = ref('')
const successMessage = ref('')

const clearMessages = () => {
    errorMessage.value = ''
    successMessage.value = ''
}

const showError = (message) => {
    errorMessage.value = message
    successMessage.value = ''
    setTimeout(() => errorMessage.value = '', 5000)
}

const showSuccess = (message) => {
    successMessage.value = message
    errorMessage.value = ''
    setTimeout(() => successMessage.value = '', 3000)
}

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
    clearMessages()

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

    clearMessages()
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
