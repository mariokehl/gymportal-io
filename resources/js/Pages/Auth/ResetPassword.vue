<!-- resources/js/Pages/Auth/ResetPassword.vue -->
<template>
  <AuthLayout>
    <template #title>Neues Passwort setzen</template>

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
            readonly
            class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md placeholder-gray-400 bg-gray-50 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
          />
          <p v-if="errors.email" class="mt-2 text-sm text-red-600">
            {{ errors.email }}
          </p>
        </div>
      </div>

      <div>
        <label for="password" class="block text-sm font-medium text-gray-700">
          Neues Passwort
        </label>
        <div class="mt-1">
          <input
            id="password"
            v-model="form.password"
            type="password"
            autocomplete="new-password"
            required
            class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            :class="{ 'border-red-300': errors.password }"
          />
          <p v-if="errors.password" class="mt-2 text-sm text-red-600">
            {{ errors.password }}
          </p>
        </div>
      </div>

      <div>
        <label for="password_confirmation" class="block text-sm font-medium text-gray-700">
          Passwort bestätigen
        </label>
        <div class="mt-1">
          <input
            id="password_confirmation"
            v-model="form.password_confirmation"
            type="password"
            autocomplete="new-password"
            required
            class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
          />
        </div>
      </div>

      <div>
        <button
          type="submit"
          :disabled="processing"
          class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
        >
          <span v-if="processing">Passwort wird zurückgesetzt...</span>
          <span v-else>Passwort zurücksetzen</span>
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
