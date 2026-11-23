<template>
    <AppLayout title="Dashboard">
        <template #header>
            Dashboard
        </template>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
            <!-- Key figures carry the card on their own: label, value, change -->
            <div v-for="(stat, index) in stats.main_stats" :key="index" class="bg-surface p-6 rounded-lg shadow-sm">
                <div>
                        <p class="text-sm text-ink-muted">{{ stat.title }}</p>
                        <!-- Value is redacted server-side for staff; render a blurred placeholder instead -->
                        <div v-if="isRedacted(stat)" class="blur-sm select-none" aria-hidden="true">
                            <p class="text-2xl font-bold mt-1 tabular-nums">000</p>
                            <span class="text-sm text-ink-muted">0% gegenüber letztem Monat</span>
                        </div>
                        <template v-else>
                            <!-- A key figure, not a heading: keeps the outline h1 → h2 -->
                            <p class="text-2xl font-bold mt-1 tabular-nums">{{ stat.value }}</p>
                            <span :class="['text-sm', trendClass(stat.color)]">
                                {{ stat.change }} gegenüber letztem Monat
                            </span>
                        </template>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Members Overview -->
            <div class="@container lg:col-span-2 bg-surface p-6 rounded-lg shadow-sm">
                <div class="flex flex-wrap justify-between items-center gap-3 mb-6">
                    <h2 class="text-lg font-semibold">Zuletzt angelegte Mitglieder</h2>
                    <Link
                        :href="route('members.create')"
                        class="bg-brand text-white px-4 py-2 pointer-coarse:py-3 rounded-md text-sm flex items-center whitespace-nowrap hover:bg-brand-strong transition-colors">
                        <Plus class="w-4 h-4 mr-1" />
                        Neues Mitglied
                    </Link>
                </div>

                <!-- Narrow cards (phones, the 2/3 column on small laptops): a
                     compact list; the table needs ~720px. -->
                <ul class="@2xl:hidden -mx-2 divide-y divide-line-subtle">
                    <li v-for="member in props.members" :key="member.id" class="flex items-center gap-3 px-2 py-3">
                        <div class="min-w-0 flex-1">
                            <MemberIdentity :member="member" size="sm" max-width="100%" />
                        </div>
                        <MemberStatusBadge class="shrink-0" :status="member.status" />
                    </li>
                </ul>

                <!-- Members Table -->
                <div class="hidden @2xl:block overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="border-b border-line">
                                <th class="text-left py-3 px-3 text-sm font-medium text-ink-muted">Name</th>
                                <th class="text-left py-3 px-3 text-sm font-medium text-ink-muted">Mitgliedschaft</th>
                                <th class="text-left py-3 px-3 text-sm font-medium text-ink-muted">Status</th>
                                <th class="text-left py-3 px-3 text-sm font-medium text-ink-muted">Letzter Besuch</th>
                                <th class="text-right py-3 px-3 text-sm font-medium text-ink-muted">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="member in props.members" :key="member.id"
                                class="border-b border-line-subtle hover:bg-surface-muted transition-colors">
                                <td class="py-3 px-3">
                                    <MemberIdentity :member="member" size="sm" />
                                </td>
                                <td class="py-3 px-3 text-sm">{{ member.membership }}</td>
                                <td class="py-3 px-3">
                                    <MemberStatusBadge :status="member.status" />
                                </td>
                                <td class="py-3 px-3 text-sm">
                                    {{ member.last_check_in ? formatDate(member.last_check_in.check_in_time) : 'Noch nie' }}
                                </td>
                                <td class="py-3 px-3 text-right">
                                    <div class="flex items-center justify-end space-x-2 pointer-coarse:space-x-7">
                                        <Link
                                            :href="route('members.show', member.id)"
                                            class="touch-target relative text-ink-secondary hover:text-ink p-1 rounded"
                                            title="Anzeigen">
                                            <Eye class="w-4 h-4" />
                                        </Link>
                                        <Link
                                            :href="route('members.show', member.id) + '?edit=true'"
                                            class="touch-target relative text-brand-ink hover:text-brand-ink-strong p-1 rounded"
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
                    <p class="text-sm text-ink-muted">
                        Zeige 1-{{ props.members.length }} von
                        <span v-if="canViewStatistics">{{ totalMembers }}</span>
                        <span v-else class="blur-sm select-none" aria-hidden="true">000</span>
                        Mitgliedern
                    </p>
                </div>
            </div>

            <!-- Right Column: Notifications & Active Check-Ins -->
            <div class="space-y-6">
                <!-- Notifications -->
                <div class="bg-surface p-6 rounded-lg shadow-sm">
                    <h2 class="text-lg font-semibold mb-4">Benachrichtigungen</h2>
                    <div class="space-y-4">
                        <component :is="notification.link ? Link : 'div'"
                            v-for="notification in notifications"
                            :key="notification.id"
                            :href="notification.link"
                            class="border-b border-line-subtle pb-3 last:border-b-0 last:pb-0"
                            :class="{ 'hover:bg-surface-muted -mx-2 px-2 py-2 rounded transition-colors cursor-pointer': notification.link }"
                        >
                            <div class="flex justify-between items-start gap-2">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium" :class="notification.read_at ? 'text-ink-secondary' : 'text-ink'">
                                        {{ notification.title }}
                                    </p>
                                    <p class="text-xs text-ink-muted mt-1 truncate">{{ notification.message }}</p>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <span class="text-xs text-ink-muted">{{ notification.created_at }}</span>
                                    <div v-if="!notification.read_at" class="w-2 h-2 bg-brand rounded-full"></div>
                                </div>
                            </div>
                        </component>
                        <div v-if="notifications.length === 0" class="text-center text-sm text-ink-muted py-4">
                            Keine Benachrichtigungen vorhanden.
                        </div>
                    </div>

                    <Link
                        v-if="notifications.length > 0"
                        :href="route('notifications.index')"
                        class="mt-4 text-brand-ink text-sm font-medium flex items-center hover:text-brand-ink-strong transition-colors"
                    >
                        Alle anzeigen
                        <ChevronRight class="w-4 h-4 ml-1" />
                    </Link>
                </div>

                <!-- Active Check-Ins -->
                <div class="bg-surface p-6 rounded-lg shadow-sm">
                    <h2 class="text-lg font-semibold mb-4">Aktuell Anwesend</h2>
                    <div v-if="activeCheckIns.length > 0" class="flex flex-wrap gap-2">
                        <Tooltip
                            v-for="checkIn in activeCheckIns"
                            :key="checkIn.id"
                            position="top"
                        >
                            <Link
                                :href="route('members.show', checkIn.member_id)"
                                class="w-10 h-10 rounded-full bg-brand-soft text-brand-ink flex items-center justify-center font-medium text-sm hover:bg-brand-line transition-colors"
                            >
                                {{ checkIn.member_initials }}
                            </Link>
                            <template #content>
                                <div class="text-center">
                                    <p class="font-medium">{{ checkIn.member_name }}</p>
                                    <p class="text-xs text-ink-muted">{{ checkIn.check_in_time }}</p>
                                </div>
                            </template>
                        </Tooltip>
                    </div>
                    <div v-else class="text-center text-sm text-ink-muted py-4">
                        Gerade keine Check-Ins.
                    </div>
                </div>
            </div>
        </div>

    </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppShell from '@/Layouts/AppShell.vue'
import { Plus, Edit, ChevronRight, Eye } from '@lucide/vue'
import MemberStatusBadge from '@/Components/MemberStatusBadge.vue'
import MemberIdentity from '@/Components/Members/MemberIdentity.vue'
import Tooltip from '@/Components/Tooltip.vue'
import { formatDate } from '@/utils/formatters'

defineOptions({ layout: AppShell })

// Props
const props = defineProps({
    user: {
        type: Object,
        required: true
    },
    members: Array,
    totalMembers: Number,
    canViewStatistics: Boolean,
    stats: Object,
    notifications: Array,
    activeCheckIns: Array
})

const REDACTED_STAT_KEYS = ['active_members', 'monthly_revenue']

const isRedacted = (stat) => REDACTED_STAT_KEYS.includes(stat.key) && !props.canViewStatistics

// Methods
// The server picks a color per KPI; map it to text shades that keep 4.5:1
// on white (the -500 shades did not) and that Tailwind can see statically.
const TREND_CLASSES = {
    green: 'text-success',
    blue: 'text-info',
    red: 'text-danger',
    gray: 'text-ink-muted',
}
const trendClass = (color) => TREND_CLASSES[color] ?? 'text-ink-muted'
</script>
