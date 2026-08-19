<template>
    <div class="space-y-6">
        <div class="bg-white shadow-sm rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                    Check-in-Aufsteller
                </h3>
                <p class="text-sm text-gray-600 mb-6">
                    Für Studios ohne Scanner-Hardware: Ein ausgedruckter QR-Code am Tresen, den Mitglieder
                    mit dem eigenen Smartphone scannen. Ein Code genügt für Check-in und Check-out – die
                    App erkennt selbst, welche Aktion ansteht.
                </p>

                <!-- Was dieses Verfahren nicht leisten kann -->
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start">
                        <AlertTriangle class="w-5 h-5 text-amber-500 mt-0.5 mr-2 flex-shrink-0" />
                        <div>
                            <p class="text-sm font-medium text-amber-800">
                                Kein Nachweis der Anwesenheit
                            </p>
                            <p class="text-sm text-amber-700 mt-1">
                                Ein gedruckter Code lässt sich abfotografieren. Wer das Foto besitzt, kann sich
                                auch von zuhause einchecken. Der Aufsteller eignet sich daher zur
                                Anwesenheits&shy;erfassung, nicht als Zutrittskontrolle.
                            </p>
                        </div>
                    </div>
                </div>

                <form @submit.prevent="saveSettings" class="space-y-6">
                    <!-- Aktivierung -->
                    <div class="flex items-start">
                        <div class="flex items-center h-7">
                            <input
                                id="checkin_station_enabled"
                                v-model="form.checkin_station_enabled"
                                type="checkbox"
                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                            />
                        </div>
                        <div class="ml-3">
                            <label for="checkin_station_enabled" class="text-sm font-medium text-gray-700">
                                Check-in per Aufsteller aktivieren
                            </label>
                            <p class="text-xs text-gray-500 mt-1">
                                Beim ersten Aktivieren wird automatisch ein Code erzeugt.
                            </p>
                            <p v-if="willDisable" class="text-xs text-red-600 mt-1">
                                Beim Speichern wird der Code gelöscht. Bereits gedruckte Aufsteller
                                funktionieren danach nicht mehr – auch nicht nach erneutem Aktivieren.
                            </p>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            :disabled="isSaving"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-md disabled:opacity-50"
                        >
                            {{ isSaving ? 'Speichern...' : 'Speichern' }}
                        </button>
                    </div>
                </form>

                <!-- Der Link, aus dem der Aufsteller-Code erzeugt wird -->
                <div v-if="checkinStation.enabled && checkinStation.scan_url" class="mt-8 border-t border-gray-200 pt-6">
                    <h4 class="text-sm font-medium text-gray-900 mb-1">
                        Link für den Aufsteller
                    </h4>
                    <p class="text-xs text-gray-500 mb-3">
                        Diesen Link als QR-Code drucken und am Tresen aufstellen. Er enthält den Zugangscode
                        des Studios – bitte nicht öffentlich teilen.
                    </p>

                    <div class="flex items-center gap-2">
                        <input
                            :value="checkinStation.scan_url"
                            type="text"
                            readonly
                            class="block w-full rounded-md bg-gray-50 px-3 py-1.5 font-mono text-xs text-gray-700 outline-1 -outline-offset-1 outline-gray-300"
                        />
                        <button
                            type="button"
                            @click="copyUrl"
                            class="whitespace-nowrap rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            {{ copied ? 'Kopiert' : 'Kopieren' }}
                        </button>
                    </div>

                    <button
                        type="button"
                        @click="regenerate"
                        :disabled="isRegenerating"
                        class="mt-4 text-sm font-medium text-red-600 hover:text-red-700 disabled:opacity-50"
                    >
                        {{ isRegenerating ? 'Wird erneuert...' : 'Code erneuern' }}
                    </button>
                    <p class="mt-1 text-xs text-gray-500">
                        Erneuern macht alle bereits gedruckten Aufsteller ungültig – nötig, wenn der Code
                        nach außen gelangt ist.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { AlertTriangle } from 'lucide-vue-next'

const props = defineProps({
    checkinStation: {
        type: Object,
        default: () => ({
            enabled: false,
            has_token: false,
            scan_url: null,
        })
    }
})

const emit = defineEmits(['success', 'error'])

// Local copy so the card keeps showing the freshly minted URL after saving,
// without a full page reload.
const checkinStation = ref({ ...props.checkinStation })

const form = ref({
    checkin_station_enabled: props.checkinStation.enabled,
})

const isSaving = ref(false)
const isRegenerating = ref(false)
const copied = ref(false)

// True while the operator has unticked a station that still holds a code —
// the one save that destroys something, so it is called out before it happens.
const willDisable = computed(
    () => checkinStation.value.enabled && ! form.value.checkin_station_enabled
)

const saveSettings = async () => {
    if (willDisable.value && ! window.confirm('Check-in-Aufsteller deaktivieren? Der Code wird gelöscht und alle gedruckten Aufsteller funktionieren danach nicht mehr.')) {
        return
    }

    isSaving.value = true
    try {
        const { data } = await axios.put(route('access-control.checkin-station.update'), form.value)
        checkinStation.value = data.checkin_station
        emit('success', data.message)
    } catch (error) {
        emit('error', error.response?.data?.message || 'Fehler beim Speichern der Einstellungen.')
    } finally {
        isSaving.value = false
    }
}

const regenerate = async () => {
    if (! window.confirm('Neuen Code erzeugen? Alle bereits gedruckten Aufsteller funktionieren danach nicht mehr.')) {
        return
    }

    isRegenerating.value = true
    try {
        const { data } = await axios.post(route('access-control.checkin-station.regenerate'))
        checkinStation.value = data.checkin_station
        emit('success', data.message)
    } catch (error) {
        emit('error', error.response?.data?.message || 'Fehler beim Erneuern des Codes.')
    } finally {
        isRegenerating.value = false
    }
}

const copyUrl = async () => {
    try {
        await navigator.clipboard.writeText(checkinStation.value.scan_url)
        copied.value = true
        setTimeout(() => (copied.value = false), 2000)
    } catch {
        emit('error', 'Der Link konnte nicht kopiert werden. Bitte manuell markieren und kopieren.')
    }
}
</script>
