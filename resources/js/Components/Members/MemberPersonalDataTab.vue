<template>
  <div class="space-y-6">
    <!-- Edit-mode status bar (standalone block above the personal data) -->
    <div v-if="editMode">
      <div
        v-if="isDirty"
        class="flex items-center gap-3 px-4 py-3 rounded-lg bg-amber-50 border border-amber-200"
      >
        <AlertCircle class="w-5 h-5 text-amber-600 flex-shrink-0" />
        <div class="flex-1 min-w-0">
          <div class="text-sm font-semibold text-amber-700">Ungespeicherte Änderungen</div>
          <div class="text-xs text-amber-600/90">
            {{ dirtyCount }} {{ dirtyCount === 1 ? 'Feld' : 'Felder' }} geändert · „Speichern“ klicken
          </div>
        </div>
        <button
          type="button"
          @click="$emit('request-cancel')"
          class="flex-none text-sm font-semibold text-amber-700 underline hover:text-amber-800"
        >
          Verwerfen
        </button>
      </div>
      <div
        v-else
        class="flex items-center gap-3 px-4 py-3 rounded-lg bg-indigo-50 border border-indigo-200"
      >
        <Edit class="w-4 h-4 text-indigo-600 flex-shrink-0" />
        <div class="text-sm text-indigo-800">
          Bearbeitungsmodus aktiv — Felder anpassen, dann speichern.
        </div>
      </div>
    </div>

    <!-- Personal data form -->
    <div class="bg-white rounded-lg shadow">
      <div class="p-5 sm:p-6">
        <form @submit.prevent="save">
          <!-- Salutation, first name, last name in one row -->
          <div class="grid grid-cols-1 md:grid-cols-8 gap-x-6 gap-y-5 mb-5">
            <!-- Salutation (25% = 2/8 columns) -->
            <div class="md:col-span-2">
              <div :class="fieldLabelRow">
                <label :class="fieldLabel">Anrede <span class="text-red-500">*</span></label>
                <span v-if="changed('salutation')" :class="changedFlag"><span :class="changedDot" />Geändert</span>
              </div>
              <div v-if="!editMode" :class="readBox">{{ form.salutation || '—' }}</div>
              <select
                v-else
                v-model="form.salutation"
                :class="fieldClass('salutation')"
              >
                <option value="">Anrede auswählen</option>
                <option value="Herr">Herr</option>
                <option value="Frau">Frau</option>
                <option value="Divers">Divers</option>
              </select>
              <div v-if="form.errors.salutation" class="text-red-500 text-sm mt-1">{{ form.errors.salutation }}</div>
            </div>
            <div class="md:col-span-3">
              <div :class="fieldLabelRow">
                <label :class="fieldLabel">Vorname <span class="text-red-500">*</span></label>
                <span v-if="changed('first_name')" :class="changedFlag"><span :class="changedDot" />Geändert</span>
              </div>
              <div v-if="!editMode" :class="readBox">{{ form.first_name || '—' }}</div>
              <input v-else v-model="form.first_name" type="text" :class="fieldClass('first_name')" />
              <div v-if="form.errors.first_name" class="text-red-500 text-sm mt-1">{{ form.errors.first_name }}</div>
            </div>
            <div class="md:col-span-3">
              <div :class="fieldLabelRow">
                <label :class="fieldLabel">Nachname <span class="text-red-500">*</span></label>
                <span v-if="changed('last_name')" :class="changedFlag"><span :class="changedDot" />Geändert</span>
              </div>
              <div v-if="!editMode" :class="readBox">{{ form.last_name || '—' }}</div>
              <input v-else v-model="form.last_name" type="text" :class="fieldClass('last_name')" />
              <div v-if="form.errors.last_name" class="text-red-500 text-sm mt-1">{{ form.errors.last_name }}</div>
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5 mb-5">
            <div>
              <div :class="fieldLabelRow">
                <label :class="fieldLabel">E-Mail <span class="text-red-500">*</span></label>
                <span v-if="changed('email')" :class="changedFlag"><span :class="changedDot" />Geändert</span>
              </div>
              <div class="flex rounded-md shadow-sm">
                <div v-if="!editMode" class="flex-1 min-w-0" :class="[readBox, hasActiveMembership ? 'rounded-r-none' : '']">
                  <span class="truncate">{{ form.email || '—' }}</span>
                </div>
                <input
                  v-else
                  v-model="form.email"
                  type="email"
                  :class="[fieldClass('email'), hasActiveMembership ? 'rounded-r-none' : '']"
                />
                <button
                  v-if="hasActiveMembership"
                  @click="sendWelcomeToMember"
                  class="px-3 bg-gray-50 border border-l-0 border-gray-300 rounded-r-md text-sm text-indigo-600 hover:text-indigo-800 hover:bg-gray-100 flex items-center gap-1 flex-none"
                  type="button"
                >
                  <Mail class="w-4 h-4" />
                  <span class="hidden sm:inline">Willkommen</span>
                </button>
              </div>
              <div v-if="form.errors.email" class="text-red-500 text-sm mt-1">{{ form.errors.email }}</div>
            </div>
            <div>
              <div :class="fieldLabelRow">
                <label :class="fieldLabel">Mobilfunknummer</label>
                <span v-if="changed('phone')" :class="changedFlag"><span :class="changedDot" />Geändert</span>
              </div>
              <div class="flex rounded-md shadow-sm">
                <div v-if="!editMode" class="flex-1 min-w-0" :class="[readBox, (telLink || whatsappLink) ? 'rounded-r-none' : '']">
                  <span class="truncate">{{ form.phone || '—' }}</span>
                </div>
                <input
                  v-else
                  v-model="form.phone"
                  type="tel"
                  :class="[fieldClass('phone'), (telLink || whatsappLink) ? 'rounded-r-none' : '']"
                />
                <a
                  v-if="telLink"
                  :href="telLink"
                  class="px-3 bg-gray-50 border border-l-0 border-gray-300 hover:bg-gray-100 text-gray-500 hover:text-indigo-600 flex items-center justify-center flex-none"
                  :class="{ 'rounded-r-md': !whatsappLink }"
                  title="Anrufen"
                >
                  <Phone class="w-5 h-5" />
                </a>
                <a
                  v-if="whatsappLink"
                  :href="whatsappLink"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="px-3 bg-gray-50 border border-l-0 border-gray-300 rounded-r-md hover:bg-gray-100 flex items-center justify-center flex-none"
                  title="Chat on WhatsApp"
                >
                  <svg class="w-5 h-5" viewBox="0 0 720 720" xmlns="http://www.w3.org/2000/svg">
                    <path fill="#25d366" d="M360,0C161.18,0,0,161.18,0,360c0,65.41,17.45,126.75,47.94,179.61L0,720l187.02-44.21c51.34,28.18,110.28,44.21,172.98,44.21,198.82,0,360-161.18,360-360S558.82,0,360,0ZM360,655.52c-60.17,0-116.13-17.98-162.82-48.87l-110.49,28.14,30.99-105.61c-33.53-47.93-53.2-106.26-53.2-169.19,0-163.21,132.31-295.52,295.52-295.52s295.52,132.31,295.52,295.52-132.31,295.52-295.52,295.52Z" />
                    <path fill="#25d366" d="M444.35,407.52l87.1,41.06c4,1.88,6.56,5.94,6.2,10.34-.94,11.46-5.54,34.43-26.13,55.02-58.12,58.12-162.49-7.64-166.74-10.18-25.67-13.79-50.06-32.24-73.19-55.36-23.12-23.12-41.58-47.52-55.37-73.19-2.55-4.24-68.31-108.61-10.18-166.74,20.59-20.59,43.56-25.19,55.02-26.13,4.41-.36,8.46,2.2,10.34,6.2l41.07,87.1c1.94,4.12,1.09,9.02-2.13,12.24l-30.61,30.61c-6.62,6.62-8.56,16.93-4,25.11,11.17,20.03,26.19,39.32,43.59,57.07,17.75,17.4,37.04,32.43,57.07,43.59,8.18,4.56,18.48,2.62,25.11-4l30.61-30.61c3.22-3.22,8.12-4.08,12.24-2.13Z" />
                  </svg>
                </a>
              </div>
            </div>
            <div>
              <div :class="fieldLabelRow">
                <label :class="fieldLabel">Geburtsdatum</label>
                <span v-if="changed('birth_date')" :class="changedFlag"><span :class="changedDot" />Geändert</span>
              </div>
              <div v-if="!editMode" :class="readBox">{{ form.birth_date ? formatDate(form.birth_date) : '—' }}</div>
              <input v-else v-model="form.birth_date" type="date" :class="fieldClass('birth_date')" />
            </div>
            <div>
              <div :class="fieldLabelRow">
                <label :class="fieldLabel">Beitrittsdatum <span class="text-red-500">*</span></label>
                <span v-if="changed('joined_date')" :class="changedFlag"><span :class="changedDot" />Geändert</span>
              </div>
              <div v-if="!editMode" :class="readBox">{{ form.joined_date ? formatDate(form.joined_date) : '—' }}</div>
              <input v-else v-model="form.joined_date" type="date" :class="fieldClass('joined_date')" />
            </div>
            <div>
              <div :class="fieldLabelRow">
                <label :class="fieldLabel">Straße und Hausnummer</label>
                <span v-if="changed('address')" :class="changedFlag"><span :class="changedDot" />Geändert</span>
              </div>
              <div v-if="!editMode" :class="readBox">{{ form.address || '—' }}</div>
              <input v-else v-model="form.address" type="text" :class="fieldClass('address')" />
            </div>
            <div>
              <div :class="fieldLabelRow">
                <label :class="fieldLabel">Adresszusatz</label>
                <span v-if="changed('address_addition')" :class="changedFlag"><span :class="changedDot" />Geändert</span>
              </div>
              <div v-if="!editMode" :class="readBox">{{ form.address_addition || '—' }}</div>
              <input v-else v-model="form.address_addition" type="text" placeholder="optional" :class="fieldClass('address_addition')" />
            </div>
            <div>
              <div :class="fieldLabelRow">
                <label :class="fieldLabel">PLZ</label>
                <span v-if="changed('postal_code')" :class="changedFlag"><span :class="changedDot" />Geändert</span>
              </div>
              <div v-if="!editMode" :class="readBox">{{ form.postal_code || '—' }}</div>
              <input v-else v-model="form.postal_code" type="text" :class="fieldClass('postal_code')" />
            </div>
            <div>
              <div :class="fieldLabelRow">
                <label :class="fieldLabel">Stadt</label>
                <span v-if="changed('city')" :class="changedFlag"><span :class="changedDot" />Geändert</span>
              </div>
              <div v-if="!editMode" :class="readBox">{{ form.city || '—' }}</div>
              <input v-else v-model="form.city" type="text" :class="fieldClass('city')" />
            </div>
            <div>
              <div :class="fieldLabelRow">
                <label :class="fieldLabel">Land</label>
                <span v-if="changed('country')" :class="changedFlag"><span :class="changedDot" />Geändert</span>
              </div>
              <div v-if="!editMode" :class="readBox">{{ countryLabel(form.country) }}</div>
              <select v-else id="country" v-model="form.country" :class="fieldClass('country')">
                <option value="DE">Deutschland</option>
                <option value="AT">Österreich</option>
                <option value="CH">Schweiz</option>
              </select>
            </div>
            <div class="md:col-start-1">
              <div :class="fieldLabelRow">
                <label :class="fieldLabel">Notfallkontakt Name</label>
                <span v-if="changed('emergency_contact_name')" :class="changedFlag"><span :class="changedDot" />Geändert</span>
              </div>
              <div v-if="!editMode" :class="readBox">{{ form.emergency_contact_name || '—' }}</div>
              <input v-else v-model="form.emergency_contact_name" type="text" :class="fieldClass('emergency_contact_name')" />
            </div>
            <div>
              <div :class="fieldLabelRow">
                <label :class="fieldLabel">Notfallkontakt Telefon</label>
                <span v-if="changed('emergency_contact_phone')" :class="changedFlag"><span :class="changedDot" />Geändert</span>
              </div>
              <div v-if="!editMode" :class="readBox">{{ form.emergency_contact_phone || '—' }}</div>
              <input v-else v-model="form.emergency_contact_phone" type="tel" :class="fieldClass('emergency_contact_phone')" />
            </div>

            <div class="md:col-span-2">
              <div :class="fieldLabelRow">
                <label :class="fieldLabel">Notizen</label>
                <span v-if="changed('notes')" :class="changedFlag"><span :class="changedDot" />Geändert</span>
              </div>
              <div v-if="!editMode" :class="[readBox, 'whitespace-pre-wrap min-h-[64px] items-start']">{{ form.notes || '—' }}</div>
              <textarea v-else v-model="form.notes" rows="3" :class="fieldClass('notes')"></textarea>
            </div>

            <!-- Legal guardian -->
            <div class="md:col-span-2 mt-4 pt-4 border-t border-gray-100">
              <h4 class="text-sm font-semibold text-gray-900 mb-1">Gesetzlicher Vertreter</h4>
              <p class="text-xs text-gray-500">Bei Minderjährigen muss ein gesetzlicher Vertreter dem Vertrag zustimmen.</p>
            </div>

            <!-- Link to an existing member -->
            <div class="md:col-span-2" v-if="editMode">
              <label :class="[fieldLabel, 'block mb-1.5']">Mit Mitglied verknüpfen (optional)</label>
              <div class="flex gap-3">
                <div class="flex-1 relative">
                  <input
                    v-model="legalGuardianSearch"
                    type="text"
                    placeholder="Nach Mitglied suchen (Name oder Mitgliedsnummer)..."
                    class="w-full px-3 py-2.5 text-[15px] border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    @input="searchLegalGuardian"
                    @focus="showLegalGuardianResults = true"
                  />
                  <!-- Search results -->
                  <div
                    v-if="showLegalGuardianResults && legalGuardianSearchResults.length > 0"
                    class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto"
                  >
                    <div
                      v-for="result in legalGuardianSearchResults"
                      :key="result.id"
                      @click="selectLegalGuardian(result)"
                      class="px-4 py-2 hover:bg-indigo-50 cursor-pointer text-sm"
                    >
                      <span class="font-medium">{{ result.first_name }} {{ result.last_name }}</span>
                      <span class="text-gray-500 ml-2">#{{ result.member_number }}</span>
                    </div>
                  </div>
                </div>
                <button
                  v-if="form.legal_guardian_member_id"
                  type="button"
                  @click="clearLegalGuardianMember"
                  class="px-3 py-2 text-sm text-red-600 hover:text-red-800 border border-red-300 rounded-md hover:bg-red-50"
                >
                  Verknüpfung entfernen
                </button>
              </div>
              <!-- Linked member display -->
              <div v-if="form.legal_guardian_member_id && selectedLegalGuardian" class="mt-2 p-2 bg-indigo-50 rounded-md text-sm">
                <span class="font-medium">Verknüpft mit:</span>
                {{ selectedLegalGuardian.first_name }} {{ selectedLegalGuardian.last_name }}
                <span class="text-gray-500">#{{ selectedLegalGuardian.member_number }}</span>
              </div>
            </div>

            <!-- Shown only when not editing and a member is linked -->
            <div v-if="!editMode && member.legal_guardian_member_id && member.legal_guardian" class="md:col-span-2">
              <label :class="[fieldLabel, 'block mb-1.5']">Verknüpftes Mitglied</label>
              <div class="p-3 bg-indigo-50 rounded-md">
                <Link
                  :href="route('members.show', member.legal_guardian.id)"
                  class="text-indigo-600 hover:text-indigo-800 font-medium"
                >
                  {{ member.legal_guardian.first_name }} {{ member.legal_guardian.last_name }}
                  <span class="text-gray-500">#{{ member.legal_guardian.member_number }}</span>
                </Link>
              </div>
            </div>

            <!-- Manual entry (only when no member is linked) -->
            <div v-if="!form.legal_guardian_member_id">
              <div :class="fieldLabelRow">
                <label :class="fieldLabel">Vorname</label>
                <span v-if="changed('legal_guardian_first_name')" :class="changedFlag"><span :class="changedDot" />Geändert</span>
              </div>
              <div v-if="!editMode" :class="readBox">{{ form.legal_guardian_first_name || '—' }}</div>
              <input v-else v-model="form.legal_guardian_first_name" type="text" :class="fieldClass('legal_guardian_first_name')" />
            </div>
            <div v-if="!form.legal_guardian_member_id">
              <div :class="fieldLabelRow">
                <label :class="fieldLabel">Nachname</label>
                <span v-if="changed('legal_guardian_last_name')" :class="changedFlag"><span :class="changedDot" />Geändert</span>
              </div>
              <div v-if="!editMode" :class="readBox">{{ form.legal_guardian_last_name || '—' }}</div>
              <input v-else v-model="form.legal_guardian_last_name" type="text" :class="fieldClass('legal_guardian_last_name')" />
            </div>
          </div>
          <!-- Submit target for Enter key while editing; visible actions live in the header -->
          <button v-if="editMode" type="submit" class="hidden" aria-hidden="true"></button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useForm, router, Link } from '@inertiajs/vue3'
import { AlertCircle, Edit, Mail, Phone } from 'lucide-vue-next'
import { formatDate, formatDateForInput } from '@/utils/formatters'

const props = defineProps({
  member: {
    type: Object,
    required: true,
  },
  editMode: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['request-cancel', 'saved'])

// Form holding all personal-data fields
const form = useForm({
  member_number: props.member.member_number,
  salutation: props.member.salutation,
  first_name: props.member.first_name,
  last_name: props.member.last_name,
  email: props.member.email,
  phone: props.member.phone,
  birth_date: formatDateForInput(props.member.birth_date),
  address: props.member.address,
  address_addition: props.member.address_addition,
  city: props.member.city,
  postal_code: props.member.postal_code,
  country: props.member.country,
  emergency_contact_name: props.member.emergency_contact_name,
  emergency_contact_phone: props.member.emergency_contact_phone,
  legal_guardian_member_id: props.member.legal_guardian_member_id,
  legal_guardian_first_name: props.member.legal_guardian_first_name,
  legal_guardian_last_name: props.member.legal_guardian_last_name,
  notes: props.member.notes,
  joined_date: formatDateForInput(props.member.joined_date),
})

const hasActiveMembership = computed(() => props.member.memberships?.some(m => m.status === 'active'))

/* ------------------------------------------------------------------ */
/* Dirty tracking                                                     */
/* ------------------------------------------------------------------ */

// Personal-data field keys tracked for the "changed" flag and dirty state
const PERSONAL_FIELD_KEYS = [
  'salutation', 'first_name', 'last_name', 'email', 'phone', 'birth_date',
  'joined_date', 'address', 'address_addition', 'postal_code', 'city', 'country',
  'emergency_contact_name', 'emergency_contact_phone', 'notes',
  'legal_guardian_first_name', 'legal_guardian_last_name', 'legal_guardian_member_id',
  'member_number',
]

// Snapshot of the form taken when entering edit mode, used to detect changed fields
const editSnapshot = ref({})

const changed = (key) => props.editMode && form[key] !== editSnapshot.value[key]
const dirtyCount = computed(() => PERSONAL_FIELD_KEYS.reduce((n, k) => n + (form[k] !== editSnapshot.value[k] ? 1 : 0), 0))
const isDirty = computed(() => dirtyCount.value > 0)

// Take a snapshot of the tracked fields to compare against for the dirty state
const captureEditSnapshot = () => {
  editSnapshot.value = PERSONAL_FIELD_KEYS.reduce((o, k) => { o[k] = form[k]; return o }, {})
}

/* ------------------------------------------------------------------ */
/* Field styling (mirrors the responsive member-view design)          */
/* ------------------------------------------------------------------ */

const fieldLabelRow = 'flex items-center gap-1.5 mb-1.5'
const fieldLabel = 'text-[13px] font-medium text-gray-700'
const changedFlag = 'ml-auto inline-flex items-center gap-1 text-[11px] font-semibold text-amber-600'
const changedDot = 'w-1.5 h-1.5 rounded-full bg-amber-600'
const readBox = 'w-full min-h-[42px] px-3 py-2.5 border border-gray-300 rounded-md bg-gray-50 text-[15px] text-gray-900 flex items-center'

// Input/select/textarea class in edit mode; turns amber when the value changed
const fieldClass = (key) => {
  const dirty = changed(key)
  return [
    'w-full px-3 py-2.5 text-[15px] rounded-md border focus:outline-none focus:ring-2',
    dirty
      ? 'border-amber-600 bg-amber-50 focus:ring-amber-500/40'
      : 'border-gray-300 bg-white focus:ring-indigo-500/40 focus:border-indigo-500',
  ]
}

const COUNTRY_LABELS = { DE: 'Deutschland', AT: 'Österreich', CH: 'Schweiz' }
const countryLabel = (code) => COUNTRY_LABELS[code] || code || '—'

/* ------------------------------------------------------------------ */
/* Phone links (call + WhatsApp)                                      */
/* ------------------------------------------------------------------ */

// ISO-3166-Alpha-2 -> international dialling code
const COUNTRY_DIAL_CODES = {
  DE: '49', AT: '43', CH: '41', LI: '423',
  NL: '31', BE: '32', LU: '352', FR: '33',
  IT: '39', ES: '34', PL: '48', CZ: '420',
  DK: '45', GB: '44',
}

// Normalise the phone number to international digits (without leading +)
const normalizedPhoneDigits = computed(() => {
  const raw = form.phone
  if (!raw) return null
  // Keep digits and a leading + only
  let digits = raw.replace(/[^\d+]/g, '')
  if (!digits) return null
  // Derive the dialling code from the gym setting (fallback: Germany)
  const country = props.member.gym?.country?.toUpperCase()
  const dialCode = COUNTRY_DIAL_CODES[country] ?? '49'
  if (digits.startsWith('+')) {
    digits = digits.slice(1)
  } else if (digits.startsWith('00')) {
    digits = digits.slice(2)
  } else if (digits.startsWith('0')) {
    // National number without country code -> prepend the gym's dialling code
    digits = dialCode + digits.slice(1)
  }
  return digits || null
})

const whatsappLink = computed(() => {
  const digits = normalizedPhoneDigits.value
  return digits ? `https://wa.me/${digits}` : null
})

const telLink = computed(() => {
  const digits = normalizedPhoneDigits.value
  return digits ? `tel:+${digits}` : null
})

/* ------------------------------------------------------------------ */
/* Legal guardian search                                              */
/* ------------------------------------------------------------------ */

const legalGuardianSearch = ref('')
const legalGuardianSearchResults = ref([])
const showLegalGuardianResults = ref(false)
const selectedLegalGuardian = ref(props.member.legal_guardian || null)
let legalGuardianSearchTimeout = null

const searchLegalGuardian = () => {
  if (legalGuardianSearchTimeout) {
    clearTimeout(legalGuardianSearchTimeout)
  }

  if (legalGuardianSearch.value.length < 2) {
    legalGuardianSearchResults.value = []
    return
  }

  legalGuardianSearchTimeout = setTimeout(async () => {
    try {
      const response = await fetch(route('members.search') + '?' + new URLSearchParams({
        search: legalGuardianSearch.value,
        exclude_id: props.member.id,
      }))
      const data = await response.json()
      legalGuardianSearchResults.value = data.members || []
    } catch (error) {
      console.error('Error searching members:', error)
      legalGuardianSearchResults.value = []
    }
  }, 300)
}

const selectLegalGuardian = (member) => {
  form.legal_guardian_member_id = member.id
  form.legal_guardian_first_name = null
  form.legal_guardian_last_name = null
  selectedLegalGuardian.value = member
  legalGuardianSearch.value = ''
  legalGuardianSearchResults.value = []
  showLegalGuardianResults.value = false
}

const clearLegalGuardianMember = () => {
  form.legal_guardian_member_id = null
  selectedLegalGuardian.value = null
  legalGuardianSearch.value = ''
}

// Close the search dropdown when clicking outside of it
const handleClickOutside = (event) => {
  if (showLegalGuardianResults.value) {
    const searchContainer = event.target.closest('.relative')
    if (!searchContainer || !searchContainer.querySelector('[placeholder*="Nach Mitglied suchen"]')) {
      showLegalGuardianResults.value = false
    }
  }
}

/* ------------------------------------------------------------------ */
/* Actions                                                            */
/* ------------------------------------------------------------------ */

const sendWelcomeToMember = () => {
  if (confirm(`Möchten Sie dem Mitglied eine Willkommensnachricht per E-Mail an ${props.member.email} senden?`)) {
    router.post(route('members.send-welcome', props.member.id), {}, {
      preserveScroll: true,
      onSuccess: () => {
        alert('E-Mail wurde erfolgreich versendet.')
      },
      onError: (errors) => {
        console.error('Send welcome email error:', errors)
        alert('Fehler beim Versenden der E-Mail. Bitte versuchen Sie es erneut.')
      },
    })
  }
}

const save = () => {
  if (!isDirty.value) return
  form.put(route('members.update', props.member.id), {
    onSuccess: () => {
      emit('saved')
    },
  })
}

// Enter edit mode: snapshot the current form so the dirty state starts clean
const enterEdit = () => {
  captureEditSnapshot()
}

// Discard local edits and reset the form to the persisted values
const resetForm = () => {
  form.reset()
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})

defineExpose({
  form,
  isDirty,
  dirtyCount,
  save,
  enterEdit,
  resetForm,
})
</script>
