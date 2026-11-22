<template>
  <transition
    enter-active-class="transform ease-out duration-300 transition"
    enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
    enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
    leave-active-class="transition ease-in duration-100"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0"
  >
    <div
      v-if="show"
      :role="type === 'error' ? 'alert' : 'status'"
      class="pointer-events-auto w-full max-w-sm overflow-hidden rounded-lg border border-line bg-surface shadow-lg"
    >
      <div class="p-4">
        <div class="flex items-start">
          <div class="flex-shrink-0">
            <component :is="config.icon" :class="['w-6 h-6', config.iconClass]" aria-hidden="true" />
          </div>
          <div class="ml-3 w-0 flex-1 pt-0.5">
            <p class="text-sm font-medium text-ink">{{ title }}</p>
            <p v-if="message" class="mt-1 text-sm text-ink-muted">{{ message }}</p>
          </div>
          <div class="ml-4 flex flex-shrink-0">
            <button
              type="button"
              class="inline-flex rounded-md bg-surface text-ink-muted hover:text-ink-muted"
              @click="dismiss"
            >
              <span class="sr-only">Schließen</span>
              <X class="w-5 h-5" aria-hidden="true" />
            </button>
          </div>
        </div>
      </div>
    </div>
  </transition>
</template>

<script setup>
import { computed, ref } from 'vue'
import { CheckCircle, XCircle, AlertTriangle, Info, X } from '@lucide/vue'

const props = defineProps({
  type: {
    type: String,
    default: 'success',
    validator: (value) => ['success', 'error', 'warning', 'info'].includes(value),
  },
  title: {
    type: String,
    required: true,
  },
  message: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['dismiss'])

const show = ref(true)

const typeConfig = {
  success: { icon: CheckCircle, iconClass: 'text-success' },
  error: { icon: XCircle, iconClass: 'text-danger' },
  warning: { icon: AlertTriangle, iconClass: 'text-warning' },
  info: { icon: Info, iconClass: 'text-info' },
}

const config = computed(() => typeConfig[props.type] ?? typeConfig.success)

function dismiss() {
  show.value = false
  emit('dismiss')
}
</script>
