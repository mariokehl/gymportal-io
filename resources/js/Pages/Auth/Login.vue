<!-- resources/js/Pages/Auth/Login.vue -->
<template>
  <AuthLayout title="Login">
    <template #title>Bei Ihrem Konto anmelden</template>

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
        <label for="password" class="block text-sm font-medium text-ink-secondary">
          Passwort
        </label>
        <div class="mt-1">
          <input
            id="password"
            v-model="form.password"
            type="password"
            autocomplete="current-password"
            required
            class="field appearance-none block w-full px-3 py-2"
            :class="{ 'border-danger': errors.password }"
          />
          <p v-if="errors.password" class="mt-2 text-sm text-danger">
            {{ errors.password }}
          </p>
        </div>
      </div>

      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <input
            id="remember"
            v-model="form.remember"
            type="checkbox"
            class="h-4 w-4 text-brand border-line-strong rounded"
          />
          <label for="remember" class="ml-2 block text-sm text-ink">
            Angemeldet bleiben
          </label>
        </div>

        <div class="text-sm">
          <Link href="/forgot-password" class="font-medium text-brand hover:text-brand-strong">
            Passwort vergessen?
          </Link>
        </div>
      </div>

      <div>
        <button
          type="submit"
          :disabled="processing"
          class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-brand hover:bg-brand-strong disabled:opacity-50"
        >
          <span v-if="processing">Wird angemeldet...</span>
          <span v-else>Anmelden</span>
        </button>
      </div>

      <div class="text-center">
        <Link href="/register" class="text-sm text-brand hover:text-brand-strong">
          Noch kein Konto? Hier registrieren
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
  password: '',
  remember: false,
})

const submit = () => {
  form.post('/login', {
    onFinish: () => form.reset('password'),
  })
}

const processing = computed(() => form.processing)
</script>
