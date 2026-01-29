<template>
    <AppLayout title="Dashboard">
        <template #header>
            Dashboard
        </template>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div v-for="(stat, index) in stats.main_stats" :key="index" class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex justify-between items-start">
                    <div :class="{ 'blur-sm select-none': !isOwnerOrAdmin && stat.title === 'Monatsumsatz' }">
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
                            <tr v-for="member in props.members" :key="member.id"
                                class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        <MemberAvatar
                                            :initials="member.initials"
                                            :age-verified="member.age_verified"
                                            :verified-at="member.age_verified_at"
                                            size="sm"
                                        />
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
                    <p class="text-sm text-gray-500">Zeige 1-{{ props.members.length }} von {{ totalMembers }} Mitgliedern</p>
                </div>
            </div>

            <!-- Right Column: Notifications & Active Check-Ins -->
            <div class="space-y-6">
                <!-- Notifications -->
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <h2 class="text-lg font-semibold mb-4">Benachrichtigungen</h2>
                    <div class="space-y-4">
                        <component :is="notification.link ? Link : 'div'"
                            v-for="notification in notifications"
                            :key="notification.id"
                            :href="notification.link"
                            class="border-b border-gray-100 pb-3 last:border-b-0 last:pb-0"
                            :class="{ 'hover:bg-gray-50 -mx-2 px-2 py-2 rounded transition-colors cursor-pointer': notification.link }"
                        >
                            <div class="flex justify-between items-start gap-2">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium" :class="notification.read_at ? 'text-gray-600' : 'text-gray-900'">
                                        {{ notification.title }}
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1 truncate">{{ notification.message }}</p>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <span class="text-xs text-gray-400">{{ notification.created_at }}</span>
                                    <div v-if="!notification.read_at" class="w-2 h-2 bg-indigo-500 rounded-full"></div>
                                </div>
                            </div>
                        </component>
                        <div v-if="notifications.length === 0" class="text-center text-sm text-gray-500 py-4">
                            Keine Benachrichtigungen vorhanden.
                        </div>
                    </div>

                    <Link
                        v-if="notifications.length > 0"
                        :href="route('notifications.index')"
                        class="mt-4 text-indigo-500 text-sm font-medium flex items-center hover:text-indigo-600 transition-colors"
                    >
                        Alle anzeigen
                        <ChevronRight class="w-4 h-4 ml-1" />
                    </Link>
                </div>

                <!-- Active Check-Ins -->
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <h2 class="text-lg font-semibold mb-4">Aktuell Anwesend</h2>
                    <div v-if="activeCheckIns.length > 0" class="flex flex-wrap gap-2">
                        <Tooltip
                            v-for="checkIn in activeCheckIns"
                            :key="checkIn.id"
                            position="top"
                        >
                            <Link
                                :href="route('members.show', checkIn.member_id)"
                                class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-500 flex items-center justify-center font-medium text-sm hover:bg-indigo-200 transition-colors"
                            >
                                {{ checkIn.member_initials }}
                            </Link>
                            <template #content>
                                <div class="text-center">
                                    <p class="font-medium">{{ checkIn.member_name }}</p>
                                    <p class="text-xs text-gray-400">{{ checkIn.check_in_time }}</p>
                                </div>
                            </template>
                        </Tooltip>
                    </div>
                    <div v-else class="text-center text-sm text-gray-500 py-4">
                        Gerade keine Check-Ins.
                    </div>
                </div>
            </div>
        </div>

    </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
    Users, FilePlus, DollarSign, BarChart,
    Plus, Edit, ChevronRight, Eye
} from 'lucide-vue-next'
import MemberStatusBadge from '@/Components/MemberStatusBadge.vue'
import MemberAvatar from '@/Components/MemberAvatar.vue'
import Tooltip from '@/Components/Tooltip.vue'
import { formatDate } from '@/utils/formatters'

// Reactive data
const page = usePage()

const isOwnerOrAdmin = computed(() => {
    const user = page.props.auth.user
    return user?.role_id === 1 || user?.role_id === 2
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
    notifications: Array,
    activeCheckIns: Array
})

// Methods
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
