<!-- resources/js/Pages/Auth/Blocked.vue -->
<template>
  <AuthLayout title="Account gesperrt">
    <template #title>Ihr Account wurde gesperrt</template>

    <div class="text-center space-y-6">
      <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-danger-soft">
        <svg class="h-6 w-6 text-danger" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
        </svg>
      </div>

      <div class="space-y-4">
        <h3 class="text-lg font-semibold text-ink">
          Zugang gesperrt
        </h3>

        <div class="text-sm text-ink-secondary bg-danger-subtle border border-danger-line rounded-md p-4">
          <p class="font-medium text-danger-strong">Grund der Sperrung:</p>
          <p class="mt-2 text-danger">{{ reason }}</p>
        </div>

        <p class="text-ink-secondary">
          Bitte kontaktieren Sie den Administrator, um Ihren Account wieder zu aktivieren.
        </p>
      </div>

      <div class="pt-4">
        <Link
          href="/login"
          class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-brand hover:bg-brand-strong"
        >
          Zurück zur Anmeldung
        </Link>
      </div>
    </div>
  </AuthLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import AuthLayout from '@/Layouts/AuthLayout.vue'

defineProps({
  reason: {
    type: String,
    default: 'Ihr Account wurde gesperrt.'
  }
})
</script>