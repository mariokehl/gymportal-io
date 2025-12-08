<!-- PwaSettingsWidget.vue -->
<template>
    <div class="pwa-settings-widget space-y-8">
        <!-- Header mit PWA Status -->
        <div class="bg-white shadow-sm rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">{{ currentGym.name }}</h2>
                    <p class="text-gray-600">Konfiguriere die Mitglieder-App (PWA) für dein Fitnessstudio</p>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="flex items-center">
                        <div :class="[
                            'w-3 h-3 rounded-full mr-2',
                            form.pwa_enabled ? 'bg-green-500' : 'bg-gray-400'
                        ]"></div>
                        <span class="text-sm text-gray-600">
                            {{ form.pwa_enabled ? 'Aktiviert' : 'Deaktiviert' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- PWA Configuration -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center">
                    <component :is="Settings" class="w-6 h-6 text-indigo-600 mr-3" />
                    <h3 class="text-xl font-semibold text-gray-900">PWA-Einstellungen</h3>
                </div>
            </div>

            <form @submit.prevent="savePwaSettings" class="space-y-6">
                <!-- PWA aktivieren/deaktivieren -->
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div>
                        <h4 class="text-sm font-medium text-gray-900">PWA aktivieren</h4>
                        <p class="text-sm text-gray-500">Macht die Mitglieder-App als installierbare PWA verfügbar</p>
                    </div>
                    <div class="relative inline-block w-11 h-5">
                        <input v-model="form.pwa_enabled" id="pwa-switch" type="checkbox" class="peer appearance-none w-11 h-5 bg-slate-100 rounded-full checked:bg-indigo-600 cursor-pointer transition-colors duration-300" />
                        <label for="pwa-switch" class="absolute top-0 left-0 w-5 h-5 bg-white rounded-full border border-slate-300 shadow-sm transition-transform duration-300 peer-checked:translate-x-6 peer-checked:border-indigo-600 cursor-pointer"></label>
                    </div>
                </div>

                <!-- App Beschreibung -->
                <div>
                    <h4 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                        <component :is="FileText" class="w-5 h-5 mr-2" />
                        App-Beschreibung
                    </h4>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Beschreibung für die Mitglieder-App</label>
                        <textarea
                            v-model="form.member_app_description"
                            rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                            placeholder="z.B. Die offizielle Mitglieder-App für unser Fitnessstudio..."
                        ></textarea>
                        <p class="mt-1 text-xs text-gray-500">
                            Diese Beschreibung wird im App Store und bei der Installation angezeigt.
                        </p>
                    </div>
                </div>

                <!-- Farben -->
                <div>
                    <h4 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                        <component :is="Palette" class="w-5 h-5 mr-2" />
                        Farben
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Primärfarbe</label>
                            <div class="flex items-center space-x-3">
                                <input
                                    type="color"
                                    v-model="form.primary_color"
                                    class="w-12 h-10 rounded border border-gray-300 cursor-pointer"
                                >
                                <input
                                    type="text"
                                    v-model="form.primary_color"
                                    class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm"
                                    placeholder="#3b82f6"
                                >
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Sekundärfarbe</label>
                            <div class="flex items-center space-x-3">
                                <input
                                    type="color"
                                    v-model="form.secondary_color"
                                    class="w-12 h-10 rounded border border-gray-300 cursor-pointer"
                                >
                                <input
                                    type="text"
                                    v-model="form.secondary_color"
                                    class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm"
                                    placeholder="#6366f1"
                                >
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Akzentfarbe</label>
                            <div class="flex items-center space-x-3">
                                <input
                                    type="color"
                                    v-model="form.accent_color"
                                    class="w-12 h-10 rounded border border-gray-300 cursor-pointer"
                                >
                                <input
                                    type="text"
                                    v-model="form.accent_color"
                                    class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm"
                                    placeholder="#f59e0b"
                                >
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Hintergrundfarbe</label>
                            <div class="flex items-center space-x-3">
                                <input
                                    type="color"
                                    v-model="form.background_color"
                                    class="w-12 h-10 rounded border border-gray-300 cursor-pointer"
                                >
                                <input
                                    type="text"
                                    v-model="form.background_color"
                                    class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm"
                                    placeholder="#ffffff"
                                >
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Textfarbe</label>
                            <div class="flex items-center space-x-3">
                                <input
                                    type="color"
                                    v-model="form.text_color"
                                    class="w-12 h-10 rounded border border-gray-300 cursor-pointer"
                                >
                                <input
                                    type="text"
                                    v-model="form.text_color"
                                    class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm"
                                    placeholder="#1f2937"
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Logos & Icons -->
                <div>
                    <h4 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                        <component :is="Image" class="w-5 h-5 mr-2" />
                        Logos & Icons
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">PWA Logo URL</label>
                            <input
                                type="url"
                                v-model="form.pwa_logo_url"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="https://..."
                            >
                            <p class="mt-1 text-xs text-gray-500">
                                Empfohlene Größe: 512x512px (PNG)
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Favicon URL</label>
                            <input
                                type="url"
                                v-model="form.favicon_url"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="https://..."
                            >
                            <p class="mt-1 text-xs text-gray-500">
                                Empfohlene Größe: 32x32px oder 16x16px
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Öffnungszeiten -->
                <div>
                    <h4 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                        <component :is="Clock" class="w-5 h-5 mr-2" />
                        Öffnungszeiten
                    </h4>
                    <p class="text-sm text-gray-500 mb-4">
                        Die Öffnungszeiten werden in der Mitglieder-App angezeigt.
                    </p>
                    <div class="space-y-3">
                        <div v-for="(day, key) in weekdays" :key="key" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center min-w-[120px]">
                                <span class="text-sm font-medium text-gray-900">{{ day }}</span>
                            </div>
                            <div class="flex items-center space-x-3">
                                <label class="flex items-center cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="form.opening_hours[key].closed"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 mr-2"
                                    >
                                    <span class="text-sm text-gray-600">Geschlossen</span>
                                </label>
                                <template v-if="!form.opening_hours[key].closed">
                                    <input
                                        type="time"
                                        v-model="form.opening_hours[key].open"
                                        class="px-2 py-1 border border-gray-300 rounded-md text-sm"
                                    >
                                    <span class="text-gray-500">-</span>
                                    <input
                                        type="time"
                                        v-model="form.opening_hours[key].close"
                                        class="px-2 py-1 border border-gray-300 rounded-md text-sm"
                                    >
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Social Media -->
                <div>
                    <h4 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                        <component :is="Share2" class="w-5 h-5 mr-2" />
                        Social Media
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <component :is="Instagram" class="w-4 h-4 inline mr-1" />
                                Instagram
                            </label>
                            <input
                                type="url"
                                v-model="form.social_media.instagram"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="https://instagram.com/..."
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <component :is="Facebook" class="w-4 h-4 inline mr-1" />
                                Facebook
                            </label>
                            <input
                                type="url"
                                v-model="form.social_media.facebook"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="https://facebook.com/..."
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <component :is="Youtube" class="w-4 h-4 inline mr-1" />
                                YouTube
                            </label>
                            <input
                                type="url"
                                v-model="form.social_media.youtube"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="https://youtube.com/..."
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <component :is="Twitter" class="w-4 h-4 inline mr-1" />
                                X (Twitter)
                            </label>
                            <input
                                type="url"
                                v-model="form.social_media.twitter"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="https://x.com/..."
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <component :is="Linkedin" class="w-4 h-4 inline mr-1" />
                                LinkedIn
                            </label>
                            <input
                                type="url"
                                v-model="form.social_media.linkedin"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="https://linkedin.com/..."
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <component :is="Globe" class="w-4 h-4 inline mr-1" />
                                TikTok
                            </label>
                            <input
                                type="url"
                                v-model="form.social_media.tiktok"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="https://tiktok.com/..."
                            >
                        </div>
                    </div>
                </div>

                <!-- PWA-spezifische Einstellungen -->
                <div>
                    <h4 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                        <component :is="Smartphone" class="w-5 h-5 mr-2" />
                        PWA-Funktionen
                    </h4>
                    <div class="space-y-3">
                        <label class="flex items-center justify-between p-3 bg-gray-50 rounded-lg cursor-pointer">
                            <div>
                                <span class="text-sm font-medium text-gray-900">Installationsaufforderung</span>
                                <p class="text-xs text-gray-500">Zeigt eine Aufforderung zur App-Installation an</p>
                            </div>
                            <input
                                type="checkbox"
                                v-model="form.pwa_settings.install_prompt_enabled"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            >
                        </label>
                        <label class="flex items-center justify-between p-3 bg-gray-50 rounded-lg cursor-pointer">
                            <div>
                                <span class="text-sm font-medium text-gray-900">Offline-Unterstützung</span>
                                <p class="text-xs text-gray-500">Ermöglicht die Nutzung der App ohne Internetverbindung</p>
                            </div>
                            <input
                                type="checkbox"
                                v-model="form.pwa_settings.offline_support_enabled"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            >
                        </label>
                        <label class="flex items-center justify-between p-3 bg-gray-50 rounded-lg cursor-pointer">
                            <div>
                                <span class="text-sm font-medium text-gray-900">Push-Benachrichtigungen</span>
                                <p class="text-xs text-gray-500">Erlaubt das Senden von Push-Benachrichtigungen</p>
                            </div>
                            <input
                                type="checkbox"
                                v-model="form.pwa_settings.push_notifications_enabled"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            >
                        </label>
                        <label class="flex items-center justify-between p-3 bg-gray-50 rounded-lg cursor-pointer">
                            <div>
                                <span class="text-sm font-medium text-gray-900">Hintergrund-Synchronisation</span>
                                <p class="text-xs text-gray-500">Synchronisiert Daten im Hintergrund</p>
                            </div>
                            <input
                                type="checkbox"
                                v-model="form.pwa_settings.background_sync_enabled"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            >
                        </label>
                    </div>
                </div>

                <!-- Custom CSS -->
                <div>
                    <h4 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                        <component :is="Code" class="w-5 h-5 mr-2" />
                        Benutzerdefiniertes CSS
                    </h4>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Custom CSS</label>
                        <textarea
                            v-model="form.custom_css"
                            rows="6"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 font-mono text-sm"
                            placeholder="/* Eigene CSS-Regeln hier einfügen */&#10;.custom-class {&#10;  color: #333;&#10;}"
                        ></textarea>
                        <p class="mt-1 text-xs text-gray-500">
                            Füge benutzerdefinierte CSS-Regeln hinzu, um das Aussehen der App anzupassen.
                        </p>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="pt-4 border-t border-gray-200 space-y-3">
                    <button
                        type="button"
                        @click="openPwaPreview"
                        class="w-full flex items-center justify-center px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors"
                    >
                        <component :is="ExternalLink" class="w-4 h-4 mr-2" />
                        Vorschau anzeigen
                    </button>
                    <button
                        type="submit"
                        :disabled="isSaving"
                        class="w-full flex items-center justify-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <component :is="Save" class="w-4 h-4 mr-2" />
                        {{ isSaving ? 'Speichern...' : 'Einstellungen speichern' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import {
    Settings, Palette, FileText, Image, Clock, Share2,
    Smartphone, Code, Save, ExternalLink, Globe,
    Instagram, Facebook, Youtube, Twitter, Linkedin
} from 'lucide-vue-next'

// Props
const props = defineProps({
    currentGym: Object
})

// Emits
const emit = defineEmits(['success', 'error'])

// Reactive data
const isSaving = ref(false)

const weekdays = {
    monday: 'Montag',
    tuesday: 'Dienstag',
    wednesday: 'Mittwoch',
    thursday: 'Donnerstag',
    friday: 'Freitag',
    saturday: 'Samstag',
    sunday: 'Sonntag'
}

const getDefaultOpeningHours = () => {
    const defaults = {}
    Object.keys(weekdays).forEach(day => {
        defaults[day] = {
            open: '06:00',
            close: '22:00',
            closed: day === 'sunday'
        }
    })
    return defaults
}

const getDefaultPwaSettings = () => ({
    install_prompt_enabled: true,
    offline_support_enabled: true,
    push_notifications_enabled: false,
    background_sync_enabled: true,
    cache_strategy: 'network_first',
    cache_duration_hours: 24
})

const getDefaultSocialMedia = () => ({
    instagram: '',
    facebook: '',
    youtube: '',
    twitter: '',
    linkedin: '',
    tiktok: ''
})

const form = ref({
    pwa_enabled: props.currentGym?.pwa_enabled || false,
    primary_color: props.currentGym?.primary_color || '#3b82f6',
    secondary_color: props.currentGym?.secondary_color || '#6366f1',
    accent_color: props.currentGym?.accent_color || '#f59e0b',
    background_color: props.currentGym?.background_color || '#ffffff',
    text_color: props.currentGym?.text_color || '#1f2937',
    pwa_logo_url: props.currentGym?.pwa_logo_url || '',
    favicon_url: props.currentGym?.favicon_url || '',
    custom_css: props.currentGym?.custom_css || '',
    member_app_description: props.currentGym?.member_app_description || '',
    opening_hours: props.currentGym?.opening_hours || getDefaultOpeningHours(),
    social_media: { ...getDefaultSocialMedia(), ...(props.currentGym?.social_media || {}) },
    pwa_settings: { ...getDefaultPwaSettings(), ...(props.currentGym?.pwa_settings || {}) }
})

// Methods
const savePwaSettings = async () => {
    isSaving.value = true
    try {
        await axios.put(route('settings.pwa.update', props.currentGym.id), form.value)
        emit('success', 'PWA-Einstellungen erfolgreich gespeichert!')
    } catch (error) {
        console.error('Fehler beim Speichern:', error)
        emit('error', 'Fehler beim Speichern der PWA-Einstellungen')
    } finally {
        isSaving.value = false
    }
}

const openPwaPreview = () => {
    const previewUrl = `https://members.gymportal.io/${props.currentGym.slug}`
    window.open(previewUrl, '_blank', 'width=400,height=800,scrollbars=yes,resizable=yes')
}

// Initialize opening hours if not set
onMounted(() => {
    if (!form.value.opening_hours || Object.keys(form.value.opening_hours).length === 0) {
        form.value.opening_hours = getDefaultOpeningHours()
    } else {
        // Ensure all days exist
        Object.keys(weekdays).forEach(day => {
            if (!form.value.opening_hours[day]) {
                form.value.opening_hours[day] = {
                    open: '06:00',
                    close: '22:00',
                    closed: day === 'sunday'
                }
            }
        })
    }
})
</script>

<style scoped>
/* Toggle Switch Styles */
.peer:checked + div {
    background-color: #2563eb;
}

.peer:checked + div:after {
    transform: translateX(100%);
    border-color: white;
}
</style>
