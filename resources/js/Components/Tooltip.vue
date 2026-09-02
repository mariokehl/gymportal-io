<template>
  <div class="relative inline-block">
    <div
      ref="triggerRef"
      @mouseenter="showTooltip"
      @mouseleave="hideTooltip"
      @focus="showTooltip"
      @blur="hideTooltip"
    >
      <slot></slot>
    </div>

    <!-- Teleported variant: escapes ancestors with overflow (e.g. a scrolling
         table), which would otherwise clip the panel and widen the scroll area.
         Positioned against the viewport, so it follows fixed coordinates. -->
    <Teleport v-if="teleport" to="body">
      <Transition name="tooltip">
        <div
          v-if="isVisible"
          :class="tooltipClasses"
          :style="tooltipStyles"
        >
          <div class="w-full">
            <slot name="content">
              {{ text }}
            </slot>
          </div>
          <div :class="arrowClasses"></div>
        </div>
      </Transition>
    </Teleport>

    <Transition v-else name="tooltip">
      <div
        v-if="isVisible"
        :class="tooltipClasses"
        :style="tooltipStyles"
      >
        <!-- Custom Content Slot mit Fallback auf text prop -->
        <div class="w-full">
          <slot name="content">
            {{ text }}
          </slot>
        </div>
        <div :class="arrowClasses"></div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, useSlots, onMounted, onBeforeUnmount } from 'vue'

// Props
const props = defineProps({
  text: {
    type: String,
    default: ''
  },
  position: {
    type: String,
    default: 'top',
    validator: (value) => ['top', 'right', 'bottom', 'left'].includes(value)
  },
  maxWidth: {
    type: String,
    default: '320px' // Erweiterte Default-Breite für komplexe Inhalte
  },
  theme: {
    type: String,
    default: 'dark',
    validator: (value) => ['dark', 'light'].includes(value)
  },
  // Render into the body instead of next to the trigger. Needed whenever an
  // ancestor clips overflow, such as a horizontally scrolling table.
  teleport: {
    type: Boolean,
    default: false
  }
})

// Slots
const slots = useSlots()

// State
const isVisible = ref(false)
const triggerRef = ref(null)
const triggerRect = ref(null)

// Methods
const showTooltip = () => {
  // Measure the trigger before showing, so the teleported panel is placed
  // against the viewport on its very first frame instead of jumping there.
  if (props.teleport) {
    triggerRect.value = triggerRef.value?.getBoundingClientRect() ?? null
  }

  isVisible.value = true
}

const hideTooltip = () => {
  isVisible.value = false
  triggerRect.value = null
}

// A teleported panel keeps viewport coordinates, so scrolling would leave it
// behind. Hiding on scroll is both simpler and less distracting than tracking.
onMounted(() => {
  if (props.teleport) {
    window.addEventListener('scroll', hideTooltip, true)
  }
})

onBeforeUnmount(() => {
  if (props.teleport) {
    window.removeEventListener('scroll', hideTooltip, true)
  }
})

// Computed Properties
const tooltipClasses = computed(() => {
  // Base classes mit conditional theme
  const themeClasses = props.theme === 'dark'
    ? 'text-white text-xs bg-gray-900'
    : 'text-gray-900 text-xs bg-white border border-gray-200'

  // Whitespace handling - nur nowrap bei einfachem Text (kein content slot).
  // Mit content slot darf der Text umbrechen, sonst sprengt er die maxWidth.
  const whitespaceClass = !slots.content ? 'whitespace-nowrap' : 'whitespace-normal break-words'

  // Text alignment - always left-aligned for consistent display
  const textAlign = 'text-left'

  const baseClasses = `z-50 px-3 py-2 text-sm ${themeClasses} ${textAlign} rounded-lg shadow-lg pointer-events-none ${whitespaceClass}`

  // Teleported panels sit in the body and are placed via inline coordinates,
  // so they must not carry the trigger-relative offsets.
  if (props.teleport) {
    return `fixed ${baseClasses} tooltip-${props.position}`
  }

  const positionClasses = {
    top: 'bottom-full left-1/2 -translate-x-1/2 mb-2',
    right: 'left-full top-1/2 -translate-y-1/2 ml-2',
    bottom: 'top-full left-1/2 -translate-x-1/2 mt-2',
    left: 'right-full top-1/2 -translate-y-1/2 mr-2'
  }

  return `absolute ${baseClasses} ${positionClasses[props.position]} tooltip-${props.position}`
})

const GAP = 8

const tooltipStyles = computed(() => {
  const styles = { maxWidth: props.maxWidth }

  if (!props.teleport || !triggerRect.value) {
    return styles
  }

  const rect = triggerRect.value

  // Anchor to the trigger, then keep the panel inside the viewport. The width
  // is capped by maxWidth, so a long text wraps instead of overflowing.
  const placements = {
    top: { bottom: `${window.innerHeight - rect.top + GAP}px`, left: `${rect.left + rect.width / 2}px`, transform: 'translateX(-50%)' },
    bottom: { top: `${rect.bottom + GAP}px`, left: `${rect.left + rect.width / 2}px`, transform: 'translateX(-50%)' },
    left: { top: `${rect.top + rect.height / 2}px`, right: `${window.innerWidth - rect.left + GAP}px`, transform: 'translateY(-50%)' },
    right: { top: `${rect.top + rect.height / 2}px`, left: `${rect.right + GAP}px`, transform: 'translateY(-50%)' }
  }

  return { ...styles, ...placements[props.position] }
})

const darkArrowPositions = {
  top: 'top-full left-1/2 -translate-x-1/2 border-l-[6px] border-l-transparent border-r-[6px] border-r-transparent border-t-[6px] border-t-gray-900',
  right: 'right-full top-1/2 -translate-y-1/2 border-t-[6px] border-t-transparent border-b-[6px] border-b-transparent border-r-[6px] border-r-gray-900',
  bottom: 'bottom-full left-1/2 -translate-x-1/2 border-l-[6px] border-l-transparent border-r-[6px] border-r-transparent border-b-[6px] border-b-gray-900',
  left: 'left-full top-1/2 -translate-y-1/2 border-t-[6px] border-t-transparent border-b-[6px] border-b-transparent border-l-[6px] border-l-gray-900'
}

const lightArrowPositions = {
  top: 'top-full left-1/2 -translate-x-1/2 border-l-[6px] border-l-transparent border-r-[6px] border-r-transparent border-t-[6px] border-t-white',
  right: 'right-full top-1/2 -translate-y-1/2 border-t-[6px] border-t-transparent border-b-[6px] border-b-transparent border-r-[6px] border-r-white',
  bottom: 'bottom-full left-1/2 -translate-x-1/2 border-l-[6px] border-l-transparent border-r-[6px] border-r-transparent border-b-[6px] border-b-white',
  left: 'left-full top-1/2 -translate-y-1/2 border-t-[6px] border-t-transparent border-b-[6px] border-b-transparent border-l-[6px] border-l-white'
}

const arrowClasses = computed(() => {
  const positions = props.theme === 'dark' ? darkArrowPositions : lightArrowPositions
  return `absolute w-0 h-0 border-solid ${positions[props.position]}`
})
</script>

<style scoped>
/* Transition animations */
/* Fade only. A directional slide would need a transform, and the teleported
   variant already spends its transform on centring the panel — animating both
   the same way in every variant keeps the tooltips consistent. */
.tooltip-enter-active,
.tooltip-leave-active {
  transition: opacity 0.15s;
}

.tooltip-enter-from,
.tooltip-leave-to {
  opacity: 0;
}
</style>
