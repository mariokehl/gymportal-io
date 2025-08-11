<template>
  <Head title="E-Mail bestätigen" />

  <div class="min-h-screen bg-gradient-to-br from-indigo-50 to-blue-50 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
      <div class="text-center">
        <svg class="mx-auto h-12 w-12 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
        </svg>
        <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
          E-Mail-Adresse bestätigen
        </h2>
        <p class="mt-2 text-sm text-gray-600">
          Fast geschafft! Überprüfen Sie Ihr E-Mail-Postfach.
        </p>
      </div>

      <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
        <!-- Flash Message -->
        <Transition
          enter-active-class="transition ease-out duration-200"
          enter-from-class="opacity-0 transform -translate-y-2"
          enter-to-class="opacity-100 transform translate-y-0"
          leave-active-class="transition ease-in duration-150"
          leave-from-class="opacity-100"
          leave-to-class="opacity-0"
        >
          <div v-if="$page.props.flash.message" class="mb-4 p-4 bg-green-50 border border-green-200 rounded-md">
            <div class="flex">
              <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
              </svg>
              <p class="ml-3 text-sm text-green-800">{{ $page.props.flash.message }}</p>
            </div>
          </div>
        </Transition>

        <div class="space-y-6">
          <div class="text-sm text-gray-600">
            <p class="mb-4">
              Hallo <span class="font-semibold">{{ userName }}</span>!
            </p>
            <p class="mb-4">
              Wir haben Ihnen eine E-Mail an <span class="font-semibold">{{ $page.props.auth.user.email }}</span> gesendet.
              Bitte klicken Sie auf den Link in der E-Mail, um Ihre Adresse zu bestätigen.
            </p>

            <div class="p-4 bg-amber-50 border border-amber-200 rounded-md">
              <div class="flex">
                <svg class="h-5 w-5 text-amber-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd"
                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                        clip-rule="evenodd" />
                </svg>
                <div class="ml-3">
                  <p class="text-sm text-amber-700">
                    <strong>Wichtig:</strong> Der Link ist nur 60 Minuten gültig.
                  </p>
                  <p class="text-xs text-amber-600 mt-1">
                    Prüfen Sie auch Ihren Spam-Ordner.
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div class="border-t border-gray-200 pt-6">
            <p class="text-sm text-gray-600 mb-4">
              Keine E-Mail erhalten?
            </p>

            <form @submit.prevent="resendEmail">
              <button
                type="submit"
                :disabled="processing"
                :class="[
                  'w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white transition-all',
                  processing
                    ? 'bg-gray-400 cursor-not-allowed'
                    : 'bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500'
                ]"
              >
                <span v-if="!processing">E-Mail erneut senden</span>
                <span v-else class="flex items-center">
                  <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  Wird gesendet...
                </span>
              </button>
            </form>
          </div>

          <div class="text-center pt-4">
            <Link
              :href="route('logout')"
              method="post"
              as="button"
              class="text-sm text-gray-500 hover:text-gray-700 underline"
            >
              Abmelden
            </Link>
          </div>
        </div>
      </div>

      <div class="text-center">
        <p class="text-xs text-gray-500">
          Probleme? Kontaktieren Sie uns unter support@gymportal.io
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router, Head, usePage, Link } from '@inertiajs/vue3'

const page = usePage()
const processing = ref(false)

// Berechne den vollen Namen des Users
const userName = computed(() => {
  const user = page.props.auth.user
  return user ? `${user.first_name} ${user.last_name}` : 'Nutzer'
})

const resendEmail = () => {
  processing.value = true

  router.post(route('verification.send'), {}, {
    preserveScroll: true,
    onFinish: () => {
      processing.value = false
    }
  })
}
</script>
