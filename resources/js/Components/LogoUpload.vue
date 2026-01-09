<template>
  <div class="md:col-span-2">
    <label class="block text-sm/6 font-medium text-gray-700">Logo</label>

    <!-- Upload Area - nur anzeigen wenn kein Logo vorhanden -->
    <div v-if="!logoPreview" class="mt-2 flex justify-center rounded-lg border border-dashed border-gray-900/25 px-6 py-10"
         :class="{ 'border-indigo-500 bg-indigo-50': isDragging }"
         @drop="handleDrop"
         @dragover.prevent="isDragging = true"
         @dragleave.prevent="isDragging = false"
         @dragenter.prevent>
      <div class="text-center">
        <svg class="mx-auto size-12 text-gray-300" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
          <path fill-rule="evenodd" d="M1.5 6a2.25 2.25 0 0 1 2.25-2.25h16.5A2.25 2.25 0 0 1 22.5 6v12a2.25 2.25 0 0 1-2.25 2.25H3.75A2.25 2.25 0 0 1 1.5 18V6ZM3 16.06V18c0 .414.336.75.75.75h16.5A.75.75 0 0 0 21 18v-1.94l-2.69-2.689a1.5 1.5 0 0 0-2.12 0l-.88.879.97.97a.75.75 0 1 1-1.06 1.06l-5.16-5.159a1.5 1.5 0 0 0-2.12 0L3 16.061Zm10.125-7.81a1.125 1.125 0 1 1 2.25 0 1.125 1.125 0 0 1-2.25 0Z" clip-rule="evenodd"></path>
        </svg>
        <div class="mt-4 flex text-sm/6 text-gray-600">
          <label for="logo-upload" class="relative cursor-pointer rounded-md bg-white font-semibold text-indigo-600 focus-within:ring-2 focus-within:ring-indigo-600 focus-within:ring-offset-2 focus-within:outline-hidden hover:text-indigo-500">
            <span>Hochladen einer Datei</span>
            <input id="logo-upload"
                   ref="fileInput"
                   name="logo-upload"
                   type="file"
                   class="sr-only"
                   accept="image/png,image/jpeg,image/gif"
                   @change="handleFileSelect">
          </label>
          <p class="pl-1">oder per Drag & Drop</p>
        </div>
        <p class="text-xs/5 text-gray-600">PNG, JPG, GIF bis zu 10 MB</p>
      </div>
    </div>

    <!-- Vorschau des hochgeladenen Logos -->
    <div v-if="logoPreview" class="mt-2">
      <div class="relative inline-block">
        <img :src="logoPreview"
             alt="Logo Vorschau"
             class="max-w-xs max-h-48 rounded-lg shadow-md">

        <!-- Remove Button -->
        <button @click="removeLogo"
                type="button"
                class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <!-- Info über die hochgeladene Datei -->
      <div v-if="fileName" class="mt-2 text-sm text-gray-600">
        <p><strong>Datei:</strong> {{ fileName }}</p>
        <p><strong>Größe:</strong> {{ formatFileSize(fileSize) }}</p>
      </div>
    </div>

    <!-- Upload Progress -->
    <div v-if="isUploading" class="mt-2">
      <div class="bg-gray-200 rounded-full h-2">
        <div class="bg-indigo-600 h-2 rounded-full transition-all duration-300"
             :style="{ width: uploadProgress + '%' }"></div>
      </div>
      <p class="text-sm text-gray-600 mt-1">Wird hochgeladen... {{ uploadProgress }}%</p>
    </div>

    <!-- Error Messages -->
    <div v-if="errorMessage" class="mt-2 text-sm text-red-600">
      {{ errorMessage }}
    </div>
  </div>
</template>

<script>
import { router } from '@inertiajs/vue3'

export default {
  props: {
    currentGym: {
      type: Object,
      required: true
    },
    modelValue: {
      type: String,
      default: null
    }
  },

  emits: ['update:modelValue'],

  data() {
    return {
      isDragging: false,
      logoPreview: null,
      fileName: '',
      fileSize: 0,
      selectedFile: null,
      isUploading: false,
      uploadProgress: 0,
      errorMessage: ''
    }
  },

  mounted() {
    // Aktuelles Logo laden falls vorhanden
    if (this.currentGym?.logo_url) {
      this.logoPreview = this.currentGym.logo_url
    }
  },

  methods: {
    handleDrop(e) {
      e.preventDefault()
      this.isDragging = false

      const files = e.dataTransfer.files
      if (files.length > 0) {
        this.processFile(files[0])
      }
    },

    handleFileSelect(e) {
      const file = e.target.files[0]
      if (file) {
        this.processFile(file)
      }
    },

    processFile(file) {
      this.errorMessage = ''

      // Validierung
      if (!this.validateFile(file)) {
        return
      }

      this.selectedFile = file
      this.fileName = file.name
      this.fileSize = file.size

      // Vorschau erstellen
      const reader = new FileReader()
      reader.onload = (e) => {
        this.logoPreview = e.target.result
      }
      reader.readAsDataURL(file)

      // Upload starten
      this.uploadFile(file)
    },

    validateFile(file) {
      // Dateityp prüfen
      const allowedTypes = ['image/png', 'image/jpeg', 'image/gif']
      if (!allowedTypes.includes(file.type)) {
        this.errorMessage = 'Nur PNG, JPG und GIF Dateien sind erlaubt.'
        return false
      }

      // Dateigröße prüfen (10 MB = 10 * 1024 * 1024 bytes)
      const maxSize = 10 * 1024 * 1024
      if (file.size > maxSize) {
        this.errorMessage = 'Die Datei ist zu groß. Maximal 10 MB sind erlaubt.'
        return false
      }

      return true
    },

    uploadFile(file) {
      this.isUploading = true
      this.uploadProgress = 0

      const formData = new FormData()
      formData.append('logo', file)
      formData.append('gym_id', this.currentGym.id)

      // Simuliere Upload Progress (in echter Implementierung würde dies über XMLHttpRequest oder ähnliches gesteuert)
      const progressInterval = setInterval(() => {
        if (this.uploadProgress < 90) {
          this.uploadProgress += Math.random() * 30
        }
      }, 200)

      router.post(route('settings.gym.logo.upload'), formData, {
        forceFormData: true,
        onSuccess: (response) => {
          clearInterval(progressInterval)
          this.uploadProgress = 100
          this.isUploading = false

          // Emit das neue logo_path
          this.$emit('update:modelValue', response.props.logoPath)

          setTimeout(() => {
            this.uploadProgress = 0
          }, 1000)
        },
        onError: (errors) => {
          clearInterval(progressInterval)
          this.isUploading = false
          this.uploadProgress = 0

          if (errors.logo) {
            this.errorMessage = errors.logo
          } else {
            this.errorMessage = 'Fehler beim Hochladen der Datei.'
          }

          // Vorschau entfernen bei Fehler
          this.removeLogo()
        }
      })
    },

    removeLogo() {
      this.logoPreview = null
      this.fileName = ''
      this.fileSize = 0
      this.selectedFile = null
      this.errorMessage = ''

      // File Input zurücksetzen
      if (this.$refs.fileInput) {
        this.$refs.fileInput.value = ''
      }

      // Falls bereits ein Logo hochgeladen war, Backend informieren
      if (this.currentGym?.logo_path) {
        router.delete(route('settings.gym.logo.delete'), {
          data: { gym_id: this.currentGym.id },
          onSuccess: () => {
            this.$emit('update:modelValue', null)
          }
        })
      } else {
        this.$emit('update:modelValue', null)
      }
    },

    formatFileSize(bytes) {
      if (bytes === 0) return '0 Bytes'

      const k = 1024
      const sizes = ['Bytes', 'KB', 'MB', 'GB']
      const i = Math.floor(Math.log(bytes) / Math.log(k))

      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
    }
  }
}
</script>
