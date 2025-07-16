<!-- resources/js/Pages/Auth/ForgotPassword.vue -->
<template>
  <AuthLayout title="Passwort vergessen">
    <template #title>Passwort zurücksetzen</template>

    <div class="mb-4 text-sm text-gray-600">
      Haben Sie Ihr Passwort vergessen? Kein Problem. Teilen Sie uns einfach Ihre E-Mail-Adresse mit und wir senden Ihnen einen Link zum Zurücksetzen des Passworts zu, mit dem Sie ein neues wählen können.
    </div>

    <!-- Status Message -->
    <div v-if="status" class="mb-4 font-medium text-sm text-green-600">
      {{ status }}
    </div>

    <form @submit.prevent="submit" class="space-y-6">
      <div>
        <label for="email" class="block text-sm font-medium text-gray-700">
          E-Mail-Adresse
        </label>
        <div class="mt-1">
          <input
            id="email"
            v-model="form.email"
            type="email"
            autocomplete="email"
            required
            class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            :class="{ 'border-red-300': errors.email }"
          />
          <p v-if="errors.email" class="mt-2 text-sm text-red-600">
            {{ errors.email }}
          </p>
        </div>
      </div>

      <div>
        <button
          type="submit"
          :disabled="processing"
          class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
        >
          <span v-if="processing">Link wird gesendet...</span>
          <span v-else>Passwort-Reset-Link senden</span>
        </button>
      </div>

      <div class="text-center">
        <Link href="/login" class="text-sm text-indigo-600 hover:text-indigo-500">
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
  status: String,
  errors: Object,
})

const form = useForm({
  email: '',
})

const submit = () => {
  form.post('/forgot-password')
}

const processing = computed(() => form.processing)
</script>
