<template>
  <div class="flex flex-col gap-3.5">
    <div class="flex items-center justify-between gap-3 flex-wrap">
      <h3 class="text-lg font-bold text-gray-900">Gebuchte Add-ons</h3>
      <button
        v-if="membershipsWithBookableAddons.length > 0"
        type="button"
        class="inline-flex items-center gap-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 px-3.5 py-2 rounded-lg text-[13px] font-semibold transition-colors"
        @click="openBookingDialog"
      >
        <Plus class="w-3.5 h-3.5" />
        Add-on hinzufügen
      </button>
    </div>

    <!-- Add-ons list -->
    <div v-if="bookedAddons.length > 0" class="flex flex-col gap-3.5">
      <div
        v-for="addon in bookedAddons"
        :key="addon.key"
        class="rounded-lg border border-gray-200 bg-white overflow-hidden"
      >
        <!-- Body -->
        <div class="p-4 flex items-start justify-between gap-3 flex-wrap">
          <div class="flex gap-3 min-w-0">
            <span
              class="w-9.5 h-9.5 rounded-lg flex items-center justify-center flex-none"
              :class="addon.isUsageService ? 'bg-indigo-50 text-indigo-600' : 'bg-gray-100 text-gray-500'"
            >
              <component :is="addon.isUsageService ? CupSoda : BadgeCheck" class="w-4.5 h-4.5" />
            </span>

            <div class="min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="text-base font-bold text-gray-900">{{ addon.name }}</span>
                <span
                  v-if="addon.mode === 'included'"
                  class="px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800"
                >
                  Inklusive
                </span>
                <span
                  v-else
                  class="px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800"
                >
                  Optional
                </span>
                <span
                  class="px-2 py-0.5 rounded-full text-xs font-semibold"
                  :class="addon.isRecurring ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'"
                >
                  {{ addon.isRecurring ? 'Wiederkehrend' : 'Einmalig' }}
                </span>
                <span
                  v-if="addon.completedAt"
                  class="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 inline-flex items-center gap-1"
                >
                  <CheckCircle class="w-3.5 h-3.5" />
                  Erledigt
                </span>
                <span
                  v-if="addon.cancelledAt"
                  class="px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700"
                >
                  Gekündigt
                </span>
              </div>

              <p class="text-[13px] text-gray-500 mt-1">
                Vertrag: {{ addon.planName }}<template v-if="addon.bookedAt"> · gebucht am {{ formatDate(addon.bookedAt) }}</template><template v-if="addon.isRecurring"> · monatlich kündbar</template>
              </p>

              <p
                v-if="addon.isRecurring && addon.nextBillingAt"
                class="text-[13px] text-gray-600 mt-1 inline-flex items-center gap-1.5"
              >
                <CalendarSync class="w-3.5 h-3.5 text-green-700" />
                Nächste Abrechnung {{ formatDate(addon.nextBillingAt) }} – synchron zum Beitrag
              </p>

              <p
                v-if="addon.cancelledAt && addon.cancellationEffectiveAt"
                class="text-[13px] text-red-700 mt-1 inline-flex items-center gap-1.5"
              >
                <CalendarX class="w-3.5 h-3.5" />
                Gekündigt zum {{ formatDate(addon.cancellationEffectiveAt) }} – danach keine Abrechnung mehr
              </p>

              <p v-if="addon.completedAt" class="text-xs text-emerald-600 mt-0.5">
                Erledigt am {{ formatDateTime(addon.completedAt) }}<template v-if="addon.completedByName"> · von {{ addon.completedByName }}</template>
              </p>
            </div>
          </div>

          <div class="text-right flex-none">
            <template v-if="addon.mode === 'included'">
              <div class="text-sm text-gray-400 line-through">{{ formatPrice(addon.basePrice) }}</div>
              <div class="font-bold text-green-700">geschenkt</div>
            </template>
            <template v-else>
              <div class="text-lg font-bold text-gray-900">{{ formatPrice(addon.price) }}</div>
              <div class="text-xs text-gray-500">{{ addon.isRecurring ? 'pro Monat' : 'einmalig' }}</div>
            </template>
          </div>
        </div>

        <!-- Trial notice -->
        <div v-if="isTrialActive(addon.trialEndsAt)" class="px-4 pb-3 -mt-1">
          <span
            class="inline-flex items-center gap-1.5 text-xs font-semibold bg-amber-100 text-amber-700 px-2.5 py-1 rounded-full"
          >
            <Gift class="w-3.5 h-3.5" />
            Testphase bis {{ formatDate(addon.trialEndsAt) }} (Restmonat gratis)
          </span>
        </div>

        <!-- Action footer bar -->
        <div class="flex border-t border-gray-100">
          <!-- Recurring add-ons are an ongoing service, so marking them as
               "carried out" does not apply — they are cancelled instead. -->
          <button
            v-if="addon.isRecurring"
            type="button"
            :disabled="togglingKey === addon.key"
            @click="toggleCancellation(addon)"
            :class="[
              'flex-1 inline-flex items-center justify-center gap-1.5 p-3 text-sm font-semibold transition-colors active:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed',
              addon.cancelledAt ? 'text-gray-500' : 'text-red-700'
            ]"
          >
            <component :is="addon.cancelledAt ? RotateCcw : Ban" class="w-4 h-4" />
            <span>{{ addon.cancelledAt ? 'Kündigung zurücknehmen' : 'Add-on kündigen' }}</span>
          </button>

          <button
            v-else
            type="button"
            :disabled="togglingKey === addon.key"
            @click="toggleCompletion(addon)"
            :class="[
              'flex-1 inline-flex items-center justify-center gap-1.5 p-3 text-sm font-semibold transition-colors active:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed',
              addon.completedAt ? 'text-gray-500' : 'text-green-700'
            ]"
          >
            <component :is="addon.completedAt ? RotateCcw : CheckCircle" class="w-4 h-4" />
            <span>{{ addon.completedAt ? 'Als offen markieren' : 'Als erledigt markieren' }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="border border-dashed border-gray-200 rounded-lg p-6 text-center">
      <p class="text-sm text-gray-500">Für dieses Mitglied sind keine Add-ons gebucht.</p>
      <button
        v-if="membershipsWithBookableAddons.length > 0"
        type="button"
        class="inline-flex items-center gap-1.5 mt-3 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 px-3.5 py-2 rounded-lg text-[13px] font-semibold transition-colors"
        @click="openBookingDialog"
      >
        <Plus class="w-3.5 h-3.5" />
        Add-on hinzufügen
      </button>
    </div>

    <!-- Booking dialog -->
    <div
      v-if="showBookingDialog"
      class="fixed inset-0 bg-gray-500/75 z-50 overflow-y-auto p-4 flex items-start justify-center"
      @click.self="closeBookingDialog"
    >
      <div class="bg-white rounded-xl shadow-lg w-full max-w-lg my-auto">
        <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-gray-100">
          <h4 class="text-base font-semibold text-gray-900">Add-on hinzufügen</h4>
          <button type="button" class="text-gray-500 hover:text-gray-700" @click="closeBookingDialog">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="p-5">
          <!-- Membership picker, only when more than one is bookable -->
          <div v-if="membershipsWithBookableAddons.length > 1" class="mb-4">
            <label for="addon-membership" class="block text-sm font-medium text-gray-700 mb-1.5">
              Mitgliedschaft
            </label>
            <select
              id="addon-membership"
              v-model="selectedMembershipId"
              class="w-full border border-gray-300 rounded-md px-3 py-2.5 text-sm bg-white focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600"
            >
              <option v-for="entry in membershipsWithBookableAddons" :key="entry.id" :value="entry.id">
                {{ entry.planName }}
              </option>
            </select>
          </div>

          <p class="text-sm text-gray-500 mb-3">
            Wähle eine Leistung aus, die dem Vertrag zugeordnet ist. Es wird keine Zahlung erzeugt.
          </p>

          <div class="flex flex-col gap-2 max-h-80 overflow-y-auto">
            <button
              v-for="addon in selectableAddons"
              :key="addon.id"
              type="button"
              class="text-left border-2 rounded-lg p-3 transition-colors"
              :class="selectedAddonId === addon.id
                ? 'border-indigo-600 bg-violet-50'
                : 'border-gray-200 hover:border-gray-300'"
              :aria-pressed="selectedAddonId === addon.id"
              @click="selectedAddonId = addon.id"
            >
              <span class="flex items-center justify-between gap-2 flex-wrap">
                <span class="flex items-center gap-2 flex-wrap">
                  <span class="font-semibold text-gray-900">{{ addon.name }}</span>
                  <span
                    class="px-2 py-0.5 rounded-full text-xs font-semibold"
                    :class="addon.mode === 'included'
                      ? 'bg-green-100 text-green-800'
                      : 'bg-indigo-100 text-indigo-800'"
                  >
                    {{ addon.mode === 'included' ? 'Inklusive' : 'Optional' }}
                  </span>
                </span>
                <span class="text-right flex-none">
                  <span v-if="addon.mode === 'included'" class="text-sm font-bold text-green-700">
                    geschenkt
                  </span>
                  <template v-else>
                    <span class="font-bold text-gray-900">{{ formatPrice(addon.price) }}</span>
                    <span class="block text-xs text-gray-500">
                      {{ addon.billing_type === 'recurring' ? 'pro Monat' : 'einmalig' }}
                    </span>
                  </template>
                </span>
              </span>
              <span v-if="addon.description" class="block text-[13px] text-gray-500 mt-1">
                {{ addon.description }}
              </span>
            </button>
          </div>

          <p v-if="selectableAddons.length === 0" class="text-sm text-gray-500 text-center py-6">
            Für diesen Vertrag sind keine weiteren Add-ons verfügbar.
          </p>
        </div>

        <div class="flex justify-end gap-2.5 px-5 py-4 border-t border-gray-100">
          <button
            type="button"
            class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors"
            @click="closeBookingDialog"
          >
            Abbrechen
          </button>
          <button
            type="button"
            :disabled="selectedAddonId === null || booking"
            class="bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-300 disabled:cursor-not-allowed text-white px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors"
            @click="bookAddon"
          >
            {{ booking ? 'Wird gebucht…' : 'Add-on buchen' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import {
  BadgeCheck, Ban, CalendarSync, CalendarX, CheckCircle, CupSoda, Gift, Plus, RotateCcw, X
} from 'lucide-vue-next'
import { formatPrice, formatDate, formatDateTime } from '@/utils/formatters'
import { endOfBookingMonth, isTrialActive, nextMonthlyBillingDate } from '@/utils/addons'

const props = defineProps({
  member: {
    type: Object,
    required: true
  },
  // Bookable add-ons keyed by membership id.
  bookableAddons: {
    type: Object,
    default: () => ({})
  }
})

const togglingKey = ref(null)

const showBookingDialog = ref(false)
const selectedMembershipId = ref(null)
const selectedAddonId = ref(null)
const booking = ref(false)

// Memberships that still have at least one add-on left to book.
const membershipsWithBookableAddons = computed(() =>
  (props.member?.memberships || [])
    .map((membership) => ({
      id: membership.id,
      planName: membership.membership_plan?.name || 'Unbekannt',
      addons: props.bookableAddons?.[membership.id] || []
    }))
    .filter((entry) => entry.addons.length > 0)
)

const selectableAddons = computed(() =>
  membershipsWithBookableAddons.value
    .find((entry) => entry.id === selectedMembershipId.value)?.addons || []
)

// Changing the membership invalidates the selection, since each plan offers a
// different set of add-ons.
watch(selectedMembershipId, () => {
  selectedAddonId.value = null
})

const openBookingDialog = () => {
  selectedMembershipId.value = membershipsWithBookableAddons.value[0]?.id ?? null
  selectedAddonId.value = null
  showBookingDialog.value = true
}

const closeBookingDialog = () => {
  showBookingDialog.value = false
  selectedAddonId.value = null
}

const bookAddon = () => {
  if (selectedAddonId.value === null || selectedMembershipId.value === null) {
    return
  }

  booking.value = true

  router.post(
    route('members.memberships.addons.store', {
      member: props.member.id,
      membership: selectedMembershipId.value
    }),
    { addon_id: selectedAddonId.value },
    {
      preserveScroll: true,
      onSuccess: () => closeBookingDialog(),
      onFinish: () => {
        booking.value = false
      }
    }
  )
}


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
        isRecurring: addon.billing_type === 'recurring',
        isUsageService: addon.service_type === 'usage',
        // Recurring add-ons are billed together with the membership fee, whose
        // due dates are anchored to the membership start date — that is only
        // the 1st of the month when the contract itself started on the 1st. A
        // cancelled add-on is not billed again, so it has no next billing date.
        nextBillingAt: addon.billing_type === 'recurring' && !pivot.cancelled_at
          ? nextMonthlyBillingDate(membership.start_date)
          : null,
        // The trial covers the rest of the booking month.
        trialEndsAt: addon.billing_type === 'recurring' && addon.trial_rest_of_month
          ? endOfBookingMonth(pivot.created_at)
          : null,
        bookedAt: pivot.created_at,
        completedAt: pivot.completed_at,
        completedByName: pivot.completed_by_name,
        cancelledAt: pivot.cancelled_at,
        cancellationEffectiveAt: pivot.cancellation_effective_at
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

const toggleCancellation = (addon) => {
  // Cancelling ends an ongoing service, so confirm before doing it. Revoking a
  // pending cancellation restores the previous state and needs no confirmation.
  if (!addon.cancelledAt
    && !window.confirm(`Soll „${addon.name}“ wirklich zum Monatsende gekündigt werden?`)) {
    return
  }

  togglingKey.value = addon.key

  router.put(
    route('members.memberships.addons.toggle-cancellation', {
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
