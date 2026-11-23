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
        'group flex w-full items-center gap-2.5 rounded-md px-2 h-9 pointer-coarse:h-11 text-sm font-medium text-left transition-colors',
        {
          'bg-brand-subtle text-brand-ink': highlighted,
          'text-ink hover:bg-fill': expanded && !highlighted,
          'text-ink-secondary hover:bg-fill hover:text-ink': !active && !disabled,
          'text-ink-disabled cursor-not-allowed': disabled,
        }
      ]"
    >
      <component
        :is="icon"
        aria-hidden="true"
        :stroke-width="1.5"
        :class="[
          'size-[18px] flex-none',
          {
            'text-brand-ink': highlighted,
            'text-ink-secondary': expanded && !highlighted,
            'text-ink-muted group-hover:text-ink-secondary': !active && !disabled,
            'text-ink-disabled': disabled,
          }
        ]"
      />

      <span class="flex-1 truncate">
        {{ label }}
        <span v-if="disabled" class="sr-only">, gesperrt: {{ lockedHint }}</span>
      </span>

      <!-- Lock icon for gated items -->
      <Lock v-if="disabled" aria-hidden="true" :stroke-width="1.5" class="size-4 flex-none text-ink-disabled" />
    </component>

    <!-- Sub-pages, only while the section is open -->
    <div v-if="expanded && $slots.children" class="mt-0.5 flex flex-col gap-0.5">
      <slot name="children" />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Lock } from '@lucide/vue'

const props = defineProps({
  icon: {
    type: [Object, Function],
    required: true
  },
  label: {
    type: String,
    required: true
  },
  // The section is open: this page or one of its sub-pages is shown.
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

const expanded = computed(() => props.active && !props.disabled)

// Only the page being shown carries the selection; an open parent of a
// sub-page stays plain so the selection never shows twice.
const highlighted = computed(() => expanded.value && (props.current ?? props.active))

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
