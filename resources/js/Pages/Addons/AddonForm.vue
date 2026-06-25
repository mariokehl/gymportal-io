<template>
  <div class="max-w-2xl">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
      <form @submit.prevent="$emit('submit')">
        <!-- Name -->
        <div class="mb-6">
          <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
            Name des Add-ons <span class="text-red-500">*</span>
          </label>
          <input
            id="name"
            v-model="form.name"
            type="text"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600"
            :class="{ 'border-red-500': form.errors.name }"
            placeholder="z.B. Trainereinweisung"
            required
          />
          <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
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
            :class="{ 'border-red-500': form.errors.description }"
            placeholder="Optionale Beschreibung, die im Widget angezeigt wird..."
          ></textarea>
          <p v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</p>
        </div>

        <!-- Price and Payment Method -->
        <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
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
              :class="{ 'border-red-500': form.errors.price }"
              placeholder="0.00"
              required
            />
            <p v-if="form.errors.price" class="mt-1 text-sm text-red-600">{{ form.errors.price }}</p>
            <p class="mt-1 text-xs text-gray-500">Einmalige Abrechnung zum Vertragsstart</p>
          </div>

          <div>
            <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-2">
              Zahlweise
            </label>
            <select
              id="payment_method"
              v-model="form.payment_method"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600"
              :class="{ 'border-red-500': form.errors.payment_method }"
            >
              <option value="">Standard-Zahlungsart des Mitglieds</option>
              <option v-for="option in paymentMethodOptions" :key="option.key" :value="option.key">
                {{ option.name }}
              </option>
            </select>
            <p v-if="form.errors.payment_method" class="mt-1 text-sm text-red-600">{{ form.errors.payment_method }}</p>
            <p class="mt-1 text-xs text-gray-500">Leer = die vom Mitglied gewählte Zahlungsart wird verwendet</p>
          </div>
        </div>

        <!-- Plan assignment -->
        <div class="mb-8">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Vertragszuordnung
          </label>
          <p class="text-xs text-gray-500 mb-3">
            Lege je Vertrag fest, ob das Add-on <strong>inklusive</strong> (vorausgewählt, nicht abwählbar)
            oder <strong>optional</strong> (zubuchbar) ist. „Nicht zugeordnet“ blendet es für den Vertrag aus.
          </p>

          <div v-if="membershipPlans.length === 0" class="text-sm text-gray-500 bg-gray-50 rounded-lg p-3">
            Es sind noch keine aktiven Verträge vorhanden, denen das Add-on zugeordnet werden könnte.
          </div>

          <div v-else class="space-y-2">
            <div
              v-for="plan in membershipPlans"
              :key="plan.id"
              class="flex items-center justify-between border border-gray-200 rounded-lg px-3 py-2"
            >
              <span class="text-sm font-medium text-gray-700">{{ plan.name }}</span>
              <select
                v-model="form.plan_modes[plan.id]"
                class="w-44 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600"
              >
                <option :value="null">Nicht zugeordnet</option>
                <option value="included">Inklusive</option>
                <option value="optional">Optional</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Active Status -->
        <div class="mb-8">
          <label for="is_active" class="flex items-start space-x-3 cursor-pointer">
            <input
              id="is_active"
              v-model="form.is_active"
              type="checkbox"
              class="mt-0.5 w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
            />
            <span class="text-sm font-medium text-gray-700">
              Add-on ist aktiv und kann im Widget angezeigt werden
            </span>
          </label>
          <p class="mt-1 text-xs text-gray-500">
            Inaktive Add-ons werden im Widget nicht angeboten
          </p>
        </div>

        <!-- Form Actions -->
        <div class="flex space-x-4">
          <button
            type="submit"
            :disabled="form.processing"
            class="bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-400 text-white px-6 py-2 rounded-lg font-medium transition-colors flex items-center space-x-2"
          >
            <Save class="w-4 h-4" />
            <span>{{ form.processing ? 'Speichern...' : submitLabel }}</span>
          </button>

          <Link
            :href="route('contracts.addons.index')"
            class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2 rounded-lg font-medium transition-colors flex items-center space-x-2"
          >
            <X class="w-4 h-4" />
            <span>Abbrechen</span>
          </Link>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import { Save, X } from 'lucide-vue-next'

defineProps({
  form: { type: Object, required: true },
  membershipPlans: { type: Array, default: () => [] },
  paymentMethodOptions: { type: Array, default: () => [] },
  submitLabel: { type: String, default: 'Speichern' }
})

defineEmits(['submit'])
</script>
