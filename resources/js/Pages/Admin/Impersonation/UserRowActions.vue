<template>
  <div class="inline-flex items-center gap-2 pointer-coarse:gap-3">
    <button
      type="button"
      :disabled="user.is_blocked"
      :title="user.is_blocked ? 'Blockierte Benutzer können nicht simuliert werden' : 'Simulieren'"
      :aria-label="`${fullName} simulieren`"
      class="touch-target relative inline-flex items-center rounded-md p-1.5 text-white bg-info-solid hover:bg-info-solid-strong transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
      @click="emit('impersonate', user)"
    >
      <UserCheck class="h-4 w-4" aria-hidden="true" />
    </button>
    <button
      v-if="user.is_blocked"
      type="button"
      title="Entsperren"
      :aria-label="`${fullName} entsperren`"
      class="touch-target relative inline-flex items-center rounded-md border border-line-strong p-1.5 text-ink-secondary bg-surface hover:bg-surface-muted transition-colors"
      @click="emit('unblock', user)"
    >
      <LockOpen class="h-4 w-4" aria-hidden="true" />
    </button>
    <button
      v-else
      type="button"
      title="Blockieren"
      :aria-label="`${fullName} blockieren`"
      class="touch-target relative inline-flex items-center rounded-md p-1.5 text-white bg-warning-solid hover:bg-warning-solid-strong transition-colors"
      @click="emit('block', user)"
    >
      <Ban class="h-4 w-4" aria-hidden="true" />
    </button>
    <button
      type="button"
      title="Löschen"
      :aria-label="`${fullName} löschen`"
      class="touch-target relative inline-flex items-center rounded-md p-1.5 text-white bg-danger-solid hover:bg-danger-solid-strong transition-colors"
      @click="emit('delete', user)"
    >
      <Trash2 class="h-4 w-4" aria-hidden="true" />
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Ban, LockOpen, Trash2, UserCheck } from '@lucide/vue'

const props = defineProps({ user: Object })
const emit = defineEmits(['impersonate', 'block', 'unblock', 'delete'])

const fullName = computed(() => `${props.user.first_name} ${props.user.last_name}`)
</script>
