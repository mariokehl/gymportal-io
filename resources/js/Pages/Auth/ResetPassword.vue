<!-- resources/js/Pages/Auth/ResetPassword.vue -->
<template>
  <AuthLayout title="Neues Passwort setzen">
    <template #title>Neues Passwort setzen</template>

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
            readonly
            class="field appearance-none block w-full px-3 py-2 bg-surface-muted"
          />
          <p v-if="errors.email" class="mt-2 text-sm text-danger">
            {{ errors.email }}
          </p>
        </div>
      </div>

      <div>
        <label for="password" class="block text-sm font-medium text-ink-secondary">
          Neues Passwort
        </label>
        <div class="mt-1">
          <input
            id="password"
            v-model="form.password"
            type="password"
            autocomplete="new-password"
            required
            class="field appearance-none block w-full px-3 py-2"
            :class="{ 'border-danger': errors.password }"
          />
          <p v-if="errors.password" class="mt-2 text-sm text-danger">
            {{ errors.password }}
          </p>
        </div>
      </div>

      <div>
        <label for="password_confirmation" class="block text-sm font-medium text-ink-secondary">
          Passwort bestätigen
        </label>
        <div class="mt-1">
          <input
            id="password_confirmation"
            v-model="form.password_confirmation"
            type="password"
            autocomplete="new-password"
            required
            class="field appearance-none block w-full px-3 py-2"
          />
        </div>
      </div>

      <div>
        <button
          type="submit"
          :disabled="processing"
          class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-brand hover:bg-brand-strong disabled:opacity-50"
        >
          <span v-if="processing">Passwort wird zurückgesetzt...</span>
          <span v-else>Passwort zurücksetzen</span>
        </button>
      </div>

      <div class="text-center">
        <Link href="/login" class="text-sm text-brand-ink hover:text-brand-ink-strong">
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

const props = defineProps({
  token: String,
  email: String,
  errors: Object,
})

const form = useForm({
  token: props.token,
  email: props.email,
  password: '',
  password_confirmation: '',
})

const submit = () => {
  form.post('/reset-password', {
    onFinish: () => form.reset('password', 'password_confirmation'),
  })
}

const processing = computed(() => form.processing)
</script>
