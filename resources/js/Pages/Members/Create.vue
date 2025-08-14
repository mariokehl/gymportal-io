<template>
  <AppLayout title="Neues Mitglied">
    <template #header>
      <div class="flex items-center">
        <Link
          :href="route('members.index')"
          class="text-gray-500 hover:text-gray-700 mr-4"
        >
          <ArrowLeft class="w-5 h-5" />
        </Link>
        Neues Mitglied anlegen
      </div>
    </template>

    <div class="max-w-4xl mx-auto">
      <!-- Progress Bar -->
      <div class="mb-8">
        <div class="flex items-center justify-between">
          <div v-for="(step, index) in steps" :key="step.id" class="flex items-center flex-1">
            <div class="flex items-center">
              <div
                class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-medium border-2 transition-colors"
                :class="getStepClasses(index)"
              >
                <CheckIcon v-if="index < currentStep" class="w-5 h-5" />
                <span v-else>{{ step.id }}</span>
              </div>
              <div class="ml-3">
                <p class="text-sm font-medium" :class="index <= currentStep ? 'text-indigo-600' : 'text-gray-500'">
                  {{ step.name }}
                </p>
              </div>
            </div>
            <!-- Connection Line -->
            <div v-if="index < steps.length - 1" class="flex-1 ml-6">
              <div
                class="h-0.5 w-full transition-colors"
                :class="index < currentStep ? 'bg-indigo-600' : 'bg-gray-300'"
              ></div>
            </div>
          </div>
        </div>
      </div>

      <form @submit.prevent="handleSubmit" class="bg-white rounded-lg shadow">

        <!-- Schritt 1: Persönliche Daten -->
        <div v-show="currentStep === 0" class="p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-6">Persönliche Daten</h3>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">
                Vorname <span class="text-red-500">*</span>
              </label>
              <input
                id="first_name"
                v-model="form.first_name"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                :class="{ 'border-red-500': errors.first_name }"
                @blur="handleFieldBlur('first_name', 'Vorname ist erforderlich')"
              />
              <p v-if="errors.first_name" class="mt-1 text-sm text-red-600">
                {{ errors.first_name }}
              </p>
            </div>

            <div>
              <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">
                Nachname <span class="text-red-500">*</span>
              </label>
              <input
                id="last_name"
                v-model="form.last_name"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                :class="{ 'border-red-500': errors.last_name }"
                @blur="handleFieldBlur('last_name', 'Nachname ist erforderlich')"
              />
              <p v-if="errors.last_name" class="mt-1 text-sm text-red-600">
                {{ errors.last_name }}
              </p>
            </div>

            <div>
              <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                E-Mail <span class="text-red-500">*</span>
              </label>
              <input
                id="email"
                v-model.trim="form.email"
                type="email"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                :class="{ 'border-red-500': errors.email }"
                @blur="handleEmailBlur"
                autocomplete="email"
              />
              <p v-if="errors.email" class="mt-1 text-sm text-red-600">
                {{ errors.email }}
              </p>
            </div>

            <div>
              <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                Mobilfunknummer <span class="text-red-500">*</span>
              </label>
              <input
                id="phone"
                v-model="form.phone"
                type="tel"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                :class="{ 'border-red-500': errors.phone }"
                @blur="handleFieldBlur('phone', 'Mobilfunknummer ist erforderlich')"
              />
              <p v-if="errors.phone" class="mt-1 text-sm text-red-600">
                {{ errors.phone }}
              </p>
            </div>

            <div>
              <label for="birth_date" class="block text-sm font-medium text-gray-700 mb-2">
                Geburtsdatum
              </label>
              <input
                id="birth_date"
                v-model="form.birth_date"
                type="date"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                :class="{ 'border-red-500': errors.birth_date }"
              />
              <p v-if="errors.birth_date" class="mt-1 text-sm text-red-600">
                {{ errors.birth_date }}
              </p>
            </div>
          </div>

          <!-- Adresse in Schritt 1 -->
          <h4 class="text-md font-medium text-gray-900 mt-8 mb-4">Adresse</h4>
          <div class="grid grid-cols-1 gap-6">
            <div>
              <label for="address" class="block text-sm font-medium text-gray-700 mb-2">
                Straße und Hausnummer <span class="text-red-500">*</span>
              </label>
              <input
                id="address"
                v-model="form.address"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                :class="{ 'border-red-500': errors.address }"
                @blur="handleFieldBlur('address', 'Straße und Hausnummer ist erforderlich')"
              />
              <p v-if="errors.address" class="mt-1 text-sm text-red-600">
                {{ errors.address }}
              </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
              <div>
                <label for="postal_code" class="block text-sm font-medium text-gray-700 mb-2">
                  PLZ <span class="text-red-500">*</span>
                </label>
                <input
                  id="postal_code"
                  v-model="form.postal_code"
                  type="text"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  :class="{ 'border-red-500': errors.postal_code }"
                  @blur="handleFieldBlur('postal_code', 'PLZ ist erforderlich')"
                />
                <p v-if="errors.postal_code" class="mt-1 text-sm text-red-600">
                  {{ errors.postal_code }}
                </p>
              </div>

              <div class="md:col-span-2">
                <label for="city" class="block text-sm font-medium text-gray-700 mb-2">
                  Stadt <span class="text-red-500">*</span>
                </label>
                <input
                  id="city"
                  v-model="form.city"
                  type="text"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  :class="{ 'border-red-500': errors.city }"
                  @blur="handleFieldBlur('city', 'Stadt ist erforderlich')"
                />
                <p v-if="errors.city" class="mt-1 text-sm text-red-600">
                  {{ errors.city }}
                </p>
              </div>
            </div>

            <div>
              <label for="country" class="block text-sm font-medium text-gray-700 mb-2">
                Land <span class="text-red-500">*</span>
              </label>
              <select
                id="country"
                v-model="form.country"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                :class="{ 'border-red-500': errors.country }"
                @blur="handleFieldBlur('country', 'Land ist erforderlich')"
              >
                <option value="" selected>Land auswählen</option>
                <option value="DE">Deutschland</option>
                <option value="AT">Österreich</option>
                <option value="CH">Schweiz</option>
              </select>
              <p v-if="errors.country" class="mt-1 text-sm text-red-600">
                {{ errors.country }}
              </p>
            </div>
          </div>

          <!-- Notfallkontakt in Schritt 1 -->
          <h4 class="text-md font-medium text-gray-900 mt-8 mb-4">Notfallkontakt</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label for="emergency_contact_name" class="block text-sm font-medium text-gray-700 mb-2">
                Name des Notfallkontakts
              </label>
              <input
                id="emergency_contact_name"
                v-model="form.emergency_contact_name"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                :class="{ 'border-red-500': errors.emergency_contact_name }"
              />
              <p v-if="errors.emergency_contact_name" class="mt-1 text-sm text-red-600">
                {{ errors.emergency_contact_name }}
              </p>
            </div>

            <div>
              <label for="emergency_contact_phone" class="block text-sm font-medium text-gray-700 mb-2">
                Telefon des Notfallkontakts
              </label>
              <input
                id="emergency_contact_phone"
                v-model="form.emergency_contact_phone"
                type="tel"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                :class="{ 'border-red-500': errors.emergency_contact_phone }"
              />
              <p v-if="errors.emergency_contact_phone" class="mt-1 text-sm text-red-600">
                {{ errors.emergency_contact_phone }}
              </p>
            </div>
          </div>

          <div class="mt-6">
            <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
              Zusätzliche Notizen (optional)
            </label>
            <textarea
              id="notes"
              v-model="form.notes"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
              placeholder="Besondere Hinweise, medizinische Informationen, etc..."
            ></textarea>
          </div>
        </div>

        <!-- Schritt 2: Mitgliedschaft -->
        <div v-show="currentStep === 1" class="p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-6">Mitgliedschaft wählen</h3>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
            <div
              v-for="plan in membershipPlans"
              :key="plan.id"
              class="border rounded-lg p-4 cursor-pointer transition-all hover:shadow-md"
              :class="form.membership_plan_id === plan.id ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300'"
              @click="selectMembershipPlan(plan.id)"
            >
              <div class="flex items-center mb-3">
                <input
                  type="radio"
                  :id="`plan_${plan.id}`"
                  :value="plan.id"
                  v-model="form.membership_plan_id"
                  class="w-4 h-4 text-indigo-600 focus:ring-indigo-500"
                />
                <label :for="`plan_${plan.id}`" class="ml-2 font-medium text-gray-900">
                  {{ plan.name }}
                </label>
              </div>
              <p class="text-sm text-gray-600 mb-3">{{ plan.description }}</p>
              <div class="flex justify-between items-center">
                <span class="text-lg font-bold text-indigo-600">
                  {{ formatCurrency(plan.price) }}
                </span>
                <span class="text-sm text-gray-500">
                  / {{ getBillingCycleText(plan.billing_cycle) }}
                </span>
              </div>
              <div class="mt-2 text-xs text-gray-500">
                <span v-if="plan.commitment_months > 0">
                  Mindestlaufzeit: {{ plan.commitment_months }} Monate
                </span>
                <span v-else>Monatlich kündbar</span>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label for="joined_date" class="block text-sm font-medium text-gray-700 mb-2">
                Startdatum der Mitgliedschaft <span class="text-red-500">*</span>
              </label>
              <input
                id="joined_date"
                v-model="form.joined_date"
                type="date"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                :class="{ 'border-red-500': errors.joined_date }"
                :min="today"
                @blur="validateStep2"
              />
              <p v-if="errors.joined_date" class="mt-1 text-sm text-red-600">
                {{ errors.joined_date }}
              </p>
            </div>

            <div v-if="selectedPlan">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Vertragslaufzeit bis
              </label>
              <div class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md text-gray-600">
                {{ getEndDate() }}
              </div>
            </div>
          </div>

          <p v-if="errors.membership_plan_id" class="mt-4 text-sm text-red-600">
            {{ errors.membership_plan_id }}
          </p>
        </div>

        <!-- Schritt 3: Zahlungsmethode -->
        <div v-show="currentStep === 2" class="p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-6">Zahlungsmethode</h3>

          <!-- Show message if no payment methods are enabled -->
          <div v-if="!paymentMethods || !Array.isArray(paymentMethods) || paymentMethods.length === 0" class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
            <div class="flex">
              <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
              </div>
              <div class="ml-3">
                <p class="text-sm text-yellow-800">
                  Keine Zahlungsmethoden aktiviert. Bitte aktiviere mindestens eine Zahlungsmethode in den Einstellungen.
                </p>
              </div>
            </div>
          </div>

          <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div
              v-for="method in paymentMethods"
              :key="method.key"
              class="border rounded-lg p-4 cursor-pointer transition-all hover:shadow-md"
              :class="form.payment_method === method.key ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300'"
              @click="form.payment_method = method.key"
            >
              <div class="flex items-center">
                <input
                  type="radio"
                  :id="`payment_${method.key}`"
                  :value="method.key"
                  v-model="form.payment_method"
                  class="w-4 h-4 text-indigo-600 focus:ring-indigo-500"
                />
                <div class="ml-3">
                  <label :for="`payment_${method.key}`" class="font-medium text-gray-900">
                    {{ method.name }}
                  </label>
                  <p class="text-sm text-gray-600">{{ method.description }}</p>
                </div>
              </div>
            </div>
          </div>

          <p v-if="errors.payment_method" class="mt-4 text-sm text-red-600">
            {{ errors.payment_method }}
          </p>

          <!-- Zusätzliche Informationen für SEPA -->
          <div v-if="form.payment_method === 'sepa'" class="mt-6 p-4 bg-indigo-50 rounded-lg">
            <h4 class="font-medium text-indigo-900 mb-2">SEPA-Lastschrift</h4>
            <p class="text-sm text-indigo-800">
              Die Kontodaten können nach der Registrierung im Mitgliederbereich hinterlegt werden.
              Der erste Beitrag wird 7 Tage nach Vertragsstart eingezogen.
            </p>
          </div>
        </div>

        <!-- Schritt 4: Zusammenfassung -->
        <div v-show="currentStep === 3" class="p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-6">Zusammenfassung</h3>

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Persönliche Daten -->
            <div class="bg-gray-50 rounded-lg p-4">
              <h4 class="font-medium text-gray-900 mb-3">Persönliche Daten</h4>
              <div class="space-y-2 text-sm">
                <div><span class="font-medium">Name:</span> {{ form.first_name }} {{ form.last_name }}</div>
                <div><span class="font-medium">E-Mail:</span> {{ form.email }}</div>
                <div><span class="font-medium">Telefon:</span> {{ form.phone }}</div>
                <div><span class="font-medium">Geburtsdatum:</span> {{ formatDate(form.birth_date) }}</div>
                <div><span class="font-medium">Adresse:</span> {{ form.address }}, {{ form.postal_code }} {{ form.city }}, {{ form.country }}</div>
                <div><span class="font-medium">Notfallkontakt:</span> {{ form.emergency_contact_name }} ({{ form.emergency_contact_phone }})</div>
              </div>
            </div>

            <!-- Mitgliedschaft -->
            <div class="bg-gray-50 rounded-lg p-4">
              <h4 class="font-medium text-gray-900 mb-3">Mitgliedschaft</h4>
              <div class="space-y-2 text-sm" v-if="selectedPlan">
                <div><span class="font-medium">Tarif:</span> {{ selectedPlan.name }}</div>
                <div><span class="font-medium">Preis:</span> {{ formatCurrency(selectedPlan.price) }} / {{ getBillingCycleText(selectedPlan.billing_cycle) }}</div>
                <div><span class="font-medium">Startdatum:</span> {{ formatDate(form.joined_date) }}</div>
                <div><span class="font-medium">Laufzeit: </span>
                  <span v-if="selectedPlan.commitment_months > 0">
                    {{ selectedPlan.commitment_months }} Monate (bis {{ getEndDate() }})
                  </span>
                  <span v-else>Monatlich kündbar</span>
                </div>
                <div><span class="font-medium">Zahlungsmethode:</span> {{ getPaymentMethodLabel() }}</div>
              </div>
            </div>
          </div>

          <!-- Datenschutz und AGB -->
          <!--
          <div class="mt-8 p-4 border border-gray-200 rounded-lg">
            <div class="flex items-start">
              <input
                id="accept_terms"
                v-model="form.accept_terms"
                type="checkbox"
                class="mt-1 w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
              />
              <label for="accept_terms" class="ml-2 text-sm text-gray-700">
                Ich akzeptiere die <a href="#" class="text-indigo-600 hover:underline">Allgemeinen Geschäftsbedingungen</a>
                und die <a href="#" class="text-indigo-600 hover:underline">Datenschutzerklärung</a>. <span class="text-red-500">*</span>
              </label>
            </div>
            <p v-if="errors.accept_terms" class="mt-2 text-sm text-red-600">
              {{ errors.accept_terms }}
            </p>
          </div>
          -->
        </div>


        <!-- Navigation Buttons -->
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-between">
          <button
            type="button"
            @click="previousStep"
            v-show="currentStep > 0"
            class="px-6 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
          >
            Zurück
          </button>

          <div class="flex space-x-3">
            <button
              type="button"
              @click="nextStep"
              v-show="currentStep < steps.length - 1"
              class="px-6 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              :disabled="!isCurrentStepValid()"
            >
              Weiter
            </button>

            <button
              type="submit"
              v-show="currentStep === steps.length - 1"
              class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500"
              :disabled="!isFormValid() || processing"
            >
              <span v-if="processing">Wird erstellt...</span>
              <span v-else>Mitglied anlegen</span>
            </button>
          </div>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import { ArrowLeft, CheckIcon } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    membershipPlans: {
        type: Array,
        default: () => []
    },
    paymentMethods: {
        type: Array,
        default: () => []
    }
})

const steps = [
  { id: 1, name: 'Persönliche Daten' },
  { id: 2, name: 'Mitgliedschaft' },
  { id: 3, name: 'Zahlungsmethode' },
  { id: 4, name: 'Zusammenfassung' },
]

const currentStep = ref(0)
const processing = ref(false)

const form = useForm({
  // Persönliche Daten
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  birth_date: '',
  address: '',
  city: '',
  postal_code: '',
  country: 'DE',
  emergency_contact_name: '',
  emergency_contact_phone: '',
  notes: '',
  status: 'active',

  // Mitgliedschaft
  membership_plan_id: null,
  joined_date: '',

  // Zahlungsmethode
  payment_method: '',

  // Zustimmung
  accept_terms: true // TODO: Momentan über Adminbereich immer erteilt, ggf. an SEPA-Lastschriftverfahren koppeln
})

const today = computed(() => {
  return new Date().toISOString().split('T')[0]
})

const selectedPlan = computed(() => {
  return props.membershipPlans.find(plan => plan.id === form.membership_plan_id)
})

const errors = computed(() => form.errors)

// Touch-Status für Felder verfolgen
const touchedFields = ref(new Set())

// Feld als berührt markieren
const markFieldAsTouched = (fieldName) => {
  touchedFields.value.add(fieldName)
}

// Prüfen ob Feld berührt wurde
const isFieldTouched = (fieldName) => {
  return touchedFields.value.has(fieldName)
}

// Email-Validierung (nur wenn Feld berührt wurde)
const validateEmail = (email) => {
  if (!isFieldTouched('email')) {
    return true // Keine Validierung wenn nicht berührt
  }

  const trimmedEmail = email.trim()

  // Email-Regex für Grundvalidierung
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

  if (!trimmedEmail) {
    form.setError('email', 'E-Mail ist erforderlich')
    return false
  }

  if (!emailRegex.test(trimmedEmail)) {
    form.setError('email', 'Bitte geben Sie eine gültige E-Mail-Adresse ein')
    return false
  }

  if (trimmedEmail.length > 255) {
    form.setError('email', 'E-Mail-Adresse ist zu lang (maximal 255 Zeichen)')
    return false
  }

  // Fehler löschen wenn E-Mail gültig ist
  form.clearErrors('email')
  return true
}

// Feldvalidierung für Pflichtfelder
const validateRequiredField = (fieldName, fieldValue, errorMessage) => {
  if (!isFieldTouched(fieldName)) {
    return true // Keine Validierung wenn nicht berührt
  }

  if (!fieldValue || fieldValue.toString().trim() === '') {
    form.setError(fieldName, errorMessage)
    return false
  }

  form.clearErrors(fieldName)
  return true
}

// Email-Feld Event Handler
const handleEmailBlur = () => {
  markFieldAsTouched('email')
  validateEmail(form.email)
}

// Generische Handler für andere Felder
const handleFieldBlur = (fieldName, errorMessage) => {
  markFieldAsTouched(fieldName)
  validateRequiredField(fieldName, form[fieldName], errorMessage)
}

// Step validation
const validateStep1 = () => {
  const step1Fields = ['first_name', 'last_name', 'phone', 'address', 'city', 'postal_code', 'country']

  let isValid = true

  // Prüfe alle Pflichtfelder
  step1Fields.forEach(field => {
    if (!form[field] || form[field].toString().trim() === '') {
      isValid = false
    }
  })

  // Email separat prüfen
  if (!form.email || form.email.trim() === '' || form.errors.email) {
    isValid = false
  }

  return isValid
}

const touchAllStep1Fields = () => {
  const step1Fields = ['first_name', 'last_name', 'email', 'phone', 'address', 'city', 'postal_code', 'country']
  step1Fields.forEach(field => markFieldAsTouched(field))
}

const validateStep2 = () => {
  return form.membership_plan_id && form.joined_date && !form.errors.membership_plan_id && !form.errors.joined_date
}

const validateStep3 = () => {
  return form.payment_method && !form.errors.payment_method
}

const validateStep4 = () => {
  return form.accept_terms && !form.errors.accept_terms
}

const isCurrentStepValid = () => {
  switch(currentStep.value) {
    case 0: return validateStep1()
    case 1: return validateStep2()
    case 2: return validateStep3()
    case 3: return validateStep4()
    default: return false
  }
}

const isFormValid = () => {
  return validateStep1() && validateStep2() && validateStep3() && validateStep4()
}

const getStepClasses = (index) => {
  if (index < currentStep.value) {
    return 'bg-indigo-600 border-indigo-600 text-white'
  } else if (index === currentStep.value) {
    return 'bg-indigo-100 border-indigo-600 text-indigo-600'
  } else {
    return 'bg-white border-gray-300 text-gray-500'
  }
}

const nextStep = () => {
  if (currentStep.value === 0) {
    // Alle Felder als berührt markieren und validieren
    touchAllStep1Fields()
    const requiredFields = [
      { field: 'first_name', message: 'Vorname ist erforderlich' },
      { field: 'last_name', message: 'Nachname ist erforderlich' },
      { field: 'phone', message: 'Mobilfunknummer ist erforderlich' },
      { field: 'address', message: 'Straße und Hausnummer ist erforderlich' },
      { field: 'city', message: 'Stadt ist erforderlich' },
      { field: 'postal_code', message: 'PLZ ist erforderlich' },
      { field: 'country', message: 'Land ist erforderlich' },
    ]

    requiredFields.forEach(({ field, message }) => {
      validateRequiredField(field, form[field], message)
    })
    validateEmail(form.email)
  }

  if (currentStep.value < steps.length - 1 && isCurrentStepValid()) {
    currentStep.value++
  }
}

const previousStep = () => {
  if (currentStep.value > 0) {
    currentStep.value--
  }
}

const selectMembershipPlan = (planId) => {
  form.membership_plan_id = planId
}

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency: 'EUR'
  }).format(amount)
}

const getBillingCycleText = (cycle) => {
  const cycles = {
    'monthly': 'Monat',
    'quarterly': 'Quartal',
    'yearly': 'Jahr'
  }
  return cycles[cycle] || cycle
}

const formatDate = (dateString) => {
  if (!dateString) return ''
  return new Date(dateString).toLocaleDateString('de-DE')
}

const getEndDate = () => {
    if (!selectedPlan.value || !form.joined_date || selectedPlan.value.commitment_months === 0) return 'Unbefristet'

    const startDate = new Date(form.joined_date)
    const endDate = new Date(startDate)
    endDate.setMonth(endDate.getMonth() + selectedPlan.value.commitment_months)

    // Einen Tag abziehen für korrektes Vertragsende
    endDate.setDate(endDate.getDate() - 1)

    return endDate.toLocaleDateString('de-DE')
}

const getPaymentMethodLabel = () => {
  if (!props.paymentMethods || !Array.isArray(props.paymentMethods)) {
    return ''
  }
  const method = props.paymentMethods.find(m => m.key === form.payment_method)
  return method ? method.name : ''
}

const handleSubmit = () => {
  if (!isFormValid()) return

  processing.value = true
  form.post(route('members.store'), {
    onSuccess: () => {
      processing.value = false
    },
    onError: () => {
      processing.value = false
      // Bei Fehlern zum entsprechenden Schritt zurück
      if (form.errors.first_name || form.errors.last_name || form.errors.email || form.errors.phone || form.errors.birth_date || form.errors.address || form.errors.city || form.errors.postal_code || form.errors.country || form.errors.emergency_contact_name || form.errors.emergency_contact_phone) {
        currentStep.value = 0
      } else if (form.errors.membership_plan_id || form.errors.joined_date) {
        currentStep.value = 1
      } else if (form.errors.payment_method) {
        currentStep.value = 2
      } else if (form.errors.accept_terms) {
        currentStep.value = 3
      }
    }
  })
}
</script>
