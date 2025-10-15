<template>
  <div class="w-full">
    <!-- Label -->
    <label
      v-if="label"
      :for="inputId"
      class="block text-sm font-medium text-gray-700 mb-2"
    >
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>

    <!-- Input Container -->
    <div class="relative">
      <input
        :id="inputId"
        v-model="internalValue"
        type="text"
        :placeholder="placeholder"
        :disabled="disabled || isCheckingNumber"
        :class="inputClasses"
        :autocomplete="autocomplete"
        @input="handleInput"
        @blur="handleBlur"
        @focus="handleFocus"
      />

      <!-- Status Icons -->
      <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
        <!-- Loading Spinner -->
        <LoaderIcon
          v-if="isCheckingNumber"
          class="h-5 w-5 animate-spin text-gray-400"
        />

        <!-- Success Checkmark -->
        <CheckIcon
          v-else-if="showSuccessIcon"
          class="h-5 w-5 text-green-500"
        />

        <!-- Error Icon -->
        <AlertCircleIcon
          v-else-if="showErrorIcon"
          class="h-5 w-5 text-red-500"
        />
      </div>
    </div>

    <!-- Status Messages -->
    <div class="mt-1 min-h-[1.25rem]">
      <!-- Error Message -->
      <p v-if="errorMessage" class="text-sm text-red-600">
        {{ errorMessage }}
      </p>

      <!-- Loading Message -->
      <p v-else-if="isCheckingNumber && showLoadingMessage" class="text-sm text-gray-500">
        {{ loadingMessage }}
      </p>

      <!-- Success Message -->
      <p v-else-if="showSuccessMessage" class="text-sm text-green-600">
        {{ successMessage }}
      </p>

      <!-- Help Text -->
      <p v-else-if="helpText && !isTouched" class="text-sm text-gray-500">
        {{ helpText }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { CheckIcon, LoaderIcon, AlertCircleIcon } from 'lucide-vue-next'
import { debounce } from 'lodash'
import axios from 'axios'

// Props
const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  label: {
    type: String,
    default: 'Mitgliedsnummer'
  },
  placeholder: {
    type: String,
    default: 'Leer lassen für automatische Nummer'
  },
  required: {
    type: Boolean,
    default: false
  },
  disabled: {
    type: Boolean,
    default: false
  },
  autocomplete: {
    type: String,
    default: 'off'
  },
  helpText: {
    type: String,
    default: 'Wenn leer, wird automatisch eine Nummer generiert'
  },
  successMessage: {
    type: String,
    default: 'Mitgliedsnummer ist verfügbar'
  },
  loadingMessage: {
    type: String,
    default: 'Prüfe Verfügbarkeit...'
  },
  showLoadingMessage: {
    type: Boolean,
    default: true
  },
  debounceMs: {
    type: Number,
    default: 800
  },
  checkUrl: {
    type: String,
    default: null
  },
  validateOnMount: {
    type: Boolean,
    default: false
  },
  id: {
    type: String,
    default: null
  },
  maxLength: {
    type: Number,
    default: 50
  },
  memberId: {
    type: Number,
    default: null
  }
})

// Emits
const emit = defineEmits([
  'update:modelValue',
  'validation-change',
  'check-start',
  'check-complete',
  'focus',
  'blur'
])

// State
const internalValue = ref(props.modelValue)
const isTouched = ref(false)
const isFocused = ref(false)
const isCheckingNumber = ref(false)
const errorMessage = ref('')
const lastCheckedNumber = ref('')

// Computed ID
const inputId = computed(() => props.id || `member-number-input-${Math.random().toString(36).substr(2, 9)}`)

// Validation state
const isValidFormat = computed(() => {
  if (!internalValue.value) return true // Empty is valid (optional field)
  const trimmed = internalValue.value.trim()
  // Allow alphanumeric characters, dashes, and underscores
  const memberNumberRegex = /^[a-zA-Z0-9_-]+$/
  return memberNumberRegex.test(trimmed) && trimmed.length <= props.maxLength
})

const isValid = computed(() => {
  // If empty and not required, valid
  if (!internalValue.value.trim() && !props.required) return true

  // If required but empty, invalid
  if (props.required && !internalValue.value.trim()) return false

  // Must have valid format
  if (!isValidFormat.value) return false

  // If currently checking, not valid yet
  if (isCheckingNumber.value) return false

  // If has error message, not valid
  if (errorMessage.value) return false

  // If check URL provided and number exists but not yet checked, not valid
  if (props.checkUrl && internalValue.value.trim() && lastCheckedNumber.value !== internalValue.value.trim()) {
    return false
  }

  return true
})

const hasError = computed(() => {
  return !!errorMessage.value
})

// UI State
const showSuccessIcon = computed(() => {
  return isTouched.value && isValid.value && internalValue.value && lastCheckedNumber.value === internalValue.value.trim()
})

const showErrorIcon = computed(() => {
  return isTouched.value && hasError.value
})

const showSuccessMessage = computed(() => {
  return showSuccessIcon.value && props.successMessage
})

// Input classes
const inputClasses = computed(() => [
  'w-full px-3 py-2 border rounded-md transition-colors duration-200',
  'focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500',
  'placeholder-gray-400',
  'pr-10', // Space for icons
  {
    // Normal state
    'border-gray-300': !isTouched.value || (!hasError.value && !showSuccessIcon.value),

    // Error state
    'border-red-500 focus:ring-red-500 focus:border-red-500': hasError.value,

    // Success state
    'border-green-500 focus:ring-green-500 focus:border-green-500': showSuccessIcon.value,

    // Disabled state
    'bg-gray-50 text-gray-500 cursor-not-allowed': props.disabled || isCheckingNumber.value,
  }
])

// Validation logic
const validateFormat = (value) => {
  const trimmedValue = value.trim()

  if (!trimmedValue && props.required) {
    errorMessage.value = 'Mitgliedsnummer ist erforderlich'
    emitValidationChange()
    return false
  }

  if (!trimmedValue) {
    errorMessage.value = ''
    emitValidationChange()
    return true
  }

  if (trimmedValue.length > props.maxLength) {
    errorMessage.value = `Mitgliedsnummer ist zu lang (maximal ${props.maxLength} Zeichen)`
    emitValidationChange()
    return false
  }

  if (!isValidFormat.value) {
    errorMessage.value = 'Nur Buchstaben, Zahlen, Bindestriche und Unterstriche erlaubt'
    emitValidationChange()
    return false
  }

  // Clear format errors, but don't clear server validation errors
  if (errorMessage.value === 'Mitgliedsnummer ist erforderlich' ||
      errorMessage.value === `Mitgliedsnummer ist zu lang (maximal ${props.maxLength} Zeichen)` ||
      errorMessage.value === 'Nur Buchstaben, Zahlen, Bindestriche und Unterstriche erlaubt') {
    errorMessage.value = ''
  }

  emitValidationChange()
  return true
}

// Server-side availability check
const checkNumberAvailability = async (number) => {
  if (!number || !isValidFormat.value || !props.checkUrl) {
    emitValidationChange()
    return
  }

  // Don't check if already checked
  if (number === lastCheckedNumber.value) {
    emitValidationChange()
    return
  }

  isCheckingNumber.value = true
  emit('check-start', number)
  emitValidationChange()

  try {
    const payload = { member_number: number }
    if (props.memberId) {
      payload.member_id = props.memberId
    }
    const response = await axios.post(props.checkUrl, payload)

    // Only process response if this is still the current number
    if (number === internalValue.value.trim()) {
      if (response.data.exists) {
        errorMessage.value = response.data.message || 'Diese Mitgliedsnummer ist bereits vergeben'
      } else {
        errorMessage.value = ''
        lastCheckedNumber.value = number
      }
    }
  } catch (error) {
    console.error('Member number check error:', error)

    // Only process error if this is still the current number
    if (number === internalValue.value.trim()) {
      if (error.response) {
        const data = error.response.data

        if (error.response.status === 422) {
          errorMessage.value = data.message || 'Ungültige Mitgliedsnummer'
        } else if (error.response.status === 400) {
          errorMessage.value = data.error || 'Fehler bei der Prüfung'
        } else {
          errorMessage.value = 'Server-Fehler bei der Prüfung. Bitte versuchen Sie es erneut.'
        }
      } else if (error.request) {
        errorMessage.value = 'Netzwerk-Fehler. Bitte versuchen Sie es erneut.'
      } else {
        errorMessage.value = 'Fehler bei der Prüfung. Bitte versuchen Sie es erneut.'
      }
    }
  } finally {
    isCheckingNumber.value = false
    emit('check-complete', number, !errorMessage.value)
    emitValidationChange()
  }
}

// Debounced check
const checkNumberAvailabilityDebounced = debounce(checkNumberAvailability, props.debounceMs)

// Event handlers
const handleInput = (event) => {
  const value = event.target.value
  internalValue.value = value
  emit('update:modelValue', value)

  // Reset server validation when number changes
  if (value.trim() !== lastCheckedNumber.value) {
    lastCheckedNumber.value = ''
  }

  if (isTouched.value) {
    validateFormat(value)

    if (isValidFormat.value && props.checkUrl && value.trim()) {
      checkNumberAvailabilityDebounced(value.trim())
    } else {
      checkNumberAvailabilityDebounced.cancel()
      lastCheckedNumber.value = ''
      emitValidationChange()
    }
  } else {
    emitValidationChange()
  }
}

const handleBlur = (event) => {
  isTouched.value = true
  isFocused.value = false

  validateFormat(internalValue.value)

  if (isValidFormat.value && props.checkUrl && internalValue.value.trim()) {
    checkNumberAvailabilityDebounced(internalValue.value.trim())
  }

  emit('blur', event)
}

const handleFocus = (event) => {
  isFocused.value = true
  emit('focus', event)
}

// Validation change emission
const emitValidationChange = () => {
  const state = {
    isValid: isValid.value,
    hasError: hasError.value,
    isChecking: isCheckingNumber.value,
    errorMessage: errorMessage.value,
    value: internalValue.value.trim()
  }

  emit('validation-change', state)
}

// Public methods
const validate = () => {
  isTouched.value = true
  const formatValid = validateFormat(internalValue.value)

  if (formatValid && isValidFormat.value && props.checkUrl && internalValue.value.trim()) {
    checkNumberAvailability(internalValue.value.trim())
  }

  return isValid.value
}

const clearError = () => {
  errorMessage.value = ''
  emitValidationChange()
}

const reset = () => {
  internalValue.value = ''
  isTouched.value = false
  errorMessage.value = ''
  lastCheckedNumber.value = ''
  checkNumberAvailabilityDebounced.cancel()
  emit('update:modelValue', '')
  emitValidationChange()
}

// Watch modelValue changes from parent
watch(() => props.modelValue, (newValue) => {
  if (newValue !== internalValue.value) {
    internalValue.value = newValue

    if (isTouched.value) {
      validateFormat(newValue)
    }

    emitValidationChange()
  }
})

// Watch for changes in computed values to emit updates
watch([isValid, hasError, isCheckingNumber, errorMessage], () => {
  emitValidationChange()
})

// Mount logic
onMounted(async () => {
  if (props.validateOnMount && internalValue.value) {
    await nextTick()
    isTouched.value = true
    validate()
  }
})

// Cleanup
onUnmounted(() => {
  checkNumberAvailabilityDebounced.cancel()
})

// Expose public methods
defineExpose({
  validate,
  clearError,
  reset,
  focus: () => document.getElementById(inputId.value)?.focus(),
  blur: () => document.getElementById(inputId.value)?.blur()
})
</script>
