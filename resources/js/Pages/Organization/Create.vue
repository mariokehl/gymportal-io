<template>
    <AppLayout title="Neue Organisation erstellen">
        <template #header>
            <div class="flex items-center">
                <button type="button" @click="goBack" aria-label="Zurück" class="touch-target relative mr-4 p-2 hover:bg-fill rounded-lg transition-colors">
                    <component :is="ArrowLeft" class="w-5 h-5 text-ink-secondary" />
                </button>
                Neue Organisation erstellen
            </div>
        </template>

        <div class="max-w-4xl mx-auto">
            <div class="bg-surface rounded-lg shadow-sm border border-line">
                <div class="p-6 border-b border-line">
                    <h2 class="text-lg font-semibold text-ink">Gym-Informationen</h2>
                    <p class="text-sm text-ink-secondary mt-1">
                        Jede Organisation verfügt über eine eigene Mitgliederverwaltung, getrennte Abrechnung sowie
                        individuelle Einstellungen.
                        Ideal für die Verwaltung mehrerer Studios oder Franchise-Standorte innerhalb eines Systems.
                    </p>
                </div>

                <form @submit.prevent="createGym" class="p-6 space-y-6">
                    <!-- Gym Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-ink-secondary mb-2">
                            Name <span class="text-danger">*</span>
                        </label>
                        <input id="name" v-model="form.name" type="text" required
                            class="field w-full px-3 py-2"
                            :class="{ 'border-danger': errors.name }" placeholder="z.B. FitnessPark München" />
                        <p v-if="errors.name" class="mt-1 text-sm text-danger">{{ errors.name }}</p>
                    </div>

                    <!-- Description -->
                    <div>
                        <label for="description" class="block text-sm font-medium text-ink-secondary mb-2">
                            Beschreibung
                        </label>
                        <textarea id="description" v-model="form.description" rows="3"
                            class="field w-full px-3 py-2"
                            :class="{ 'border-danger': errors.description }"
                            placeholder="Kurze Beschreibung Ihres Fitnessstudios..."></textarea>
                        <p v-if="errors.description" class="mt-1 text-sm text-danger">{{ errors.description }}</p>
                    </div>

                    <!-- Address Section -->
                    <div class="border-t border-line pt-6">
                        <h3 class="text-base font-semibold text-ink mb-4">Adresse</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Street -->
                            <div class="md:col-span-2">
                                <label for="address" class="block text-sm font-medium text-ink-secondary mb-2">
                                    Straße und Hausnummer <span class="text-danger">*</span>
                                </label>
                                <input id="address" v-model="form.address" type="text" required
                                    class="field w-full px-3 py-2"
                                    :class="{ 'border-danger': errors.address }" placeholder="Musterstraße 1" />
                                <p v-if="errors.address" class="mt-1 text-sm text-danger">{{ errors.address }}</p>
                            </div>

                            <!-- ZIP Code -->
                            <div>
                                <label for="postal_code" class="block text-sm font-medium text-ink-secondary mb-2">
                                    Postleitzahl <span class="text-danger">*</span>
                                </label>
                                <input id="postal_code" v-model="form.postal_code" type="text" required
                                    class="field w-full px-3 py-2"
                                    :class="{ 'border-danger': errors.postal_code }" placeholder="80331" />
                                <p v-if="errors.postal_code" class="mt-1 text-sm text-danger">{{ errors.postal_code }}
                                </p>
                            </div>

                            <!-- City -->
                            <div>
                                <label for="city" class="block text-sm font-medium text-ink-secondary mb-2">
                                    Stadt <span class="text-danger">*</span>
                                </label>
                                <input id="city" v-model="form.city" type="text" required
                                    class="field w-full px-3 py-2"
                                    :class="{ 'border-danger': errors.city }" placeholder="München" />
                                <p v-if="errors.city" class="mt-1 text-sm text-danger">{{ errors.city }}</p>
                            </div>

                            <!-- Country -->
                            <div>
                                <label for="create-form-country" class="block text-sm font-medium text-ink-secondary mb-2">
                                    Land <span class="text-danger">*</span>
                                </label>
                                <select id="create-form-country" v-model="form.country" class="field w-full p-2">
                                    <option value="DE">Deutschland</option>
                                    <option value="AT">Österreich</option>
                                    <option value="CH">Schweiz</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="border-t border-line pt-6">
                        <h3 class="text-base font-semibold text-ink mb-4">Kontaktinformationen</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Phone -->
                            <div>
                                <label for="phone" class="block text-sm font-medium text-ink-secondary mb-2">
                                    Telefon <span class="text-danger">*</span>
                                </label>
                                <input id="phone" v-model="form.phone" type="tel" required
                                    class="field w-full px-3 py-2"
                                    :class="{ 'border-danger': errors.phone }" placeholder="+49 89 12345678" />
                                <p v-if="errors.phone" class="mt-1 text-sm text-danger">{{ errors.phone }}</p>
                            </div>

                            <!-- Email -->
                            <div>
                                <label for="email" class="block text-sm font-medium text-ink-secondary mb-2">
                                    E-Mail <span class="text-danger">*</span>
                                </label>
                                <input id="email" v-model="form.email" type="email" required
                                    class="field w-full px-3 py-2"
                                    :class="{ 'border-danger': errors.email }"
                                    placeholder="info@fitnesspark-muenchen.de" />
                                <p v-if="errors.email" class="mt-1 text-sm text-danger">{{ errors.email }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Website -->
                    <div>
                        <label for="website" class="block text-sm font-medium text-ink-secondary mb-2">
                            Website
                        </label>
                        <input id="website" v-model="form.website" type="url"
                            class="field w-full px-3 py-2"
                            :class="{ 'border-danger': errors.website }"
                            placeholder="https://www.fitnesspark-muenchen.de" />
                        <p v-if="errors.website" class="mt-1 text-sm text-danger">{{ errors.website }}</p>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-end space-x-3 pt-6 border-t border-line">
                        <button type="button" @click="goBack"
                            class="px-4 py-2 text-sm font-medium text-ink-secondary bg-surface border border-line-strong rounded-lg hover:bg-surface-muted transition-colors">
                            Abbrechen
                        </button>
                        <button type="submit" :disabled="processing"
                            class="px-4 py-2 text-sm font-medium text-white bg-brand border border-transparent rounded-lg hover:bg-brand-strong disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center">
                            <component :is="processing ? Loader2 : Building"
                                :class="['w-4 h-4 mr-2', processing ? 'animate-spin' : '']" />
                            {{ processing ? 'Wird erstellt...' : 'Organisation erstellen' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Info Box -->
            <div class="mt-6 bg-brand-subtle border border-brand-line rounded-lg p-4">
                <div class="flex">
                    <component :is="Info" class="w-5 h-5 text-brand mr-3 mt-0.5 flex-shrink-0" />
                    <div>
                        <h4 class="text-sm font-medium text-brand-strong">Information</h4>
                        <p class="text-sm text-brand-strong mt-1">
                            Nach der Erstellung werden Sie automatisch als Administrator der neuen Organisation
                            hinzugefügt.
                            Sie können anschließend weitere Mitarbeiter einladen und die Einstellungen anpassen.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import { ArrowLeft, Building, Info, Loader2 } from '@lucide/vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppShell from '@/Layouts/AppShell.vue'

defineOptions({ layout: AppShell })

// Form data
const form = reactive({
    name: '',
    description: '',
    address: '',
    postal_code: '',
    city: '',
    country: 'DE',
    phone: '',
    email: '',
    website: ''
})

// State
const processing = ref(false)
const errors = ref({})

// Methods
const createGym = async () => {
    processing.value = true
    errors.value = {}

    try {
        await router.post('/gyms', form, {
            onSuccess: () => {
                // Redirect to dashboard or gym settings after successful creation
                router.visit('/dashboard')
            },
            onError: (formErrors) => {
                errors.value = formErrors
            },
            onFinish: () => {
                processing.value = false
            }
        })
    } catch (error) {
        console.error('Error creating gym:', error)
        processing.value = false
    }
}

const goBack = () => {
    if (confirm('Möchten Sie wirklich zurückgehen? Alle eingegebenen Daten gehen verloren.')) {
        history.back()
    }
}
</script>
