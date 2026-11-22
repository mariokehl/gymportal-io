<template>
  <Head :title="pageTitle" />

  <!-- Persistent mode renders inside the AppShell set via defineOptions; the
       fallback wraps the page in its own (non-persistent) shell. -->
  <component :is="persistent ? Passthrough : AppShell">
    <Teleport defer :to="`#${APP_HEADER_TITLE_ID}`">
      <slot name="header" />
    </Teleport>
    <Teleport v-if="$slots['header-actions']" defer :to="`#${APP_HEADER_ACTIONS_ID}`">
      <slot name="header-actions" />
    </Teleport>

    <slot />
  </component>
</template>

<script setup>
import { computed, inject, provide, watchEffect, onBeforeUnmount } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppShell, { APP_HEADER_TITLE_ID, APP_HEADER_ACTIONS_ID } from '@/Layouts/AppShell.vue'
import { APP_SHELL_KEY, createAppShellState } from '@/composables/useAppShell'

const props = defineProps({
  title: {
    type: String,
    default: null
  },
  // Hide the default header user cluster (admin badge, notifications, profile)
  // on mobile so a page can surface its own actions via the header-actions slot.
  hideHeaderUserOnMobile: {
    type: Boolean,
    default: false
  }
})

const Passthrough = (_, { slots }) => slots.default?.()

// Pages are expected to opt into the persistent shell with
// `defineOptions({ layout: AppShell })`. Without it there is no shell above
// this component, so it provides the state for the shell it renders itself.
const injectedShell = inject(APP_SHELL_KEY, null)
const persistent = injectedShell !== null
const shell = injectedShell ?? createAppShellState()
if (!persistent) provide(APP_SHELL_KEY, shell)

const ownerToken = Symbol('page')
watchEffect(() => {
  shell.claim(ownerToken, { hideHeaderUserOnMobile: props.hideHeaderUserOnMobile })
})
onBeforeUnmount(() => shell.release(ownerToken))

const pageTitle = computed(() => props.title || 'Unbekannt')
</script>
