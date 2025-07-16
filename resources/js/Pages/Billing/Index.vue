<template>
  <div class="min-h-screen bg-gray-50">
    <Head title="Abrechnung" />

    <!-- Header -->
    <header class="bg-white shadow-sm border-b border-gray-200">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center py-6">
          <div class="flex items-center">
            <Link :href="route('dashboard')" class="flex items-center text-gray-600 hover:text-gray-900 transition-colors">
              <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
              </svg>
              Zurück zum Dashboard
            </Link>
          </div>
          <div class="flex items-center">
            <h1 class="text-2xl font-bold text-gray-900">gymportal.io</h1>
            <span class="ml-2 text-sm text-gray-500">Abrechnung</span>
          </div>
          <div class="flex items-center">
            <span class="text-sm text-gray-500">Angemeldet als {{ user.first_name }} {{ user.last_name }}</span>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
      <div class="space-y-8">
        <!-- Trial Banner -->
        <div v-if="trial.is_active" class="bg-blue-50 border border-blue-200 rounded-lg p-6">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <svg class="h-6 w-6 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-blue-800">Testphase aktiv</h3>
              <div class="mt-2 text-sm text-blue-700">
                <p>Ihre Testphase endet am {{ trial.ends_at }}. Sie haben noch {{ roundedTrialDaysLeft }} {{ roundedTrialDaysLeft === 1 ? 'Tag' : 'Tage' }} Zeit.</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Expired Trial Banner -->
        <div v-if="!trial.is_active && !subscription.is_active" class="bg-red-50 border border-red-200 rounded-lg p-6">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <svg class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.084 16.5c-.77.833.192 2.5 1.732 2.5z" />
              </svg>
            </div>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-red-800">Testphase abgelaufen</h3>
              <div class="mt-2 text-sm text-red-700">
                <p>Ihre Testphase ist am {{ trial.ends_at }} abgelaufen. Schließen Sie ein Abonnement ab, um alle Funktionen weiterhin zu nutzen.</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Current Subscription Plan -->
        <div class="bg-white shadow rounded-lg">
          <div class="px-6 py-5 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Aktueller Tarif</h3>
          </div>
          <div class="px-6 py-6">
            <div class="flex items-center justify-between">
              <div>
                <h4 class="text-xl font-semibold text-gray-900">
                  {{ subscription.is_active ? 'SaaS Hosted' : 'Testphase' }}
                </h4>
                <p class="text-gray-600 mt-1">
                  {{ subscription.is_active ? '€29,00 (exkl. MwSt.) / Monat' : 'Kostenlos für 30 Tage' }}
                </p>
                <p class="text-sm text-gray-500 mt-2">
                  Für kleine und mittlere Fitnessstudios, die das Beste aus gymportal.io herausholen möchten.
                </p>
              </div>
              <div class="text-right">
                <span v-if="subscription.is_active" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                  Aktiv
                </span>
                <span v-else-if="trial.is_active" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                  Testphase
                </span>
                <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                  Abgelaufen
                </span>
              </div>
            </div>

            <!-- Features List -->
            <div class="mt-6">
              <ul class="space-y-2">
                <li class="flex items-center">
                  <svg class="h-5 w-5 text-green-500 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  <span class="text-sm text-gray-700">Unbegrenzte Mitglieder</span>
                </li>
                <li class="flex items-center">
                  <svg class="h-5 w-5 text-green-500 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  <span class="text-sm text-gray-700">Mitarbeiterverwaltung</span>
                </li>
                <li class="flex items-center">
                  <svg class="h-5 w-5 text-green-500 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  <span class="text-sm text-gray-700">Finanzmanagement</span>
                </li>
                <li class="flex items-center">
                  <svg class="h-5 w-5 text-green-500 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  <span class="text-sm text-gray-700">Vertragsverwaltung</span>
                </li>
                <li class="flex items-center">
                  <svg class="h-5 w-5 text-green-500 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  <span class="text-sm text-gray-700">Widget-Integration</span>
                </li>
                <li class="flex items-center">
                  <svg class="h-5 w-5 text-green-500 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  <span class="text-sm text-gray-700">Priority Support</span>
                </li>
              </ul>
            </div>

            <!-- Action Button -->
            <div class="mt-8">
              <button
                v-if="!subscription.is_active"
                @click="subscribeToProfessional"
                :disabled="isProcessing"
                class="bg-blue-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50"
              >
                {{ isProcessing ? 'Wird verarbeitet...' : 'Jetzt abonnieren' }}
              </button>
            </div>
          </div>
        </div>

        <!-- Next Payment -->
        <div v-if="subscription.is_active && nextPayment" class="bg-white shadow rounded-lg">
          <div class="px-6 py-5 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Nächste Zahlung</h3>
          </div>
          <div class="px-6 py-6">
            <p class="text-sm text-gray-600">
              Ihre nächste Zahlung von €{{ nextPayment.amount }} wird am {{ nextPayment.date }} verarbeitet.
            </p>
          </div>
        </div>

        <!-- Cancel Subscription -->
        <div v-if="subscription.is_active" class="bg-white shadow rounded-lg">
          <div class="px-6 py-5 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Abonnement kündigen</h3>
          </div>
          <div class="px-6 py-6">
            <p class="text-sm text-gray-600 mb-4">
              Sie können Ihr Abonnement jederzeit kündigen. Nach der Kündigung haben Sie noch bis zum Ende Ihres aktuellen Abrechnungszeitraums Zugriff auf alle Funktionen.
            </p>
            <button
              @click="cancelSubscription"
              class="bg-red-600 text-white px-4 py-2 rounded-md font-medium hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
            >
              Abonnement kündigen
            </button>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { router, usePage, Head, Link } from '@inertiajs/vue3'

const page = usePage()

const props = defineProps({
  gym: Object,
  subscription: Object,
  trial: Object,
  next_payment: Object,
  paddle_token: String,
  paddle_environment: String,
})

const isProcessing = ref(false)
const nextPayment = ref(props.next_payment)

const user = computed(() => page.props.auth.user)

const roundedTrialDaysLeft = computed(() => {
  return Math.round(props.trial.days_left)
})

onMounted(() => {
  // Paddle.js laden
  if (typeof window !== 'undefined' && !window.Paddle) {
    const script = document.createElement('script')
    script.src = props.paddle_environment === 'sandbox'
      ? 'https://cdn.paddle.com/paddle/v2/paddle.js'
      : 'https://cdn.paddle.com/paddle/v2/paddle.js'
    script.onload = () => {
      window.Paddle.Initialize({
        token: `${props.paddle_token}`,
      })
      window.Paddle.Environment.set(props.paddle_environment);
    }
    document.head.appendChild(script)
  }
})

const subscribeToProfessional = async () => {
  if (isProcessing.value) return

  isProcessing.value = true

  try {
    const response = await axios.post('/billing/subscribe')

    const data = response.data;

    if (data.success && window.Paddle) {
      window.Paddle.Checkout.open({
        ...data.checkout_data,
        successCallback: () => {
          router.reload()
        },
        closeCallback: () => {
          isProcessing.value = false
        }
      })
    }
  } catch (error) {
    console.error('Error:', error)
    isProcessing.value = false
  }
}

const cancelSubscription = async () => {
  if (!confirm('Sind Sie sicher, dass Sie Ihr Abonnement kündigen möchten?')) {
    return
  }

  try {
    await router.post('/billing/cancel', {}, {
      preserveScroll: true,
      onFinish: () => {
        // Seite neu laden nach der Kündigung
        router.reload()
      }
    })
  } catch (error) {
    console.error('Error:', error)
  }
}
</script>
