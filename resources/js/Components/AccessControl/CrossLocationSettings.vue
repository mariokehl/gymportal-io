<template>
    <div v-if="state.has_siblings" class="bg-white shadow-sm rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-1">
                Standortübergreifender Check-in
            </h3>
            <p class="text-sm text-gray-600 mb-6">
                Legt fest, welche Mitglieder an diesem Standort einchecken dürfen.
            </p>

            <!-- Zusammenspiel mit dem Vertrag -->
            <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 mb-6">
                <div class="flex items-start">
                    <Info class="w-5 h-5 text-indigo-600 mt-0.5 mr-2 flex-shrink-0" />
                    <div>
                        <p class="text-sm font-medium text-indigo-900">
                            Zusammenspiel mit dem Vertrag
                        </p>
                        <p class="text-sm text-indigo-800 mt-1">
                            Ein Fremd-Check-in gelingt nur, wenn dieser Standort die Mitglieder zulässt
                            <em>und</em> der Vertrag des Mitglieds diesen Standort erlaubt.
                            Die Vertragsregel pflegen Sie unter
                            <Link :href="route('contracts.index')" class="font-medium underline">Verträge</Link>
                            im Reiter „Standorte“.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Aktuelle Regel -->
            <div v-if="!isEditing" class="border border-gray-200 rounded-lg p-5 bg-gray-50 flex gap-6 items-start">
                <div class="flex-1 min-w-0">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">
                        Zugang zu diesem Standort
                    </div>
                    <div class="mt-2 text-lg font-semibold text-gray-900">
                        {{ currentOption.title }}
                    </div>
                    <div class="mt-1 text-sm text-gray-600">
                        {{ currentOption.desc }}
                    </div>
                    <div v-if="state.rule === 'selected'" class="flex flex-wrap gap-2 mt-3">
                        <span
                            v-for="name in allowedNames"
                            :key="name"
                            class="bg-indigo-50 text-indigo-800 rounded-full px-3 py-1 text-xs font-medium"
                        >
                            {{ name }}
                        </span>
                    </div>
                </div>
                <button
                    type="button"
                    @click="startEditing"
                    class="flex items-center gap-2 px-4 py-2 rounded-md border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    <Pencil class="w-4 h-4" />
                    Bearbeiten
                </button>
            </div>

            <!-- Bearbeiten -->
            <div v-else class="border border-gray-200 rounded-lg p-5">
                <div class="space-y-4">
                    <label
                        v-for="option in RULE_OPTIONS"
                        :key="option.value"
                        class="flex gap-3 items-start cursor-pointer"
                    >
                        <input
                            v-model="form.rule"
                            type="radio"
                            name="cross-location-rule"
                            :value="option.value"
                            class="mt-0.5 h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300"
                        />
                        <span>
                            <span class="block text-sm font-medium text-gray-700">{{ option.title }}</span>
                            <span class="block text-xs text-gray-500 mt-0.5">{{ option.desc }}</span>
                        </span>
                    </label>
                </div>

                <!-- Standortauswahl -->
                <div v-if="form.rule === 'selected'" class="mt-5 ml-7 border border-gray-200 rounded-lg overflow-hidden">
                    <div class="bg-gray-50 border-b border-gray-200 px-4 py-2.5 text-xs font-medium uppercase tracking-wide text-gray-500">
                        Mitglieder dieser Standorte zulassen
                    </div>
                    <label
                        v-for="location in siblingLocations"
                        :key="location.id"
                        class="flex items-center gap-3 px-4 py-3 border-b border-gray-100 last:border-b-0 cursor-pointer hover:bg-gray-50"
                    >
                        <input
                            v-model="form.allowed_gym_ids"
                            type="checkbox"
                            :value="location.id"
                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                        />
                        <span class="flex-1 min-w-0">
                            <span class="block text-sm font-medium text-gray-900">{{ location.name }}</span>
                            <span class="block text-xs text-gray-500">{{ locationAddress(location) }}</span>
                        </span>
                    </label>
                </div>

                <p class="mt-4 text-xs text-gray-500">
                    Standort-Codes sind nicht erforderlich. Sie wählen die Standorte direkt aus;
                    Änderungen gelten ab dem nächsten Check-in.
                </p>

                <!-- Hinweis: Gerätekonfiguration -->
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mt-4">
                    <div class="flex items-start">
                        <AlertTriangle class="w-5 h-5 text-amber-500 mt-0.5 mr-2 flex-shrink-0" />
                        <p class="text-sm text-amber-700">
                            Nach dem Speichern laden Sie bitte die Gerätekonfiguration im Reiter
                            <strong>Geräte</strong> erneut herunter. Die Scanner prüfen den QR-Code
                            selbst und benötigen dafür die Schlüssel der freigegebenen Standorte.
                        </p>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-5">
                    <button
                        type="button"
                        @click="cancelEditing"
                        class="px-4 py-2 rounded-md border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Abbrechen
                    </button>
                    <button
                        type="button"
                        @click="save"
                        :disabled="isSaving"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2 px-4 rounded-md disabled:opacity-50"
                    >
                        {{ isSaving ? 'Speichern...' : 'Speichern' }}
                    </button>
                </div>
            </div>

            <!-- Standorte der Organisation -->
            <div class="mt-8">
                <h4 class="text-sm font-medium text-gray-900 mb-1">Standorte der Organisation</h4>
                <p class="text-xs text-gray-500 mb-3">
                    Die Check-in-Regel gilt jeweils für Mitglieder, die nicht an diesem Standort angemeldet sind.
                </p>
                <div class="border border-gray-200 rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Standort</th>
                                <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Adresse</th>
                                <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Übergreifender Check-in</th>
                                <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Mitglieder</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <tr v-for="location in state.locations" :key="location.id">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <MapPin class="w-4 h-4 text-gray-400 flex-shrink-0" />
                                        <span class="text-sm font-medium text-gray-900">{{ location.name }}</span>
                                        <span
                                            v-if="location.is_current"
                                            class="bg-indigo-50 text-indigo-800 rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        >
                                            Aktuell
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ locationAddress(location) }}</td>
                                <td class="px-4 py-3">
                                    <span :class="['rounded-full px-3 py-1 text-xs font-medium whitespace-nowrap', ruleBadgeClass(location.rule)]">
                                        {{ RULE_SHORT[location.rule] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 text-right">{{ location.members_count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'
import { Info, Pencil, MapPin, AlertTriangle } from 'lucide-vue-next'

const props = defineProps({
    crossLocation: {
        type: Object,
        required: true
    }
})

const emit = defineEmits(['success', 'error'])

const RULE_OPTIONS = [
    {
        value: 'own',
        title: 'Nur Mitglieder dieses Standorts',
        desc: 'Es checken ausschließlich Mitglieder ein, die hier angemeldet wurden.'
    },
    {
        value: 'selected',
        title: 'Mitglieder ausgewählter Standorte',
        desc: 'Nur Mitglieder der unten gewählten Standorte dürfen zusätzlich einchecken.'
    },
    {
        value: 'all',
        title: 'Mitglieder aller Standorte der Organisation',
        desc: 'Jedes Mitglied der Organisation darf hier einchecken, sofern sein Vertrag es erlaubt.'
    }
]

const RULE_SHORT = {
    own: 'Nur eigene Mitglieder',
    selected: 'Ausgewählte Standorte',
    all: 'Alle Standorte'
}

const state = ref({ ...props.crossLocation })
const isEditing = ref(false)
const isSaving = ref(false)
const form = ref({ rule: 'own', allowed_gym_ids: [] })

const currentOption = computed(
    () => RULE_OPTIONS.find(o => o.value === state.value.rule) ?? RULE_OPTIONS[0]
)

const siblingLocations = computed(() => state.value.locations.filter(l => !l.is_current))

const allowedNames = computed(() =>
    state.value.locations
        .filter(l => state.value.allowed_gym_ids.includes(l.id))
        .map(l => l.name)
)

const locationAddress = (location) =>
    [location.address, location.city].filter(Boolean).join(', ') || '—'

const ruleBadgeClass = (rule) => ({
    own: 'bg-gray-100 text-gray-700',
    selected: 'bg-indigo-50 text-indigo-800',
    all: 'bg-green-100 text-green-800'
}[rule] ?? 'bg-gray-100 text-gray-700')

const startEditing = () => {
    form.value = {
        rule: state.value.rule,
        allowed_gym_ids: [...state.value.allowed_gym_ids]
    }
    isEditing.value = true
}

const cancelEditing = () => {
    isEditing.value = false
}

const save = async () => {
    isSaving.value = true

    try {
        const response = await axios.put(route('access-control.cross-location.update'), form.value)

        state.value = response.data.crossLocation
        isEditing.value = false
        emit('success', response.data.message)
    } catch (error) {
        emit('error', error.response?.data?.message ?? 'Die Einstellungen konnten nicht gespeichert werden.')
    } finally {
        isSaving.value = false
    }
}
</script>
