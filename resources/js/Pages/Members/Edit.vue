<template>
  <AppLayout title="Mitglied bearbeiten">
    <template #header>
      <div class="flex items-center">
        <Link
          :href="route('members.index')"
          class="text-gray-500 hover:text-gray-700 mr-4"
        >
          <ArrowLeft class="w-5 h-5" />
        </Link>
        Mitglied bearbeiten: {{ member.first_name }} {{ member.last_name }}
      </div>
    </template>

    <div class="max-w-4xl mx-auto">
      <form @submit.prevent="submitForm" class="space-y-6">
        <!-- Persönliche Daten -->
        <div class="bg-white rounded-lg shadow p-6">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Persönliche Daten</h3>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label for="member_number" class="block text-sm font-medium text-gray-700">
                Mitgliedsnummer *
              </label>
              <input
                id="member_number"
                v-model="form.member_number"
                type="text"
                required
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                :class="{ 'border-red-300': errors.member_number }"
              />
              <p v-if="errors.member_number" class="mt-1 text-sm text-red-600">{{ errors.member_number }}</p>
            </div>

            <div>
              <label for="status" class="block text-sm font-medium text-gray-700">
                Status *
              </label>
              <select
                id="status"
                v-model="form.status"
                required
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                :class="{ 'border-red-300': errors.status }"
              >
                <option value="">Status wählen</option>
                <option value="active">Aktiv</option>
                <option value="inactive">Inaktiv</option>
                <option value="paused">Pausiert</option>
                <option value="overdue">Überfällig</option>
              </select>
              <p v-if="errors.status" class="mt-1 text-sm text-red-600">{{ errors.status }}</p>
            </div>

            <div>
              <label for="first_name" class="block text-sm font-medium text-gray-700">
                Vorname *
              </label>
              <input
                id="first_name"
                v-model="form.first_name"
                type="text"
                required
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                :class="{ 'border-red-300': errors.first_name }"
              />
              <p v-if="errors.first_name" class="mt-1 text-sm text-red-600">{{ errors.first_name }}</p>
            </div>

            <div>
              <label for="last_name" class="block text-sm font-medium text-gray-700">
                Nachname *
              </label>
              <input
                id="last_name"
                v-model="form.last_name"
                type="text"
                required
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                :class="{ 'border-red-300': errors.last_name }"
              />
              <p v-if="errors.last_name" class="mt-1 text-sm text-red-600">{{ errors.last_name }}</p>
            </div>

            <div>
              <label for="email" class="block text-sm font-medium text-gray-700">
                E-Mail *
              </label>
              <input
                id="email"
                v-model="form.email"
                type="email"
                required
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                :class="{ 'border-red-300': errors.email }"
              />
              <p v-if="errors.email" class="mt-1 text-sm text-red-600">{{ errors.email }}</p>
            </div>

            <div>
              <label for="phone" class="block text-sm font-medium text-gray-700">
                Telefon
              </label>
              <input
                id="phone"
                v-model="form.phone"
                type="tel"
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                :class="{ 'border-red-300': errors.phone }"
              />
              <p v-if="errors.phone" class="mt-1 text-sm text-red-600">{{ errors.phone }}</p>
            </div>

            <div>
              <label for="birth_date" class="block text-sm font-medium text-gray-700">
                Geburtsdatum
              </label>
              <input
                id="birth_date"
                v-model="form.birth_date"
                type="date"
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                :class="{ 'border-red-300': errors.birth_date }"
              />
              <p v-if="errors.birth_date" class="mt-1 text-sm text-red-600">{{ errors.birth_date }}</p>
            </div>

            <div>
              <label for="joined_date" class="block text-sm font-medium text-gray-700">
                Beitrittsdatum *
              </label>
              <input
                id="joined_date"
                v-model="form.joined_date"
                type="date"
                required
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                :class="{ 'border-red-300': errors.joined_date }"
              />
              <p v-if="errors.joined_date" class="mt-1 text-sm text-red-600">{{ errors.joined_date }}</p>
            </div>
          </div>
        </div>

        <!-- Adressdaten -->
        <div class="bg-white rounded-lg shadow p-6">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Adresse</h3>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-2">
              <label for="address" class="block text-sm font-medium text-gray-700">
                Straße und Hausnummer
              </label>
              <input
                id="address"
                v-model="form.address"
                type="text"
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                :class="{ 'border-red-300': errors.address }"
              />
              <p v-if="errors.address" class="mt-1 text-sm text-red-600">{{ errors.address }}</p>
            </div>

            <div>
              <label for="postal_code" class="block text-sm font-medium text-gray-700">
                Postleitzahl
              </label>
              <input
                id="postal_code"
                v-model="form.postal_code"
                type="text"
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                :class="{ 'border-red-300': errors.postal_code }"
              />
              <p v-if="errors.postal_code" class="mt-1 text-sm text-red-600">{{ errors.postal_code }}</p>
            </div>

            <div>
              <label for="city" class="block text-sm font-medium text-gray-700">
                Stadt
              </label>
              <input
                id="city"
                v-model="form.city"
                type="text"
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                :class="{ 'border-red-300': errors.city }"
              />
              <p v-if="errors.city" class="mt-1 text-sm text-red-600">{{ errors.city }}</p>
            </div>

            <div class="md:col-span-2">
              <label for="country" class="block text-sm font-medium text-gray-700">
                Land
              </label>
              <input
                id="country"
                v-model="form.country"
                type="text"
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                :class="{ 'border-red-300': errors.country }"
              />
              <p v-if="errors.country" class="mt-1 text-sm text-red-600">{{ errors.country }}</p>
            </div>
          </div>
        </div>

        <!-- Notfallkontakt -->
        <div class="bg-white rounded-lg shadow p-6">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Notfallkontakt</h3>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label for="emergency_contact_name" class="block text-sm font-medium text-gray-700">
                Name
              </label>
              <input
                id="emergency_contact_name"
                v-model="form.emergency_contact_name"
                type="text"
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                :class="{ 'border-red-300': errors.emergency_contact_name }"
              />
              <p v-if="errors.emergency_contact_name" class="mt-1 text-sm text-red-600">{{ errors.emergency_contact_name }}</p>
            </div>

            <div>
              <label for="emergency_contact_phone" class="block text-sm font-medium text-gray-700">
                Telefon
              </label>
              <input
                id="emergency_contact_phone"
                v-model="form.emergency_contact_phone"
                type="tel"
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                :class="{ 'border-red-300': errors.emergency_contact_phone }"
              />
              <p v-if="errors.emergency_contact_phone" class="mt-1 text-sm text-red-600">{{ errors.emergency_contact_phone }}</p>
            </div>
          </div>
        </div>

        <!-- Notizen -->
        <div class="bg-white rounded-lg shadow p-6">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Notizen</h3>

          <div>
            <label for="notes" class="block text-sm font-medium text-gray-700">
              Zusätzliche Informationen
            </label>
            <textarea
              id="notes"
              v-model="form.notes"
              rows="4"
              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
              :class="{ 'border-red-300': errors.notes }"
              placeholder="Zusätzliche Informationen zum Mitglied..."
            />
            <p v-if="errors.notes" class="mt-1 text-sm text-red-600">{{ errors.notes }}</p>
          </div>
        </div>

        <!-- Aktionen -->
        <div class="flex items-center justify-end space-x-4 bg-white rounded-lg shadow p-6">
          <Link
            :href="route('members.index')"
            class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
          >
            Abbrechen
          </Link>
          <button
            type="submit"
            :disabled="processing"
            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
          >
            <span v-if="processing">Wird gespeichert...</span>
            <span v-else>Änderungen speichern</span>
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { ArrowLeft } from 'lucide-vue-next'

// Props
const props = defineProps({
  member: Object,
  errors: Object
})

// Reactive data
const processing = ref(false)

const form = reactive({
  member_number: props.member.member_number,
  first_name: props.member.first_name,
  last_name: props.member.last_name,
  email: props.member.email,
  phone: props.member.phone || '',
  birth_date: props.member.birth_date || '',
  address: props.member.address || '',
  city: props.member.city || '',
  postal_code: props.member.postal_code || '',
  country: props.member.country || '',
  status: props.member.status,
  joined_date: props.member.joined_date,
  notes: props.member.notes || '',
  emergency_contact_name: props.member.emergency_contact_name || '',
  emergency_contact_phone: props.member.emergency_contact_phone || ''
})

// Methods
const submitForm = () => {
  processing.value = true

  router.put(route('members.update', props.member.id), form, {
    onFinish: () => {
      processing.value = false
    }
  })
}
</script>
