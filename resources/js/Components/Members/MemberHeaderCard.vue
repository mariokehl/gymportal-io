<template>
  <div>
    <!-- Header card -->
    <div
      class="bg-white rounded-lg shadow p-5 sm:p-6"
      :class="editMode ? 'ring-1 ring-indigo-200' : ''"
    >
      <!-- Top row: identity + actions -->
      <div class="flex flex-row items-start justify-between gap-3 lg:gap-5">
        <!-- Identity -->
        <div class="flex items-center gap-4 min-w-0 flex-1">
          <MemberAvatar
            :initials="getInitials(member.first_name, member.last_name)"
            :age-verified="member.age_verified"
            :verified-at="member.age_verified_at"
            :is-guest="member.guest_access"
            size="xl"
          />
          <div class="min-w-0">
            <h2 class="text-xl sm:text-2xl font-bold text-gray-900 leading-tight truncate">
              {{ member.salutation ? member.salutation + ' ' : '' }}{{ member.first_name }} {{ member.last_name }}
            </h2>

            <!-- Member number - editable in edit mode -->
            <div v-if="editMode" class="mt-2 max-w-xs">
              <MemberNumberInput
                :model-value="memberNumber"
                @update:model-value="$emit('update:memberNumber', $event)"
                label="Mitgliedsnummer"
                :required="true"
                :check-url="route('members.check-member-number')"
                :member-id="member.id"
                help-text="Eindeutige Mitgliedsnummer"
                :validate-on-mount="false"
              />
            </div>
            <p v-else class="text-sm text-gray-500 mt-1">Mitgliedsnummer: #{{ member.member_number }}</p>

            <div class="mt-2.5 flex items-center gap-2 flex-wrap">
              <!-- Edit mode: editable status component -->
              <MemberStatusEditor
                v-if="editMode"
                :member="member"
                :status="member.status"
                @status-changed="$emit('status-changed', $event)"
                @status-changing="$emit('status-changing', $event)"
              />

              <!-- View mode: read-only badge -->
              <MemberStatusBadge
                v-else
                :status="member.status"
                :show-icon="true"
              />

              <!-- Age -->
              <span v-if="memberAge !== null" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700">
                {{ memberAge }} Jahre
              </span>
            </div>
          </div>
        </div>

        <!-- Actions (desktop only; on mobile the view actions live in the app
             header's "⋯" sheet and the edit actions in a sticky bottom bar) -->
        <div class="hidden lg:flex items-center gap-2.5 flex-shrink-0 flex-wrap self-start">
          <template v-if="editMode">
            <button
              type="button"
              @click="requestCancelEdit"
              class="px-4 py-2 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50 flex items-center gap-2"
            >
              Abbrechen
            </button>
            <button
              type="button"
              @click="$emit('save')"
              :disabled="!isDirty || isSaving"
              class="px-4 py-2 rounded-md flex items-center gap-2 text-white"
              :class="(!isDirty || isSaving) ? 'bg-gray-300 cursor-not-allowed' : 'bg-indigo-600 hover:bg-indigo-700'"
            >
              <Check class="w-4 h-4" />
              {{ isSaving ? 'Speichern...' : 'Speichern' }}
            </button>
          </template>
          <template v-else>
            <Link
              :href="route('members.create')"
              class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 flex items-center gap-2"
            >
              <Plus class="w-4 h-4" />
              Neues Mitglied
            </Link>
            <button
              type="button"
              @click="$emit('block')"
              class="px-4 py-2 rounded-lg flex items-center gap-2 border border-red-200 text-red-600 hover:bg-red-50"
            >
              <ShieldX class="w-4 h-4" />
              Sperren
            </button>
            <button
              type="button"
              @click="$emit('edit')"
              class="px-4 py-2 rounded-lg flex items-center gap-2 bg-gray-700 text-white hover:bg-gray-800"
            >
              <Edit class="w-4 h-4" />
              Bearbeiten
            </button>
          </template>
        </div>
      </div>

      <!-- Toggles + credit tile (variant A): toggles on the left, credit on the
           right (aligned under the action buttons) -->
      <div
        v-if="!editMode"
        class="mt-5 pt-4 border-t border-gray-100 flex flex-col lg:flex-row lg:items-center gap-x-10 gap-y-4"
      >
        <!-- Toggles: age verification + guest access (same two-column grid as in edit mode) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-10 max-w-2xl flex-1">
          <!-- Age verification toggle -->
          <div class="flex items-center gap-3 py-2.5 border-b border-gray-100 sm:border-b-0">
            <button
              type="button"
              @click="$emit('toggle-age')"
              :disabled="verifyingAge"
              class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
              :class="member.age_verified ? 'bg-blue-500' : 'bg-gray-300'"
              role="switch"
              :aria-checked="member.age_verified"
            >
              <span
                aria-hidden="true"
                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                :class="member.age_verified ? 'translate-x-5' : 'translate-x-0'"
              />
            </button>
            <span class="text-sm font-medium text-gray-700">Alter verifiziert</span>
            <span
              v-if="member.age_verified && member.age_verified_at"
              class="text-xs text-gray-400"
            >
              ({{ formatDate(member.age_verified_at) }})
            </span>
          </div>

          <!-- Guest access toggle -->
          <div class="flex items-center gap-3 py-2.5">
            <button
              type="button"
              @click="$emit('toggle-guest')"
              :disabled="togglingGuestAccess"
              class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2"
              :class="member.guest_access ? 'bg-orange-500' : 'bg-gray-300'"
              role="switch"
              :aria-checked="member.guest_access"
            >
              <span
                aria-hidden="true"
                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                :class="member.guest_access ? 'translate-x-5' : 'translate-x-0'"
              />
            </button>
            <span class="text-sm font-medium text-gray-700">Gastzugang</span>
            <span
              v-if="member.guest_access && member.guest_access_granted_at"
              class="text-xs text-gray-400"
            >
              ({{ formatDate(member.guest_access_granted_at) }})
            </span>
          </div>
        </div>

        <!-- Credit tile: available balance + top-up action (right, under the action buttons) -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 rounded-lg border border-indigo-100 bg-gradient-to-r from-indigo-50 to-white px-3.5 py-3 flex-none lg:ml-auto lg:max-w-md">
          <div class="flex items-center gap-3 flex-1 min-w-0">
            <div class="w-9 h-9 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center flex-none">
              <Wallet class="w-5 h-5" />
            </div>
            <div class="min-w-0">
              <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Verfügbares Guthaben</div>
              <div class="text-2xl font-bold text-indigo-600 leading-tight">
                {{ member.credit_balance_formatted ?? '0,00 €' }}
              </div>
            </div>
          </div>
          <button
            type="button"
            @click="$emit('topup')"
            class="inline-flex items-center justify-center gap-1.5 rounded-md border border-indigo-200 bg-white px-3.5 py-2 text-[13px] font-semibold text-indigo-600 hover:bg-indigo-50 transition-colors whitespace-nowrap flex-none"
          >
            <Plus class="w-[15px] h-[15px]" />
            Aufladen
          </button>
        </div>
      </div>
    </div>

    <!-- Mobile action sheet (view mode): new member / edit / block -->
    <teleport to="body">
      <div class="lg:hidden">
        <transition
          enter-active-class="transition-opacity duration-200"
          leave-active-class="transition-opacity duration-200"
          enter-from-class="opacity-0"
          leave-to-class="opacity-0"
        >
          <div
            v-if="showActionSheet"
            class="fixed inset-0 z-[60] bg-gray-900/45"
            @click="showActionSheet = false"
          />
        </transition>
        <transition
          enter-active-class="transition-transform duration-300 ease-out"
          leave-active-class="transition-transform duration-300 ease-out"
          enter-from-class="translate-y-full"
          leave-to-class="translate-y-full"
        >
          <div
            v-if="showActionSheet"
            class="fixed inset-x-0 bottom-0 z-[60] px-2.5 pb-[calc(0.625rem+env(safe-area-inset-bottom))]"
          >
            <div class="bg-white rounded-2xl overflow-hidden shadow-[0_-2px_30px_rgba(0,0,0,0.18)]">
              <div class="px-4 pt-3.5 pb-2.5 text-center text-xs text-gray-500 border-b border-gray-100">
                {{ member.salutation ? member.salutation + ' ' : '' }}{{ member.first_name }} {{ member.last_name }} · #{{ member.member_number }}
              </div>
              <Link
                :href="route('members.create')"
                class="w-full flex items-center gap-3.5 px-4 py-4 text-base font-medium text-gray-900 hover:bg-gray-50"
                @click="showActionSheet = false"
              >
                <Plus class="w-5 h-5 text-indigo-600" />
                Neues Mitglied
              </Link>
              <button
                type="button"
                @click="showActionSheet = false; $emit('edit')"
                class="w-full flex items-center gap-3.5 px-4 py-4 text-base font-medium text-gray-900 border-t border-gray-100 hover:bg-gray-50"
              >
                <Edit class="w-5 h-5 text-gray-500" />
                Bearbeiten
              </button>
              <button
                type="button"
                @click="showActionSheet = false; $emit('block')"
                class="w-full flex items-center gap-3.5 px-4 py-4 text-base font-medium text-red-600 border-t border-gray-100 hover:bg-gray-50"
              >
                <ShieldX class="w-5 h-5 text-red-600" />
                Sperren
              </button>
            </div>
            <button
              type="button"
              @click="showActionSheet = false"
              class="w-full mt-2 py-4 rounded-2xl bg-white text-base font-semibold text-indigo-600 shadow-[0_-2px_30px_rgba(0,0,0,0.10)]"
            >
              Abbrechen
            </button>
          </div>
        </transition>
      </div>
    </teleport>

    <!-- Discard confirmation when leaving edit mode -->
    <teleport to="body">
      <div v-if="showDiscardConfirm" class="fixed inset-0 z-[70] flex items-center justify-center p-6" @click="showDiscardConfirm = false">
        <div class="absolute inset-0 bg-gray-500/75"></div>
        <div class="relative w-full max-w-md bg-white rounded-lg shadow-xl p-6" @click.stop>
          <div class="flex gap-4">
            <span class="flex-none w-10 h-10 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center">
              <AlertTriangle class="w-5 h-5" />
            </span>
            <div class="min-w-0">
              <h3 class="text-base font-semibold text-gray-900">Änderungen verwerfen?</h3>
              <p class="mt-1 text-sm text-gray-500 leading-relaxed">
                Nicht gespeicherte Eingaben gehen verloren. Diese Aktion kann nicht rückgängig gemacht werden.
              </p>
            </div>
          </div>
          <div class="mt-5 flex justify-end gap-2.5">
            <button
              type="button"
              @click="showDiscardConfirm = false"
              class="px-4 py-2 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50"
            >
              Abbrechen
            </button>
            <button
              type="button"
              @click="confirmDiscardEdit"
              class="px-4 py-2 rounded-md bg-red-600 text-white hover:bg-red-700 flex items-center gap-2"
            >
              <Trash2 class="w-4 h-4" />
              Verwerfen
            </button>
          </div>
        </div>
      </div>
    </teleport>

  </div>
</template>

<script setup>
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Plus, Edit, ShieldX, Check, AlertTriangle, Trash2, Wallet } from 'lucide-vue-next'
import MemberAvatar from '@/Components/MemberAvatar.vue'
import MemberStatusBadge from '@/Components/MemberStatusBadge.vue'
import MemberStatusEditor from '@/Components/MemberStatusEditor.vue'
import MemberNumberInput from '@/Components/MemberNumberInput.vue'
import { formatDate } from '@/utils/formatters'

const props = defineProps({
  member: {
    type: Object,
    required: true,
  },
  editMode: {
    type: Boolean,
    default: false,
  },
  memberAge: {
    type: Number,
    default: null,
  },
  memberNumber: {
    type: [String, Number],
    default: '',
  },
  isDirty: {
    type: Boolean,
    default: false,
  },
  isSaving: {
    type: Boolean,
    default: false,
  },
  verifyingAge: {
    type: Boolean,
    default: false,
  },
  togglingGuestAccess: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits([
  'edit', 'cancel', 'save', 'block',
  'toggle-age', 'toggle-guest', 'topup',
  'status-changed', 'status-changing',
  'update:memberNumber',
])

const showActionSheet = ref(false)
const showDiscardConfirm = ref(false)

// Initials helper (kept local so the card is self-contained)
const getInitials = (firstName, lastName) => {
  return `${firstName?.charAt(0) || ''}${lastName?.charAt(0) || ''}`.toUpperCase()
}

// Cancel edit — confirm only when there are unsaved changes
const requestCancelEdit = () => {
  if (props.isDirty) {
    showDiscardConfirm.value = true
    return
  }
  emit('cancel')
}

const confirmDiscardEdit = () => {
  showDiscardConfirm.value = false
  emit('cancel')
}

// Open the shared view-mode action sheet (triggered from the app header on mobile)
const openActionSheet = () => {
  showActionSheet.value = true
}

// Exposed so the parent can route the personal-data tab's "discard" link
// through the same confirmation dialog, and open the action sheet from the header.
defineExpose({ requestCancelEdit, openActionSheet })
</script>
