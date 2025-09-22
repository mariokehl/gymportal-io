<template>
    <AppLayout title="Dashboard">
        <template #header>
            Dashboard
        </template>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div v-for="(stat, index) in stats.main_stats" :key="index" class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm text-gray-500">{{ stat.title }}</p>
                        <h3 class="text-2xl font-bold mt-1">{{ stat.value }}</h3>
                        <span :class="[
                            'text-sm',
                            `text-${stat.color}-500`
                        ]">
                            {{ stat.change }} gegenüber letztem Monat
                        </span>
                    </div>
                    <div class="p-2 bg-indigo-50 rounded-lg">
                        <component :is="getIcon(stat.icon)" class="w-6 h-6 text-indigo-500" />
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Members Overview -->
            <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-sm">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-lg font-semibold">Zuletzt angelegte Mitglieder</h2>
                    <Link
                        :href="route('members.create')"
                        class="bg-indigo-500 text-white px-4 py-2 rounded-md text-sm flex items-center hover:bg-indigo-600 transition-colors">
                        <Plus class="w-4 h-4 mr-1" />
                        Neuer Vertrag
                    </Link>
                </div>

                <!-- Search Bar -->
                <div class="mb-4 flex items-center space-x-2">
                    <div class="flex-1 relative">
                        <component :is="Search"
                            class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                        <input v-model="searchTerm" type="text" placeholder="Mitglied suchen..."
                            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>
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
                                <th class="text-right py-3 px-4 text-sm font-medium text-gray-500">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="member in filteredMembers" :key="member.id"
                                class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        <div
                                            class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-500 flex items-center justify-center font-medium">
                                            {{ member.initials }}
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium">{{ member.name }}</p>
                                            <p class="text-xs text-gray-500">{{ member.email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-sm">{{ member.membership }}</td>
                                <td class="py-3 px-4">
                                    <MemberStatusBadge :status="member.status" />
                                </td>
                                <td class="py-3 px-4 text-sm">
                                    {{ member.last_check_in ? formatDate(member.last_check_in.check_in_time) : 'Noch nie' }}
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <div class="flex items-center justify-end space-x-2">
                                        <Link
                                            :href="route('members.show', member.id)"
                                            class="text-gray-700 hover:text-gray-900 p-1 rounded"
                                            title="Anzeigen">
                                            <Eye class="w-4 h-4" />
                                        </Link>
                                        <Link
                                            :href="route('members.show', member.id) + '?edit=true'"
                                            class="text-indigo-600 hover:text-indigo-900 p-1 rounded"
                                            title="Bearbeiten">
                                            <Edit class="w-4 h-4" />
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-4 flex justify-between items-center">
                    <p class="text-sm text-gray-500">Zeige 1-{{ filteredMembers.length }} von {{ totalMembers }} Mitgliedern</p>
                </div>
            </div>

            <!-- Notifications -->
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <h2 class="text-lg font-semibold mb-4">Benachrichtigungen</h2>
                <div class="space-y-4">
                    <div v-for="notification in notifications" :key="notification.id"
                        class="border-b border-gray-100 pb-3 last:border-b-0">
                        <div class="flex justify-between items-start">
                            <p class="text-sm">{{ notification.text }}</p>
                            <span class="text-xs text-gray-500">{{ notification.time }}</span>
                        </div>
                    </div>
                    <span v-if="notifications.length === 0" class="text-center">Keine Benachrichtigungen vorhanden.</span>
                </div>

                <Link
                    :href="route('notifications.index')"
                    class="mt-4 text-indigo-500 text-sm font-medium flex items-center hover:text-indigo-600 transition-colors"
                >
                    Alle anzeigen
                    <ChevronRight class="w-4 h-4 ml-1" />
                </Link>
            </div>
        </div>

    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
    Users, FilePlus, DollarSign, BarChart,
    Plus, Search, Edit, ChevronRight, Eye
} from 'lucide-vue-next'
import MemberStatusBadge from '@/Components/MemberStatusBadge.vue'

// Reactive data
const searchTerm = ref('')

// Computed properties
const filteredMembers = computed(() => {
    if (!searchTerm.value) return props.members
    return props.members.filter(member =>
        member.name.toLowerCase().includes(searchTerm.value.toLowerCase()) ||
        member.email.toLowerCase().includes(searchTerm.value.toLowerCase())
    )
})

// Props
const props = defineProps({
    user: {
        type: Object,
        required: true
    },
    members: Array,
    totalMembers: Number,
    stats: Object,
    notifications: Array
})

// Methods
const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('de-DE')
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
</script>
