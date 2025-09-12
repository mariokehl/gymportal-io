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
        type="email"
        :placeholder="placeholder"
        :disabled="disabled || isCheckingEmail"
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
          v-if="isCheckingEmail"
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
      <p v-else-if="isCheckingEmail && showLoadingMessage" class="text-sm text-gray-500">
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
    default: 'E-Mail'
  },
  placeholder: {
    type: String,
    default: 'ihre@email.de'
  },
  required: {
    type: Boolean,
    default: true
  },
  disabled: {
    type: Boolean,
    default: false
  },
  autocomplete: {
    type: String,
    default: 'email'
  },
  helpText: {
    type: String,
    default: null
  },
  successMessage: {
    type: String,
    default: 'E-Mail-Adresse ist verfügbar'
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
  }
})

// Emits
const emit = defineEmits([
  'update:modelValue',
  'validation-change',
  'email-check-start',
  'email-check-complete',
  'focus',
  'blur'
])

// State
const internalValue = ref(props.modelValue)
const isTouched = ref(false)
const isFocused = ref(false)
const isCheckingEmail = ref(false)
const errorMessage = ref('')
const lastCheckedEmail = ref('')

// Computed ID
const inputId = computed(() => props.id || `email-input-${Math.random().toString(36).substr(2, 9)}`)

// Validation state
const isValidFormat = computed(() => {
  if (!internalValue.value) return !props.required
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  return emailRegex.test(internalValue.value.trim())
})

const isValid = computed(() => {
  // Must have valid format
  if (!isValidFormat.value) return false

  // If required but empty, invalid
  if (props.required && !internalValue.value.trim()) return false

  // If currently checking, not valid yet
  if (isCheckingEmail.value) return false

  // If has error message, not valid
  if (errorMessage.value) return false

  // If check URL provided but email not yet checked, not valid
  if (props.checkUrl && internalValue.value.trim() && lastCheckedEmail.value !== internalValue.value.trim()) {
    return false
  }

  return true
})

const hasError = computed(() => {
  return !!errorMessage.value
})

// UI State
const showSuccessIcon = computed(() => {
  return isTouched.value && isValid.value && internalValue.value && lastCheckedEmail.value === internalValue.value.trim()
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
    'bg-gray-50 text-gray-500 cursor-not-allowed': props.disabled || isCheckingEmail.value,
  }
])

// Email validation logic
const validateEmailFormat = (email) => {
  const trimmedEmail = email.trim()

  if (!trimmedEmail && props.required) {
    errorMessage.value = 'E-Mail ist erforderlich'
    emitValidationChange()
    return false
  }

  if (!trimmedEmail) {
    errorMessage.value = ''
    emitValidationChange()
    return true
  }

  if (trimmedEmail.length > 255) {
    errorMessage.value = 'E-Mail-Adresse ist zu lang (maximal 255 Zeichen)'
    emitValidationChange()
    return false
  }

  if (!isValidFormat.value) {
    errorMessage.value = 'Bitte geben Sie eine gültige E-Mail-Adresse ein'
    emitValidationChange()
    return false
  }

  // Clear format errors, but don't clear server validation errors
  if (errorMessage.value === 'E-Mail ist erforderlich' ||
      errorMessage.value === 'E-Mail-Adresse ist zu lang (maximal 255 Zeichen)' ||
      errorMessage.value === 'Bitte geben Sie eine gültige E-Mail-Adresse ein') {
    errorMessage.value = ''
  }

  emitValidationChange()
  return true
}

// Server-side email check
const checkEmailAvailability = async (email) => {
  if (!email || !isValidFormat.value || !props.checkUrl) {
    emitValidationChange()
    return
  }

  // Don't check if already checked
  if (email === lastCheckedEmail.value) {
    emitValidationChange()
    return
  }

  isCheckingEmail.value = true
  emit('email-check-start', email)
  emitValidationChange() // Emit state change when checking starts

  try {
    const response = await axios.post(props.checkUrl, { email })

    // Only process response if this is still the current email
    if (email === internalValue.value.trim()) {
      if (response.data.exists) {
        errorMessage.value = response.data.message || 'Diese E-Mail-Adresse ist bereits registriert'
      } else {
        errorMessage.value = ''
        lastCheckedEmail.value = email
      }
    }
  } catch (error) {
    console.error('Email check error:', error)

    // Only process error if this is still the current email
    if (email === internalValue.value.trim()) {
      if (error.response) {
        // Server responded with error status
        const data = error.response.data

        if (error.response.status === 422) {
          // Validation error
          errorMessage.value = data.message || 'Ungültige E-Mail-Adresse'
        } else if (error.response.status === 400) {
          // Bad request (e.g., no gym found)
          errorMessage.value = data.error || 'Fehler bei der E-Mail-Prüfung'
        } else {
          // Other server errors
          errorMessage.value = 'Server-Fehler bei der E-Mail-Prüfung. Bitte versuchen Sie es erneut.'
        }
      } else if (error.request) {
        // Network error
        errorMessage.value = 'Netzwerk-Fehler bei der E-Mail-Prüfung. Bitte versuchen Sie es erneut.'
      } else {
        // Other error
        errorMessage.value = 'Fehler bei der E-Mail-Prüfung. Bitte versuchen Sie es erneut.'
      }
    }
  } finally {
    isCheckingEmail.value = false
    emit('email-check-complete', email, !errorMessage.value)
    emitValidationChange() // Always emit when checking completes
  }
}

// Debounced email check
const checkEmailAvailabilityDebounced = debounce(checkEmailAvailability, props.debounceMs)

// Event handlers
const handleInput = (event) => {
  const value = event.target.value
  internalValue.value = value
  emit('update:modelValue', value)

  // Reset server validation when email changes
  if (value.trim() !== lastCheckedEmail.value) {
    lastCheckedEmail.value = ''
  }

  if (isTouched.value) {
    validateEmailFormat(value)

    if (isValidFormat.value && props.checkUrl) {
      checkEmailAvailabilityDebounced(value.trim())
    } else {
      // Cancel pending check if format is invalid
      checkEmailAvailabilityDebounced.cancel()
      lastCheckedEmail.value = ''
      emitValidationChange()
    }
  } else {
    emitValidationChange()
  }
}

const handleBlur = (event) => {
  isTouched.value = true
  isFocused.value = false

  validateEmailFormat(internalValue.value)

  if (isValidFormat.value && props.checkUrl) {
    checkEmailAvailabilityDebounced(internalValue.value.trim())
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
    isChecking: isCheckingEmail.value,
    errorMessage: errorMessage.value,
    email: internalValue.value.trim()
  }

  emit('validation-change', state)
}

// Public methods (exposed via defineExpose)
const validate = () => {
  isTouched.value = true
  const formatValid = validateEmailFormat(internalValue.value)

  if (formatValid && isValidFormat.value && props.checkUrl) {
    checkEmailAvailability(internalValue.value.trim())
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
  lastCheckedEmail.value = ''
  checkEmailAvailabilityDebounced.cancel()
  emit('update:modelValue', '')
  emitValidationChange()
}

// Watch modelValue changes from parent
watch(() => props.modelValue, (newValue) => {
  if (newValue !== internalValue.value) {
    internalValue.value = newValue

    if (isTouched.value) {
      validateEmailFormat(newValue)
    }

    emitValidationChange()
  }
})

// Watch for changes in computed values to emit updates
watch([isValid, hasError, isCheckingEmail, errorMessage], () => {
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
  checkEmailAvailabilityDebounced.cancel()
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
