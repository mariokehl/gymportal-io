<template>
    <!-- Organization Selector -->
    <div class="relative">
        <button @click="showOrgPopover = !showOrgPopover"
            class="w-full flex items-center justify-between p-3 hover:bg-gray-100 transition-colors">
            <div class="flex items-center flex-1 min-w-0">
                <div
                    class="w-8 h-8 flex-shrink-0 bg-blue-500 rounded-lg flex items-center justify-center text-white text-sm font-semibold leading-none">
                    {{ currentOrgName.charAt(0) }}
                </div>
                <div class="ml-3 text-left min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate">{{ currentOrgName }}</p>
                    <p class="text-xs text-gray-500">Organisation</p>
                </div>
            </div>
            <component :is="ChevronDown"
                :class="['w-4 h-4 text-gray-400 transition-transform', showOrgPopover ? 'rotate-180' : '']" />
        </button>

        <!-- Popover -->
        <div v-if="showOrgPopover"
            class="absolute bottom-full left-0 right-0 mb-2 ml-2 mr-2 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
            <div class="p-3 border-b border-gray-100">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Organisation verwalten</p>
            </div>
            <div class="py-1">
                <Link :href="route('settings.index')"
                    class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors flex items-center"
                    @click="showOrgPopover = false">
                <component :is="Settings" class="w-4 h-4 mr-2 text-gray-400" />
                Einstellungen
                </Link>
                <button
                    class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors flex items-center">
                    <component :is="DollarSign" class="w-4 h-4 mr-2 text-gray-400" />
                    Abrechnung
                </button>
                <Link :href="route('gyms.create')"
                    class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors flex items-center"
                    @click="showOrgPopover = false">
                <component :is="Plus" class="w-4 h-4 mr-2 text-gray-400" />
                Neue Organisation erstellen
                </Link>
            </div>
            <template v-if="allOrganizations.length > 1">
                <div class="p-3 border-b border-gray-100">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Organisation wechseln</p>
                </div>
                <div class="py-1">
                    <template v-for="organization in allOrganizations" :key="organization.id">
                        <button @click="switchOrganization(organization.id)" :disabled="switchingOrg"
                            class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors flex items-center disabled:opacity-50 disabled:cursor-not-allowed">
                            <component v-if="organization.id == page.props.auth.user.current_gym?.id"
                                :is="MapPinCheckInside" class="w-4 h-4 mr-2 text-green-500" />
                            <component v-else :is="MapPin" class="w-4 h-4 mr-2 text-gray-400" />
                            {{ organization.name }}
                            <component v-if="switchingOrg && switchingOrgId === organization.id" :is="Loader2"
                                class="w-4 h-4 ml-auto animate-spin text-gray-400" />
                        </button>
                    </template>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import {
    ChevronDown, Settings, DollarSign, Plus, MapPin, MapPinCheckInside, Loader2
} from 'lucide-vue-next'
import { router, usePage, Link } from '@inertiajs/vue3'

// Shared data
const page = usePage()

// Reactive data
const showOrgPopover = ref(false)
const switchingOrg = ref(false)
const switchingOrgId = ref(null)

// Computed properties
const currentOrgName = computed(() => {
    return page.props.auth.user.current_gym?.name || 'Kein Gym vorhanden';
})

const allOrganizations = computed(() => {
    return page.props.auth.user.all_gyms;
})

// Organization switching logic
const switchOrganization = async (organizationId) => {
    // Don't switch if it's already the current organization
    if (organizationId === page.props.auth.user.current_gym?.id) {
        showOrgPopover.value = false
        return
    }

    // Set loading state
    switchingOrg.value = true
    switchingOrgId.value = organizationId

    try {
        // Make the API call to switch organization
        await router.post(route('user.switch-organization'), {
            gym_id: organizationId
        }, {
            preserveState: false, // This will reload the page with new data
            preserveScroll: true,
            onSuccess: () => {
                // Close the popover on success
                showOrgPopover.value = false
            },
            onError: (errors) => {
                console.error('Failed to switch organization:', errors)
                // You could show a toast notification here
            },
            onFinish: () => {
                // Reset loading state
                switchingOrg.value = false
                switchingOrgId.value = null
            }
        })
    } catch (error) {
        console.error('Error switching organization:', error)
        switchingOrg.value = false
        switchingOrgId.value = null
    }
}

// Click outside handler
const handleClickOutside = (event) => {
    if (showOrgPopover.value && !event.target.closest('.relative')) {
        showOrgPopover.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})</script>
