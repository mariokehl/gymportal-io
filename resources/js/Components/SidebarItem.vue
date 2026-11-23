<template>
  <div>
    <component
      :is="getComponentType"
      :href="getHref"
      @click="handleClick"
      :aria-current="isCurrentPage ? 'page' : undefined"
      :aria-disabled="disabled ? 'true' : undefined"
      :title="disabled ? lockedHint : undefined"
      :class="[
        'w-full flex items-center px-4 py-3 text-sm font-medium text-left transition-colors',
        {
          'bg-brand-subtle text-brand-ink border-r-2 border-brand': active && !disabled,
          'text-ink-secondary hover:bg-surface-muted hover:text-ink': !active && !disabled,
          'text-ink-disabled cursor-not-allowed': disabled,
        }
      ]"
    >
      <component
        :is="icon"
        aria-hidden="true"
        :class="[
          'w-5 h-5 mr-3',
          {
            'text-brand-ink': active && !disabled,
            'text-ink-secondary': !active && !disabled,
            'text-ink-disabled': disabled,
          }
        ]"
      />

      <span class="flex-1">
        {{ label }}
        <span v-if="disabled" class="sr-only">, gesperrt: {{ lockedHint }}</span>
      </span>

      <!-- Lock icon for gated items -->
      <svg
        v-if="disabled"
        aria-hidden="true"
        class="h-4 w-4 text-ink-disabled ml-2"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
      </svg>
    </component>

    <!-- Submenu (only visible while active) -->
    <div v-if="active && $slots.children" class="bg-brand-subtle/50">
      <slot name="children" />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  icon: {
    type: [Object, Function],
    required: true
  },
  label: {
    type: String,
    required: true
  },
  active: {
    type: Boolean,
    default: false
  },
  href: {
    type: String,
    default: null
  },
  disabled: {
    type: Boolean,
    default: false
  },
  // Whether this item is the page being shown. Defaults to `active`; parents
  // with sub-pages pass false while one of their children is the current page.
  current: {
    type: Boolean,
    default: null
  }
})

const lockedHint = 'Nur mit aktivem Abo oder in der Testphase verfügbar'

const isCurrentPage = computed(() => {
  if (props.disabled || !props.href) return false
  return props.current ?? props.active
})

const emit = defineEmits(['click'])

const getComponentType = computed(() => {
  if (props.disabled) return 'div'
  if (props.href) return Link
  return 'button'
})

const getHref = computed(() => {
  if (props.disabled) return undefined
  return props.href
})

const handleClick = (event) => {
  if (props.disabled) {
    event.preventDefault()
    return
  }

  emit('click', event)
}
</script>
