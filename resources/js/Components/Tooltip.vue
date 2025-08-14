<template>
  <div class="relative inline-block">
    <div
      @mouseenter="showTooltip"
      @mouseleave="hideTooltip"
      @focus="showTooltip"
      @blur="hideTooltip"
    >
      <slot></slot>
    </div>
    <Transition name="tooltip">
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
import { ref, computed, useSlots } from 'vue'

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
  }
})

// Slots
const slots = useSlots()

// State
const isVisible = ref(false)

// Methods
const showTooltip = () => {
  isVisible.value = true
}

const hideTooltip = () => {
  isVisible.value = false
}

// Computed Properties
const tooltipClasses = computed(() => {
  // Base classes mit conditional theme
  const themeClasses = props.theme === 'dark'
    ? 'text-white text-xs bg-gray-900'
    : 'text-gray-900 text-xs bg-white border border-gray-200'

  // Whitespace handling - nur nowrap bei einfachem Text (kein content slot)
  const whitespaceClass = !slots.content ? 'whitespace-nowrap' : ''

  // Text alignment - always left-aligned for consistent display
  const textAlign = 'text-left'

  const baseClasses = `absolute z-50 px-3 py-2 text-sm ${themeClasses} ${textAlign} rounded-lg shadow-lg pointer-events-none ${whitespaceClass}`

  const positionClasses = {
    top: 'bottom-full left-1/2 -translate-x-1/2 mb-2',
    right: 'left-full top-1/2 -translate-y-1/2 ml-2',
    bottom: 'top-full left-1/2 -translate-x-1/2 mt-2',
    left: 'right-full top-1/2 -translate-y-1/2 mr-2'
  }

  return `${baseClasses} ${positionClasses[props.position]} tooltip-${props.position}`
})

const tooltipStyles = computed(() => {
  return {
    maxWidth: props.maxWidth
  }
})

const arrowClasses = computed(() => {
  const baseClasses = 'absolute w-0 h-0 border-solid'

  const arrowColor = props.theme === 'dark' ? 'gray-900' : 'white'

  const arrowPositions = {
    top: `top-full left-1/2 -translate-x-1/2 border-l-[6px] border-l-transparent border-r-[6px] border-r-transparent border-t-[6px] border-t-${arrowColor}`,
    right: `right-full top-1/2 -translate-y-1/2 border-t-[6px] border-t-transparent border-b-[6px] border-b-transparent border-r-[6px] border-r-${arrowColor}`,
    bottom: `bottom-full left-1/2 -translate-x-1/2 border-l-[6px] border-l-transparent border-r-[6px] border-r-transparent border-b-[6px] border-b-${arrowColor}`,
    left: `left-full top-1/2 -translate-y-1/2 border-t-[6px] border-t-transparent border-b-[6px] border-b-transparent border-l-[6px] border-l-${arrowColor}`
  }

  return `${baseClasses} ${arrowPositions[props.position]}`
})
</script>

<style scoped>
/* Transition animations */
.tooltip-enter-active,
.tooltip-leave-active {
  transition: opacity 0.2s, transform 0.2s;
}

.tooltip-enter-from,
.tooltip-leave-to {
  opacity: 0;
}

.tooltip-enter-from.tooltip-top,
.tooltip-leave-to.tooltip-top {
  transform: translateY(4px);
}

.tooltip-enter-from.tooltip-bottom,
.tooltip-leave-to.tooltip-bottom {
  transform: translateY(-4px);
}

.tooltip-enter-from.tooltip-left,
.tooltip-leave-to.tooltip-left {
  transform: translateX(4px);
}

.tooltip-enter-from.tooltip-right,
.tooltip-leave-to.tooltip-right {
  transform: translateX(-4px);
}
</style>
