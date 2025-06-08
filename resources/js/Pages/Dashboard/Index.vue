<template>
  <div class="flex h-screen bg-gray-100">
    <!-- Sidebar -->
    <div class="w-64 bg-white shadow-md">
      <div class="p-4 border-b border-gray-200">
        <h2 class="text-xl font-bold text-blue-600">gymportal.io</h2>
        <p class="text-sm text-gray-500">Mitgliederverwaltung</p>
      </div>

      <nav class="mt-6">
        <SidebarItem
          :icon="BarChart"
          label="Dashboard"
          :active="activeTab === 'dashboard'"
          @click="activeTab = 'dashboard'"
        />
        <SidebarItem
          :icon="Users"
          label="Mitglieder"
          :active="activeTab === 'members'"
          @click="activeTab = 'members'"
        />
        <SidebarItem
          :icon="FilePlus"
          label="Verträge"
          :active="activeTab === 'contracts'"
          @click="activeTab = 'contracts'"
        />
        <SidebarItem
          :icon="DollarSign"
          label="Finanzen"
          :active="activeTab === 'finances'"
          @click="activeTab = 'finances'"
        />
        <SidebarItem
          :icon="Bell"
          label="Benachrichtigungen"
          :active="activeTab === 'notifications'"
          @click="activeTab = 'notifications'"
        />
        <SidebarItem
          :icon="Settings"
          label="Einstellungen"
          :active="activeTab === 'settings'"
          @click="activeTab = 'settings'"
        />
        <SidebarItem
          :icon="LogOut"
          label="Abmelden"
          :active="false"
          @click="handleLogout"
        />
      </nav>
    </div>

    <!-- Main Content -->
    <div class="flex-1 overflow-y-auto">
      <!-- Header -->
      <header class="bg-white shadow-sm p-4 flex justify-between items-center sticky top-0 z-10">
        <h1 class="text-xl font-semibold">
          {{ getPageTitle(activeTab) }}
        </h1>

        <div class="flex items-center space-x-4">
          <div class="relative">
            <component :is="Bell" class="w-5 h-5 text-gray-500 cursor-pointer" />
            <span class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-4 h-4 flex items-center justify-center text-xs">
              {{ notifications.length }}
            </span>
          </div>
          <div class="flex items-center">
            <div class="w-8 h-8 bg-blue-500 rounded-full text-white flex items-center justify-center text-xs font-semibold">{{ userInitials }}</div>
            <span class="ml-2 text-sm font-medium">{{ user.first_name }} {{ user.last_name }}</span>
          </div>
        </div>
      </header>

      <!-- Dashboard Content -->
      <main class="p-6">
        <div v-if="activeTab === 'dashboard'">
          <!-- Stats Cards -->
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div v-for="(stat, index) in stats" :key="index" class="bg-white p-6 rounded-lg shadow-sm">
              <div class="flex justify-between items-start">
                <div>
                  <p class="text-sm text-gray-500">{{ stat.title }}</p>
                  <h3 class="text-2xl font-bold mt-1">{{ stat.value }}</h3>
                  <span :class="[
                    'text-sm',
                    stat.change.startsWith('+') ? 'text-green-500' : 'text-red-500'
                  ]">
                    {{ stat.change }} gegenüber letztem Monat
                  </span>
                </div>
                <div class="p-2 bg-blue-50 rounded-lg">
                  <component :is="getIcon(stat.icon)" class="w-6 h-6 text-blue-500" />
                </div>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Members Overview -->
            <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-sm">
              <div class="flex justify-between items-center mb-6">
                <h2 class="text-lg font-semibold">Mitgliederübersicht</h2>
                <button
                  class="bg-blue-500 text-white px-4 py-2 rounded-md text-sm flex items-center hover:bg-blue-600 transition-colors"
                  @click="showNewContract = !showNewContract"
                >
                  <component :is="Plus" class="w-4 h-4 mr-1" />
                  Neuer Vertrag
                </button>
              </div>

              <!-- New Contract Form -->
              <div v-if="showNewContract" class="mb-6 bg-blue-50 p-4 rounded-lg">
                <h3 class="font-medium mb-3">Neuen Online-Vertrag erstellen</h3>
                <form @submit.prevent="submitContract">
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Vorname & Nachname</label>
                      <input
                        v-model="contractForm.name"
                        type="text"
                        class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Max Mustermann"
                        required
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">E-Mail</label>
                      <input
                        v-model="contractForm.email"
                        type="email"
                        class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="max@example.com"
                        required
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Mitgliedschaft</label>
                      <select
                        v-model="contractForm.membership"
                        class="w-full p-2 border border-gray-300 rounded-md bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        required
                      >
                        <option value="Basic">Basic (€29,99/Monat)</option>
                        <option value="Standard">Standard (€49,99/Monat)</option>
                        <option value="Premium">Premium (€69,99/Monat)</option>
                      </select>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Vertragsdauer</label>
                      <select
                        v-model="contractForm.duration"
                        class="w-full p-2 border border-gray-300 rounded-md bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        required
                      >
                        <option value="12 Monate">12 Monate</option>
                        <option value="24 Monate">24 Monate</option>
                        <option value="Flexibel (monatlich)">Flexibel (monatlich)</option>
                      </select>
                    </div>
                  </div>
                  <div class="mt-4 flex justify-end space-x-2">
                    <button
                      type="button"
                      class="px-4 py-2 text-sm border border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
                      @click="showNewContract = false"
                    >
                      Abbrechen
                    </button>
                    <button
                      type="submit"
                      class="px-4 py-2 text-sm bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors"
                      :disabled="isSubmitting"
                    >
                      {{ isSubmitting ? 'Wird erstellt...' : 'Vertrag erstellen' }}
                    </button>
                  </div>
                </form>
              </div>

              <!-- Search Bar -->
              <div class="mb-4 flex items-center space-x-2">
                <div class="flex-1 relative">
                  <component :is="Search" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                  <input
                    v-model="searchTerm"
                    type="text"
                    placeholder="Mitglied suchen..."
                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  />
                </div>
                <button class="p-2 border border-gray-300 rounded-md flex items-center hover:bg-gray-50 transition-colors">
                  <component :is="Filter" class="w-4 h-4 text-gray-500 mr-1" />
                  <span class="text-sm">Filter</span>
                </button>
                <button class="p-2 border border-gray-300 rounded-md flex items-center hover:bg-gray-50 transition-colors">
                  <component :is="ChevronDown" class="w-4 h-4 text-gray-500" />
                </button>
              </div>

              <!-- Members Table -->
              <div class="overflow-x-auto">
                <table class="min-w-full">
                  <thead>
                    <tr class="border-b border-gray-200">
                      <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Name</th>
                      <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Mitgliedschaft</th>
                      <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Status</th>
                      <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Letzter Besuch</th>
                      <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Vertragsende</th>
                      <th class="text-right py-3 px-4 text-sm font-medium text-gray-500">Aktionen</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="member in filteredMembers"
                      :key="member.id"
                      class="border-b border-gray-100 hover:bg-gray-50 transition-colors"
                    >
                      <td class="py-3 px-4">
                        <div class="flex items-center">
                          <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-500 flex items-center justify-center font-medium">
                            {{ member.name.charAt(0) }}
                          </div>
                          <div class="ml-3">
                            <p class="text-sm font-medium">{{ member.name }}</p>
                            <p class="text-xs text-gray-500">{{ member.email }}</p>
                          </div>
                        </div>
                      </td>
                      <td class="py-3 px-4 text-sm">{{ member.membership }}</td>
                      <td class="py-3 px-4">
                        <span :class="[
                          'px-2 py-1 text-xs rounded-full',
                          member.status === 'Aktiv' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                        ]">
                          {{ member.status }}
                        </span>
                      </td>
                      <td class="py-3 px-4 text-sm">{{ member.lastVisit }}</td>
                      <td class="py-3 px-4 text-sm">{{ member.contractEnd }}</td>
                      <td class="py-3 px-4 text-right">
                        <button class="p-1 text-blue-500 hover:text-blue-700 transition-colors">
                          <component :is="Edit" class="w-4 h-4" />
                        </button>
                        <button class="p-1 text-red-500 hover:text-red-700 ml-2 transition-colors">
                          <component :is="Trash2" class="w-4 h-4" />
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Pagination -->
              <div class="mt-4 flex justify-between items-center">
                <p class="text-sm text-gray-500">Zeige 1-{{ filteredMembers.length }} von 248 Mitgliedern</p>
                <div class="flex items-center space-x-1">
                  <button class="p-2 border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                    <component :is="ChevronRight" class="w-4 h-4 text-gray-500 transform rotate-180" />
                  </button>
                  <button class="w-8 h-8 bg-blue-500 text-white rounded-md flex items-center justify-center">1</button>
                  <button class="w-8 h-8 border border-gray-300 rounded-md flex items-center justify-center hover:bg-gray-50 transition-colors">2</button>
                  <button class="w-8 h-8 border border-gray-300 rounded-md flex items-center justify-center hover:bg-gray-50 transition-colors">3</button>
                  <button class="p-2 border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                    <component :is="ChevronRight" class="w-4 h-4 text-gray-500" />
                  </button>
                </div>
              </div>
            </div>

            <!-- Notifications -->
            <div class="bg-white p-6 rounded-lg shadow-sm">
              <h2 class="text-lg font-semibold mb-4">Benachrichtigungen</h2>
              <div class="space-y-4">
                <div
                  v-for="notification in notifications"
                  :key="notification.id"
                  class="border-b border-gray-100 pb-3 last:border-b-0"
                >
                  <div class="flex justify-between items-start">
                    <p class="text-sm">{{ notification.text }}</p>
                    <span class="text-xs text-gray-500">{{ notification.time }}</span>
                  </div>
                </div>
              </div>

              <button class="mt-4 text-blue-500 text-sm font-medium flex items-center hover:text-blue-600 transition-colors">
                Alle anzeigen
                <component :is="ChevronRight" class="w-4 h-4 ml-1" />
              </button>
            </div>
          </div>
        </div>

        <!-- Other Tab Content -->
        <div v-else class="bg-white p-6 rounded-lg shadow-sm flex items-center justify-center">
          <p class="text-gray-500">Inhalt für "{{ activeTab }}" hier anzeigen.</p>
        </div>
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import {
  Users, Bell, DollarSign,
  BarChart, Settings, LogOut,
  Search, Plus, Filter, ChevronDown,
  ChevronRight, Edit, Trash2, FilePlus
} from 'lucide-vue-next'
import SidebarItem from '@/Components/SidebarItem.vue'

// Props
const props = defineProps({
  user: {
    type: Object,
    required: true
  },
  members: Array,
  stats: Array,
  notifications: Array
})

// Reactive data
const activeTab = ref('dashboard')
const showNewContract = ref(false)
const searchTerm = ref('')
const isSubmitting = ref(false)
const contractForm = ref({
  name: '',
  email: '',
  membership: 'Basic',
  duration: '12 Monate'
})

// Computed properties
const userInitials = computed(() => {
  const first = props.user.first_name?.charAt(0) || ''
  const last = props.user.last_name?.charAt(0) || ''
  return (first + last).toUpperCase()
})

const filteredMembers = computed(() => {
  if (!searchTerm.value) return props.members
  return props.members.filter(member =>
    member.name.toLowerCase().includes(searchTerm.value.toLowerCase()) ||
    member.email.toLowerCase().includes(searchTerm.value.toLowerCase())
  )
})

// Methods
const getPageTitle = (tab) => {
  const titles = {
    dashboard: 'Dashboard',
    members: 'Mitgliederverwaltung',
    contracts: 'Verträge',
    finances: 'Finanzen',
    notifications: 'Benachrichtigungen',
    settings: 'Einstellungen'
  }
  return titles[tab] || 'Dashboard'
}

const getIcon = (iconName) => {
  const icons = {
    users: Users,
    'file-plus': FilePlus,
    'dollar-sign': DollarSign,
    'bar-chart': BarChart
  }
  return icons[iconName] || BarChart
}

const handleLogout = () => {
  if (confirm('Möchten Sie sich wirklich abmelden?')) {
    router.post('/logout')
  }
}

const submitContract = async () => {
  isSubmitting.value = true

  try {
    await router.post('/contracts', contractForm.value, {
      onSuccess: () => {
        showNewContract.value = false
        contractForm.value = {
          name: '',
          email: '',
          membership: 'Basic',
          duration: '12 Monate'
        }
      }
    })
  } catch (error) {
    console.error('Error creating contract:', error)
  } finally {
    isSubmitting.value = false
  }
}
</script>
