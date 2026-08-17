<template>
    <Teleport to="body">
        <div
            v-if="log"
            class="fixed inset-0 z-50 flex items-center justify-center p-6 bg-gray-500/75"
            @click.self="emit('close')"
        >
            <div
                class="bg-white rounded-lg shadow-xl w-full max-w-xl overflow-hidden"
                role="dialog"
                aria-modal="true"
                :aria-label="`Standort wechseln zu ${log.home_gym_name}`"
            >
                <!-- Kopf -->
                <div class="px-6 pt-6 flex items-start gap-3.5">
                    <span class="flex-none w-10 h-10 rounded-full bg-indigo-50 flex items-center justify-center">
                        <ArrowUpDown class="w-5 h-5 text-indigo-600" />
                    </span>
                    <div class="min-w-0">
                        <h3 class="text-lg font-semibold text-gray-900">Standort wechseln</h3>
                        <p class="mt-1 text-sm text-gray-600">{{ leadText }}</p>
                    </div>
                </div>

                <div class="px-6 pt-5 flex flex-col gap-4">
                    <!-- Betroffenes Mitglied -->
                    <div class="flex items-center gap-3 border border-gray-200 rounded-lg px-4 py-3.5">
                        <span class="flex-none w-9 h-9 rounded-full bg-indigo-600 text-white flex items-center justify-center text-xs font-semibold">
                            {{ initials }}
                        </span>
                        <div class="min-w-0">
                            <div class="font-medium text-gray-900 truncate">
                                {{ log.member_name }}
                                <span v-if="log.member_number" class="text-gray-400 font-normal">
                                    ({{ log.member_number }})
                                </span>
                            </div>
                            <div class="text-xs text-gray-500">
                                Check-in-Versuch {{ log.formatted_time }}
                            </div>
                        </div>
                    </div>

                    <!-- Von / Nach -->
                    <div class="flex items-center gap-3">
                        <div class="flex-1 min-w-0 bg-gray-50 border border-gray-200 rounded-lg px-3.5 py-3">
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">
                                Aktuelle Organisation
                            </div>
                            <div class="mt-1 font-medium text-gray-700 truncate">{{ currentGymName }}</div>
                        </div>

                        <ArrowRight class="w-5 h-5 text-gray-400 flex-none" />

                        <div class="flex-1 min-w-0 bg-indigo-50 border border-indigo-200 rounded-lg px-3.5 py-3">
                            <div class="text-xs font-medium uppercase tracking-wide text-indigo-600">
                                Wechsel nach
                            </div>
                            <div class="mt-1 font-medium text-indigo-900 truncate">{{ log.home_gym_name }}</div>
                        </div>
                    </div>

                    <!-- Was passiert -->
                    <div class="border border-gray-200 rounded-lg px-4 py-3.5 flex flex-col gap-2">
                        <div v-for="(line, index) in consequences" :key="index" class="flex gap-2 items-start text-sm text-gray-700">
                            <Check class="w-4 h-4 text-green-600 flex-none mt-0.5" />
                            <span>{{ line }}</span>
                        </div>
                    </div>

                    <p class="text-xs text-gray-500">
                        Am Mitglied und an seinem Vertrag wird durch den Wechsel nichts geändert.
                    </p>
                </div>

                <!-- Aktionen -->
                <div class="mt-5 px-6 py-4 border-t border-gray-200 flex justify-end gap-3 flex-wrap">
                    <button
                        type="button"
                        @click="emit('close')"
                        class="px-4 py-2 rounded-md border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Abbrechen
                    </button>
                    <button
                        type="button"
                        @click="confirm"
                        :disabled="isSwitching"
                        class="px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
                    >
                        {{ isSwitching ? 'Wird gewechselt...' : `Zu ${log.home_gym_name} wechseln` }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { ArrowUpDown, ArrowRight, Check } from 'lucide-vue-next'

const props = defineProps({
    /**
     * The log entry the switch was triggered from, or null while closed.
     */
    log: {
        type: Object,
        default: null
    },
    /**
     * What to open after switching: 'member' or 'contract'.
     */
    target: {
        type: String,
        default: 'member'
    },
    currentGymName: {
        type: String,
        default: ''
    }
})

const emit = defineEmits(['close'])

const isSwitching = ref(false)

const isContract = computed(() => props.target === 'contract')

const initials = computed(() => {
    const name = props.log?.member_name

    if (!name) {
        return '?'
    }

    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map(part => part[0])
        .join('')
        .toUpperCase()
})

const contractLabel = computed(() => props.log?.contract_name ?? 'des Mitglieds')

const leadText = computed(() => {
    if (!props.log) {
        return ''
    }

    const what = isContract.value
        ? `Der Vertrag „${contractLabel.value}“ von ${props.log.member_name}`
        : `Das Profil von ${props.log.member_name}`

    return `${what} wird in ${props.log.home_gym_name} verwaltet. Für diese Aktion wechselt die Organisation.`
})

const consequences = computed(() => {
    if (!props.log) {
        return []
    }

    return [
        isContract.value
            ? `Nach dem Wechsel öffnet sich der Vertrag in ${props.log.home_gym_name}.`
            : `Nach dem Wechsel öffnet sich das Mitgliedsprofil in ${props.log.home_gym_name}.`,
        `Sie verlassen die Organisation ${props.currentGymName}. Das Live-Protokoll dieses Standorts läuft weiter, wird aber nicht mehr angezeigt.`,
        'Sie können jederzeit über den Standortwechsler in der Seitenleiste zurückwechseln.'
    ]
})

const confirm = () => {
    if (!props.log) {
        return
    }

    // The contract shortcut needs a plan to open; without one the switch still
    // makes sense, it just lands on the dashboard.
    const targetId = isContract.value ? props.log.contract_id : props.log.member_id

    isSwitching.value = true

    router.post(route('user.switch-organization'), {
        gym_id: props.log.home_gym_id,
        target: targetId ? props.target : null,
        target_id: targetId ?? null
    }, {
        preserveState: false,
        onFinish: () => {
            isSwitching.value = false
        }
    })
}
</script>
