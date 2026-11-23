<template>
    <!-- Organization Selector -->
    <div ref="root" class="relative">
        <!-- Without anything to expand the switcher is a plain label, not a
             button that does nothing. -->
        <component :is="canExpand ? 'button' : 'div'"
            ref="trigger"
            :type="canExpand ? 'button' : undefined"
            :aria-expanded="canExpand ? showOrgPopover : undefined"
            :aria-controls="canExpand ? popoverId : undefined"
            @click="canExpand && (showOrgPopover = !showOrgPopover)"
            :class="['w-full flex items-center justify-between p-3 transition-colors', canExpand ? 'hover:bg-fill cursor-pointer' : 'cursor-default']">
            <div class="flex items-center flex-1 min-w-0">
                <div class="w-8 h-8 flex-shrink-0 rounded-lg flex items-center justify-center text-sm font-semibold leading-none"
                    aria-hidden="true"
                    :style="symbolTileStyle(currentOrganization)">
                    {{ resolveSymbol(currentOrganization).content }}
                </div>
                <div class="ml-3 text-left min-w-0">
                    <p class="text-sm font-medium text-ink truncate">{{ currentOrgName }}</p>
                    <p class="text-xs text-ink-muted">Organisation</p>
                </div>
            </div>
            <!-- Only show the expand caret when there is something to expand:
                 management links (owner/admin) or more than one organization. -->
            <component v-if="canExpand" :is="ChevronDown" aria-hidden="true"
                :class="['w-4 h-4 text-ink-muted transition-transform', showOrgPopover ? 'rotate-180' : '']" />
        </component>

        <!-- Popover -->
        <div v-if="showOrgPopover && canExpand"
            :id="popoverId"
            role="region"
            aria-label="Organisation"
            class="absolute bottom-full left-0 right-0 mb-2 ml-2 mr-2 bg-surface border border-line rounded-lg shadow-lg z-50">
            <!-- Management links are reserved for owners/admins of the current
                 organization. Staff and trainers never see them. -->
            <template v-if="canManage">
                <div class="p-3 border-b border-line-subtle">
                    <h2 class="text-xs font-medium text-ink-muted">Organisation verwalten</h2>
                </div>
                <div class="py-1">
                    <Link :href="route('settings.index')"
                        class="w-full text-left px-3 py-2 text-sm text-ink-secondary hover:bg-surface-muted transition-colors flex items-center"
                        @click="showOrgPopover = false">
                    <component :is="Settings" class="w-4 h-4 mr-2 text-ink-muted" />
                    Einstellungen
                    </Link>
                    <Link :href="route('billing.index')"
                        class="w-full text-left px-3 py-2 text-sm text-ink-secondary hover:bg-surface-muted transition-colors flex items-center"
                        @click="showOrgPopover = false">
                        <component :is="DollarSign" class="w-4 h-4 mr-2 text-ink-muted" />
                        Abrechnung
                    </Link>
                    <Link :href="route('gyms.create')"
                        class="w-full text-left px-3 py-2 text-sm text-ink-secondary hover:bg-surface-muted transition-colors flex items-center"
                        @click="showOrgPopover = false">
                    <component :is="Plus" class="w-4 h-4 mr-2 text-ink-muted" />
                    Neue Organisation erstellen
                    </Link>
                </div>
            </template>
            <template v-if="allOrganizations.length > 1">
                <div class="p-3 border-b border-line-subtle">
                    <h2 class="text-xs font-medium text-ink-muted">Organisation wechseln</h2>
                </div>
                <div class="py-1">
                    <template v-for="organization in allOrganizations" :key="organization.id">
                        <button type="button" @click="switchOrganization(organization.id)" :disabled="switchingOrg"
                            :aria-current="organization.id == page.props.auth.user.current_gym?.id ? 'true' : undefined"
                            class="w-full text-left px-3 py-2 text-sm text-ink-secondary hover:bg-surface-muted transition-colors flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                            <span
                                aria-hidden="true"
                                class="w-6 h-6 flex-shrink-0 rounded-md flex items-center justify-center text-xs font-semibold leading-none"
                                :style="symbolTileStyle(organization)">
                                {{ resolveSymbol(organization).content }}
                            </span>
                            <span class="flex-1 min-w-0 truncate"
                                :class="organization.id == page.props.auth.user.current_gym?.id ? 'font-semibold text-ink' : ''">
                                {{ organization.name }}
                            </span>
                            <component v-if="switchingOrg && switchingOrgId === organization.id" :is="Loader2"
                                class="w-4 h-4 flex-shrink-0 animate-spin text-ink-muted" />
                            <component
                                v-else-if="organization.id == page.props.auth.user.current_gym?.id"
                                :is="Check" class="w-4 h-4 flex-shrink-0 text-success" />
                        </button>
                    </template>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, useId } from 'vue'
import {
    ChevronDown, Settings, DollarSign, Plus, Check, Loader2
} from '@lucide/vue'
import { router, usePage, Link } from '@inertiajs/vue3'
import { resolveSymbol, symbolTileStyle } from '@/utils/organizationSymbol'
import { useDismissable } from '@/composables/useDismissable'
import { useToast } from '@/composables/useToast'

// Shared data
const page = usePage()

// Reactive data
const showOrgPopover = ref(false)
const root = ref(null)
const trigger = ref(null)
const popoverId = `org-switcher-${useId()}`
useDismissable(root, showOrgPopover, { triggerRef: trigger })
const toast = useToast()
const switchingOrg = ref(false)
const switchingOrgId = ref(null)

// Computed properties
const currentOrganization = computed(() => {
    return page.props.auth.user.current_gym ?? { name: currentOrgName.value };
})

const currentOrgName = computed(() => {
    return page.props.auth.user.current_gym?.name || 'Kein Gym vorhanden';
})

const allOrganizations = computed(() => {
    return page.props.auth.user.all_gyms ?? [];
})

// Whether the user may manage the currently active organization (owner/admin).
// Staff and trainers get no management links.
const canManage = computed(() => {
    return page.props.auth.user.current_gym?.can_manage === true;
})

// The switcher only expands when there is something to show: management links
// for owners/admins, or more than one organization to switch between. A trainer
// or staff member with a single organization gets a plain, non-expandable label
// (no caret).
const canExpand = computed(() => {
    return canManage.value || allOrganizations.value.length > 1;
})

// Organization switching logic
const switchOrganization = (organizationId) => {
    // Don't switch if it's already the current organization
    if (organizationId === page.props.auth.user.current_gym?.id) {
        showOrgPopover.value = false
        return
    }

    switchingOrg.value = true
    switchingOrgId.value = organizationId

    router.post(route('user.switch-organization'), {
        gym_id: organizationId,
        // Stay on the current page (and tab) when the target organization
        // allows it; the server falls back to the dashboard otherwise.
        return_to: window.location.pathname + window.location.search
    }, {
        preserveState: false, // This will reload the page with new data
        preserveScroll: true,
        onSuccess: () => {
            showOrgPopover.value = false
        },
        onError: () => {
            toast.error('Organisation konnte nicht gewechselt werden', 'Bitte versuchen Sie es erneut.')
        },
        onFinish: () => {
            switchingOrg.value = false
            switchingOrgId.value = null
        }
    })
}
</script>
