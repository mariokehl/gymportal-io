import { ref, watch, unref } from 'vue'
import { router } from '@inertiajs/vue3'
import { resolveTab, urlWithTab } from '@/utils/tabQuery'

/**
 * Active-tab state mirrored into the `?tab=` query parameter.
 *
 * @param {Array|import('vue').Ref<Array>} tabs Tab definitions ({ key } or { id })
 * @param {string} fallback Tab shown when the URL names none or an unknown one
 */
export function useTabQuery(tabs, fallback) {
  const allowed = () => unref(tabs).map(tab => tab.key ?? tab.id)

  const activeTab = ref(resolveTab(window.location.search, allowed(), fallback))

  watch(activeTab, (tab) => {
    // Client-side replace: updates Inertia's page URL without a server round trip.
    router.replace({
      url: urlWithTab(window.location.href, tab, fallback),
      preserveState: true,
      preserveScroll: true,
    })
  })

  return activeTab
}
