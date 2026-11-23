<template>
  <div class="flex flex-col gap-3.5">
    <!-- Check-ins list -->
    <div v-if="visibleCheckins.length > 0" class="rounded-lg border border-line bg-surface overflow-hidden">
      <!-- Horizontally scrollable table on narrow viewports -->
      <div class="overflow-x-auto">
        <table class="min-w-[640px] w-full border-collapse">
          <thead class="bg-surface-muted">
            <tr>
              <!-- Location marker; the icon and its tooltip carry the meaning -->
              <th class="w-px pl-3.5 pr-1 py-3"><span class="sr-only">Standort</span></th>
              <th class="pl-1 pr-3.5 py-3 text-left text-2xs font-medium tracking-wide text-ink-muted uppercase whitespace-nowrap">Datum</th>
              <th class="px-3.5 py-3 text-left text-2xs font-medium tracking-wide text-ink-muted uppercase whitespace-nowrap">Check-In</th>
              <th class="px-3.5 py-3 text-left text-2xs font-medium tracking-wide text-ink-muted uppercase whitespace-nowrap">Check-Out</th>
              <th class="px-3.5 py-3 text-left text-2xs font-medium tracking-wide text-ink-muted uppercase whitespace-nowrap">Dauer</th>
              <th class="px-3.5 py-3 text-left text-2xs font-medium tracking-wide text-ink-muted uppercase whitespace-nowrap">Methode</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line-subtle">
            <tr v-for="checkin in visibleCheckins" :key="checkin.id" class="hover:bg-surface-muted">
              <td class="w-px pl-3.5 pr-1 py-3.5 align-middle leading-none">
                <!-- Visit at the member's home location -->
                <Tooltip v-if="isHomeGym(checkin)" position="top" teleport text="Check-In am Heimatstandort">
                  <House class="w-4 h-4 text-ink-muted" aria-label="Check-In am Heimatstandort" />
                </Tooltip>
                <!-- Visit at another location of the organisation -->
                <Tooltip v-else position="top" teleport :text="`Fremd-Check-In: ${gymName(checkin)}`">
                  <MapPin class="w-4 h-4 text-warning" :aria-label="`Fremd-Check-In: ${gymName(checkin)}`" />
                </Tooltip>
              </td>
              <td class="pl-1 pr-3.5 py-3.5 text-sm font-medium text-ink whitespace-nowrap">{{ formatDate(checkin.check_in_time) }}</td>
              <td class="px-3.5 py-3.5 text-sm text-ink-secondary whitespace-nowrap">{{ formatTime(checkin.check_in_time) }}</td>
              <td class="px-3.5 py-3.5 text-sm text-ink-secondary whitespace-nowrap">
                {{ checkin.check_out_time ? formatTime(checkin.check_out_time) : '-' }}
              </td>
              <td class="px-3.5 py-3.5 text-sm text-ink-secondary whitespace-nowrap">
                {{ checkin.check_out_time ? calculateDuration(checkin.check_in_time, checkin.check_out_time) : '-' }}
              </td>
              <td class="px-3.5 py-3.5 whitespace-nowrap">
                <span
                  :class="[
                    'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                    checkin.check_in_method === 'nfc_card'
                      ? 'bg-purple-100 text-purple-800'
                      : checkin.check_in_method === 'manual'
                        ? 'bg-fill text-ink'
                        : 'bg-info-soft text-info-strong'
                  ]"
                >
                  <CreditCard v-if="checkin.check_in_method === 'nfc_card'" class="w-3 h-3 mr-1" />
                  <Edit v-else-if="checkin.check_in_method === 'manual'" class="w-3 h-3 mr-1" />
                  <QrCode v-else class="w-3 h-3 mr-1" />
                  {{ checkin.check_in_method_text || 'Unbekannt' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Scroll hint (mobile only; the table needs horizontal scrolling there) -->
      <div class="sm:hidden flex items-center gap-1.5 px-3.5 py-2.5 text-xs text-ink-muted border-t border-line-subtle">
        <MoveHorizontal class="w-3.5 h-3.5" />
        Zum Scrollen wischen
      </div>

      <div v-if="hasMoreCheckins" class="px-4 py-4 text-center border-t border-line-subtle">
        <button
          type="button"
          :disabled="loadingMoreCheckins"
          class="inline-flex items-center px-4 py-2 border border-line-strong rounded-md text-sm font-medium text-ink-secondary bg-surface hover:bg-surface-muted disabled:opacity-50"
          @click="loadMoreCheckins"
        >
          <Loader2 v-if="loadingMoreCheckins" class="w-4 h-4 mr-2 animate-spin" />
          {{ loadingMoreCheckins ? 'Laden...' : 'Weitere laden' }}
        </button>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="rounded-lg border border-line bg-surface text-center py-12">
      <Clock class="w-12 h-12 text-ink-muted mx-auto mb-4" />
      <p class="text-ink-muted">Keine Check-Ins vorhanden</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Clock, CreditCard, Edit, House, Loader2, MapPin, QrCode, MoveHorizontal } from '@lucide/vue'
import { formatDate, formatTime } from '@/utils/formatters'
import Tooltip from '@/Components/Tooltip.vue'

const props = defineProps({
  member: {
    type: Object,
    required: true,
  },
  // Total number of check-ins. Only the first page is delivered with the page;
  // the rest is fetched on demand.
  checkInsTotal: {
    type: Number,
    default: 0,
  },
})

// Computed rather than copied on mount, so the list follows Inertia reloads.
const initialCheckins = computed(() => props.member?.check_ins ?? [])

// Pages loaded on top of the initial one. Reset whenever Inertia delivers a
// fresh first page, so a reload never shows stale rows below the new ones.
const additionalCheckins = ref([])
const checkinsPage = ref(1)
const checkinsHasMore = ref(null)
const loadingMoreCheckins = ref(false)

watch(initialCheckins, () => {
  additionalCheckins.value = []
  checkinsPage.value = 1
  checkinsHasMore.value = null
})

const visibleCheckins = computed(() => [...initialCheckins.value, ...additionalCheckins.value])

// Before the first fetch the server-provided total decides; afterwards the
// endpoint's own has_more flag is authoritative.
const hasMoreCheckins = computed(() => checkinsHasMore.value !== null
  ? checkinsHasMore.value
  : visibleCheckins.value.length < props.checkInsTotal)

const loadMoreCheckins = async () => {
  if (loadingMoreCheckins.value || !hasMoreCheckins.value) return

  loadingMoreCheckins.value = true
  try {
    const response = await axios.get(route('members.check-ins', props.member.id), {
      params: { page: checkinsPage.value + 1 },
    })

    additionalCheckins.value.push(...(response.data.data ?? []))
    checkinsPage.value = response.data.current_page ?? checkinsPage.value + 1
    checkinsHasMore.value = response.data.has_more ?? false
  } catch (error) {
    console.error('Failed to load more check-ins:', error)
  } finally {
    loadingMoreCheckins.value = false
  }
}

// A check-in is booked against the visited location, so anything other than the
// member's home gym is a cross-location visit.
const isHomeGym = (checkin) => !checkin.gym_id || checkin.gym_id === props.member.gym_id

const gymName = (checkin) => checkin.gym?.display_name || checkin.gym?.name || 'Unbekannter Standort'

const calculateDuration = (checkIn, checkOut) => {
  if (!checkIn || !checkOut) return '-'
  const duration = new Date(checkOut) - new Date(checkIn)
  const hours = Math.floor(duration / (1000 * 60 * 60))
  const minutes = Math.floor((duration % (1000 * 60 * 60)) / (1000 * 60))
  return `${hours}h ${minutes}m`
}
</script>
