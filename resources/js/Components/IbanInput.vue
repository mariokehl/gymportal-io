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
      type="text"
      autocomplete="off"
      spellcheck="false"
    />

    <!-- Validation Messages -->
    <div v-if="validation.errors.length > 0" class="mt-1 space-y-1">
      <div
        v-for="error in validation.errors"
        :key="error"
        class="text-red-600 text-sm flex items-center gap-1"
      >
        <XCircle class="w-3 h-3 flex-shrink-0" />
        {{ error }}
      </div>
    </div>

    <!-- Success Message -->
    <div v-else-if="validation.isValid && modelValue" class="mt-1">
      <div class="text-green-600 text-sm flex items-center gap-1">
        <CheckCircle class="w-3 h-3 flex-shrink-0" />
        Gültige IBAN für {{ validation.countryName }}
      </div>
    </div>

    <!-- Format Hint -->
    <div v-if="showHint && !modelValue" class="mt-1 text-xs text-gray-500">
      {{ formatHint }}
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { CheckCircle, XCircle } from 'lucide-vue-next'
import { formatIbanDisplay } from '@/utils/formatters'

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

// DACH Country formats
const supportedCountries = {
  'DE': { length: 22, name: 'Deutschland' },
  'AT': { length: 20, name: 'Österreich' },
  'CH': { length: 21, name: 'Schweiz' }
}

// Computed
const maxDisplayLength = computed(() => {
  // Längste IBAN (DE) mit Leerzeichen: 22 + 5 Leerzeichen = 27
  return 27
})

const inputClasses = computed(() => {
  const baseClasses = 'w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 transition-colors'

  if (validation.value.errors.length > 0) {
    return `${baseClasses} border-red-300 focus:ring-red-500 focus:border-red-500`
  }

  if (validation.value.isValid) {
    return `${baseClasses} border-green-300 focus:ring-green-500 focus:border-green-500`
  }

  return `${baseClasses} border-gray-300 focus:ring-indigo-500 focus:border-indigo-500`
})

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

/**
 * Entfernt alle Leerzeichen aus einer IBAN
 */
const cleanIban = (iban) => {
  if (!iban) return ''
  return iban.replace(/\s/g, '').toUpperCase()
}

/**
 * MOD-97 Algorithmus für IBAN Prüfziffer-Validierung
 */
const calculateIbanChecksum = (iban) => {
  const cleanedIban = cleanIban(iban)

  // Verschiebe die ersten 4 Zeichen ans Ende
  const rearranged = cleanedIban.slice(4) + cleanedIban.slice(0, 4)

  // Ersetze Buchstaben durch Zahlen (A=10, B=11, ..., Z=35)
  let numericString = ''
  for (let char of rearranged) {
    if (char >= 'A' && char <= 'Z') {
      numericString += (char.charCodeAt(0) - 'A'.charCodeAt(0) + 10).toString()
    } else {
      numericString += char
    }
  }

  // MOD-97 Berechnung
  let remainder = 0
  for (let i = 0; i < numericString.length; i++) {
    remainder = (remainder * 10 + parseInt(numericString[i])) % 97
  }

  return remainder
}

/**
 * Validiert eine IBAN für DACH-Region
 */
const validateIban = (iban) => {
  const result = {
    isValid: false,
    errors: [],
    country: '',
    countryName: ''
  }

  if (!iban) {
    if (props.required) {
      result.errors.push('IBAN ist erforderlich')
    }
    return result
  }

  const cleanedIban = cleanIban(iban)

  // Mindestlänge prüfen
  if (cleanedIban.length < 15) {
    result.errors.push('IBAN ist zu kurz')
    return result
  }

  // Grundformat prüfen
  const formatRegex = /^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/
  if (!formatRegex.test(cleanedIban)) {
    result.errors.push('Ungültiges IBAN-Format')
    return result
  }

  // Ländercode extrahieren
  const countryCode = cleanedIban.slice(0, 2)
  result.country = countryCode

  // Nur DACH-Länder unterstützen
  if (!supportedCountries[countryCode]) {
    result.errors.push(`Nur Deutschland (DE), Österreich (AT) und Schweiz (CH) werden unterstützt`)
    return result
  }

  const countryFormat = supportedCountries[countryCode]
  result.countryName = countryFormat.name

  // Länderspezifische Länge prüfen
  if (cleanedIban.length !== countryFormat.length) {
    result.errors.push(
      `IBAN für ${countryFormat.name} muss ${countryFormat.length} Zeichen haben (aktuell: ${cleanedIban.length})`
    )
    return result
  }

  // Prüfziffer validieren
  const checksum = calculateIbanChecksum(cleanedIban)
  if (checksum !== 1) {
    result.errors.push('Ungültige Prüfziffer')
    return result
  }

  // Alles OK
  result.isValid = true
  return result
}

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
