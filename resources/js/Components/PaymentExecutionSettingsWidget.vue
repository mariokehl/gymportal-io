<template>
    <div class="bg-white shadow-sm rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <div class="flex items-end justify-between gap-6 flex-wrap">
                <div class="max-w-2xl">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        Ausführungsdaten der Zahlungen
                    </h3>
                    <p class="mt-1.5 text-sm text-gray-500">
                        Legen Sie pro Zahlungsart fest, wann eine Zahlung relativ zur Fälligkeit vorgemerkt und
                        ausgeführt wird. Ohne abweichende Einstellung gelten die Systemstandards.
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Beispiel-Fälligkeit</label>
                    <input
                        v-model="sampleDueDate"
                        type="date"
                        class="block rounded-md bg-white px-3 py-1.5 text-base text-gray-700 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6" />
                </div>
            </div>

            <!-- Hint: only enabled payment methods can be configured -->
            <div v-if="inactiveMethods.length" class="flex gap-3 rounded-lg bg-indigo-50 border border-indigo-200 p-4 mt-5">
                <component :is="Info" class="w-5 h-5 text-indigo-400 flex-none mt-px" />
                <p class="text-sm text-indigo-800">
                    Nur aktivierte Zahlungsarten sind konfigurierbar. Deaktivierte Zahlungsarten aktivieren Sie unter
                    <strong class="font-semibold">Standard Zahlungsarten</strong>.
                </p>
            </div>

            <div v-if="isLoading" class="py-10 text-center text-sm text-gray-500">
                Ausführungsdaten werden geladen …
            </div>

            <template v-else>
                <!-- Column headers (desktop only; rows stack on mobile) -->
                <div class="hidden lg:grid grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,1fr)_104px] gap-5 pt-5 pb-2.5 border-b border-gray-200">
                    <div class="text-xs font-medium uppercase tracking-wider text-gray-500">Zahlungsart</div>
                    <div class="text-xs font-medium uppercase tracking-wider text-gray-500">Initiale Zahlung</div>
                    <div class="text-xs font-medium uppercase tracking-wider text-gray-500">Wiederkehrende Zahlung</div>
                    <div class="text-xs font-medium uppercase tracking-wider text-gray-500 text-right">Abweichend</div>
                </div>

                <!-- Configurable (enabled) payment methods -->
                <div
                    v-for="method in methods"
                    :key="method.key"
                    class="grid grid-cols-1 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,1fr)_104px] gap-4 lg:gap-5 items-start py-4 border-b border-gray-100">

                    <!-- Method name -->
                    <div class="flex items-start gap-3 min-w-0">
                        <div class="w-9 h-9 flex-none rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                            <component :is="CalendarClock" class="w-4.5 h-4.5" />
                        </div>
                        <div class="min-w-0 flex flex-col justify-center min-h-9">
                            <p class="text-sm font-medium text-gray-900">{{ method.name }}</p>
                            <span
                                v-if="method.type === 'mollie'"
                                class="inline-flex self-start items-center mt-1.5 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">
                                Via Mollie
                            </span>
                        </div>
                    </div>

                    <!-- Initial payment offset -->
                    <div class="min-w-0">
                        <p class="lg:hidden text-xs font-medium uppercase tracking-wider text-gray-500 mb-1.5">
                            Initiale Zahlung
                        </p>
                        <div v-if="method.is_custom" class="flex items-center gap-2">
                            <OffsetStepper
                                :model-value="draftValue(method, 'initial')"
                                :min="limits.min"
                                :max="limits.max"
                                :disabled="isSaving"
                                :highlight="isFieldDirty(method, 'initial')"
                                @update:modelValue="value => setDraft(method, 'initial', value)" />
                            <!-- Unsaved change: persist only on explicit click -->
                            <button
                                v-if="isFieldDirty(method, 'initial')"
                                type="button"
                                :disabled="isSaving"
                                @click="saveField(method, 'initial')"
                                title="Änderung speichern"
                                aria-label="Änderung speichern"
                                class="flex-none w-9 h-9 rounded-md border border-orange-400 bg-orange-50 text-orange-600 flex items-center justify-center hover:bg-orange-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                <component :is="Save" class="w-4 h-4" />
                            </button>
                        </div>
                        <div v-else class="flex items-center gap-2 flex-wrap min-h-9">
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-500">Standard</span>
                            <span class="text-sm text-gray-500">{{ offsetText(method.initial) }}</span>
                        </div>
                        <p class="text-xs mt-2" :class="isFieldDirty(method, 'initial') ? 'text-orange-600' : 'text-gray-500'">
                            Ausführung:
                            <span class="font-medium" :class="isFieldDirty(method, 'initial') ? 'text-orange-700' : 'text-gray-700'">
                                {{ shiftedDate(draftValue(method, 'initial')) }}
                            </span>
                        </p>
                    </div>

                    <!-- Recurring payment offset -->
                    <div class="min-w-0">
                        <p class="lg:hidden text-xs font-medium uppercase tracking-wider text-gray-500 mb-1.5">
                            Wiederkehrende Zahlung
                        </p>
                        <div v-if="method.is_custom" class="flex items-center gap-2">
                            <OffsetStepper
                                :model-value="draftValue(method, 'recurring')"
                                :min="limits.min"
                                :max="limits.max"
                                :disabled="isSaving"
                                :highlight="isFieldDirty(method, 'recurring')"
                                @update:modelValue="value => setDraft(method, 'recurring', value)" />
                            <button
                                v-if="isFieldDirty(method, 'recurring')"
                                type="button"
                                :disabled="isSaving"
                                @click="saveField(method, 'recurring')"
                                title="Änderung speichern"
                                aria-label="Änderung speichern"
                                class="flex-none w-9 h-9 rounded-md border border-orange-400 bg-orange-50 text-orange-600 flex items-center justify-center hover:bg-orange-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                <component :is="Save" class="w-4 h-4" />
                            </button>
                        </div>
                        <div v-else class="flex items-center gap-2 flex-wrap min-h-9">
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-500">Standard</span>
                            <span class="text-sm text-gray-500">{{ offsetText(method.recurring) }}</span>
                        </div>
                        <p class="text-xs mt-2" :class="isFieldDirty(method, 'recurring') ? 'text-orange-600' : 'text-gray-500'">
                            Ausführung:
                            <span class="font-medium" :class="isFieldDirty(method, 'recurring') ? 'text-orange-700' : 'text-gray-700'">
                                {{ shiftedDate(draftValue(method, 'recurring')) }}
                            </span>
                        </p>
                    </div>

                    <!-- Toggle: gym-specific override on/off -->
                    <div class="flex lg:flex-col items-center lg:items-end gap-3 lg:gap-2">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input
                                type="checkbox"
                                class="sr-only peer"
                                :checked="method.is_custom"
                                :disabled="isSaving"
                                @change="toggleCustom(method)">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                        </label>
                        <button
                            v-if="method.is_custom"
                            type="button"
                            :disabled="isSaving"
                            @click="resetMethod(method)"
                            class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 disabled:opacity-50">
                            Zurücksetzen
                        </button>
                    </div>
                </div>

                <!-- Inactive payment methods (read-only) -->
                <div
                    v-for="method in inactiveMethods"
                    :key="method.key"
                    class="grid grid-cols-1 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,2fr)_104px] gap-4 lg:gap-5 items-center py-4 border-b border-gray-100 opacity-60">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 flex-none rounded-lg bg-gray-100 text-gray-400 flex items-center justify-center">
                            <component :is="Lock" class="w-4 h-4" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-700">{{ method.name }}</p>
                        </div>
                    </div>
                    <p class="text-sm text-gray-500">
                        Zahlungsart ist nicht aktiviert — keine Konfiguration möglich.
                    </p>
                    <p class="text-xs text-gray-400 lg:text-right">Inaktiv</p>
                </div>

                <!-- Summary + reset all -->
                <div class="flex items-center justify-between gap-5 flex-wrap pt-5">
                    <div class="max-w-xl">
                        <p class="text-sm text-gray-500">
                            {{ summary }} · Werte „vorher“ merken die Zahlung vor der Fälligkeit vor, „danach“
                            entsprechend später.
                        </p>
                        <p v-if="hasPendingChanges" class="flex items-center gap-1.5 mt-1.5 text-sm font-medium text-orange-600">
                            <component :is="Save" class="w-4 h-4 flex-none" />
                            Ungespeicherte Änderungen · „Speichern“ klicken
                        </p>
                    </div>
                    <button
                        type="button"
                        :disabled="isSaving || customCount === 0"
                        @click="resetAll"
                        class="px-4 py-2 rounded-md border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                        Alle auf Standard
                    </button>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, h } from 'vue'
import { CalendarClock, Info, Lock, Save } from 'lucide-vue-next'
import { executionOffsetText, shiftExecutionDate } from '@/utils/payments'
import { useToast } from '@/composables/useToast'

const { success, error: toastError } = useToast()

const emit = defineEmits(['updated'])

const methods = ref([])
const inactiveMethods = ref([])
const limits = ref({ min: -14, max: 30 })
const isLoading = ref(true)
const isSaving = ref(false)

// Unsaved stepper values, keyed by "<methodKey>.<field>". A field only appears
// here while it deviates from the persisted value; saving or reverting it to
// the stored value removes the entry again.
const drafts = ref({})

const draftKey = (method, field) => `${method.key}.${field}`

// The value to display: the pending edit if there is one, otherwise the
// persisted value.
const draftValue = (method, field) => {
    const pending = drafts.value[draftKey(method, field)]

    return pending === undefined ? method[field] : pending
}

const isFieldDirty = (method, field) => drafts.value[draftKey(method, field)] !== undefined

const hasPendingChanges = computed(() => Object.keys(drafts.value).length > 0)

// Records a stepper change without persisting it. Stepping back to the stored
// value clears the pending state, so the orange highlight disappears again.
const setDraft = (method, field, value) => {
    const key = draftKey(method, field)
    const next = { ...drafts.value }

    if (value === method[field]) {
        delete next[key]
    } else {
        next[key] = value
    }

    drafts.value = next
}

/**
 * Drops pending edits. Without arguments every draft is cleared; with a method
 * key only that method's, optionally narrowed to a single field so an unsaved
 * edit in the neighbouring column survives.
 */
const clearDrafts = (methodKey = null, field = null) => {
    if (methodKey === null) {
        drafts.value = {}

        return
    }

    const next = { ...drafts.value }
    const fields = field ? [field] : ['initial', 'recurring']

    fields.forEach(name => delete next[`${methodKey}.${name}`])

    drafts.value = next
}

// Sample due date used only to preview the resulting execution dates.
const sampleDueDate = ref(new Date().toISOString().slice(0, 10))

const customCount = computed(() => methods.value.filter(method => method.is_custom).length)

const summary = computed(() => {
    if (customCount.value === 0) {
        return 'Keine abweichenden Einstellungen — es gelten die Systemstandards.'
    }

    return customCount.value === 1
        ? '1 abweichende Einstellung'
        : `${customCount.value} abweichende Einstellungen`
})

const offsetText = (offset) => executionOffsetText(offset)

// Preview of the execution date for the chosen sample due date.
const shiftedDate = (offset) => shiftExecutionDate(sampleDueDate.value, offset)

// Small +/- stepper used for both offset columns.
const OffsetStepper = (props, { emit: emitStep }) => {
    const step = (delta) => {
        const next = Math.max(props.min, Math.min(props.max, props.modelValue + delta))
        emitStep('update:modelValue', next)
    }

    const button = (label, delta, extraClass) => h('button', {
        type: 'button',
        disabled: props.disabled,
        onClick: () => step(delta),
        class: [
            'flex-none w-8 h-9 text-base disabled:opacity-50 disabled:cursor-not-allowed transition-colors',
            props.highlight
                ? 'text-orange-600 hover:bg-orange-100 border-orange-300'
                : 'text-gray-600 hover:bg-gray-50 hover:text-indigo-600 border-gray-200',
            extraClass,
        ].join(' '),
    }, label)

    // An unsaved value is highlighted in orange until the user saves it.
    const frame = props.highlight
        ? 'border-orange-400 bg-orange-50'
        : 'border-gray-300 bg-white'

    return h('div', { class: `flex items-center rounded-md border ${frame} w-full max-w-52` }, [
        button('−', -1, 'border-r rounded-l-md'),
        h('span', {
            class: [
                'flex-1 min-w-0 text-center text-sm font-medium px-1.5 whitespace-nowrap',
                props.highlight ? 'text-orange-700' : 'text-gray-900',
            ].join(' '),
        }, offsetText(props.modelValue)),
        button('+', 1, 'border-l rounded-r-md'),
    ])
}

OffsetStepper.props = {
    modelValue: { type: Number, required: true },
    min: { type: Number, default: -14 },
    max: { type: Number, default: 30 },
    disabled: { type: Boolean, default: false },
    highlight: { type: Boolean, default: false },
}
OffsetStepper.emits = ['update:modelValue']

const applyPayload = (data) => {
    methods.value = data.methods ?? []
    inactiveMethods.value = data.inactive_methods ?? []

    if (data.limits) {
        limits.value = data.limits
    }
}

const loadSettings = async () => {
    isLoading.value = true

    try {
        const response = await axios.get(route('settings.payment-methods.execution-settings.index'))
        applyPayload(response.data)
        // Reloaded values are authoritative — any pending edit is obsolete.
        clearDrafts()
    } catch (error) {
        console.error('Fehler beim Laden der Ausführungsdaten:', error)
        toastError('Fehler beim Laden der Ausführungsdaten')
    } finally {
        isLoading.value = false
    }
}

/**
 * Persists a method's offsets; null values reset it to the system default.
 * `clearedField` limits which pending edit is dropped afterwards — omitted, the
 * method's drafts are cleared entirely (reset / toggle).
 */
const persist = async (methodKey, payload, successMessage, clearedField = null) => {
    isSaving.value = true

    try {
        const response = await axios.put(route('settings.payment-methods.execution-settings.update'), {
            method: methodKey,
            ...payload,
        })

        applyPayload(response.data)
        // The stored values are now authoritative for this method again.
        clearDrafts(methodKey, clearedField)
        success(successMessage)
        emit('updated')
    } catch (error) {
        console.error('Fehler beim Speichern der Ausführungsdaten:', error)
        toastError('Fehler beim Speichern der Ausführungsdaten')
        // Reload so the UI never shows a value that was not persisted, and drop
        // the pending edits that could not be saved.
        clearDrafts(methodKey, clearedField)
        await loadSettings()
    } finally {
        isSaving.value = false
    }
}

/**
 * Persists a single pending stepper change. Both offsets are sent so the other
 * column keeps its value — a pending edit there is preserved as well, since the
 * user explicitly saved this field only.
 */
const saveField = (method, field) => {
    if (!isFieldDirty(method, field)) {
        return
    }

    const payload = {
        initial: method.initial,
        recurring: method.recurring,
        [field]: draftValue(method, field),
    }

    const label = field === 'initial' ? 'Initiale Zahlung' : 'Wiederkehrende Zahlung'

    persist(method.key, payload, `${label}: ${method.name} gespeichert`, field)
}

const toggleCustom = (method) => {
    if (method.is_custom) {
        resetMethod(method)

        return
    }

    // Enabling an override starts from the current (default) values.
    persist(method.key, {
        initial: method.initial,
        recurring: method.recurring,
    }, `Abweichende Ausführungsdaten für ${method.name} aktiviert`)
}

const resetMethod = (method) => {
    persist(method.key, { initial: null, recurring: null }, `${method.name} auf Standard zurückgesetzt`)
}

const resetAll = async () => {
    if (!confirm('Möchten Sie wirklich alle Ausführungsdaten auf die Systemstandards zurücksetzen?')) {
        return
    }

    isSaving.value = true

    try {
        const response = await axios.delete(route('settings.payment-methods.execution-settings.reset'))

        applyPayload(response.data)
        clearDrafts()
        success('Alle Ausführungsdaten auf Standard zurückgesetzt')
        emit('updated')
    } catch (error) {
        console.error('Fehler beim Zurücksetzen der Ausführungsdaten:', error)
        toastError('Fehler beim Zurücksetzen der Ausführungsdaten')
    } finally {
        isSaving.value = false
    }
}

defineExpose({ loadSettings })

onMounted(() => {
    loadSettings()
})
</script>
