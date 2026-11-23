<template>
  <div>
    <!-- Scrolls horizontally like a plain overflow-x container (trackpad,
         Shift+wheel, keyboard), but its own scrollbar is hidden: the bar
         below takes over. Classes and attributes land here. -->
    <div
      ref="content"
      v-bind="$attrs"
      class="overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
      @scroll="syncFrom(content, bar)"
    >
      <slot />
    </div>

    <!-- Stays at the bottom of the screen while the content is in view and
         rests right under it at its end, so the scrollbar is always reachable
         on long lists. Sits above the mobile bottom bar when there is one.
         Mouse only: keyboard and screen reader users scroll the content. -->
    <div
      v-show="overflowing"
      ref="bar"
      class="sticky-scrollbar sticky z-10 overflow-x-auto overflow-y-hidden border-t border-line bg-surface"
      style="bottom: var(--bottom-bar-height, 0px)"
      aria-hidden="true"
      @scroll="syncFrom(bar, content)"
    >
      <div class="h-px" :style="{ width: `${scrollWidth}px` }" />
    </div>
  </div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, onUpdated, ref } from 'vue'

// The stuck bar only works when no ancestor between this component and the
// page's scroll region clips with `overflow: hidden` (that makes it the
// sticky container); use `overflow-clip` on rounded cards instead.
defineOptions({ inheritAttrs: false })

const content = ref(null)
const bar = ref(null)
const scrollWidth = ref(0)
const overflowing = ref(false)

let observer = null

const measure = () => {
  const element = content.value
  if (!element) return

  scrollWidth.value = element.scrollWidth
  overflowing.value = element.scrollWidth > element.clientWidth + 1

  // Keep the bar in step, e.g. after the content got narrower.
  nextTick(() => syncFrom(content.value, bar.value))
}

// Setting an equal scrollLeft fires no further scroll event, so the two
// handlers don't ping-pong.
const syncFrom = (source, target) => {
  if (source && target && target.scrollLeft !== source.scrollLeft) {
    target.scrollLeft = source.scrollLeft
  }
}

onMounted(() => {
  measure()

  if (typeof ResizeObserver !== 'undefined') {
    observer = new ResizeObserver(measure)
    observer.observe(content.value)
  }
})

// New rows or columns can change the content width without resizing the
// container itself.
onUpdated(measure)

onBeforeUnmount(() => observer?.disconnect())
</script>

<style scoped>
/* Always visible, also with macOS overlay scrollbars that would otherwise
   only show while scrolling. */
.sticky-scrollbar::-webkit-scrollbar {
  height: 12px;
}

.sticky-scrollbar::-webkit-scrollbar-thumb {
  background-color: var(--color-line-strong);
  background-clip: padding-box;
  border: 3px solid transparent;
  border-radius: 9999px;
}

.sticky-scrollbar::-webkit-scrollbar-thumb:hover {
  background-color: var(--color-ink-muted);
}

@supports (-moz-appearance: none) {
  .sticky-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: var(--color-line-strong) transparent;
  }
}
</style>
