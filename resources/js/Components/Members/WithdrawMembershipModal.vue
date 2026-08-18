<template>
  <teleport to="body">
    <div class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50" @click="emit('close')">
      <div class="relative top-20 mx-auto p-5 border border-gray-50 w-11/12 md:w-1/2 lg:w-1/3 shadow-lg rounded-md bg-white" @click.stop>
        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
          <div class="mb-4">
            <h3 class="text-lg font-medium text-gray-900">
              Mitgliedschaft widerrufen
            </h3>
            <p class="text-sm text-gray-500 mt-1">
              Widerruf gemäß § 356a BGB innerhalb der 14-tägigen Widerrufsfrist.
            </p>
          </div>

          <div v-if="membership" class="space-y-4">
            <!-- Membership Info -->
            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
              <h4 class="font-medium text-purple-900">{{ membership.membership_plan?.name }}</h4>
              <p class="text-sm text-purple-700 mt-1">
                Vertragsbeginn: {{ formatDate(membership.contract_start_date || membership.start_date) }}
              </p>
              <p v-if="membership.withdrawal_deadline" class="text-sm text-purple-700">
                Widerrufsfrist endet: {{ formatDate(membership.withdrawal_deadline) }}
              </p>
            </div>

            <!-- Warning -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
              <div class="flex">
                <AlertCircle class="w-5 h-5 text-yellow-600 mr-2 flex-shrink-0" />
                <div class="text-sm text-yellow-800">
                  <p class="font-medium">Hinweis zum Widerruf:</p>
                  <ul class="mt-1 list-disc list-inside space-y-1">
                    <li>Der Widerruf ist unwiderruflich</li>
                    <li>Eine E-Mail-Bestätigung wird automatisch versendet</li>
                    <li>Bereits gezahlte Beträge werden innerhalb von 14 Tagen erstattet</li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
          <button
            type="button"
            @click="emit('confirm')"
            :disabled="processing"
            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-purple-600 text-base font-medium text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
          >
            {{ processing ? 'Wird widerrufen...' : 'Mitgliedschaft widerrufen' }}
          </button>
          <button
            type="button"
            @click="emit('close')"
            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
          >
            Abbrechen
          </button>
        </div>
      </div>
    </div>
  </teleport>
</template>

<script setup>
import { AlertCircle } from 'lucide-vue-next'
import { formatDate } from '@/utils/formatters'

defineProps({
  membership: { type: Object, default: null },
  processing: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'confirm'])
</script>
