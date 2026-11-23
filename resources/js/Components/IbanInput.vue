<template>
  <div>
    <input
      ref="inputRef"
      :value="formattedValue"
      @input="handleInput"
      @blur="handleBlur"
      :placeholder="placeholder"
      :disabled="disabled"
      :maxlength="maxDisplayLength"
      :class="inputClasses"
      :aria-invalid="validation.errors.length > 0 ? 'true' : undefined"
      type="text"
      autocomplete="off"
      spellcheck="false"
    />

    <!-- Validation Messages -->
    <div v-if="validation.errors.length > 0" class="mt-1 space-y-1">
      <div
        v-for="error in validation.errors"
        :key="error"
        class="text-danger text-sm flex items-center gap-1"
      >
        <XCircle class="w-3 h-3 flex-shrink-0" />
        {{ error }}
      </div>
    </div>

    <!-- Success Message -->
    <div v-else-if="validation.isValid && modelValue" class="mt-1">
      <div class="text-success text-sm flex items-center gap-1">
        <CheckCircle class="w-3 h-3 flex-shrink-0" />
        Gültige IBAN für {{ validation.countryName }}
      </div>
    </div>

    <!-- Format Hint -->
    <div v-if="showHint && !modelValue" class="mt-1 text-xs text-ink-muted">
      {{ formatHint }}
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { CheckCircle, XCircle } from '@lucide/vue'
import { formatIbanDisplay } from '@/utils/formatters'
import { MAX_FORMATTED_IBAN_LENGTH, cleanIban, validateIban as validateSepaIban } from '@/utils/iban'

// Props
const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  placeholder: {
    type: String,
    default: 'DE89 3704 0044 0532 0130 00'
  },
  disabled: {
    type: Boolean,
    default: false
  },
  required: {
    type: Boolean,
    default: false
  },
  showHint: {
    type: Boolean,
    default: true
  },
  formatHint: {
    type: String,
    default: 'Format: Ländercode (2 Buchstaben) + Prüfziffer (2 Ziffern) + Kontonummer'
  }
})

// Emits
const emit = defineEmits(['update:modelValue', 'validation-change'])

// Refs
const inputRef = ref(null)

// Longest SEPA IBAN (Malta) including group spaces
const maxDisplayLength = MAX_FORMATTED_IBAN_LENGTH

// Computed
// Border, focus and the error state (aria-invalid) come from `.field`.
const inputClasses = computed(() => [
  'field w-full px-3 py-2',
  { 'border-success': validation.value.isValid && validation.value.errors.length === 0 },
])

// Reactive validation state
const validation = ref({
  isValid: false,
  errors: [],
  country: '',
  countryName: ''
})

// Computed formatted value for display
const formattedValue = computed(() => {
  if (!props.modelValue) return ''
  return formatIbanDisplay(props.modelValue)
})

const validateIban = (iban) => validateSepaIban(iban, { required: props.required })

/**
 * Behandelt Eingabe mit automatischer Formatierung
 */
const handleInput = (event) => {
  const input = event.target
  const value = input.value
  const cursorPosition = input.selectionStart

  // Nur gültige Zeichen zulassen (Buchstaben, Zahlen, Leerzeichen)
  const filteredValue = value.replace(/[^A-Za-z0-9\s]/g, '')

  // Bereinigen und validieren
  const cleanValue = cleanIban(filteredValue)
  const newValidation = validateIban(cleanValue)

  validation.value = newValidation

  // Model Value aktualisieren (ohne Leerzeichen)
  emit('update:modelValue', cleanValue)
  emit('validation-change', newValidation)

  // Input formatieren
  const formatted = formatIbanDisplay(cleanValue)

  // Cursor-Position nach Formatierung korrigieren
  nextTick(() => {
    if (input === document.activeElement) {
      // Berechne neue Cursor-Position basierend auf hinzugefügten Leerzeichen
      const charsBeforeCursor = value.slice(0, cursorPosition).replace(/\s/g, '').length
      const formattedCharsBeforeCursor = formatted.replace(/\s/g, '').slice(0, charsBeforeCursor).length

      // Zähle Leerzeichen vor der gewünschten Position
      let newCursorPos = 0
      let charCount = 0

      for (let i = 0; i < formatted.length && charCount < formattedCharsBeforeCursor; i++) {
        if (formatted[i] !== ' ') {
          charCount++
        }
        newCursorPos = i + 1
      }

      input.value = formatted
      input.setSelectionRange(newCursorPos, newCursorPos)
    }
  })
}

/**
 * Behandelt Fokus-Verlust
 */
const handleBlur = (event) => {
  const value = event.target.value
  if (value) {
    const cleanValue = cleanIban(value)
    const formatted = formatIbanDisplay(cleanValue)
    const newValidation = validateIban(cleanValue)

    validation.value = newValidation
    event.target.value = formatted

    emit('update:modelValue', cleanValue)
    emit('validation-change', newValidation)
  }
}

// Watchers
watch(() => props.modelValue, (newValue) => {
  if (newValue) {
    const newValidation = validateIban(newValue)
    validation.value = newValidation
    emit('validation-change', newValidation)
  } else {
    validation.value = { isValid: false, errors: [], country: '', countryName: '' }
    emit('validation-change', validation.value)
  }
}, { immediate: true })

// Expose validation state for parent components
defineExpose({
  validation,
  isValid: computed(() => validation.value.isValid),
  focus: () => inputRef.value?.focus()
})
</script>
