<template>
  <AppLayout :title="`${member.first_name} ${member.last_name}`">
    <template #header>
      <div class="flex items-center">
        <Link
          :href="route('members.index')"
          class="text-gray-500 hover:text-gray-700 mr-4"
        >
          <ArrowLeft class="w-5 h-5" />
        </Link>
        Mitglied {{ !editMode ? 'anzeigen' : 'bearbeiten' }}: {{ member.first_name }} {{ member.last_name }}
      </div>
    </template>

    <div class="space-y-6">
      <!-- Header Section -->
      <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-4">
            <div class="h-16 w-16 rounded-full bg-blue-500 flex items-center justify-center text-white text-xl font-bold">
              {{ getInitials(member.first_name, member.last_name) }}
            </div>
            <div>
              <h2 class="text-2xl font-bold text-gray-900">
                {{ member.first_name }} {{ member.last_name }}
              </h2>
              <p class="text-gray-600">Mitgliedsnummer: #{{ member.member_number }}</p>
              <span :class="getStatusBadgeClass(member.status)" class="inline-flex px-3 py-1 text-sm font-semibold rounded-full mt-2">
                {{ getStatusText(member.status) }}
              </span>
            </div>
          </div>
          <div class="flex items-center space-x-3">
            <Link
              :href="route('members.create')"
              class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2"
            >
              <Plus class="w-4 h-4" />
              Neues Mitglied
            </Link>
            <button
              @click="editMode = !editMode"
              class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 flex items-center gap-2"
            >
              <Edit class="w-4 h-4" />
              {{ editMode ? 'Bearbeitung beenden' : 'Bearbeiten' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Tabs Navigation -->
      <div class="bg-white rounded-lg shadow">
        <div class="border-b border-gray-200">
          <nav class="flex space-x-8 px-6">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              :class="[
                activeTab === tab.id
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2'
              ]"
            >
              <component :is="tab.icon" class="w-4 h-4" />
              {{ tab.name }}
            </button>
          </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
          <!-- Personal Data Tab -->
          <div v-show="activeTab === 'personal'" class="space-y-6">
            <form @submit.prevent="updateMember">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Vorname <span class="text-red-500">*</span></label>
                  <input
                    v-model="form.first_name"
                    :disabled="!editMode"
                    type="text"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-50"
                  />
                  <div v-if="form.errors.first_name" class="text-red-500 text-sm mt-1">{{ form.errors.first_name }}</div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Nachname <span class="text-red-500">*</span></label>
                  <input
                    v-model="form.last_name"
                    :disabled="!editMode"
                    type="text"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-50"
                  />
                  <div v-if="form.errors.last_name" class="text-red-500 text-sm mt-1">{{ form.errors.last_name }}</div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">E-Mail <span class="text-red-500">*</span></label>
                  <input
                    v-model="form.email"
                    :disabled="!editMode"
                    type="email"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-50"
                  />
                  <div v-if="form.errors.email" class="text-red-500 text-sm mt-1">{{ form.errors.email }}</div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Telefon</label>
                  <input
                    v-model="form.phone"
                    :disabled="!editMode"
                    type="tel"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-50"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Geburtsdatum</label>
                  <input
                    v-model="form.birth_date"
                    :disabled="!editMode"
                    type="date"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-50"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Beitrittsdatum <span class="text-red-500">*</span></label>
                  <input
                    v-model="form.joined_date"
                    :disabled="!editMode"
                    type="date"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-50"
                  />
                </div>
                <div class="md:col-span-2">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Straße und Hausnummer</label>
                  <input
                    v-model="form.address"
                    :disabled="!editMode"
                    type="text"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-50"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">PLZ</label>
                  <input
                    v-model="form.postal_code"
                    :disabled="!editMode"
                    type="text"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-50"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Stadt</label>
                  <input
                    v-model="form.city"
                    :disabled="!editMode"
                    type="text"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-50"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Land</label>
                  <select
                    id="country"
                    v-model="form.country"
                    :disabled="!editMode"
                    class="w-full p-2 border border-gray-300 rounded-md bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  >
                    <option value="DE">Deutschland</option>
                    <option value="AT">Österreich</option>
                    <option value="CH">Schweiz</option>
                  </select>
                </div>
                <div class="md:col-start-1">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Notfallkontakt Name</label>
                  <input
                    v-model="form.emergency_contact_name"
                    :disabled="!editMode"
                    type="text"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-50"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Notfallkontakt Telefon</label>
                  <input
                    v-model="form.emergency_contact_phone"
                    :disabled="!editMode"
                    type="tel"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-50"
                  />
                </div>
                <div class="md:col-span-2">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Notizen</label>
                  <textarea
                    v-model="form.notes"
                    :disabled="!editMode"
                    rows="3"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-50"
                  ></textarea>
                </div>
              </div>
              <div v-if="editMode" class="mt-6 flex justify-end space-x-3">
                <button
                  type="button"
                  @click="cancelEdit"
                  class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                >
                  Abbrechen
                </button>
                <button
                  type="submit"
                  :disabled="form.processing"
                  class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                >
                  {{ form.processing ? 'Speichern...' : 'Speichern' }}
                </button>
              </div>
            </form>
          </div>

          <!-- Membership Tab -->
          <div v-show="activeTab === 'membership'" class="space-y-6">
            <div v-if="member.memberships && member.memberships.length > 0">
              <div v-for="membership in member.memberships" :key="membership.id" class="border border-gray-200 rounded-lg p-4 mb-4">
                <div class="flex justify-between items-start">
                  <div>
                    <h4 class="text-lg font-semibold">
                        {{ membership.membership_plan.name }}
                        <span :class="getStatusBadgeClass(membership.status)" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ml-1">
                          {{ getStatusText(membership.status) }}
                        </span>
                    </h4>
                    <p class="text-gray-600">{{ membership.membership_plan.description }}</p>
                    <div class="mt-2 space-y-1">
                      <p class="text-sm"><span class="font-medium">Laufzeit:</span> {{ formatDate(membership.start_date) }} - {{ formatDate(membership.end_date) }}</p>
                    </div>
                  </div>
                  <div class="text-right">
                    <p class="text-2xl font-bold text-blue-600">{{ formatCurrency(membership.membership_plan.price) }}</p>
                    <p class="text-sm text-gray-500">pro {{ getBillingCycleText(membership.membership_plan.billing_cycle) }}</p>
                  </div>
                </div>
                <div v-if="membership.pause_start_date" class="mt-3 p-3 bg-yellow-50 rounded-md">
                  <p class="text-sm text-yellow-800">
                    <Clock class="w-4 h-4 inline mr-1" />
                    Pausiert vom {{ formatDate(membership.pause_start_date) }} bis {{ formatDate(membership.pause_end_date) }}
                  </p>
                </div>
              </div>
            </div>
            <div v-else class="text-center py-8">
              <UserX class="w-12 h-12 text-gray-400 mx-auto mb-4" />
              <p class="text-gray-500">Keine Mitgliedschaften vorhanden</p>
            </div>
          </div>

          <!-- Check-ins Tab -->
          <div v-show="activeTab === 'checkins'" class="space-y-4">
            <div v-if="member.check_ins && member.check_ins.length > 0">
              <div class="overflow-x-auto">
                <table class="w-full">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Datum</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Check-In</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Check-Out</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dauer</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Methode</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-200">
                    <tr v-for="checkin in member.check_ins" :key="checkin.id" class="hover:bg-gray-50">
                      <td class="px-4 py-3 text-sm">{{ formatDate(checkin.check_in_time) }}</td>
                      <td class="px-4 py-3 text-sm">{{ formatTime(checkin.check_in_time) }}</td>
                      <td class="px-4 py-3 text-sm">
                        {{ checkin.check_out_time ? formatTime(checkin.check_out_time) : '-' }}
                      </td>
                      <td class="px-4 py-3 text-sm">
                        {{ checkin.check_out_time ? calculateDuration(checkin.check_in_time, checkin.check_out_time) : '-' }}
                      </td>
                      <td class="px-4 py-3 text-sm">{{ checkin.check_in_method || 'Unbekannt' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div v-else class="text-center py-8">
              <Clock class="w-12 h-12 text-gray-400 mx-auto mb-4" />
              <p class="text-gray-500">Keine Check-Ins vorhanden</p>
            </div>
          </div>

          <!-- Payments Tab -->
          <div v-show="activeTab === 'payments'" class="space-y-4">
            <div v-if="member.payments && member.payments.length > 0">
              <div class="overflow-x-auto">
                <table class="w-full">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Datum</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Betrag</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Beschreibung</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Zahlungsmethode</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-200">
                    <tr v-for="payment in member.payments" :key="payment.id" class="hover:bg-gray-50">
                      <td class="px-4 py-3 text-sm">{{ formatDate(payment.paid_date || payment.due_date) }}</td>
                      <td class="px-4 py-3 text-sm font-medium">{{ formatCurrency(payment.amount) }}</td>
                      <td class="px-4 py-3 text-sm">{{ payment.description }}</td>
                      <td class="px-4 py-3 text-sm">
                        <span :class="getPaymentStatusClass(payment.status)" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full">
                          {{ getPaymentStatusText(payment.status) }}
                        </span>
                      </td>
                      <td class="px-4 py-3 text-sm">{{ payment.payment_method || '-' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div v-else class="text-center py-8">
              <CreditCard class="w-12 h-12 text-gray-400 mx-auto mb-4" />
              <p class="text-gray-500">Keine Zahlungen vorhanden</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
  User, FileText, Clock, CreditCard, Plus, Edit,
  UserX, ArrowLeft
} from 'lucide-vue-next'

const props = defineProps({
  member: Object,
})

const editMode = ref(false)
const activeTab = ref('personal')

const tabs = [
  { id: 'personal', name: 'Persönliche Daten', icon: User },
  { id: 'membership', name: 'Mitgliedschaften', icon: FileText },
  { id: 'checkins', name: 'Check-Ins', icon: Clock },
  { id: 'payments', name: 'Zahlungen', icon: CreditCard },
]

const formatDateForInput = (dateString) => {
  return dateString ? dateString.split('T')[0] : '';
};

const form = useForm({
  member_number: props.member.member_number,
  first_name: props.member.first_name,
  last_name: props.member.last_name,
  email: props.member.email,
  phone: props.member.phone,
  birth_date: formatDateForInput(props.member.birth_date),
  address: props.member.address,
  city: props.member.city,
  postal_code: props.member.postal_code,
  country: props.member.country,
  status: props.member.status,
  emergency_contact_name: props.member.emergency_contact_name,
  emergency_contact_phone: props.member.emergency_contact_phone,
  notes: props.member.notes,
  joined_date: formatDateForInput(props.member.joined_date),
})

const getInitials = (firstName, lastName) => {
  return `${firstName?.charAt(0) || ''}${lastName?.charAt(0) || ''}`.toUpperCase()
}

const getStatusBadgeClass = (status) => {
  const classes = {
    active: 'bg-green-100 text-green-800',
    inactive: 'bg-gray-100 text-gray-800',
    suspended: 'bg-yellow-100 text-yellow-800',
    cancelled: 'bg-red-100 text-red-800',
    paid: 'bg-green-100 text-green-800',
    pending: 'bg-yellow-100 text-yellow-800',
    failed: 'bg-red-100 text-red-800',
  }
  return classes[status] || 'bg-gray-100 text-gray-800'
}

const getStatusText = (status) => {
  const texts = {
    active: 'Aktiv',
    inactive: 'Inaktiv',
    suspended: 'Pausiert',
    cancelled: 'Gekündigt',
  }
  return texts[status] || status
}

const getBillingCycleText = (cycle) => {
  const cycles = {
    'monthly': 'Monat',
    'quarterly': 'Quartal',
    'yearly': 'Jahr'
  }
  return cycles[cycle] || cycle
}

const getPaymentStatusClass = (status) => getStatusBadgeClass(status)

const getPaymentStatusText = (status) => {
  const texts = {
    paid: 'Bezahlt',
    pending: 'Ausstehend',
    failed: 'Fehlgeschlagen',
    cancelled: 'Storniert',
  }
  return texts[status] || status
}

const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('de-DE')
}

const formatTime = (datetime) => {
  if (!datetime) return '-'
  return new Date(datetime).toLocaleTimeString('de-DE', {
    hour: '2-digit',
    minute: '2-digit'
  })
}

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency: 'EUR'
  }).format(amount)
}

const calculateDuration = (checkIn, checkOut) => {
  if (!checkIn || !checkOut) return '-'
  const duration = new Date(checkOut) - new Date(checkIn)
  const hours = Math.floor(duration / (1000 * 60 * 60))
  const minutes = Math.floor((duration % (1000 * 60 * 60)) / (1000 * 60))
  return `${hours}h ${minutes}m`
}

const updateMember = () => {
  form.put(route('members.update', props.member.id), {
    onSuccess: () => {
      editMode.value = false
    }
  })
}

const cancelEdit = () => {
  form.reset()
  editMode.value = false
}

onMounted(() => {
  const urlParams = new URLSearchParams(window.location.search)
  if (urlParams.get('edit') === 'true') {
    editMode.value = true
  }
})
</script>
