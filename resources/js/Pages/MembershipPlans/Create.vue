<template>
  <AppLayout title="Neuer Vertrag">
    <template #header>
      Neuen Vertrag erstellen
    </template>

    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm">
      <Link :href="route('contracts.index')" class="text-indigo-600 hover:text-indigo-800">
        Verträge
      </Link>
      <span class="text-gray-500 mx-2">/</span>
      <span class="text-gray-900">Neuer Vertrag</span>
    </nav>

    <div class="max-w-2xl">
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <form @submit.prevent="submit">
          <!-- Name -->
          <div class="mb-6">
            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
              Name des Vertrags <span class="text-red-500">*</span>
            </label>
            <input
              id="name"
              v-model="form.name"
              type="text"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600"
              :class="{ 'border-red-500': errors.name }"
              placeholder="z.B. Standard Mitgliedschaft"
              required
            />
            <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name }}</p>
          </div>

          <!-- Description -->
          <div class="mb-6">
            <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
              Beschreibung
            </label>
            <textarea
              id="description"
              v-model="form.description"
              rows="3"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600"
              :class="{ 'border-red-500': errors.description }"
              placeholder="Optionale Beschreibung des Vertrags..."
            ></textarea>
            <p v-if="errors.description" class="mt-1 text-sm text-red-600">{{ errors.description }}</p>
          </div>

          <!-- Price, Setup Fee and Billing Cycle -->
          <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label for="price" class="block text-sm font-medium text-gray-700 mb-2">
                Preis (€) <span class="text-red-500">*</span>
              </label>
              <input
                id="price"
                v-model="form.price"
                type="number"
                step="0.01"
                min="0"
                max="9999.99"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600"
                :class="{ 'border-red-500': errors.price }"
                placeholder="0.00"
                required
              />
              <p v-if="errors.price" class="mt-1 text-sm text-red-600">{{ errors.price }}</p>
            </div>

            <div>
              <label for="setup_fee" class="block text-sm font-medium text-gray-700 mb-2">
                Aktivierungsgebühr (€)
              </label>
              <input
                id="setup_fee"
                v-model="form.setup_fee"
                type="number"
                step="0.01"
                min="0"
                max="999.99"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600"
                :class="{ 'border-red-500': errors.setup_fee }"
                placeholder="0.00"
              />
              <p v-if="errors.setup_fee" class="mt-1 text-sm text-red-600">{{ errors.setup_fee }}</p>
              <p class="mt-1 text-xs text-gray-500">Einmalige Gebühr bei Vertragsabschluss</p>
            </div>

            <div>
              <label for="billing_cycle" class="block text-sm font-medium text-gray-700 mb-2">
                Abrechnungszyklus <span class="text-red-500">*</span>
              </label>
              <select
                id="billing_cycle"
                v-model="form.billing_cycle"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600"
                :class="{ 'border-red-500': errors.billing_cycle }"
                required
              >
                <option value="">Bitte wählen</option>
                <option value="monthly">Monatlich</option>
                <option value="quarterly">Vierteljährlich</option>
                <option value="yearly">Jährlich</option>
              </select>
              <p v-if="errors.billing_cycle" class="mt-1 text-sm text-red-600">{{ errors.billing_cycle }}</p>
            </div>
          </div>

          <!-- Commitment and Cancellation -->
          <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="commitment_months" class="block text-sm font-medium text-gray-700 mb-2">
                Mindestlaufzeit (Monate)
              </label>
              <input
                id="commitment_months"
                v-model="form.commitment_months"
                type="number"
                min="0"
                max="36"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600"
                :class="{ 'border-red-500': errors.commitment_months }"
                placeholder="0 = keine Mindestlaufzeit"
              />
              <p v-if="errors.commitment_months" class="mt-1 text-sm text-red-600">{{ errors.commitment_months }}</p>
              <p class="mt-1 text-xs text-gray-500">Leer lassen für keine Mindestlaufzeit</p>
            </div>

            <div>
              <label for="cancellation_period" class="block text-sm font-medium text-gray-700 mb-2">
                Kündigungsfrist <span class="text-red-500">*</span>
              </label>
              <div class="flex gap-2">
                <input
                  id="cancellation_period"
                  v-model="form.cancellation_period"
                  type="number"
                  min="0"
                  :max="form.cancellation_period_unit === 'months' ? 24 : 365"
                  class="flex-1 border border-gray-300 rounded-lg px-3 py-2 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600"
                  :class="{ 'border-red-500': errors.cancellation_period }"
                  placeholder="30"
                  required
                />
                <select
                  id="cancellation_period_unit"
                  v-model="form.cancellation_period_unit"
                  class="w-28 border border-gray-300 rounded-lg px-3 py-2 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600"
                  :class="{ 'border-red-500': errors.cancellation_period_unit }"
                >
                  <option value="days">Tage</option>
                  <option value="months">Monate</option>
                </select>
              </div>
              <p v-if="errors.cancellation_period" class="mt-1 text-sm text-red-600">{{ errors.cancellation_period }}</p>
              <p v-if="errors.cancellation_period_unit" class="mt-1 text-sm text-red-600">{{ errors.cancellation_period_unit }}</p>
            </div>
          </div>

          <!-- Active Status -->
          <div class="mb-8">
            <div class="flex items-center">
              <input
                id="is_active"
                v-model="form.is_active"
                type="checkbox"
                class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
              />
              <label for="is_active" class="ml-2 text-sm font-medium text-gray-700">
                Vertrag ist aktiv und kann von Mitgliedern gewählt werden
              </label>
            </div>
            <p class="mt-1 text-xs text-gray-500">
              Inaktive Verträge sind für neue Mitgliedschaften nicht verfügbar
            </p>
          </div>

          <!-- Form Actions -->
          <div class="flex space-x-4">
            <button
              type="submit"
              :disabled="processing"
              class="bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-400 text-white px-6 py-2 rounded-lg font-medium transition-colors flex items-center space-x-2"
            >
              <Save class="w-4 h-4" />
              <span>{{ processing ? 'Speichern...' : 'Vertrag erstellen' }}</span>
            </button>

            <Link
              :href="route('contracts.index')"
              class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2 rounded-lg font-medium transition-colors flex items-center space-x-2"
            >
              <X class="w-4 h-4" />
              <span>Abbrechen</span>
            </Link>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { reactive } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Save, X } from 'lucide-vue-next'

// Form data
const form = useForm({
  name: '',
  description: '',
  price: '',
  setup_fee: '',
  billing_cycle: '',
  is_active: true,
  commitment_months: '',
  cancellation_period: 30,
  cancellation_period_unit: 'days'
})

// Computed
const { errors, processing } = form

// Methods
const submit = () => {
  form.post(route('contracts.store'))
}
</script>
