<!-- resources/js/Pages/Auth/ForgotPassword.vue -->
<template>
  <AuthLayout title="Passwort vergessen">
    <template #title>Passwort zurücksetzen</template>

    <div class="mb-4 text-sm text-ink-secondary">
      Haben Sie Ihr Passwort vergessen? Kein Problem. Teilen Sie uns einfach Ihre E-Mail-Adresse mit und wir senden Ihnen einen Link zum Zurücksetzen des Passworts zu, mit dem Sie ein neues wählen können.
    </div>

    <form @submit.prevent="submit" class="space-y-6">
      <div>
        <label for="email" class="block text-sm font-medium text-ink-secondary">
          E-Mail-Adresse
        </label>
        <div class="mt-1">
          <input
            id="email"
            v-model="form.email"
            type="email"
            autocomplete="email"
            required
            class="field appearance-none block w-full px-3 py-2"
            :class="{ 'border-danger': errors.email }"
          />
          <p v-if="errors.email" class="mt-2 text-sm text-danger">
            {{ errors.email }}
          </p>
        </div>
      </div>

      <div>
        <button
          type="submit"
          :disabled="processing"
          class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-brand hover:bg-brand-strong disabled:opacity-50"
        >
          <span v-if="processing">Link wird gesendet...</span>
          <span v-else>Passwort-Reset-Link senden</span>
        </button>
      </div>

      <div class="text-center">
        <Link href="/login" class="text-sm text-brand hover:text-brand-strong">
          Zurück zur Anmeldung
        </Link>
      </div>
    </form>
  </AuthLayout>
</template>

<script setup>
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Link } from '@inertiajs/vue3'
import AuthLayout from '@/Layouts/AuthLayout.vue'

defineProps({
  errors: Object,
})

const form = useForm({
  email: '',
})

const submit = () => {
  form.post('/forgot-password', {
    onSuccess: () => form.reset('email'),
  })
}

const processing = computed(() => form.processing)
</script>
