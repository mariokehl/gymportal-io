<template>
  <!-- Rows of the services breakdown in MembershipTab, one per booked add-on -->
  <div
    v-for="addon in bookedAddons"
    :key="addon.key"
    class="grid grid-cols-[minmax(0,1fr)_auto] sm:grid-cols-[minmax(0,1fr)_7rem_10rem] gap-x-3 gap-y-2 items-center px-4 py-3.5 border-t border-gray-100"
  >
    <div class="flex gap-3 items-start min-w-0">
      <span
        class="w-8 h-8 rounded-lg flex items-center justify-center flex-none"
        :class="addon.isUsageService ? 'bg-indigo-50 text-indigo-600' : 'bg-gray-100 text-gray-500'"
      >
        <component :is="addon.isUsageService ? CupSoda : BadgeCheck" class="w-4 h-4" />
      </span>

      <div class="min-w-0 flex flex-col gap-1">
        <div class="flex items-center gap-2 flex-wrap">
          <span class="text-sm font-semibold text-gray-900">{{ addon.name }}</span>
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
        </div>

        <p class="text-xs text-gray-500">
          Add-on<template v-if="addon.bookedAt"> · gebucht am {{ formatDate(addon.bookedAt) }}</template><template v-if="addon.isRecurring"> · monatlich kündbar</template>
        </p>

        <p
          v-if="addon.isRecurring && addon.nextBillingAt"
          class="hidden sm:inline-flex text-xs text-gray-600 items-center gap-1.5"
        >
          <CalendarSync class="w-3.5 h-3.5 text-green-700" />
          Nächste Abrechnung {{ formatDate(addon.nextBillingAt) }} – synchron zum Beitrag
        </p>

        <p
          v-if="addon.cancelledAt && addon.cancellationEffectiveAt"
          class="text-xs text-red-700 inline-flex items-center gap-1.5"
        >
          <CalendarX class="w-3.5 h-3.5" />
          Gekündigt zum {{ formatDate(addon.cancellationEffectiveAt) }} – danach keine Abrechnung mehr
        </p>

        <p v-if="addon.completedAt" class="text-xs text-emerald-600">
          Erledigt am {{ formatDateTime(addon.completedAt) }}<template v-if="addon.completedByName"> · von {{ addon.completedByName }}</template>
        </p>

        <span
          v-if="isTrialActive(addon.trialEndsAt)"
          class="self-start inline-flex items-center gap-1.5 text-xs font-semibold bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full"
        >
          <Gift class="w-3.5 h-3.5" />
          Testphase bis {{ formatDate(addon.trialEndsAt) }} (Restmonat gratis)
        </span>
      </div>
    </div>

    <div class="text-right self-start sm:self-center">
      <template v-if="addon.mode === 'included'">
        <div class="text-xs text-gray-400 line-through">{{ formatPrice(addon.basePrice) }}</div>
        <div class="text-sm font-semibold text-green-700">geschenkt</div>
      </template>
      <template v-else>
        <div :class="['text-sm font-semibold text-gray-900', addon.cancelledAt ? 'line-through' : '']">{{ formatPrice(addon.price) }}</div>
        <div class="text-xs text-gray-500">{{ addon.isRecurring ? 'pro Monat' : 'einmalig' }}</div>
      </template>
    </div>

    <div v-if="!readonly" class="col-span-2 sm:col-span-1 flex sm:justify-end pl-11 sm:pl-0">
      <!-- Recurring add-ons are an ongoing service, so marking them as
           "carried out" does not apply — they are cancelled instead. -->
      <button
        v-if="addon.isRecurring"
        type="button"
        :disabled="togglingKey === addon.key"
        @click="toggleCancellation(addon)"
        :class="[
          'inline-flex items-center gap-1.5 sm:p-1 text-[13px] font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed',
          addon.cancelledAt ? 'text-gray-700 hover:text-gray-900' : 'text-red-600 hover:text-red-800'
        ]"
      >
        <component :is="addon.cancelledAt ? RotateCcw : Ban" class="w-3.5 h-3.5" />
        <span>{{ addon.cancelledAt ? 'Zurücknehmen' : 'Kündigen' }}</span>
      </button>

      <button
        v-else
        type="button"
        :disabled="togglingKey === addon.key"
        @click="toggleCompletion(addon)"
        :class="[
          'inline-flex items-center gap-1.5 sm:p-1 text-[13px] font-medium text-right transition-colors disabled:opacity-50 disabled:cursor-not-allowed',
          addon.completedAt ? 'text-gray-700 hover:text-gray-900' : 'text-green-700 hover:text-green-800'
        ]"
      >
        <component :is="addon.completedAt ? RotateCcw : CheckCircle" class="w-3.5 h-3.5 flex-none" />
        <span>{{ addon.completedAt ? 'Als offen markieren' : 'Als erledigt markieren' }}</span>
      </button>
    </div>
  </div>

  <!-- Cancellation dialog -->
  <CancelAddonModal
    v-if="addonToCancel"
    :member-id="member.id"
    :addon="addonToCancel"
    @close="addonToCancel = null"
  />

  <!-- Booking dialog -->
  <teleport to="body">
    <div
      v-if="showBookingDialog"
      class="fixed inset-0 bg-gray-500/75 z-50 overflow-y-auto p-4 flex items-start justify-center"
      @click.self="closeBookingDialog"
    >
      <div class="bg-white rounded-xl shadow-lg w-full max-w-lg my-auto">
        <div class="flex items-start justify-between gap-3 px-5 py-4 border-b border-gray-100">
          <div>
            <h4 class="text-base font-semibold text-gray-900">Add-on hinzufügen</h4>
            <p class="text-sm text-gray-600 mt-1">
              zu Vertrag <span class="font-semibold text-gray-900">{{ planName }}</span>
            </p>
          </div>
          <button type="button" class="text-gray-500 hover:text-gray-700" @click="closeBookingDialog">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="p-5">
          <p class="text-sm text-gray-500 mb-3">
            Wählen Sie eine Leistung aus, die dem Vertrag zugeordnet ist. Es wird keine Zahlung erzeugt.
          </p>

          <div class="flex flex-col gap-2 max-h-80 overflow-y-auto">
            <button
              v-for="addon in bookableAddons"
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

          <p v-if="bookableAddons.length === 0" class="text-sm text-gray-500 text-center py-6">
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
  </teleport>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import {
  BadgeCheck, Ban, CalendarSync, CalendarX, CheckCircle, CupSoda, Gift, RotateCcw, X
} from 'lucide-vue-next'
import { formatPrice, formatDate, formatDateTime } from '@/utils/formatters'
import { cappedBillingDate, endOfBookingMonth, isTrialActive, nextMonthlyBillingDate } from '@/utils/addons'
import CancelAddonModal from '@/Components/Members/CancelAddonModal.vue'

const props = defineProps({
  member: {
    type: Object,
    required: true
  },
  // The membership whose booked add-ons are listed and booked.
  membership: {
    type: Object,
    required: true
  },
  // Past contracts list their add-ons without any actions.
  readonly: {
    type: Boolean,
    default: false
  },
  // Add-ons that can still be booked on this membership.
  bookableAddons: {
    type: Array,
    default: () => []
  }
})

const togglingKey = ref(null)

// The booked add-on the cancellation dialog is open for, if any.
const addonToCancel = ref(null)

const showBookingDialog = ref(false)
const selectedAddonId = ref(null)
const booking = ref(false)

const planName = computed(() => props.membership.membership_plan?.name || 'Unbekannt')

const openBookingDialog = () => {
  selectedAddonId.value = null
  showBookingDialog.value = true
}

const closeBookingDialog = () => {
  showBookingDialog.value = false
  selectedAddonId.value = null
}

const bookAddon = () => {
  if (selectedAddonId.value === null) {
    return
  }

  booking.value = true

  router.post(
    route('members.memberships.addons.store', {
      member: props.member.id,
      membership: props.membership.id
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

defineExpose({ openBookingDialog })

// Map the add-ons booked on this membership (addon_membership pivot) into
// display rows.
const bookedAddons = computed(() => {
  const membership = props.membership

  return (membership.addons || []).map((addon) => {
    const pivot = addon.pivot || {}
    // A contract taken over from another system was booked long before its
    // import row was written, so the stored booking date wins over created_at.
    const bookedAt = pivot.booked_at || pivot.created_at

    return {
      key: `${membership.id}-${addon.id}`,
      membershipId: membership.id,
      addonId: addon.id,
      name: addon.name,
      planName: planName.value,
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
      // The add-on stops with the contract it hangs on, so its own
      // cancellation date and the membership end both cap the result.
      nextBillingAt: addon.billing_type === 'recurring' && !pivot.cancelled_at
        ? cappedBillingDate(
          nextMonthlyBillingDate(membership.start_date),
          pivot.cancellation_effective_at || membership.end_date
        )
        : null,
      // The trial covers the rest of the booking month.
      trialEndsAt: addon.billing_type === 'recurring' && addon.trial_rest_of_month
        ? endOfBookingMonth(bookedAt)
        : null,
      bookedAt,
      completedAt: pivot.completed_at,
      completedByName: pivot.completed_by_name,
      cancelledAt: pivot.cancelled_at,
      cancellationEffectiveAt: pivot.cancellation_effective_at
    }
  })
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
  // Cancelling ends an ongoing service and needs an effective date, so it is
  // handled in a dialog. Revoking a pending cancellation restores the previous
  // state and needs no further input.
  if (!addon.cancelledAt) {
    addonToCancel.value = addon

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
