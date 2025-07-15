<template>
  <nav v-if="links && links.length > 3" class="flex items-center justify-between">
    <!-- Mobile View -->
    <div class="flex-1 flex justify-between sm:hidden">
      <Link
        v-if="prevPageUrl"
        :href="prevPageUrl"
        class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
      >
        Vorherige
      </Link>
      <Link
        v-if="nextPageUrl"
        :href="nextPageUrl"
        class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
      >
        Nächste
      </Link>
    </div>

    <!-- Desktop View -->
    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
      <div>
        <p class="text-sm text-gray-700">
          <span v-if="from && to">
            Zeige {{ from }} bis {{ to }} von {{ total }} Einträgen
          </span>
          <span v-else-if="total === 0">
            Keine Einträge gefunden
          </span>
          <span v-else>
            {{ total }} Einträge gesamt
          </span>
        </p>
      </div>

      <div>
        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
          <template v-for="(link, index) in links" :key="`${link.label}-${index}`">
            <!-- Link mit URL -->
            <Link
              v-if="link.url"
              :href="link.url"
              class="relative inline-flex items-center px-4 py-2 border text-sm font-medium hover:bg-gray-50 transition-colors"
              :class="[
                link.active
                  ? 'bg-blue-50 border-blue-500 text-blue-600 z-10'
                  : 'bg-white border-gray-300 text-gray-700',
                index === 0 ? 'rounded-l-md' : '',
                index === links.length - 1 ? 'rounded-r-md' : ''
              ]"
              v-html="link.label"
            />

            <!-- Disabled Link ohne URL -->
            <span
              v-else
              class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-gray-100 text-sm font-medium text-gray-400 cursor-not-allowed"
              :class="[
                index === 0 ? 'rounded-l-md' : '',
                index === links.length - 1 ? 'rounded-r-md' : ''
              ]"
              v-html="link.label"
            />
          </template>
        </nav>
      </div>
    </div>
  </nav>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
  links: {
    type: Array,
    default: () => []
  },
  from: {
    type: Number,
    default: null
  },
  to: {
    type: Number,
    default: null
  },
  total: {
    type: Number,
    default: 0
  },
  prevPageUrl: {
    type: String,
    default: null
  },
  nextPageUrl: {
    type: String,
    default: null
  }
})
</script>
