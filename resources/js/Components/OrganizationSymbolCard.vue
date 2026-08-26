<template>
    <div class="bg-white shadow-sm rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Symbol der Organisation</h3>
                    <p class="mt-2 text-sm text-gray-500 max-w-xl">
                        Farbe und Symbol werden links unten und im Organisations-Wechsler verwendet. So lassen sich
                        mehrere Standorte auf einen Blick unterscheiden.
                    </p>
                </div>
                <!-- Reminder which organization is being edited: the settings page
                     always edits the currently active one. -->
                <div
                    class="flex items-center gap-2.5 bg-gray-50 border border-gray-200 rounded-full py-1.5 pl-2 pr-3.5 whitespace-nowrap">
                    <span
                        class="w-6 h-6 flex-shrink-0 rounded-md flex items-center justify-center text-xs font-semibold leading-none"
                        :style="previewTileStyle">
                        {{ previewSymbol.content }}
                    </span>
                    <span class="text-xs text-gray-500">
                        Bearbeitet: <span class="text-gray-900 font-medium">{{ organizationName }}</span>
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_20rem] gap-8">
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm/6 font-medium text-gray-700 mb-2">Symbol</label>
                        <div class="inline-flex bg-gray-100 rounded-lg p-0.5 gap-0.5">
                            <button v-for="option in symbolTypeOptions" :key="option.value" type="button"
                                @click="selectSymbolType(option.value)" :class="[
                                    'px-4 py-1.5 rounded-md text-sm font-medium transition-colors',
                                    symbolType === option.value
                                        ? 'bg-white text-gray-900 shadow-sm'
                                        : 'text-gray-500 hover:text-gray-700'
                                ]">
                                {{ option.label }}
                            </button>
                        </div>
                    </div>

                    <div v-if="symbolType === SYMBOL_TYPE_EMOJI">
                        <label class="block text-sm/6 font-medium text-gray-700 mb-2">Emoji auswählen</label>
                        <div class="grid grid-cols-8 sm:grid-cols-12 gap-1.5 max-w-lg">
                            <button v-for="emoji in SYMBOL_EMOJIS" :key="emoji" type="button"
                                @click="selectEmoji(emoji)" :aria-pressed="emoji === symbolEmoji" :class="[
                                    'aspect-square flex items-center justify-center text-xl leading-none rounded-md transition-colors',
                                    emoji === symbolEmoji
                                        ? 'bg-indigo-50 ring-2 ring-inset ring-indigo-600'
                                        : 'bg-gray-50 ring-1 ring-inset ring-gray-200 hover:bg-gray-100'
                                ]">
                                {{ emoji }}
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm/6 font-medium text-gray-700 mb-2">Farbe</label>
                        <div class="flex flex-wrap gap-2.5">
                            <button v-for="color in SYMBOL_COLORS" :key="color.value" type="button"
                                @click="selectColor(color.value)" :title="color.name" :aria-label="color.name"
                                :aria-pressed="color.value === symbolColor" class="w-7 h-7 rounded-full transition-shadow"
                                :style="colorSwatchStyle(color.value)"></button>
                        </div>
                        <p class="mt-2.5 text-xs text-gray-500">Ohne Auswahl wird weiterhin Indigo verwendet.</p>
                    </div>
                </div>

                <!-- Preview of both places the symbol shows up, so operators can
                     judge the choice without leaving the settings page. -->
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-5">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 mb-4">Vorschau</p>

                    <div class="bg-white border border-gray-200 rounded-lg p-2.5 flex items-center gap-3">
                        <span
                            class="w-8 h-8 flex-shrink-0 rounded-lg flex items-center justify-center text-sm font-semibold leading-none"
                            :style="previewTileStyle">
                            {{ previewSymbol.content }}
                        </span>
                        <span class="flex-1 min-w-0">
                            <span class="block text-sm font-medium text-gray-900 truncate">{{ organizationName }}</span>
                            <span class="block text-xs text-gray-500">Organisation</span>
                        </span>
                        <component :is="ChevronDown" class="w-4 h-4 text-gray-400" />
                    </div>

                    <template v-if="otherOrganizations.length">
                        <p class="text-xs text-gray-500 mt-4 mb-2.5">Alle Standorte im Vergleich</p>
                        <div class="bg-white border border-gray-200 rounded-lg py-1.5">
                            <div v-for="organization in comparisonOrganizations" :key="organization.id"
                                class="flex items-center gap-2.5 px-3 py-1.5">
                                <span
                                    class="w-6 h-6 flex-shrink-0 rounded-md flex items-center justify-center text-xs font-semibold leading-none"
                                    :style="organization.tileStyle">
                                    {{ organization.content }}
                                </span>
                                <span class="text-sm text-gray-700 truncate">{{ organization.name }}</span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="flex items-center justify-end gap-4 border-t border-gray-100 mt-6 pt-5">
                <button type="button" @click="reset" :disabled="isSubmitting"
                    class="inline-flex justify-center items-center py-2 px-4 border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-sm font-medium rounded-md disabled:opacity-50 disabled:cursor-not-allowed">
                    Zurücksetzen
                </button>
                <button type="button" @click="save" :disabled="isSubmitting"
                    class="inline-flex justify-center items-center py-2 px-4 border border-transparent bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ isSubmitting ? 'Wird gespeichert…' : 'Änderungen speichern' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { ChevronDown } from 'lucide-vue-next'
import { useToast } from '@/composables/useToast'
import {
    DEFAULT_SYMBOL_COLOR,
    SYMBOL_COLORS,
    SYMBOL_EMOJIS,
    SYMBOL_TYPE_EMOJI,
    SYMBOL_TYPE_INITIAL,
    resolveSymbol,
    symbolTileStyle,
} from '@/utils/organizationSymbol'

const props = defineProps({
    currentGym: {
        type: Object,
        required: true,
    },
})

const page = usePage()
const { success, error: toastError } = useToast()

const symbolTypeOptions = [
    { value: SYMBOL_TYPE_INITIAL, label: 'Anfangsbuchstabe' },
    { value: SYMBOL_TYPE_EMOJI, label: 'Emoji' },
]

const symbolType = ref(props.currentGym?.symbol_type || SYMBOL_TYPE_INITIAL)
const symbolEmoji = ref(props.currentGym?.symbol_emoji || null)
const symbolColor = ref(props.currentGym?.symbol_color || DEFAULT_SYMBOL_COLOR)
const isSubmitting = ref(false)

// The name shown in the preview follows the switcher: display name first,
// falling back to the organization name.
const organizationName = computed(() => {
    return props.currentGym?.display_name?.trim() || props.currentGym?.name || ''
})

// Preview reflects the unsaved selection rather than the persisted symbol.
const previewSymbol = computed(() => resolveSymbol({
    name: organizationName.value,
    symbol: {
        type: symbolType.value,
        emoji: symbolEmoji.value,
        color: symbolColor.value,
    },
}))

const previewTileStyle = computed(() => symbolTileStyle({
    symbol: { color: symbolColor.value },
}))

const otherOrganizations = computed(() => {
    return (page.props.auth.user.all_gyms ?? [])
        .filter(organization => organization.id !== props.currentGym?.id)
})

// The edited organization first, then its siblings — all rendered from the
// same helper the switcher uses.
const comparisonOrganizations = computed(() => {
    const edited = {
        id: props.currentGym?.id,
        name: organizationName.value,
        content: previewSymbol.value.content,
        tileStyle: previewTileStyle.value,
    }

    const others = otherOrganizations.value.map(organization => ({
        id: organization.id,
        name: organization.name,
        content: resolveSymbol(organization).content,
        tileStyle: symbolTileStyle(organization),
    }))

    return [edited, ...others]
})

const colorSwatchStyle = (value) => {
    return {
        backgroundColor: value,
        boxShadow: value === symbolColor.value ? `0 0 0 2px #fff, 0 0 0 4px ${value}` : 'none',
    }
}

const selectSymbolType = (value) => {
    symbolType.value = value

    // Switching to the emoji tab without a previous choice would render an
    // empty tile, so preselect the first emoji.
    if (value === SYMBOL_TYPE_EMOJI && !symbolEmoji.value) {
        symbolEmoji.value = SYMBOL_EMOJIS[0]
    }
}

const selectEmoji = (emoji) => {
    symbolEmoji.value = emoji
    symbolType.value = SYMBOL_TYPE_EMOJI
}

const selectColor = (value) => {
    symbolColor.value = value
}

const reset = () => {
    symbolType.value = SYMBOL_TYPE_INITIAL
    symbolEmoji.value = null
    symbolColor.value = DEFAULT_SYMBOL_COLOR
}

const save = async () => {
    isSubmitting.value = true

    try {
        await axios.put(route('settings.gym.symbol.update', props.currentGym.id), {
            symbol_type: symbolType.value,
            symbol_emoji: symbolType.value === SYMBOL_TYPE_EMOJI ? symbolEmoji.value : null,
            symbol_color: symbolColor.value,
        })

        success('Symbol der Organisation wurde gespeichert!')

        // Reload the shared props so the sidebar and the switcher pick up the
        // new symbol without a full page refresh.
        router.reload({ only: ['auth'] })
    } catch (error) {
        toastError('Fehler beim Speichern des Symbols')
    } finally {
        isSubmitting.value = false
    }
}

// Keep the preview in sync when the organization is reloaded after a save.
watch(() => props.currentGym, (gym) => {
    symbolType.value = gym?.symbol_type || SYMBOL_TYPE_INITIAL
    symbolEmoji.value = gym?.symbol_emoji || null
    symbolColor.value = gym?.symbol_color || DEFAULT_SYMBOL_COLOR
})
</script>
