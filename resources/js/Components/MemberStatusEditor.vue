<!-- components/MemberStatusEditor.vue -->
<template>
  <div class="relative inline-block">
    <!-- Loading Overlay -->
    <div
      v-if="isChanging"
      class="absolute inset-0 flex items-center justify-center bg-white bg-opacity-75 rounded-full z-20"
    >
      <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-indigo-600"></div>
    </div>

    <!-- Editierbares Status Badge Button -->
    <button
      @click="toggleMenu"
      :disabled="isChanging || disabled"
      class="inline-flex items-center gap-1 px-3 py-1 text-sm font-semibold rounded-full cursor-pointer transition-all hover:opacity-80 hover:scale-105 disabled:cursor-not-allowed disabled:opacity-50"
      :class="currentStatusClasses"
      :title="disabled ? 'Status kann nicht geändert werden' : 'Klicken zum Ändern des Status'"
    >
      <component :is="currentStatusIcon" class="w-3 h-3" />
      {{ currentStatusText }}
      <ChevronDown :class="['w-3 h-3 transition-transform', showMenu ? 'rotate-180' : '']" />
    </button>

    <!-- Status Dropdown Menu -->
    <Transition
      enter-active-class="transition ease-out duration-100"
      enter-from-class="transform opacity-0 scale-95"
      enter-to-class="transform opacity-100 scale-100"
      leave-active-class="transition ease-in duration-75"
      leave-from-class="transform opacity-100 scale-100"
      leave-to-class="transform opacity-0 scale-95"
    >
      <div
        v-if="showMenu && !disabled"
        class="absolute z-50 mt-2 w-72 rounded-lg shadow-xl bg-white ring-1 ring-gray-300 ring-opacity-5 divide-y divide-gray-100 overflow-hidden"
        @click.stop
      >
        <!-- Status Options -->
        <div class="p-2">
          <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider px-3 py-2">
            Status ändern zu:
          </div>

          <div v-for="option in statusOptions" :key="option.value" class="relative">
            <button
              @click="handleStatusChange(option.value)"
              :disabled="!canChangeTo(option.value)"
              :class="[
                'w-full text-left px-3 py-2.5 text-sm rounded-md transition-colors flex items-center justify-between group',
                canChangeTo(option.value) && option.value !== status
                  ? 'hover:bg-gray-50 cursor-pointer'
                  : 'cursor-not-allowed opacity-60 bg-gray-50'
              ]"
            >
              <div class="flex items-center gap-3">
                <component :is="option.icon" :class="['w-4 h-4', option.iconColor]" />
                <div>
                  <div class="font-medium">{{ option.label }}</div>
                  <div class="text-xs text-gray-500">{{ option.description }}</div>
                </div>
              </div>

              <!-- Status Indicators -->
              <div class="flex items-center gap-1">
                <CheckCircle
                  v-if="option.value === status"
                  class="w-4 h-4 text-green-500"
                  title="Aktueller Status"
                />
                <Lock
                  v-else-if="!canChangeTo(option.value)"
                  class="w-4 h-4 text-gray-400"
                  :title="getBlockReason(option.value)"
                />
                <ArrowRight
                  v-else
                  class="w-4 h-4 text-gray-400 group-hover:text-gray-600 transition-colors"
                />
              </div>
            </button>

            <!-- Reason Tooltip -->
            <div
              v-if="!canChangeTo(option.value) && option.value !== status"
              class="mx-3 mb-1 px-2 py-1 bg-amber-50 rounded text-xs text-amber-700 flex items-start gap-1"
            >
              <Info class="w-3 h-3 mt-0.5 flex-shrink-0" />
              <span>{{ getBlockReason(option.value) }}</span>
            </div>
          </div>
        </div>

        <!-- Info Footer -->
        <div class="p-3 bg-gray-50">
          <div class="flex items-start gap-2">
            <Info class="w-4 h-4 text-gray-400 mt-0.5" />
            <div class="text-xs text-gray-600">
              <p class="font-medium mb-1">Hinweise:</p>
              <ul class="space-y-0.5 text-gray-500">
                <li>• Änderungen werden sofort wirksam</li>
                <li>• Alle Änderungen werden protokolliert</li>
                <li v-if="member.status_history">
                  • {{ member.status_history.length || 0 }} bisherige Änderungen
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import {
  ChevronDown, CheckCircle, Lock, ArrowRight, Info,
  UserCheck, UserX, Pause, Clock, AlertTriangle
} from 'lucide-vue-next'

// Importiere gemeinsame Utilities
import {
  getStatusText,
  getStatusBadgeClass,
  getStatusIcon,
  statusConfig
} from '@/utils/memberStatus'

const props = defineProps({
  member: {
    type: Object,
    required: true
  },
  status: {
    type: String,
    required: true
  },
  disabled: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['statusChanged', 'statusChanging'])

// State
const showMenu = ref(false)
const isChanging = ref(false)

// Status Options aus der zentralen Config
const statusOptions = computed(() => {
  return Object.entries(statusConfig).map(([value, config]) => ({
    value,
    label: config.label,
    description: config.description,
    icon: config.icon,
    iconColor: `text-${config.color}-600`,
    classes: config.classes
  }))
})

// Current Status Config
const currentStatusConfig = computed(() =>
  statusConfig[props.status] || {
    label: props.status,
    classes: 'bg-gray-100 text-gray-700',
    icon: null
  }
)

const currentStatusClasses = computed(() => currentStatusConfig.value.classes)
const currentStatusText = computed(() => currentStatusConfig.value.label)
const currentStatusIcon = computed(() => currentStatusConfig.value.icon)

// Menu Toggle
const toggleMenu = () => {
  if (!isChanging.value && !props.disabled) {
    showMenu.value = !showMenu.value
  }
}

// Validation Logic
const canChangeTo = (newStatus) => {
  if (props.status === newStatus) return false

  const validation = validateStatusChange(newStatus)
  return validation.allowed
}

const getBlockReason = (newStatus) => {
  const validation = validateStatusChange(newStatus)
  return validation.reason || ''
}

const validateStatusChange = (newStatus) => {
  const currentStatus = props.status

  // Gleicher Status
  if (currentStatus === newStatus) {
    return { allowed: false, reason: 'Bereits im gewählten Status' }
  }

  // Validierungslogik für verschiedene Statusübergänge

  // Inaktivierung
  if (newStatus === 'inactive') {
    // Prüfe auf aktive oder ausstehende Mitgliedschaften
    const hasActiveMembership = props.member.memberships?.some(m =>
      ['active', 'pending'].includes(m.status)
    )
    if (hasActiveMembership) {
      return {
        allowed: false,
        reason: 'Aktive oder ausstehende Mitgliedschaft vorhanden'
      }
    }

    // Prüfe auf ausstehende Zahlungen
    const hasPendingPayments = props.member.payments?.some(p =>
      p.status === 'pending'
    )
    if (hasPendingPayments) {
      return {
        allowed: false,
        reason: 'Ausstehende Zahlungen müssen erst beglichen werden'
      }
    }
  }

  // Aktivierung von Pending
  if (newStatus === 'active' && currentStatus === 'pending') {
    // Prüfe SEPA-Mandat
    const needsSepaMandate = props.member.payment_methods?.some(pm =>
      pm.requires_mandate && pm.sepa_mandate_status !== 'active'
    )
    if (needsSepaMandate) {
      return {
        allowed: false,
        reason: 'SEPA-Mandat muss erst aktiviert werden'
      }
    }

    // Prüfe aktive Zahlungsmethode
    const hasActivePaymentMethod = props.member.payment_methods?.some(pm =>
      pm.status === 'active'
    )
    if (!hasActivePaymentMethod) {
      return {
        allowed: false,
        reason: 'Keine aktive Zahlungsmethode verfügbar'
      }
    }
  }

  // Aktivierung von Overdue
  if (newStatus === 'active' && currentStatus === 'overdue') {
    // Prüfe überfällige Zahlungen
    const hasOverduePayments = props.member.payments?.some(p =>
      p.status === 'pending' && new Date(p.due_date) < new Date()
    )
    if (hasOverduePayments) {
      return {
        allowed: false,
        reason: 'Überfällige Zahlungen müssen erst beglichen werden'
      }
    }
  }

  // Pausierung
  if (newStatus === 'paused') {
    const hasActiveMembership = props.member.memberships?.some(m =>
      m.status === 'active' && !m.pause_start_date
    )
    if (!hasActiveMembership) {
      return {
        allowed: false,
        reason: 'Keine aktive Mitgliedschaft zum Pausieren vorhanden'
      }
    }
  }

  // Overdue nur von Active oder Paused
  if (newStatus === 'overdue') {
    if (!['active', 'paused'].includes(currentStatus)) {
      return {
        allowed: false,
        reason: 'Nur aktive oder pausierte Mitglieder können als überfällig markiert werden'
      }
    }
  }

  return { allowed: true }
}

// Status Change Handler
const handleStatusChange = async (newStatus) => {
  const validation = validateStatusChange(newStatus)

  if (!validation.allowed || newStatus === props.status) {
    return
  }

  // Bestätigungsdialoge basierend auf dem neuen Status
  const confirmMessages = {
    'inactive': {
      title: '⚠️ ACHTUNG: Inaktivierung',
      message: 'Die Inaktivierung beendet alle Dienste für dieses Mitglied.\n\nMöchten Sie fortfahren?'
    },
    'active': {
      pending: {
        title: '✓ Mitglied aktivieren',
        message: 'Sind alle Voraussetzungen erfüllt?\n\n• Zahlungsmethode aktiv\n• SEPA-Mandat (falls erforderlich) unterschrieben\n\nMöchten Sie das Mitglied aktivieren?'
      },
      default: {
        title: 'Status aktivieren',
        message: 'Möchten Sie den Status auf "Aktiv" ändern?'
      }
    },
    'overdue': {
      title: '⚠️ Als überfällig markieren',
      message: 'Das Mitglied wird als überfällig markiert.\n\nDies kann zu Einschränkungen führen. Fortfahren?'
    },
    'paused': {
      title: 'Mitgliedschaft pausieren',
      message: 'Die Mitgliedschaft wird pausiert.\n\nFortfahren?'
    },
    'pending': {
      title: 'Status auf Ausstehend setzen',
      message: 'Möchten Sie den Status auf "Ausstehend" ändern?'
    }
  }

  // Bestätigungsnachricht auswählen
  let confirmConfig
  if (confirmMessages[newStatus]) {
    if (typeof confirmMessages[newStatus] === 'object' && confirmMessages[newStatus][props.status]) {
      confirmConfig = confirmMessages[newStatus][props.status]
    } else if (confirmMessages[newStatus].default) {
      confirmConfig = confirmMessages[newStatus].default
    } else {
      confirmConfig = confirmMessages[newStatus]
    }
  } else {
    confirmConfig = {
      title: 'Status ändern',
      message: `Möchten Sie den Status auf "${getStatusText(newStatus)}" ändern?`
    }
  }

  if (!confirm(confirmConfig.message)) {
    return
  }

  // Status ändern
  isChanging.value = true
  showMenu.value = false
  emit('statusChanging', newStatus)

  router.put(route('members.update-status', props.member.id), {
    status: newStatus,
    previous_status: props.status,
    reason: null // Könnte erweitert werden um Grund-Eingabe
  }, {
    preserveScroll: true,
    onSuccess: () => {
      isChanging.value = false
      emit('statusChanged', newStatus)
    },
    onError: (errors) => {
      isChanging.value = false
      const errorMessage = errors.status ||
                          errors.message ||
                          'Der Status konnte nicht geändert werden.'
      alert(`❌ Fehler: ${errorMessage}`)
    }
  })
}

// Click-outside Handler
const handleClickOutside = (event) => {
  const target = event.target
  const dropdown = target.closest('.relative.inline-block')

  if (!dropdown || dropdown !== event.currentTarget.closest('.relative.inline-block')) {
    showMenu.value = false
  }
}

// Lifecycle
onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>

<style scoped>
/* Optional: Zusätzliche Styles falls benötigt */
.relative.inline-block {
  /* Stelle sicher, dass das Dropdown korrekt positioniert wird */
  position: relative;
  display: inline-block;
}
</style>
