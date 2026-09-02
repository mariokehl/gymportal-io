<template>
  <AppLayout :title="`${member.first_name} ${member.last_name}`" :hide-header-user-on-mobile="true">
    <template #header>
      <div class="flex items-center min-w-0 flex-1">
        <Link
          :href="route('members.index')"
          class="text-gray-500 hover:text-gray-700 mr-4 flex-shrink-0"
        >
          <ArrowLeft class="w-5 h-5" />
        </Link>

        <!-- On mobile the plain title swaps for a compact member identity once
             the profile card scrolls under the app bar. Desktop always shows the
             title. Both variants live in normal flow (toggled, not overlaid) so
             they render reliably inside the layout's <h1>. -->
        <div class="min-w-0 flex-1">
          <!-- Title (always on desktop; on mobile only until collapsed) -->
          <span
            class="block truncate"
            :class="showMobileIdentity ? 'hidden lg:block' : 'block'"
          >
            <template v-if="editMode">
              Mitglied bearbeiten
            </template>
            <template v-else>
              Mitglied anzeigen: {{ member.first_name }} {{ member.last_name }}
            </template>
          </span>

          <!-- Collapsed member identity (mobile only, view mode only) -->
          <div
            v-if="showMobileIdentity"
            class="lg:hidden flex items-center gap-2.5 min-w-0"
          >
            <MemberAvatar
              :initials="headerInitials"
              :is-guest="member.guest_access"
              size="sm"
            />
            <div class="min-w-0">
              <div class="text-sm font-bold text-gray-900 leading-tight truncate">
                {{ member.salutation ? member.salutation + ' ' : '' }}{{ member.first_name }} {{ member.last_name }}
              </div>
              <div class="flex items-center gap-1.5 mt-0.5 min-w-0">
                <span class="w-1.5 h-1.5 rounded-full flex-none" :class="headerStatusDotClass" />
                <span class="text-[11.5px] font-normal text-gray-500 truncate">
                  {{ headerStatusText }} · #{{ member.member_number }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Mobile header actions (desktop keeps its actions in the profile card).
         View mode: the "⋯" overflow sheet. Edit mode: Abbrechen / Speichern. -->
    <template #header-actions>
      <div class="lg:hidden flex items-center">
        <template v-if="editMode">
          <button
            type="button"
            @click="headerCard?.requestCancelEdit()"
            class="px-2 py-2 text-sm font-medium text-gray-600"
          >
            Abbrechen
          </button>
          <button
            type="button"
            @click="savePersonalData"
            :disabled="!isDirty || isSaving"
            class="px-2 py-2 text-sm font-bold"
            :class="(!isDirty || isSaving) ? 'text-gray-400' : 'text-indigo-600'"
          >
            {{ isSaving ? 'Speichern...' : 'Speichern' }}
          </button>
        </template>
        <button
          v-else
          type="button"
          @click="headerCard?.openActionSheet()"
          class="-mr-1 w-11 h-11 rounded-xl flex items-center justify-center text-gray-600 hover:bg-gray-100 transition-colors"
          aria-label="Aktionen"
        >
          <MoreVertical class="w-6 h-6" />
        </button>
      </div>
    </template>

    <!-- Extra bottom space on mobile while editing so the sticky action bar
         does not cover the last form field -->
    <div ref="pageRoot" class="space-y-6" :class="editMode ? 'pb-24 lg:pb-0' : ''">
      <!-- Header card / member identity + actions -->
      <MemberHeaderCard
        ref="headerCard"
        :member="member"
        :edit-mode="editMode"
        :member-age="memberAge"
        v-model:member-number="memberNumber"
        :is-dirty="isDirty"
        :is-saving="isSaving"
        :verifying-age="verifyingAge"
        :toggling-guest-access="togglingGuestAccess"
        :open-checkin="openCheckin"
        :toggling-checkin="togglingCheckin"
        @edit="enterEditMode"
        @cancel="exitEditMode"
        @save="savePersonalData"
        @block="showBlockModal = true"
        @toggle-age="toggleAgeVerification"
        @toggle-guest="toggleGuestAccess"
        @toggle-checkin="toggleCheckin"
        @topup="handleTopup"
        @status-changed="handleStatusChanged"
        @status-changing="handleStatusChanging"
      />

      <!-- Fraud-Warnbanner -->
      <div v-if="fraudCheck" class="bg-amber-50 border border-amber-300 rounded-lg shadow p-4">
        <div class="flex items-start gap-3">
          <AlertCircle class="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" />
          <div class="flex-1">
            <h3 class="text-sm font-semibold text-amber-800">Verdächtige Registrierung erkannt</h3>
            <p class="mt-1 text-sm text-amber-700">
              Fraud-Score: <span class="font-semibold">{{ fraudCheck.fraud_score }}/100</span>
              — Übereinstimmungen:
              <span class="font-medium">
                {{ Object.keys(fraudCheck.matched_fields || {}).filter(k => k !== '_combination_bonus').join(', ') }}
              </span>
            </p>
            <p v-if="fraudCheck.blocklist_entry" class="mt-1 text-sm text-amber-700">
              Sperrgrund: {{ fraudCheck.blocklist_entry.reason === 'chargeback' ? 'Rücklastschrift' : (fraudCheck.blocklist_entry.reason === 'payment_failed' ? 'Zahlungsausfall' : fraudCheck.blocklist_entry.reason) }}
              <template v-if="fraudCheck.blocklist_entry.notes"> — {{ fraudCheck.blocklist_entry.notes }}</template>
            </p>
            <p class="mt-2 text-xs text-amber-600">
              Dieses Mitglied muss manuell aktiviert werden. Geprüft am {{ new Date(fraudCheck.checked_at).toLocaleDateString('de-DE') }}.
            </p>
          </div>
        </div>
      </div>

      <!-- Tabs navigation (pill rail, consistent across all breakpoints; hidden while editing).
           On mobile the rail sticks below the app header once the page is scrolled. -->
      <TabRail v-if="!editMode" v-model="activeTab" :tabs="tabs" sticky>
        <template #badge="{ tab, active }">
          <span
            v-if="tab.id === 'history' && member.status_history?.length > 0"
            class="ml-0.5 text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center"
            :class="active ? 'bg-white/25 text-white' : 'bg-indigo-100 text-indigo-700'"
          >{{ member.status_history.length }}</span>
          <span
            v-if="tab.id === 'membership' && pendingMembershipCount > 0"
            class="ml-0.5 text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center"
            :class="active ? 'bg-white/25 text-white' : 'bg-orange-100 text-orange-700'"
            :title="`${pendingMembershipCount} ausstehende Mitgliedschaft(en)`"
          >{{ pendingMembershipCount }}</span>
          <span
            v-if="tab.id === 'payments' && paymentsAttentionSignal"
            class="ml-0.5 inline-flex items-center justify-center w-5 h-5 rounded-full"
            :class="active ? 'bg-white/25 text-white' : paymentsAttentionSignal.colorClass"
            :title="paymentsAttentionHint"
          >
            <!-- out-in keeps a single icon in the badge at any time, so the
                 pill never reflows while the two signals alternate. -->
            <Transition
              mode="out-in"
              enter-active-class="transition-opacity duration-200"
              leave-active-class="transition-opacity duration-200"
              enter-from-class="opacity-0"
              leave-to-class="opacity-0"
            >
              <component :is="paymentsAttentionSignal.icon" :key="paymentsAttentionSignal.key" class="w-3 h-3" />
            </Transition>
          </span>
          <span
            v-if="tab.id === 'documents' && documentCount > 0"
            class="ml-0.5 text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center"
            :class="active ? 'bg-white/25 text-white' : 'bg-indigo-100 text-indigo-700'"
          >{{ documentCount }}</span>
          <span
            v-if="tab.id === 'checkins' && checkInsTotal > 0"
            class="ml-0.5 text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center"
            :class="active ? 'bg-white/25 text-white' : 'bg-indigo-100 text-indigo-700'"
          >{{ checkInsTotal }}</span>
          <span
            v-if="tab.id === 'access' && activeAccessCount > 0"
            class="ml-0.5 text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center"
            :class="active ? 'bg-white/25 text-white' : 'bg-green-100 text-green-600'"
          >{{ activeAccessCount }}</span>
        </template>
      </TabRail>

      <!-- Personal Data Tab (own component; sole panel while editing) -->
      <div v-show="editMode || activeTab === 'personal'">
        <MemberPersonalDataTab
          ref="personalDataTab"
          :member="member"
          :edit-mode="editMode"
          @request-cancel="headerCard?.requestCancelEdit()"
          @saved="onPersonalDataSaved"
        />
      </div>

      <!-- Membership Tab (rendered directly on the gray canvas; only the cards are white) -->
      <div v-if="!editMode" v-show="activeTab === 'membership'">
        <MembershipTab
          :member="member"
          :membership-plans="membershipPlans"
          :resuming-membership="resumingMembership"
          :revoking-cancellation="revokingCancellation"
          :activating-membership="activatingMembership"
          :aborting-membership="abortingMembership"
          :withdrawing-membership="withdrawingMembership"
          :forcing-membership-status="forcingMembershipStatus"
          @activate="activateMembership"
          @pause="openPauseMembership"
          @resume="resumeMembership"
          @cancel="openCancelMembership"
          @revoke-cancellation="revokeCancellation"
          @abort="abortMembership"
          @withdraw="openWithdrawMembership"
          @force-status="handleForceStatus"
        />

        <!-- Booked add-ons (addon_membership) -->
        <div class="mt-6">
          <MemberAddons :member="member" :bookable-addons="bookableAddons" />
        </div>
      </div>

      <!-- Payments Tab (rendered directly on the gray canvas; only the cards are white) -->
      <div v-if="!editMode" v-show="activeTab === 'payments'">
        <PaymentsTab
          ref="paymentsTab"
          :member="member"
          :available-payment-methods="availablePaymentMethods"
        />
      </div>

      <!-- Documents Tab (rendered directly on the gray canvas; only the cards are white).
           Mounted eagerly (v-show, not v-if) whenever contracts are enabled so its
           document count loads on page load and the tab badge is accurate before the
           first click. -->
      <div v-if="!editMode && contractsEnabled" v-show="activeTab === 'documents'">
        <MemberDocumentsTab ref="memberDocumentsTab" :member="member" @documents-loaded="documentCount = $event" />
      </div>

      <!-- Access Control Tab (own component; rendered directly on the gray canvas).
           Mounted eagerly (v-show, not v-if) so its active-access count is available
           for the tab badge before the tab is first opened. -->
      <div v-if="!editMode" v-show="activeTab === 'access'">
        <AccessTab
          ref="accessTab"
          :member="member"
          :max-devices-per-member="maxDevicesPerMember"
          :access-logs-total="accessLogsTotal"
        />
      </div>

      <!-- Check-ins Tab (own component; rendered directly on the gray canvas) -->
      <div v-if="!editMode" v-show="activeTab === 'checkins'">
        <CheckinsTab :member="member" />
      </div>

      <!-- Status History Tab (rendered directly on the gray canvas; only the cards are white) -->
      <div v-if="!editMode" v-show="activeTab === 'history'">
        <StatusHistory :member="member" />
      </div>
    </div>

    <!-- Membership action modals (each owns its own form state) -->
    <PauseMembershipModal
      v-if="showPauseMembershipModal && selectedMembership"
      :member-id="member.id"
      :membership="selectedMembership"
      @close="closePauseMembership"
    />

    <CancelMembershipModal
      v-if="showCancelMembershipModal && selectedMembership"
      :member-id="member.id"
      :membership="selectedMembership"
      @close="closeCancelMembership"
    />

    <WithdrawMembershipModal
      v-if="showWithdrawMembershipModal && selectedMembership"
      :membership="selectedMembership"
      :processing="withdrawingMembership !== null"
      @confirm="confirmWithdrawMembership"
      @close="closeWithdrawMembership"
    />

    <BlockMemberModal
      v-if="showBlockModal"
      :member="member"
      @close="showBlockModal = false"
    />
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useInertiaPayments } from '@/composables/useInertiaPayments'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusHistory from '@/Components/StatusHistory.vue'
import MembershipTab from '@/Components/Members/MembershipTab.vue'
import PaymentsTab from '@/Components/Members/PaymentsTab.vue'
import MemberAddons from '@/Components/Members/MemberAddons.vue'
import MemberDocumentsTab from '@/Components/MemberDocumentsTab.vue'
import MemberPersonalDataTab from '@/Components/Members/MemberPersonalDataTab.vue'
import MemberHeaderCard from '@/Components/Members/MemberHeaderCard.vue'
import AccessTab from '@/Components/Members/AccessTab.vue'
import CheckinsTab from '@/Components/Members/CheckinsTab.vue'
import MemberAvatar from '@/Components/MemberAvatar.vue'
import TabRail from '@/Components/TabRail.vue'
import PauseMembershipModal from '@/Components/Members/PauseMembershipModal.vue'
import CancelMembershipModal from '@/Components/Members/CancelMembershipModal.vue'
import WithdrawMembershipModal from '@/Components/Members/WithdrawMembershipModal.vue'
import BlockMemberModal from '@/Components/Members/BlockMemberModal.vue'
import { useMembershipActions } from '@/composables/useMembershipActions'
import { useMemberTabBadges } from '@/composables/useMemberTabBadges'
import {
  User, FileText, Clock, CreditCard,
  ArrowLeft, AlertCircle, History, Key, FolderOpen,
  MoreVertical
} from 'lucide-vue-next'
import { getStatusText, getStatusColor } from '@/utils/memberStatus'

const props = defineProps({
  member: Object,
  availablePaymentMethods: {
    type: Array,
    default: () => []
  },
  membershipPlans: {
    type: Array,
    default: () => []
  },
  // Bookable add-ons keyed by membership id.
  bookableAddons: {
    type: Object,
    default: () => ({})
  },
  contractsEnabled: {
    type: Boolean,
    default: false
  },
  maxDevicesPerMember: {
    type: Number,
    default: 2
  },
  fraudCheck: {
    type: Object,
    default: null
  },
  // Total number of access log entries; the tab lazy-loads beyond the first page.
  accessLogsTotal: {
    type: Number,
    default: 0
  },
  // Total number of check-ins; the tab itself only lists the newest ones.
  checkInsTotal: {
    type: Number,
    default: 0
  },
  // The member's currently open visit, or null when they are not checked in.
  openCheckin: {
    type: Object,
    default: null
  }
})

// Payments themselves live in the PaymentsTab component; the parent only reads
// them to drive the outstanding-balance badge on the payments tab.
const { payments } = useInertiaPayments(props.member.id)

const outstandingBalance = computed(() => {
  const list = payments.value || props.member.payments || []
  const chargebacks = list.filter(p => p.status === 'chargeback')
  const sum = chargebacks.reduce((acc, cb) => {
    const hasSettlement = list.some(p => p.status === 'paid' && p.notes === cb.mollie_payment_id)
    return hasSettlement ? acc : acc + Math.abs(parseFloat(cb.amount))
  }, 0)
  return sum > 0 ? sum : null
})

// Tab badges: pending memberships and payment issues that need the operator.
const {
  pendingMembershipCount,
  paymentsAttentionSignal,
  paymentsAttentionHint,
} = useMemberTabBadges(() => props.member, outstandingBalance)

const editMode = ref(false)

// Mobile collapsing header: the app-bar title morphs into a compact member
// identity once the page is scrolled past the profile card. Desktop is
// unaffected (see the lg: overrides in the template).
const headerCollapsed = ref(false)

// Show the compact identity in the app bar only when scrolled and not editing.
const showMobileIdentity = computed(() => headerCollapsed.value && !editMode.value)

const headerInitials = computed(() =>
  `${props.member.first_name?.charAt(0) || ''}${props.member.last_name?.charAt(0) || ''}`.toUpperCase()
)
const headerStatusText = computed(() => getStatusText(props.member.status))
const headerStatusDotClass = computed(() => {
  const dotColors = {
    green: 'bg-green-500',
    gray: 'bg-gray-400',
    yellow: 'bg-yellow-500',
    orange: 'bg-orange-500',
    red: 'bg-red-500',
    black: 'bg-gray-900',
  }
  return dotColors[getStatusColor(props.member.status)] || 'bg-gray-400'
})

// Component refs: the header card owns the header UI (incl. discard dialog),
// the personal-data tab owns the personal-data form and its dirty state.
const headerCard = ref(null)
const personalDataTab = ref(null)

// Bridge the header controls to the personal-data component
const isDirty = computed(() => personalDataTab.value?.isDirty ?? false)
const isSaving = computed(() => personalDataTab.value?.form?.processing ?? false)

// Member number lives in the personal-data form but is edited in the header
const memberNumber = computed({
  get: () => personalDataTab.value?.form?.member_number ?? props.member.member_number,
  set: (value) => {
    if (personalDataTab.value?.form) {
      personalDataTab.value.form.member_number = value
    }
  },
})

// Sperrliste (the form itself lives in BlockMemberModal)
const showBlockModal = ref(false)
const activeTab = ref('personal')
const documentCount = ref(0)
const memberDocumentsTab = ref(null)
const paymentsTab = ref(null)

// Credit top-up: switch to the payments tab and open its modal in top-up mode.
const handleTopup = () => {
  activeTab.value = 'payments'
  nextTick(() => paymentsTab.value?.openTopup())
}

// Access-control UI lives in the AccessTab component; the parent only reads its
// active-access count to drive the tab badge.
const accessTab = ref(null)
const activeAccessCount = computed(() => accessTab.value?.activeAccessCount ?? 0)

// Age verification state
const verifyingAge = ref(false)

// Guest access state
const togglingGuestAccess = ref(false)

// Membership-related state
const showPauseMembershipModal = ref(false)
const showCancelMembershipModal = ref(false)
const showWithdrawMembershipModal = ref(false)
const selectedMembership = ref(null)

// Age is derived from the persisted member record (the form lives in the tab component)
const memberAge = computed(() => {
  const birthDate = props.member.birth_date
  if (!birthDate) return null
  const birth = new Date(birthDate)
  const today = new Date()
  let age = today.getFullYear() - birth.getFullYear()
  const monthDiff = today.getMonth() - birth.getMonth()
  if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
    age--
  }
  return age
})

const tabs = computed(() => {
  const baseTabs = [
    { id: 'personal', name: 'Persönliche Daten', icon: User },
    { id: 'membership', name: 'Mitgliedschaften', icon: FileText },
    { id: 'payments', name: 'Zahlungen', icon: CreditCard },
  ]
  if (props.contractsEnabled) {
    baseTabs.push({ id: 'documents', name: 'Dokumente', icon: FolderOpen })
  }
  baseTabs.push(
    { id: 'checkins', name: 'Check-Ins', icon: Clock },
    { id: 'access', name: 'Zugänge', icon: Key },
    { id: 'history', name: 'Verlauf', icon: History },
  )
  return baseTabs
})

// Status-Change Handler
const handleStatusChanged = (newStatus) => {
  // Die Seite wird automatisch durch Inertia aktualisiert.
  // Dokumente-Tab neu laden, da bei Aktivierung ggf. Verträge generiert werden.
  if (newStatus === 'active') {
    memberDocumentsTab.value?.fetchDocuments()
  }
}

// Membership actions (confirm + PUT + loading state) live in a composable.
const {
  resumingMembership,
  revokingCancellation,
  activatingMembership,
  abortingMembership,
  withdrawingMembership,
  forcingMembershipStatus,
  activateMembership,
  resumeMembership,
  abortMembership,
  revokeCancellation,
  withdrawMembership,
  forceMembershipStatus,
} = useMembershipActions(props.member.id, {
  onActivated: () => memberDocumentsTab.value?.fetchDocuments(),
})

const openPauseMembership = (membership) => {
  selectedMembership.value = membership
  showPauseMembershipModal.value = true
}

const closePauseMembership = () => {
  showPauseMembershipModal.value = false
  selectedMembership.value = null
}

const openCancelMembership = (membership) => {
  selectedMembership.value = membership
  showCancelMembershipModal.value = true
}

const closeCancelMembership = () => {
  showCancelMembershipModal.value = false
  selectedMembership.value = null
}

// Widerruf gemäß § 356a BGB
const forceWithdraw = ref(false)

const openWithdrawMembership = (membership, force = false) => {
  selectedMembership.value = membership
  forceWithdraw.value = force
  showWithdrawMembershipModal.value = true
}

const closeWithdrawMembership = () => {
  showWithdrawMembershipModal.value = false
  selectedMembership.value = null
  forceWithdraw.value = false
}

const confirmWithdrawMembership = () => {
  withdrawMembership(selectedMembership.value, forceWithdraw.value, {
    onSuccess: closeWithdrawMembership,
  })
}

// Force-Status: reuse the existing modals as confirmation where one exists.
const handleForceStatus = (membership, newStatus) => {
  if (newStatus === 'paused') {
    openPauseMembership(membership)
    return
  }

  if (newStatus === 'cancelled') {
    openCancelMembership(membership)
    return
  }

  if (newStatus === 'withdrawn') {
    openWithdrawMembership(membership, true)
    return
  }

  forceMembershipStatus(membership, newStatus)
}

const enterEditMode = () => {
  activeTab.value = 'personal'
  editMode.value = true
  // Snapshot the form once the component is mounted/updated with editMode on
  nextTick(() => personalDataTab.value?.enterEdit())
}

// Leave edit mode and discard local edits (the discard confirmation, if any,
// is handled by the header card before this is emitted).
const exitEditMode = () => {
  personalDataTab.value?.resetForm()
  editMode.value = false
}

// Trigger the save from the header button
const savePersonalData = () => {
  personalDataTab.value?.save()
}

// Called after the personal-data form was persisted successfully
const onPersonalDataSaved = () => {
  editMode.value = false
}

const toggleAgeVerification = () => {
  verifyingAge.value = true

  router.post(route('members.toggle-age-verification', props.member.id), {}, {
    preserveScroll: true,
    onSuccess: () => {
      verifyingAge.value = false
    },
    onError: () => {
      verifyingAge.value = false
    }
  })
}

const toggleGuestAccess = () => {
  togglingGuestAccess.value = true

  router.post(route('members.toggle-guest-access', props.member.id), {}, {
    preserveScroll: true,
    onSuccess: () => {
      togglingGuestAccess.value = false
    },
    onError: () => {
      togglingGuestAccess.value = false
    }
  })
}

// Manual check-in / check-out from the header menu. Direction is decided by
// the server from the member's open visit, so this posts no direction itself.
const togglingCheckin = ref(false)

const toggleCheckin = () => {
  togglingCheckin.value = true

  router.post(route('members.toggle-checkin', props.member.id), {}, {
    preserveScroll: true,
    onFinish: () => {
      togglingCheckin.value = false
    }
  })
}

// Mobile collapsing-header wiring: watch the AppLayout scroll container and
// flip `headerCollapsed` once the profile card has scrolled under the app bar.
// Also publishes the app-bar height as a CSS variable so the sticky tab rail
// can offset itself correctly below it.
const pageRoot = ref(null)
let scrollContainer = null
let appHeader = null

// Walk up from our own root to the nearest actually-scrollable ancestor. This
// is more robust than matching Tailwind class names on the AppLayout container.
const findScrollParent = (el) => {
  let node = el?.parentElement
  while (node && node !== document.body) {
    const oy = getComputedStyle(node).overflowY
    if ((oy === 'auto' || oy === 'scroll') && node.scrollHeight > node.clientHeight) {
      return node
    }
    node = node.parentElement
  }
  return null
}

const updateHeaderHeight = () => {
  if (appHeader) {
    document.documentElement.style.setProperty('--gp-header-height', `${appHeader.offsetHeight}px`)
  }
}

const onLayoutScroll = () => {
  if (!scrollContainer) return
  headerCollapsed.value = scrollContainer.scrollTop > 96
}

onMounted(() => {
  const urlParams = new URLSearchParams(window.location.search)
  if (urlParams.get('edit') === 'true') {
    enterEditMode()
  }

  scrollContainer = findScrollParent(pageRoot.value)
    || document.querySelector('.flex-1.overflow-y-auto')
  appHeader = scrollContainer?.querySelector('header')
    || document.querySelector('header')
  updateHeaderHeight()
  window.addEventListener('resize', updateHeaderHeight)
  if (scrollContainer) {
    scrollContainer.addEventListener('scroll', onLayoutScroll, { passive: true })
    onLayoutScroll()
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', updateHeaderHeight)
  scrollContainer?.removeEventListener('scroll', onLayoutScroll)
  document.documentElement.style.removeProperty('--gp-header-height')
})
</script>
