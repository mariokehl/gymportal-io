<!-- components/Members/MembershipStatusEditor.vue -->
<template>
  <div class="relative inline-block" ref="containerRef">
    <!-- Loading Overlay -->
    <div
      v-if="isChanging"
      class="absolute inset-0 flex items-center justify-center bg-white bg-opacity-75 rounded-full z-20"
    >
      <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-indigo-600"></div>
    </div>

    <!-- Editierbares Status Badge Button -->
    <button
      ref="buttonRef"
      @click="toggleMenu"
      :disabled="isChanging"
      class="inline-flex items-center gap-1 px-3 py-1 text-sm font-semibold rounded-full cursor-pointer transition-all hover:opacity-80 hover:scale-105 disabled:cursor-not-allowed disabled:opacity-50"
      :class="currentStatusClasses"
      title="Klicken zum Ändern des Mitgliedschafts-Status"
    >
      {{ currentStatusText }}
      <ChevronDown :class="['w-3 h-3 transition-transform', showMenu ? 'rotate-180' : '']" />
    </button>

    <!-- Status Dropdown Menu (teleported to body to avoid overflow clipping) -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition ease-out duration-100"
        enter-from-class="transform opacity-0 scale-95"
        enter-to-class="transform opacity-100 scale-100"
        leave-active-class="transition ease-in duration-75"
        leave-from-class="transform opacity-100 scale-100"
        leave-to-class="transform opacity-0 scale-95"
      >
        <div
          v-if="showMenu"
          class="fixed z-[9999] w-64 rounded-lg shadow-xl bg-white ring-1 ring-gray-300 ring-opacity-5 divide-y divide-gray-100 overflow-hidden"
          :style="menuStyle"
          @click.stop
        >
          <!-- Status Options -->
          <div class="p-2">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider px-3 py-2">
              Status forcieren:
            </div>

            <div v-for="option in availableOptions" :key="option.value">
              <button
                @click.stop="handleStatusChange(option.value)"
                class="w-full text-left px-3 py-2.5 text-sm rounded-md transition-colors flex items-center justify-between group hover:bg-gray-50 cursor-pointer"
              >
                <div class="flex items-center gap-3">
                  <span :class="['inline-block w-2.5 h-2.5 rounded-full', option.dotClass]"></span>
                  <div>
                    <div class="font-medium">{{ option.label }}</div>
                  </div>
                </div>
                <ArrowRight class="w-4 h-4 text-gray-400 group-hover:text-gray-600 transition-colors" />
              </button>
            </div>
          </div>

          <!-- Info Footer -->
          <div class="p-3 bg-gray-50">
            <div class="flex items-start gap-2">
              <Info class="w-4 h-4 text-gray-400 mt-0.5" />
              <div class="text-xs text-gray-500">
                Statuswechsel ohne weitere Prüfung. Vorhandene Aktionen werden als Bestätigung angezeigt.
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue'
import { ChevronDown, ArrowRight, Info } from 'lucide-vue-next'

const props = defineProps({
  membership: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['force-status'])

const allStatuses = [
  { value: 'active', label: 'Aktiv', dotClass: 'bg-green-500', badgeClass: 'bg-green-100 text-green-800' },
  { value: 'paused', label: 'Pausiert', dotClass: 'bg-yellow-500', badgeClass: 'bg-yellow-100 text-yellow-800' },
  { value: 'cancelled', label: 'Gekündigt', dotClass: 'bg-red-500', badgeClass: 'bg-red-100 text-red-800' },
  { value: 'expired', label: 'Abgelaufen', dotClass: 'bg-gray-500', badgeClass: 'bg-gray-100 text-gray-800' },
  { value: 'pending', label: 'Ausstehend', dotClass: 'bg-orange-500', badgeClass: 'bg-orange-100 text-orange-800' },
  { value: 'withdrawn', label: 'Widerrufen', dotClass: 'bg-purple-500', badgeClass: 'bg-purple-100 text-purple-800' },
]

// State
const showMenu = ref(false)
const isChanging = ref(false)
const buttonRef = ref(null)
const menuStyle = ref({})

// Computed
const currentStatus = computed(() => allStatuses.find(s => s.value === props.membership.status))
const currentStatusText = computed(() => currentStatus.value?.label || props.membership.status)
const currentStatusClasses = computed(() => currentStatus.value?.badgeClass || 'bg-gray-100 text-gray-800')

const availableOptions = computed(() =>
  allStatuses.filter(s => s.value !== props.membership.status)
)

// Position the menu below the button
const updateMenuPosition = () => {
  if (!buttonRef.value) return
  const rect = buttonRef.value.getBoundingClientRect()
  menuStyle.value = {
    top: `${rect.bottom + 8}px`,
    left: `${rect.left}px`,
  }
}

// Menu Toggle
const toggleMenu = async () => {
  if (isChanging.value) return
  showMenu.value = !showMenu.value
  if (showMenu.value) {
    await nextTick()
    updateMenuPosition()
  }
}

// Status Change Handler
const handleStatusChange = (newStatus) => {
  showMenu.value = false
  emit('force-status', props.membership, newStatus)
}

// Click-outside Handler
const handleClickOutside = (event) => {
  if (buttonRef.value && buttonRef.value.contains(event.target)) return
  showMenu.value = false
}

// Lifecycle
onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})

// Expose for parent to control loading state
defineExpose({ isChanging })
</script>
