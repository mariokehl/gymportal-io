<!-- resources/js/Pages/Auth/Register.vue -->
<template>
  <AuthLayout title="Registrieren">
    <template #title>Konto erstellen</template>

    <form @submit.prevent="submit" class="space-y-6">
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div>
          <label for="first_name" class="block text-sm font-medium text-ink-secondary">
            Vorname
          </label>
          <div class="mt-1">
            <input
              id="first_name"
              v-model="form.first_name"
              type="text"
              autocomplete="given-name"
              required
              class="field appearance-none block w-full px-3 py-2"
              :class="{ 'border-danger': errors.first_name }"
            />
            <p v-if="errors.first_name" class="mt-2 text-sm text-danger">
              {{ errors.first_name }}
            </p>
          </div>
        </div>

        <div>
          <label for="last_name" class="block text-sm font-medium text-ink-secondary">
            Nachname
          </label>
          <div class="mt-1">
            <input
              id="last_name"
              v-model="form.last_name"
              type="text"
              autocomplete="family-name"
              required
              class="field appearance-none block w-full px-3 py-2"
              :class="{ 'border-danger': errors.last_name }"
            />
            <p v-if="errors.last_name" class="mt-2 text-sm text-danger">
              {{ errors.last_name }}
            </p>
          </div>
        </div>
      </div>

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
        <label for="password" class="block text-sm font-medium text-ink-secondary">
          Passwort
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

      <!-- Terms and Privacy Policy Checkbox -->
      <div>
        <div class="flex items-start">
          <div class="flex items-center h-5">
            <input
              id="terms_accepted"
              v-model="form.terms_accepted"
              type="checkbox"
              required
              class="h-4 w-4 text-brand-ink border-line-strong rounded"
              :class="{ 'border-danger': errors.terms_accepted }"
            />
          </div>
          <div class="ml-3 text-sm">
            <label for="terms_accepted" class="text-ink-secondary">
              Ich stimme den
              <a
                href="https://gymportal.io/terms"
                target="_blank"
                rel="noopener noreferrer"
                class="text-brand-ink hover:text-brand-ink-strong underline">AGB</a>
              und dem
              <a
                href="https://gymportal.io/privacy"
                target="_blank"
                rel="noopener noreferrer"
                class="text-brand-ink hover:text-brand-ink-strong underline">Datenschutz</a>
              zu
            </label>
          </div>
        </div>
        <p v-if="errors.terms_accepted" class="mt-2 text-sm text-danger">
          {{ errors.terms_accepted }}
        </p>
      </div>

      <div>
        <button
          type="submit"
          :disabled="processing || !form.terms_accepted"
          class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-brand hover:bg-brand-strong disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <span v-if="processing">Wird registriert...</span>
          <span v-else>Registrieren</span>
        </button>
      </div>

      <div class="text-center">
        <Link href="/login" class="text-sm text-brand-ink hover:text-brand-ink-strong">
          Bereits registriert? Hier anmelden
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
  first_name: '',
  last_name: '',
  email: '',
  password: '',
  password_confirmation: '',
  terms_accepted: false,
})

const submit = () => {
  form.post('/register', {
    onFinish: () => form.reset('password', 'password_confirmation'),
  })
}

const processing = computed(() => form.processing)
</script>
