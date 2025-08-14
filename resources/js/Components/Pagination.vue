<template>
  <div v-if="showPagination" class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
    <!-- Mobile Pagination -->
    <div class="flex-1 flex justify-between sm:hidden">
      <button
        v-if="data.prev_page_url"
        @click="navigate(data.prev_page_url)"
        class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
      >
        Zurück
      </button>
      <button
        v-if="data.next_page_url"
        @click="navigate(data.next_page_url)"
        class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
      >
        Weiter
      </button>
    </div>

    <!-- Desktop Pagination -->
    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
      <!-- Results Info -->
      <div>
        <p class="text-sm text-gray-700">
          <template v-if="data.total > 0">
            Zeige
            <span class="font-medium">{{ data.from || 0 }}</span>
            bis
            <span class="font-medium">{{ data.to || 0 }}</span>
            von
            <span class="font-medium">{{ data.total }}</span>
            {{ itemLabel }}
          </template>
          <template v-else>
            Keine {{ itemLabel }} gefunden
          </template>
        </p>
      </div>

      <!-- Pagination Controls -->
      <div v-if="data.total > 0">
        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
          <!-- Previous Button -->
          <button
            v-if="data.prev_page_url"
            @click="navigate(data.prev_page_url)"
            class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 focus:z-10 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            :disabled="isLoading"
          >
            <span class="sr-only">Zurück</span>
            <ChevronLeft class="h-5 w-5" />
          </button>
          <span
            v-else
            class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-gray-50 text-sm font-medium text-gray-300 cursor-not-allowed"
          >
            <ChevronLeft class="h-5 w-5" />
          </span>

          <!-- Page Numbers -->
          <template v-for="(link, index) in visibleLinks" :key="`page-${index}`">
            <!-- Active Page -->
            <span
              v-if="link.active"
              class="relative inline-flex items-center px-4 py-2 border border-indigo-500 bg-indigo-50 text-sm font-medium text-indigo-600 z-10"
              v-html="link.label"
            />
            <!-- Clickable Page -->
            <button
              v-else-if="link.url"
              @click="navigate(link.url)"
              class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:z-10 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              :disabled="isLoading"
              v-html="link.label"
            />
            <!-- Dots -->
            <span
              v-else
              class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700"
            >
              ...
            </span>
          </template>

          <!-- Next Button -->
          <button
            v-if="data.next_page_url"
            @click="navigate(data.next_page_url)"
            class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 focus:z-10 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            :disabled="isLoading"
          >
            <span class="sr-only">Weiter</span>
            <ChevronRight class="h-5 w-5" />
          </button>
          <span
            v-else
            class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-gray-50 text-sm font-medium text-gray-300 cursor-not-allowed"
          >
            <ChevronRight class="h-5 w-5" />
          </span>
        </nav>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'

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
  // Maximum number of page links to show
  maxVisiblePages: {
    type: Number,
    default: 7
  }
})

// Emits
const emit = defineEmits(['navigate'])

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
    // Use Inertia router for navigation
    router.get(url, {}, {
      preserveState: props.preserveState,
      preserveScroll: props.preserveScroll,
      onStart: () => emit('navigate', { url, type: 'start' }),
      onFinish: () => emit('navigate', { url, type: 'finish' })
    })
  } else {
    // Emit event for parent to handle
    emit('navigate', { url })
  }
}

// Expose methods if needed
defineExpose({
  navigate
})
</script>
