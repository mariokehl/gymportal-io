<template>
  <teleport to="body">
    <div
      class="fixed inset-0 bg-gray-500/75 flex items-center justify-center z-50 p-6 overflow-y-auto"
      @click.self="$emit('close')"
    >
      <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl max-h-[90vh] flex flex-col">
        <div class="px-6 pt-6 pb-4 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-gray-900">DIAGONAL Inkasso aktivieren</h2>
          <p class="text-sm text-gray-500 mt-1">Einrichtung der Schnittstelle für diese Organisation</p>
        </div>

        <div class="p-6 overflow-y-auto space-y-5">
          <!-- Step indicator -->
          <div class="flex gap-2">
            <div v-for="(label, index) in STEPS" :key="label" class="flex-1 flex flex-col gap-1.5">
              <div :class="index < step ? 'bg-indigo-600' : 'bg-gray-200'" class="h-1 rounded-full" />
              <div
                :class="[
                  index < step ? 'text-indigo-700' : 'text-gray-400',
                  index === step - 1 ? 'font-semibold' : 'font-normal',
                ]"
                class="text-xs"
              >
                {{ index + 1 }}. {{ label }}
              </div>
            </div>
          </div>

          <!-- Step 1: credentials -->
          <template v-if="step === 1">
            <div class="flex gap-3 bg-indigo-50 border border-indigo-200 rounded-lg px-4 py-3.5 text-indigo-800 text-sm">
              Die Zugangsdaten erhältst du von DIAGONAL nach Vertragsabschluss.
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Mandanten-ID *</label>
                <input v-model="form.tenant_id" type="text" class="input-field" >
                <p class="text-xs text-gray-400 mt-1.5">z. B. 40218-BER</p>
                <p v-if="errors.tenant_id" class="mt-1 text-sm text-red-600">{{ errors.tenant_id }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Gläubigernummer *</label>
                <input v-model="form.client_number" type="text" maxlength="5" class="input-field" >
                <p class="text-xs text-gray-400 mt-1.5">Genau 5 Zeichen</p>
                <p v-if="errors.client_number" class="mt-1 text-sm text-red-600">{{ errors.client_number }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Benutzername *</label>
                <input v-model="form.username" type="text" autocomplete="off" class="input-field" >
                <p class="text-xs text-gray-400 mt-1.5">API-Benutzer bei DIAGONAL</p>
                <p v-if="errors.username" class="mt-1 text-sm text-red-600">{{ errors.username }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Passwort *</label>
                <input v-model="form.password" type="password" autocomplete="new-password" class="input-field" >
                <p class="text-xs text-gray-400 mt-1.5">wird verschlüsselt gespeichert</p>
                <p v-if="errors.password" class="mt-1 text-sm text-red-600">{{ errors.password }}</p>
              </div>
            </div>
          </template>

          <!-- Step 2: review -->
          <template v-else-if="step === 2">
            <div class="border border-gray-200 rounded-lg overflow-hidden">
              <div
                v-for="(row, index) in summaryRows"
                :key="row[0]"
                :class="index % 2 ? 'bg-gray-50' : 'bg-white'"
                class="flex justify-between px-4 py-3 text-sm"
                :style="index ? 'border-top: 1px solid #f3f4f6' : ''"
              >
                <span class="text-gray-500">{{ row[0] }}</span>
                <span class="font-medium text-gray-900">{{ row[1] }}</span>
              </div>
            </div>

            <div class="flex items-center gap-3">
              <button
                type="button"
                :disabled="testing"
                class="px-3.5 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-50 transition-colors disabled:opacity-60"
                @click="testConnection"
              >
                {{ testing ? 'Prüfe Verbindung …' : 'Verbindung testen' }}
              </button>
              <span v-if="testResult" :class="testResult.success ? 'text-green-700' : 'text-red-600'" class="text-sm">
                {{ testResult.message }}
              </span>
            </div>

            <div class="bg-amber-100 border border-amber-200 rounded-lg px-4 py-3.5 text-amber-700">
              <div class="font-semibold text-sm mb-1.5">Vor der Aktivierung bestätigen</div>
              <div class="space-y-1 text-sm">
                <div>· DIAGONAL wird über die Aktivierung benachrichtigt und bestätigt die Freigabe.</div>
                <div>· Informiere DIAGONAL zusätzlich selbst über die geplante Zusammenarbeit.</div>
                <div>· Ab Aktivierung werden neue Inkassoläufe an DIAGONAL übertragen.</div>
              </div>
            </div>

            <div class="flex gap-2.5 items-center cursor-pointer" @click="confirmed = !confirmed">
              <span
                :class="confirmed ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-white border-gray-300'"
                class="w-[18px] h-[18px] rounded border flex items-center justify-center flex-none"
              >
                <Check v-if="confirmed" class="w-3 h-3" />
              </span>
              <span class="text-sm text-gray-700">Ich habe die Hinweise gelesen und bestätige die Aktivierung.</span>
            </div>
          </template>

          <!-- Step 3: done -->
          <template v-else>
            <div class="flex gap-3 bg-green-100 border border-green-200 rounded-lg p-4 text-green-800">
              <CheckCircle2 class="w-5 h-5 flex-none text-green-500" />
              <div class="text-sm">
                <div class="font-semibold">Schnittstelle verbunden</div>
                <div>
                  DIAGONAL Inkasso ist aktiv. Prüfe jetzt Mindestbetrag, Gebühren und den Umgang
                  mit Restforderungen.
                </div>
              </div>
            </div>
          </template>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-2.5">
          <template v-if="step === 1">
            <button type="button" class="btn-secondary" @click="$emit('close')">Abbrechen</button>
            <button type="button" class="btn-primary" :disabled="!step1Valid" @click="step = 2">Weiter</button>
          </template>
          <template v-else-if="step === 2">
            <button type="button" class="btn-secondary" @click="step = 1">Zurück</button>
            <button
              type="button"
              :disabled="!confirmed || activating"
              class="btn-primary"
              @click="activate"
            >
              {{ activating ? 'Aktiviert …' : 'Jetzt aktivieren' }}
            </button>
          </template>
          <template v-else>
            <button type="button" class="btn-primary" @click="$emit('activated', activatedSettings)">
              Konfiguration öffnen
            </button>
          </template>
        </div>
      </div>
    </div>
  </teleport>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { Check, CheckCircle2 } from 'lucide-vue-next'

const emit = defineEmits(['close', 'activated'])

const STEPS = ['Zugangsdaten', 'Prüfen', 'Fertig']

const step = ref(1)
const confirmed = ref(false)
const testing = ref(false)
const activating = ref(false)
const testResult = ref(null)
const activatedSettings = ref(null)
const errors = reactive({})

const form = reactive({
  tenant_id: '',
  client_number: '',
  username: '',
  password: '',
})

const step1Valid = computed(() =>
  form.tenant_id.trim() !== '' &&
  form.client_number.trim().length === 5 &&
  form.username.trim() !== '' &&
  form.password !== ''
)

const summaryRows = computed(() => [
  ['Partner', 'DIAGONAL Inkasso'],
  ['Mandanten-ID', form.tenant_id],
  ['Gläubigernummer', form.client_number],
  ['Benutzername', form.username],
  ['Passwort', '••••••••••'],
])

const testConnection = async () => {
  testing.value = true
  testResult.value = null

  try {
    const { data } = await axios.post(route('settings.inkasso.test-connection'), {
      tenant_id: form.tenant_id,
      username: form.username,
      password: form.password,
    })
    testResult.value = data
  } catch (error) {
    testResult.value = error.response?.data ?? {
      success: false,
      message: 'Die Verbindung konnte nicht geprüft werden.',
    }
  } finally {
    testing.value = false
  }
}

const activate = async () => {
  activating.value = true
  Object.keys(errors).forEach(key => delete errors[key])

  try {
    const { data } = await axios.post(route('settings.inkasso.activate'), form)
    activatedSettings.value = data.settings
    step.value = 3
  } catch (error) {
    if (error.response?.status === 422) {
      Object.assign(
        errors,
        Object.fromEntries(
          Object.entries(error.response.data.errors ?? {}).map(([key, messages]) => [
            key,
            Array.isArray(messages) ? messages[0] : messages,
          ])
        )
      )
      step.value = 1
    }
  } finally {
    activating.value = false
  }
}
</script>

<style scoped>
@reference "tailwindcss";

.input-field {
  @apply w-full px-3 py-2.5 border border-gray-300 rounded-md text-sm focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600;
}

.btn-secondary {
  @apply px-4 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-50 transition-colors;
}

.btn-primary {
  @apply px-4 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed;
}
</style>
