<template>
  <div v-if="showPagination" ref="root" class="bg-surface px-4 py-3 flex items-center justify-between border-t border-line sm:px-6">
    <!-- Mobile Pagination -->
    <div class="flex-1 flex justify-between sm:hidden">
      <button
        v-if="data.prev_page_url"
        :disabled="isLoading"
        aria-keyshortcuts="ArrowLeft"
        @click="navigate(data.prev_page_url)"
        class="disabled:opacity-50 relative inline-flex items-center px-4 py-2 border border-line-strong text-sm font-medium rounded-md text-ink-secondary bg-surface hover:bg-surface-muted"
      >
        Zurück
      </button>
      <button
        v-if="data.next_page_url"
        :disabled="isLoading"
        aria-keyshortcuts="ArrowRight"
        @click="navigate(data.next_page_url)"
        class="disabled:opacity-50 ml-3 relative inline-flex items-center px-4 py-2 border border-line-strong text-sm font-medium rounded-md text-ink-secondary bg-surface hover:bg-surface-muted"
      >
        Weiter
      </button>
    </div>

    <!-- Desktop Pagination -->
    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
      <!-- Results Info -->
      <div>
        <p class="text-sm text-ink-secondary">
          <template v-if="data.total > 0">
            Zeige
            <span class="font-medium">{{ data.from || 0 }}</span>
            bis
            <span class="font-medium">{{ data.to || 0 }}</span>
            von
            <span v-if="hideTotal" class="font-medium blur-sm select-none" aria-hidden="true">000</span>
            <span v-else class="font-medium">{{ data.total }}</span>
            {{ itemLabel }}
          </template>
          <template v-else>
            Keine {{ itemLabel }} gefunden
          </template>
          <!-- Only where a keyboard is likely; touch screens have none. -->
          <span v-if="data.total > 0" class="ml-3 hidden text-xs text-ink-muted pointer-fine:inline" aria-hidden="true">
            <kbd class="rounded border border-line-strong px-1 font-sans">←</kbd>
            <kbd class="rounded border border-line-strong px-1 font-sans">→</kbd>
            zum Blättern
          </span>
        </p>
      </div>

      <!-- Pagination Controls -->
      <div v-if="data.total > 0">
        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
          <!-- Previous Button -->
          <button
            v-if="data.prev_page_url"
            aria-keyshortcuts="ArrowLeft"
            title="Vorherige Seite (←)"
            @click="navigate(data.prev_page_url)"
            class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-line-strong bg-surface text-sm font-medium text-ink-muted hover:bg-surface-muted focus:z-10"
            :disabled="isLoading"
          >
            <span class="sr-only">Zurück</span>
            <ChevronLeft class="h-5 w-5" />
          </button>
          <span
            v-else
            class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-line-strong bg-surface-muted text-sm font-medium text-ink-disabled cursor-not-allowed"
          >
            <ChevronLeft class="h-5 w-5" />
          </span>

          <!-- Page Numbers -->
          <template v-for="(link, index) in visibleLinks" :key="`page-${index}`">
            <!-- Active Page -->
            <span
              v-if="link.active"
              class="relative inline-flex items-center px-4 py-2 border border-brand bg-brand-subtle text-sm font-medium text-brand-ink z-10"
              v-html="link.label"
            />
            <!-- Clickable Page -->
            <button
              v-else-if="link.url"
              @click="navigate(link.url)"
              class="relative inline-flex items-center px-4 py-2 border border-line-strong bg-surface text-sm font-medium text-ink-secondary hover:bg-surface-muted focus:z-10"
              :disabled="isLoading"
              v-html="link.label"
            />
            <!-- Dots -->
            <span
              v-else
              class="relative inline-flex items-center px-4 py-2 border border-line-strong bg-surface text-sm font-medium text-ink-secondary"
            >
              ...
            </span>
          </template>

          <!-- Next Button -->
          <button
            v-if="data.next_page_url"
            aria-keyshortcuts="ArrowRight"
            title="Nächste Seite (→)"
            @click="navigate(data.next_page_url)"
            class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-line-strong bg-surface text-sm font-medium text-ink-muted hover:bg-surface-muted focus:z-10"
            :disabled="isLoading"
          >
            <span class="sr-only">Weiter</span>
            <ChevronRight class="h-5 w-5" />
          </button>
          <span
            v-else
            class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-line-strong bg-surface-muted text-sm font-medium text-ink-disabled cursor-not-allowed"
          >
            <ChevronRight class="h-5 w-5" />
          </span>
        </nav>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { ChevronLeft, ChevronRight } from '@lucide/vue'
import { focusListIfPaginating, scrollListIntoView } from '@/utils/listScroll'
import { paginationDirection } from '@/utils/paginationKeys'

// Props
const props = defineProps({
  // Paginated data object from Laravel
  data: {
    type: Object,
    required: true,
    validator: (value) => {
      // Validate that it has the required pagination properties
      return value &&
        'total' in value &&
        'links' in value
    }
  },
  // Label for items (e.g., "Mitglieder", "Zahlungen")
  itemLabel: {
    type: String,
    default: 'Ergebnisse'
  },
  // Blur the total count (e.g. for users without permission to see it)
  hideTotal: {
    type: Boolean,
    default: false
  },
  // Whether to preserve scroll position on navigation
  preserveScroll: {
    type: Boolean,
    default: true
  },
  // Whether to preserve component state on navigation
  preserveState: {
    type: Boolean,
    default: true
  },
  // Loading state
  isLoading: {
    type: Boolean,
    default: false
  },
  // Whether to use Inertia router or emit events
  useInertia: {
    type: Boolean,
    default: true
  },
  // Element whose top is brought back into view on a page change (usually
  // the list container). Give it tabindex="-1" so keyboard focus can follow.
  scrollTarget: {
    default: null
  },
  // Maximum number of page links to show
  maxVisiblePages: {
    type: Number,
    default: 7
  }
})

// Emits
const emit = defineEmits(['navigate'])

const root = ref(null)

// Computed
const showPagination = computed(() => {
  return props.data &&
    props.data.links &&
    props.data.links.length > 3 && // More than just prev, current, next
    props.data.total > 0
})

const visibleLinks = computed(() => {
  if (!props.data.links) return []

  // Filter out Previous and Next text links (keep only page numbers)
  return props.data.links.filter(link =>
    !link.label.includes('Previous') &&
    !link.label.includes('Next') &&
    !link.label.includes('&laquo;') &&
    !link.label.includes('&raquo;')
  )
})

// Methods
const navigate = (url) => {
  if (!url || props.isLoading) return

  if (props.useInertia && router) {
    // Scroll up right away, while the next page loads, so the skeleton
    // is what the user sees instead of the stale rows at the bottom.
    scrollListIntoView(props.scrollTarget)

    // Use Inertia router for navigation
    router.get(url, {}, {
      preserveState: props.preserveState,
      preserveScroll: props.preserveScroll,
      onStart: () => emit('navigate', { url, type: 'start' }),
      onFinish: () => {
        focusListIfPaginating(props.scrollTarget, root.value)
        emit('navigate', { url, type: 'finish' })
      }
    })
  } else {
    // Emit event for parent to handle
    emit('navigate', { url })
  }
}

// ← / → page through the list from anywhere on the page, unless the key
// belongs to a field, widget, dialog or horizontal scroller.
const onKeydown = event => {
  if (!showPagination.value || props.isLoading) return

  const direction = paginationDirection(event)
  const url = direction === 'prev' ? props.data.prev_page_url : direction === 'next' ? props.data.next_page_url : null
  if (!url) return

  event.preventDefault()
  navigate(url)
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))

// Expose methods if needed
defineExpose({
  navigate
})
</script>
