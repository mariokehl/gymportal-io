<template>
  <div class="space-y-4">
    <div class="flex justify-between items-center">
      <h3 class="text-lg font-semibold text-gray-900">Gebuchte Add-ons</h3>
    </div>

    <!-- Add-ons list -->
    <div v-if="bookedAddons.length > 0" class="space-y-3">
      <div
        v-for="addon in bookedAddons"
        :key="addon.key"
        class="border border-gray-200 rounded-lg p-4 flex items-start justify-between gap-4"
      >
        <div class="min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="font-medium text-gray-900">{{ addon.name }}</span>
            <span
              v-if="addon.mode === 'included'"
              class="px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800"
            >
              Inklusive
            </span>
            <span
              v-else
              class="px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700"
            >
              Optional
            </span>
            <span
              v-if="addon.completedAt"
              class="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 inline-flex items-center gap-1"
            >
              <CheckCircle class="w-3.5 h-3.5" />
              Erledigt
            </span>
          </div>
          <p class="text-sm text-gray-500 mt-1">
            Vertrag: {{ addon.planName }}
          </p>
          <p v-if="addon.bookedAt" class="text-xs text-gray-400 mt-0.5">
            Gebucht am {{ formatDate(addon.bookedAt) }}
          </p>
          <p v-if="addon.completedAt" class="text-xs text-emerald-600 mt-0.5">
            Erledigt am {{ formatDateTime(addon.completedAt) }}<template v-if="addon.completedByName"> · von {{ addon.completedByName }}</template>
          </p>
        </div>

        <div class="flex flex-col items-end gap-2 flex-shrink-0">
          <div class="text-right">
            <template v-if="addon.mode === 'included'">
              <div class="text-sm text-gray-400 line-through">{{ formatPrice(addon.basePrice) }}</div>
              <div class="font-semibold text-green-700">geschenkt</div>
            </template>
            <template v-else>
              <div class="font-semibold text-gray-900">{{ formatPrice(addon.price) }}</div>
              <div class="text-xs text-gray-400">einmalig</div>
            </template>
          </div>

          <button
            type="button"
            :disabled="togglingKey === addon.key"
            @click="toggleCompletion(addon)"
            :class="[
              'px-3 py-1.5 rounded-md text-xs font-medium transition-colors disabled:opacity-50',
              addon.completedAt
                ? 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                : 'bg-emerald-600 text-white hover:bg-emerald-700'
            ]"
          >
            {{ addon.completedAt ? 'Als offen markieren' : 'Als erledigt markieren' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="border border-dashed border-gray-200 rounded-lg p-6 text-center">
      <p class="text-sm text-gray-500">Für dieses Mitglied sind keine Add-ons gebucht.</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { CheckCircle } from 'lucide-vue-next'
import { formatPrice, formatDate, formatDateTime } from '@/utils/formatters'

const props = defineProps({
  member: {
    type: Object,
    required: true
  }
})

const togglingKey = ref(null)

// Flatten the add-ons booked across all of the member's memberships
// (addon_membership pivot) into a single list for display.
const bookedAddons = computed(() => {
  const memberships = props.member?.memberships || []
  const list = []

  memberships.forEach((membership) => {
    const planName = membership.membership_plan?.name || 'Unbekannt'

    ;(membership.addons || []).forEach((addon) => {
      const pivot = addon.pivot || {}
      list.push({
        key: `${membership.id}-${addon.id}`,
        membershipId: membership.id,
        addonId: addon.id,
        name: addon.name,
        planName,
        mode: pivot.mode,
        // Snapshot price stored on the pivot (0 for included add-ons).
        price: pivot.price,
        // The add-on's list price, used to show the struck-through value.
        basePrice: addon.price,
        bookedAt: pivot.created_at,
        completedAt: pivot.completed_at,
        completedByName: pivot.completed_by_name
      })
    })
  })

  return list
})

const toggleCompletion = (addon) => {
  togglingKey.value = addon.key

  router.put(
    route('members.memberships.addons.toggle-completion', {
      member: props.member.id,
      membership: addon.membershipId,
      addon: addon.addonId
    }),
    {},
    {
      preserveScroll: true,
      onFinish: () => {
        togglingKey.value = null
      }
    }
  )
}
</script>
