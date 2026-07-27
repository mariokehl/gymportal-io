<template>
  <!-- Pill-style tab rail (consistent across all breakpoints). When `sticky`
       is enabled the rail sticks below the app header once the page is
       scrolled on mobile; on desktop it always stays in normal flow. -->
  <nav
    class="flex gap-2 overflow-x-auto gp-tab-rail"
    :class="sticky
      ? '-mx-4 px-4 py-2 sm:mx-0 sm:px-0 sm:py-1 sticky z-20 bg-gray-100 border-b border-gray-200 lg:static lg:z-auto lg:bg-transparent lg:border-b-0'
      : 'py-1'"
    :style="sticky ? { top: 'var(--gp-header-height, 56px)' } : undefined"
  >
    <button
      v-for="tab in normalizedTabs"
      :key="tab.id"
      type="button"
      @click="$emit('update:modelValue', tab.id)"
      :class="[
        modelValue === tab.id
          ? 'bg-indigo-600 text-white border-transparent shadow-[0_2px_8px_rgba(79,70,229,0.3)]'
          : 'bg-white text-gray-600 border-gray-200 shadow-sm hover:border-gray-300',
        'flex-none whitespace-nowrap flex items-center gap-1.5 px-4 py-2.5 rounded-full border text-sm font-medium transition-colors'
      ]"
    >
      <component :is="tab.icon" v-if="tab.icon" class="w-4 h-4" />
      {{ tab.name }}
      <!-- Per-tab badge; parents render badge content via a scoped slot and
           receive the active state so they can style it to match the rail. -->
      <slot name="badge" :tab="tab" :active="modelValue === tab.id" />
    </button>
  </nav>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  // Currently active tab id (v-model).
  modelValue: {
    type: [String, Number],
    default: null,
  },
  // Tab definitions. Each tab accepts either `id`/`name` or `key`/`label`
  // so existing pages can pass their current shape unchanged.
  tabs: {
    type: Array,
    required: true,
  },
  // Enable the sticky-below-header behaviour used on the member detail page.
  sticky: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['update:modelValue'])

// Normalize the two supported tab shapes into a single { id, name, icon, ... }
// form while keeping the original fields available for slot consumers.
const normalizedTabs = computed(() =>
  props.tabs.map(tab => ({
    ...tab,
    id: tab.id ?? tab.key,
    name: tab.name ?? tab.label,
    icon: tab.icon,
  }))
)
</script>

<style scoped>
/* Hide the horizontal scrollbar on the tab rail */
.gp-tab-rail {
  scrollbar-width: none;
  -ms-overflow-style: none;
}
.gp-tab-rail::-webkit-scrollbar {
  display: none;
}
</style>
