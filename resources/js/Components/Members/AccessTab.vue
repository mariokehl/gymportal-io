<template>
  <div class="space-y-6">
    <!-- Primary Access Methods -->
    <div class="flex flex-col gap-3">
      <h3 class="text-lg font-bold text-gray-900">Primäre Zugangsmethoden</h3>

      <!-- QR Code Section -->
      <div class="rounded-lg border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="flex items-center gap-3 p-4">
          <div class="w-11 h-11 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center flex-none">
            <QrCode class="w-[22px] h-[22px]" />
          </div>
          <div class="flex-1 min-w-0">
            <h4 class="font-semibold text-gray-900">QR-Code Zugang</h4>
            <p class="text-sm text-gray-500 mt-0.5">Digitaler Zugang über Mitglieder-App</p>
          </div>
          <label class="relative inline-flex items-center cursor-pointer flex-none">
            <input
              v-model="accessForm.qr_code_enabled"
              type="checkbox"
              class="sr-only peer"
              @change="updateAccessSettings"
            >
            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
          </label>
        </div>

        <!-- Status footer strip -->
        <div
          class="px-4 py-2.5 border-t text-sm flex items-center gap-2"
          :class="accessForm.qr_code_enabled ? 'bg-green-50 border-green-100 text-green-700' : 'bg-gray-50 border-gray-100 text-gray-500'"
        >
          <CheckCircle v-if="accessForm.qr_code_enabled" class="w-4 h-4 flex-none" />
          <XCircle v-else class="w-4 h-4 flex-none" />
          <span v-if="accessForm.qr_code_enabled">QR-Code ist aktiviert — sichtbar in der Mitglieder-App (PWA)</span>
          <span v-else>QR-Code-Zugang ist deaktiviert</span>
        </div>

        <!-- QR actions -->
        <div v-if="accessForm.qr_code_enabled" class="flex flex-wrap gap-x-4 gap-y-2 px-4 py-3 border-t border-gray-100">
          <button
            @click="invalidateQrCode"
            type="button"
            class="text-sm font-semibold text-red-600 hover:text-red-800 flex items-center gap-1.5"
          >
            <XCircle class="w-4 h-4" />
            QR-Code invalidieren
          </button>
          <button
            @click="sendQrCodeToMember"
            type="button"
            class="text-sm font-semibold text-indigo-600 hover:text-indigo-800 flex items-center gap-1.5"
          >
            <Mail class="w-4 h-4" />
            App-Link per E-Mail senden
          </button>
        </div>
      </div>

      <!-- NFC Tag Section -->
      <div class="rounded-lg border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="flex items-center gap-3 p-4">
          <div class="w-11 h-11 rounded-xl bg-purple-100 text-purple-600 flex items-center justify-center flex-none">
            <Nfc class="w-[22px] h-[22px]" />
          </div>
          <div class="flex-1 min-w-0">
            <h4 class="font-semibold text-gray-900">NFC-Tag Zugang</h4>
            <p class="text-sm text-gray-500 mt-0.5">Kontaktloser Zugang via NFC-Chip oder Karte</p>
          </div>
          <label class="relative inline-flex items-center cursor-pointer flex-none">
            <input
              v-model="accessForm.nfc_enabled"
              type="checkbox"
              class="sr-only peer"
              @change="updateAccessSettings"
            >
            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
          </label>
        </div>

        <!-- Status footer strip (shown only when NFC is off; when on the editor takes over) -->
        <div
          v-if="!accessForm.nfc_enabled"
          class="px-4 py-2.5 border-t border-gray-100 bg-gray-50 text-sm text-gray-500 flex items-center gap-2"
        >
          <XCircle class="w-4 h-4 flex-none" />
          NFC-Zugang ist deaktiviert
        </div>

        <!-- Registered tag summary (full-width status strip, analogous to the QR strip) -->
        <div
          v-else-if="accessForm.nfc_uid && !editingNfc && !isNfcScanning"
          class="px-4 py-2.5 border-t border-gray-100 bg-purple-50 flex flex-wrap items-center justify-between gap-x-4 gap-y-2"
        >
          <div class="flex items-center gap-2 min-w-0">
            <CheckCircle class="w-4 h-4 text-purple-600 flex-none" />
            <div class="min-w-0 text-sm">
              <span class="font-medium text-purple-900">NFC-Tag registriert</span>
              <span class="text-purple-700 font-mono ml-2">{{ formatNfcIdForDisplay(accessForm.nfc_uid) }}</span>
            </div>
          </div>
          <div class="flex items-center gap-4 flex-none">
            <button
              @click="startNfcEdit"
              type="button"
              class="text-sm font-semibold text-indigo-600 hover:text-indigo-800"
            >
              Bearbeiten
            </button>
            <button
              @click="startNfcScanning"
              type="button"
              class="text-sm font-semibold text-purple-600 hover:text-purple-800 flex items-center gap-1.5"
            >
              <Radio class="w-4 h-4" />
              Einlesen
            </button>
            <button
              @click="removeNfcTag"
              type="button"
              class="text-sm font-semibold text-red-600 hover:text-red-800"
            >
              Entfernen
            </button>
          </div>
        </div>

        <!-- NFC editor (no tag yet, or actively editing/scanning) -->
        <div v-else class="px-4 py-4 border-t border-gray-100 space-y-3">
          <!-- Input + actions (stacks on mobile, inline on larger screens) -->
          <div class="flex flex-col sm:flex-row sm:items-center gap-2">
            <input
              v-model="nfcInputValue"
              type="text"
              placeholder="NFC ID eingeben..."
              class="flex-1 min-w-0 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-50"
              :disabled="!editingNfc"
              @input="validateNfcInput"
            />
            <div class="flex gap-2">
              <template v-if="!editingNfc && !isNfcScanning">
                <button
                  @click="startNfcEdit"
                  type="button"
                  class="flex-1 sm:flex-none px-4 py-2 text-indigo-600 border border-indigo-600 rounded-md hover:bg-indigo-50 text-sm font-medium"
                >
                  Bearbeiten
                </button>
                <button
                  @click="startNfcScanning"
                  type="button"
                  class="flex-1 sm:flex-none px-4 py-2 text-purple-600 border border-purple-600 rounded-md hover:bg-purple-50 flex items-center justify-center gap-2 text-sm font-medium"
                >
                  <Radio class="w-4 h-4" />
                  Einlesen
                </button>
              </template>
              <template v-else-if="isNfcScanning">
                <button
                  @click="stopNfcScanning"
                  type="button"
                  class="flex-1 sm:flex-none px-4 py-2 text-red-600 border border-red-600 rounded-md hover:bg-red-50 flex items-center justify-center gap-2 text-sm font-medium"
                >
                  <Loader2 class="w-4 h-4 animate-spin" />
                  Abbrechen
                </button>
              </template>
              <template v-else>
                <button
                  @click="saveNfcUid"
                  type="button"
                  :disabled="!isNfcValid"
                  class="flex-1 sm:flex-none px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-sm font-medium"
                >
                  Speichern
                </button>
                <button
                  @click="cancelNfcEdit"
                  type="button"
                  class="flex-1 sm:flex-none px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50 text-sm font-medium"
                >
                  Abbrechen
                </button>
              </template>
            </div>
          </div>

          <!-- Format hints -->
          <div v-if="editingNfc" class="text-xs text-gray-500 space-y-1">
            <p>Akzeptierte Formate:</p>
            <ul class="ml-4 space-y-0.5">
              <li>• Hex mit Trennzeichen: <code class="bg-gray-100 px-1 rounded">04:A1:B2:C3</code> oder <code class="bg-gray-100 px-1 rounded">04-A1-B2-C3</code></li>
              <li>• Hex mit Prefix: <code class="bg-gray-100 px-1 rounded">0x04A1B2C3</code></li>
              <li>• Reines Hex: <code class="bg-gray-100 px-1 rounded">04A1B2C3</code></li>
              <li>• Dezimal: <code class="bg-gray-100 px-1 rounded">77856451</code></li>
            </ul>
          </div>

          <!-- Validation feedback -->
          <div v-if="editingNfc && nfcInputValue && !isNfcValid" class="flex items-center gap-2 text-sm text-red-600">
            <XCircle class="w-4 h-4" />
            Ungültiges Format
          </div>
          <div v-if="editingNfc && normalizedNfcId && isNfcValid" class="flex items-center gap-2 text-sm text-green-600">
            <CheckCircle class="w-4 h-4" />
            Normalisiert: {{ formatNfcIdForDisplay(normalizedNfcId) }}
          </div>
        </div>
      </div>
    </div>

    <!-- Additional Services -->
    <div class="flex flex-col gap-3">
      <h3 class="text-lg font-bold text-gray-900">Zusätzliche Services</h3>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <!-- Solarium -->
        <div class="rounded-lg border border-gray-200 bg-white shadow-sm p-3.5">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-[10px] bg-yellow-100 text-yellow-600 flex items-center justify-center flex-none">
              <Sun class="w-5 h-5" />
            </div>
            <div class="flex-1 min-w-0">
              <h5 class="font-semibold text-gray-900">Solarium</h5>
              <p class="text-sm text-gray-500 mt-0.5">Zugang zur Sonnenbank</p>
              <p v-if="accessForm.solarium_enabled" class="text-xs text-gray-400 mt-1">
                {{ accessForm.solarium_minutes || 0 }} Minuten verfügbar
              </p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer flex-none">
              <input
                v-model="accessForm.solarium_enabled"
                type="checkbox"
                class="sr-only peer"
                @change="updateAccessSettings"
              >
              <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-yellow-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-yellow-500"></div>
            </label>
          </div>
        </div>

        <!-- Vending Machine -->
        <div class="rounded-lg border border-gray-200 bg-white shadow-sm p-3.5">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-[10px] bg-green-100 text-green-600 flex items-center justify-center flex-none">
              <Package class="w-5 h-5" />
            </div>
            <div class="flex-1 min-w-0">
              <h5 class="font-semibold text-gray-900">Vending Machine</h5>
              <p class="text-sm text-gray-500 mt-0.5">Proteinriegel & Snacks</p>
              <p v-if="accessForm.vending_enabled" class="text-xs text-gray-400 mt-1">
                Guthaben: {{ formatCurrency(accessForm.vending_credit || 0) }}
              </p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer flex-none">
              <input
                v-model="accessForm.vending_enabled"
                type="checkbox"
                class="sr-only peer"
                @change="updateAccessSettings"
              >
              <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
            </label>
          </div>
        </div>

        <!-- Massage Chair -->
        <div class="rounded-lg border border-gray-200 bg-white shadow-sm p-3.5">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-[10px] bg-blue-100 text-blue-600 flex items-center justify-center flex-none">
              <Armchair class="w-5 h-5" />
            </div>
            <div class="flex-1 min-w-0">
              <h5 class="font-semibold text-gray-900">Massagestuhl</h5>
              <p class="text-sm text-gray-500 mt-0.5">Wellness & Entspannung</p>
              <p v-if="accessForm.massage_enabled" class="text-xs text-gray-400 mt-1">
                {{ accessForm.massage_sessions || 0 }} Sitzungen verfügbar
              </p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer flex-none">
              <input
                v-model="accessForm.massage_enabled"
                type="checkbox"
                class="sr-only peer"
                @change="updateAccessSettings"
              >
              <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
            </label>
          </div>
        </div>

        <!-- Coffee Flat -->
        <div class="rounded-lg border border-gray-200 bg-white shadow-sm p-3.5">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-[10px] bg-orange-100 text-orange-600 flex items-center justify-center flex-none">
              <Coffee class="w-5 h-5" />
            </div>
            <div class="flex-1 min-w-0">
              <h5 class="font-semibold text-gray-900">Kaffee-Flatrate</h5>
              <p class="text-sm text-gray-500 mt-0.5">Unbegrenzt Kaffee</p>
              <p v-if="accessForm.coffee_flat_enabled" class="text-xs text-gray-400 mt-1">
                Gültig bis: {{ accessForm.coffee_flat_expiry ? formatDate(accessForm.coffee_flat_expiry) : 'Unbegrenzt' }}
              </p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer flex-none">
              <input
                v-model="accessForm.coffee_flat_enabled"
                type="checkbox"
                class="sr-only peer"
                @change="updateAccessSettings"
              >
              <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-orange-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-orange-600"></div>
            </label>
          </div>
        </div>
      </div>
    </div>

    <!-- Linked Devices (only visible when PWA login is disabled, i.e. branded app only) -->
    <div v-if="member.gym?.pwa_settings?.pwa_login_disabled" class="flex flex-col gap-3">
      <h3 class="text-lg font-bold text-gray-900">Verknüpfte Geräte</h3>

      <div v-if="member.devices && member.devices.length > 0" class="flex flex-col gap-3">
        <div class="bg-amber-50 p-3 rounded-lg">
          <div class="flex items-start gap-2">
            <Info class="w-5 h-5 text-amber-600 mt-0.5 flex-none" />
            <div class="text-sm text-amber-800">
              <p>Maximal {{ maxDevicesPerMember }} {{ maxDevicesPerMember === 1 ? 'Gerät kann' : 'Geräte können' }} mit diesem Mitglied verknüpft sein. Neue Geräte werden beim Login über die Branded App automatisch registriert.</p>
            </div>
          </div>
        </div>

        <div
          v-for="device in member.devices"
          :key="device.id"
          class="flex items-center justify-between gap-3 p-4 rounded-lg border border-gray-200 bg-white shadow-sm"
        >
          <div class="flex items-center gap-3 min-w-0">
            <div class="p-2 bg-gray-100 rounded-lg flex-none">
              <Smartphone class="w-5 h-5 text-gray-600" />
            </div>
            <div class="min-w-0">
              <p class="text-sm font-medium text-gray-900 truncate">
                {{ device.device_name || 'Unbekanntes Gerät' }}
              </p>
              <p class="text-xs text-gray-500 font-mono truncate">
                {{ device.device_token.substring(0, 8) }}...
              </p>
              <p v-if="device.last_used_at" class="text-xs text-gray-400">
                Zuletzt aktiv: {{ formatDateTime(device.last_used_at) }}
              </p>
            </div>
          </div>
          <button
            @click="removeDevice(device)"
            type="button"
            :disabled="removingDeviceId === device.id"
            class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors flex-none"
            title="Gerät entfernen"
          >
            <Loader2 v-if="removingDeviceId === device.id" class="w-5 h-5 animate-spin" />
            <X v-else class="w-5 h-5" />
          </button>
        </div>
      </div>

      <div v-else class="text-center py-8 bg-gray-50 rounded-lg">
        <Smartphone class="w-12 h-12 text-gray-400 mx-auto mb-4" />
        <p class="text-gray-500">Keine Geräte verknüpft</p>
        <p class="text-xs text-gray-400 mt-1">Geräte werden beim Login über die Branded App automatisch registriert</p>
      </div>
    </div>

    <!-- Access Log -->
    <div class="flex flex-col gap-3">
      <h3 class="text-lg font-bold text-gray-900">Zugangshistorie</h3>

      <div v-if="accessLogs && accessLogs.length > 0" class="rounded-lg border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-[560px] w-full border-collapse">
            <thead>
              <tr class="bg-gray-50">
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide whitespace-nowrap">Zeitpunkt</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide whitespace-nowrap">Service</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide whitespace-nowrap">Methode</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide whitespace-nowrap">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="log in accessLogs" :key="log.id" class="hover:bg-gray-50">
                <td class="px-4 py-3 text-sm whitespace-nowrap">{{ formatDateTime(log.accessed_at) }}</td>
                <td class="px-4 py-3 text-sm whitespace-nowrap">{{ log.service_name }}</td>
                <td class="px-4 py-3 text-sm whitespace-nowrap">
                  <span class="inline-flex items-center gap-1">
                    <component :is="getAccessMethodIcon(log.method)" class="w-4 h-4" />
                    {{ log.method }}
                  </span>
                </td>
                <td class="px-4 py-3 text-sm whitespace-nowrap">
                  <span :class="log.success ? 'text-green-600' : 'text-red-600'">
                    {{ log.success ? 'Erfolgreich' : 'Verweigert' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="flex items-center gap-1.5 px-4 py-2.5 text-xs text-gray-400 border-t border-gray-100 sm:hidden">
          <MoveHorizontal class="w-3.5 h-3.5" />
          Zum Scrollen wischen
        </div>
      </div>
      <div v-else class="text-center py-8 bg-gray-50 rounded-lg">
        <Key class="w-12 h-12 text-gray-400 mx-auto mb-4" />
        <p class="text-gray-500">Noch keine Zugänge protokolliert</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import {
  QrCode, Nfc, Sun, Package, Armchair, Coffee, Info, Mail, Loader2, Radio,
  Smartphone, X, XCircle, CheckCircle, Key, MoveHorizontal,
} from 'lucide-vue-next'
import { formatCurrency, formatDate, formatDateTime } from '@/utils/formatters'

const props = defineProps({
  member: {
    type: Object,
    required: true,
  },
  maxDevicesPerMember: {
    type: Number,
    default: 2,
  },
})

// Access Control state
const editingNfc = ref(false)
const nfcInputValue = ref('')
const normalizedNfcId = ref('')
const isNfcValid = ref(false)
const accessLogs = ref([])

// Device management
const removingDeviceId = ref(null)

// NFC Scanning state
const isNfcScanning = ref(false)
const nfcScanChannel = ref(null)
const nfcScanConnected = ref(false)

// Access form for managing permissions
const accessForm = useForm({
  // Primary access methods
  qr_code_enabled: props.member.access_config?.qr_code_enabled ?? props.member?.gym?.pwa_enabled,
  nfc_enabled: props.member.access_config?.nfc_enabled,
  nfc_uid: props.member.access_config?.nfc_uid || '',

  // Additional services
  solarium_enabled: props.member.access_config?.solarium_enabled || false,
  solarium_minutes: props.member.access_config?.solarium_minutes || 0,

  vending_enabled: props.member.access_config?.vending_enabled || false,
  vending_credit: props.member.access_config?.vending_credit || 0,

  massage_enabled: props.member.access_config?.massage_enabled || false,
  massage_sessions: props.member.access_config?.massage_sessions || 0,

  coffee_flat_enabled: props.member.access_config?.coffee_flat_enabled || false,
  coffee_flat_expiry: props.member.access_config?.coffee_flat_expiry || null,
})

// Number of currently active access methods/services — surfaced to the parent
// so it can render the tab badge without owning the access form.
const activeAccessCount = computed(() => {
  let count = 0
  if (accessForm.qr_code_enabled) count++
  if (accessForm.nfc_enabled) count++
  if (accessForm.solarium_enabled) count++
  if (accessForm.vending_enabled) count++
  if (accessForm.massage_enabled) count++
  if (accessForm.coffee_flat_enabled) count++
  return count
})

// Device management
const removeDevice = (device) => {
  if (!confirm('Möchten Sie dieses Gerät wirklich entfernen? Das Mitglied kann sich dann mit einem neuen Gerät anmelden.')) {
    return
  }
  removingDeviceId.value = device.id
  router.delete(route('members.access.remove-device', { member: props.member.id, device: device.id }), {
    preserveScroll: true,
    onFinish: () => {
      removingDeviceId.value = null
    },
  })
}

// NFC ID normalisation
const normalizeCardId = (cardId) => {
  if (!cardId) return null

  // Trim whitespace and uppercase
  cardId = cardId.trim().toUpperCase()

  // 1. UID format with separators (04:A1:B2:C3 or 04-A1-B2-C3)
  if (cardId.includes(':') || cardId.includes('-')) {
    const normalized = cardId.replace(/[:-]/g, '')
    if (/^[0-9A-F]+$/.test(normalized)) {
      return normalized
    }
  }

  // 2. Hex with 0x prefix
  else if (cardId.startsWith('0X')) {
    const hexPart = cardId.substring(2)
    if (/^[0-9A-F]+$/.test(hexPart)) {
      return hexPart
    }
  }

  // 3. Plain hex (A-F, 0-9)
  else if (/^[0-9A-F]+$/.test(cardId)) {
    return cardId
  }

  // 4. Plain decimal
  else if (/^[0-9]+$/.test(cardId)) {
    // Convert decimal to hex for uniform storage
    return parseInt(cardId, 10).toString(16).toUpperCase()
  }

  return null
}

// Format NFC ID for display (with colons for readability)
const formatNfcIdForDisplay = (nfcId) => {
  if (!nfcId) return ''
  return nfcId.match(/.{1,2}/g)?.join(':') || nfcId
}

const invalidateQrCode = () => {
  if (confirm('Möchten Sie wirklich den QR-Code invalidieren? Das Mitglied kann sich dann nicht mehr per QR-Code einloggen, bis ein neuer Code generiert wird.')) {
    router.post(route('members.access.invalidate-qr', props.member.id), {}, {
      preserveScroll: true,
    })
  }
}

const sendQrCodeToMember = () => {
  if (confirm(`Möchten Sie dem Mitglied einen Link zur Mitglieder-App per E-Mail an ${props.member.email} senden?`)) {
    router.post(route('members.access.send-app-link', props.member.id), {}, {
      preserveScroll: true,
      onSuccess: () => {
        alert('E-Mail wurde erfolgreich versendet.')
      },
    })
  }
}

const validateNfcInput = () => {
  const normalized = normalizeCardId(nfcInputValue.value)
  normalizedNfcId.value = normalized || ''
  isNfcValid.value = normalized !== null
}

const startNfcEdit = () => {
  editingNfc.value = true
  nfcInputValue.value = formatNfcIdForDisplay(accessForm.nfc_uid) || ''
  validateNfcInput()
}

const saveNfcUid = () => {
  if (!isNfcValid.value) return

  // Store the normalised version
  accessForm.nfc_uid = normalizedNfcId.value

  accessForm.put(route('members.access.update', props.member.id), {
    preserveScroll: true,
    onSuccess: () => {
      editingNfc.value = false
      nfcInputValue.value = ''
      normalizedNfcId.value = ''
    },
    onError: (errors) => {
      console.error('Fehler beim Speichern der NFC-ID:', errors)
      alert('Die NFC-ID konnte nicht gespeichert werden. Möglicherweise ist diese ID bereits einem anderen Mitglied zugeordnet.')
    },
  })
}

const cancelNfcEdit = () => {
  nfcInputValue.value = formatNfcIdForDisplay(accessForm.nfc_uid) || ''
  normalizedNfcId.value = ''
  isNfcValid.value = false
  editingNfc.value = false
}

const removeNfcTag = () => {
  if (confirm('Möchten Sie den NFC-Tag wirklich entfernen?')) {
    accessForm.nfc_uid = ''
    nfcInputValue.value = ''
    normalizedNfcId.value = ''

    accessForm.put(route('members.access.update', props.member.id), {
      preserveScroll: true,
    })
  }
}

const updateAccessSettings = () => {
  accessForm.put(route('members.access.update', props.member.id), {
    preserveScroll: true,
  })
}

// NFC Scanning functions
const startNfcScanning = () => {
  if (!window.Echo || !props.member.gym?.id) {
    alert('WebSocket-Verbindung nicht verfügbar.')
    return
  }

  isNfcScanning.value = true
  const gymId = props.member.gym.id

  try {
    nfcScanChannel.value = window.Echo.private(`gym.${gymId}.access-logs`)

    nfcScanChannel.value.listen('.scanner.access', (event) => {
      const log = event.log
      // Only process NFC card scans
      if (log.scan_type === 'nfc_card' && log.nfc_card_id) {
        // Found an NFC card scan - use the nfc_card_id
        const nfcCardId = log.nfc_card_id

        // Set the value and save
        accessForm.nfc_uid = nfcCardId
        nfcInputValue.value = formatNfcIdForDisplay(nfcCardId)

        // Save immediately
        accessForm.put(route('members.access.update', props.member.id), {
          preserveScroll: true,
          onSuccess: () => {
            stopNfcScanning()
          },
          onError: (errors) => {
            console.error('Fehler beim Speichern der NFC-ID:', errors)
            alert('Die NFC-ID konnte nicht gespeichert werden. Möglicherweise ist diese ID bereits einem anderen Mitglied zugeordnet.')
            stopNfcScanning()
          },
        })
      }
    })

    nfcScanChannel.value.subscribed(() => {
      nfcScanConnected.value = true
      console.log(`NFC scanning started for gym.${gymId}.access-logs`)
    })

    nfcScanChannel.value.error((error) => {
      console.error('NFC scan WebSocket error:', error)
      nfcScanConnected.value = false
      stopNfcScanning()
    })
  } catch (error) {
    console.error('Failed to start NFC scanning:', error)
    isNfcScanning.value = false
  }
}

const stopNfcScanning = () => {
  if (nfcScanChannel.value && window.Echo && props.member.gym?.id) {
    window.Echo.leave(`gym.${props.member.gym.id}.access-logs`)
    nfcScanChannel.value = null
  }
  isNfcScanning.value = false
  nfcScanConnected.value = false
}

const getAccessMethodIcon = (method) => {
  const icons = {
    'QR-Code': QrCode,
    'NFC': Nfc,
    'Manual': Key,
  }
  return icons[method] || Key
}

// Lifecycle
onMounted(() => {
  // Load access logs if available
  if (props.member?.access_logs) {
    accessLogs.value = props.member.access_logs
  }

  // Initialize NFC value with proper formatting
  if (props.member?.access_config?.nfc_uid) {
    accessForm.nfc_uid = props.member.access_config.nfc_uid
    nfcInputValue.value = formatNfcIdForDisplay(props.member.access_config.nfc_uid)
  }
})

onUnmounted(() => {
  stopNfcScanning()
})

defineExpose({ activeAccessCount })
</script>
