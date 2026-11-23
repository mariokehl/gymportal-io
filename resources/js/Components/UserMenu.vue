<template>
  <!-- Account disclosure in the app header: who is signed in, the profile and
       signing out. A plain disclosure (button + panel of links), not an ARIA
       menu, so Tab moves through the entries as everywhere else. -->
  <div ref="root" class="relative">
    <button
      ref="trigger"
      type="button"
      :aria-expanded="open"
      :aria-controls="panelId"
      :aria-label="`Konto von ${fullName}`"
      class="flex items-center gap-2 rounded-lg px-2 py-1 hover:bg-fill transition-colors"
      @click="open = !open"
    >
      <span class="size-8 flex-none rounded-full bg-brand text-white flex items-center justify-center text-xs font-semibold" aria-hidden="true">
        {{ initials }}
      </span>
      <span class="hidden sm:inline max-w-48 truncate text-sm font-medium" aria-hidden="true">{{ fullName }}</span>
      <ChevronDown
        aria-hidden="true"
        :stroke-width="1.5"
        :class="['hidden sm:block size-4 text-ink-muted transition-transform', open ? 'rotate-180' : '']"
      />
    </button>

    <div
      v-if="open"
      :id="panelId"
      class="absolute right-0 top-full mt-2 z-50 w-64 py-1 bg-surface border border-line rounded-lg shadow-lg"
    >
      <div class="px-3 py-2">
        <p class="text-sm font-medium text-ink truncate">{{ fullName }}</p>
        <p v-if="user.email" class="text-xs text-ink-muted truncate">{{ user.email }}</p>
      </div>

      <div class="my-1 border-t border-line-subtle" />

      <Link :href="route('profile.index')" :class="itemClass">
        <UserRound aria-hidden="true" :stroke-width="1.5" class="size-4 flex-none text-ink-muted" />
        Profil
      </Link>

      <div class="my-1 border-t border-line-subtle" />

      <!-- Choosing it in an opened menu is deliberate enough; no extra confirm -->
      <Link :href="route('logout')" method="post" as="button" :class="itemClass">
        <LogOut aria-hidden="true" :stroke-width="1.5" class="size-4 flex-none text-ink-muted" />
        Abmelden
      </Link>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, useId } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'
import { ChevronDown, LogOut, UserRound } from '@lucide/vue'
import { useDismissable } from '@/composables/useDismissable'

const page = usePage()

const open = ref(false)
const root = ref(null)
const trigger = ref(null)
const panelId = `user-menu-${useId()}`
useDismissable(root, open, { triggerRef: trigger })

const itemClass = 'flex w-full items-center gap-2.5 px-3 h-9 pointer-coarse:h-11 text-sm text-left text-ink-secondary hover:bg-fill hover:text-ink transition-colors'

const user = computed(() => page.props.auth.user)

const fullName = computed(() => `${user.value.first_name ?? ''} ${user.value.last_name ?? ''}`.trim())

const initials = computed(() => {
  const first = user.value.first_name?.charAt(0) || ''
  const last = user.value.last_name?.charAt(0) || ''
  return (first + last).toUpperCase()
})
</script>
