<!-- PwaSettingsWidget.vue -->
<template>
    <div class="pwa-settings-widget space-y-8">
        <!-- Header mit PWA Status -->
        <div class="bg-surface shadow-sm rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-ink tabular-nums">{{ currentGym.name }}</h2>
                    <p class="text-ink-secondary">Konfiguriere die Mitglieder-App (PWA) für dein Fitnessstudio</p>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="flex items-center">
                        <div :class="[
                            'w-3 h-3 rounded-full mr-2',
                            form.pwa_enabled ? 'bg-success-solid' : 'bg-gray-400'
                        ]"></div>
                        <span class="text-sm text-ink-secondary">
                            {{ form.pwa_enabled ? 'Aktiviert' : 'Deaktiviert' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- PWA Configuration -->
        <div class="bg-surface rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center">
                    <component :is="Settings" class="w-6 h-6 text-brand-ink mr-3" />
                    <h3 class="text-xl font-semibold text-ink">PWA-Einstellungen</h3>
                </div>
            </div>

            <form @submit.prevent="savePwaSettings" class="space-y-6">
                <!-- PWA aktivieren/deaktivieren -->
                <div class="flex items-center justify-between p-4 bg-surface-muted rounded-lg">
                    <div>
                        <h4 class="text-sm font-medium text-ink">PWA aktivieren</h4>
                        <p class="text-sm text-ink-muted">Macht die Mitglieder-App als installierbare PWA verfügbar</p>
                    </div>
                    <div class="relative inline-block w-11 h-5">
                        <input v-model="form.pwa_enabled" id="pwa-switch" type="checkbox" role="switch" aria-label="Mitglieder-App aktivieren" class="peer appearance-none w-11 h-5 bg-slate-100 rounded-full checked:bg-brand cursor-pointer transition-colors duration-300" />
                        <label for="pwa-switch" class="absolute top-0 left-0 w-5 h-5 bg-surface rounded-full border border-slate-300 shadow-sm transition-transform duration-300 peer-checked:translate-x-6 peer-checked:border-brand cursor-pointer"></label>
                    </div>
                </div>

                <!-- App Beschreibung -->
                <div>
                    <h4 class="text-lg font-semibold text-ink mb-4 flex items-center">
                        <component :is="FileText" class="w-5 h-5 mr-2" />
                        App-Beschreibung
                    </h4>
                    <div>
                        <label for="pwa-settings-widget-form-member-app-description" class="block text-sm font-medium text-ink-secondary mb-2">Beschreibung für die Mitglieder-App</label>
                        <textarea id="pwa-settings-widget-form-member-app-description"
                            v-model="form.member_app_description"
                            rows="3"
                            class="field w-full px-3 py-2"
                            placeholder="z.B. Die offizielle Mitglieder-App für unser Fitnessstudio..."
                        ></textarea>
                        <p class="mt-1 text-xs text-ink-muted">
                            Diese Beschreibung wird im App Store und bei der Installation angezeigt.
                        </p>
                    </div>
                </div>

                <!-- Farben -->
                <div>
                    <h4 class="text-lg font-semibold text-ink mb-4 flex items-center">
                        <component :is="Palette" class="w-5 h-5 mr-2" />
                        Farben
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="pwa-settings-widget-form-primary-color" class="block text-sm font-medium text-ink-secondary mb-2">Primärfarbe</label>
                            <div class="flex items-center space-x-3">
                                <input id="pwa-settings-widget-form-primary-color"
                                    type="color"
                                    v-model="form.primary_color"
                                    aria-label="Primärfarbe wählen"
                                    class="w-12 h-10 rounded border border-line-strong cursor-pointer"
                                >
                                <input
                                    type="text"
                                    v-model="form.primary_color"
                                    class="field flex-1 px-3 py-2"
                                    placeholder="#3b82f6"
                                >
                            </div>
                        </div>
                        <div>
                            <label for="pwa-settings-widget-form-secondary-color" class="block text-sm font-medium text-ink-secondary mb-2">Sekundärfarbe</label>
                            <div class="flex items-center space-x-3">
                                <input id="pwa-settings-widget-form-secondary-color"
                                    type="color"
                                    v-model="form.secondary_color"
                                    aria-label="Sekundärfarbe wählen"
                                    class="w-12 h-10 rounded border border-line-strong cursor-pointer"
                                >
                                <input
                                    type="text"
                                    v-model="form.secondary_color"
                                    class="field flex-1 px-3 py-2"
                                    placeholder="#6366f1"
                                >
                            </div>
                        </div>
                        <div>
                            <label for="pwa-settings-widget-form-accent-color" class="block text-sm font-medium text-ink-secondary mb-2">Akzentfarbe</label>
                            <div class="flex items-center space-x-3">
                                <input id="pwa-settings-widget-form-accent-color"
                                    type="color"
                                    v-model="form.accent_color"
                                    aria-label="Akzentfarbe wählen"
                                    class="w-12 h-10 rounded border border-line-strong cursor-pointer"
                                >
                                <input
                                    type="text"
                                    v-model="form.accent_color"
                                    class="field flex-1 px-3 py-2"
                                    placeholder="#f59e0b"
                                >
                            </div>
                        </div>
                        <div>
                            <label for="pwa-settings-widget-form-background-color" class="block text-sm font-medium text-ink-secondary mb-2">Hintergrundfarbe</label>
                            <div class="flex items-center space-x-3">
                                <input id="pwa-settings-widget-form-background-color"
                                    type="color"
                                    v-model="form.background_color"
                                    aria-label="Hintergrundfarbe wählen"
                                    class="w-12 h-10 rounded border border-line-strong cursor-pointer"
                                >
                                <input
                                    type="text"
                                    v-model="form.background_color"
                                    class="field flex-1 px-3 py-2"
                                    placeholder="#ffffff"
                                >
                            </div>
                        </div>
                        <div>
                            <label for="pwa-settings-widget-form-text-color" class="block text-sm font-medium text-ink-secondary mb-2">Textfarbe</label>
                            <div class="flex items-center space-x-3">
                                <input id="pwa-settings-widget-form-text-color"
                                    type="color"
                                    v-model="form.text_color"
                                    aria-label="Textfarbe wählen"
                                    class="w-12 h-10 rounded border border-line-strong cursor-pointer"
                                >
                                <input
                                    type="text"
                                    v-model="form.text_color"
                                    class="field flex-1 px-3 py-2"
                                    placeholder="#1f2937"
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Logos & Icons -->
                <div>
                    <h4 class="text-lg font-semibold text-ink mb-4 flex items-center">
                        <component :is="Image" class="w-5 h-5 mr-2" />
                        Logos & Icons
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="pwa-settings-widget-form-pwa-logo-url" class="block text-sm font-medium text-ink-secondary mb-2">PWA Logo URL</label>
                            <input id="pwa-settings-widget-form-pwa-logo-url"
                                type="url"
                                v-model="form.pwa_logo_url"
                                class="field w-full px-3 py-2"
                                placeholder="https://..."
                            >
                            <p class="mt-1 text-xs text-ink-muted">
                                Empfohlene Größe: 512x512px (PNG)
                            </p>
                        </div>
                        <div>
                            <label for="pwa-settings-widget-form-favicon-url" class="block text-sm font-medium text-ink-secondary mb-2">Favicon URL</label>
                            <input id="pwa-settings-widget-form-favicon-url"
                                type="url"
                                v-model="form.favicon_url"
                                class="field w-full px-3 py-2"
                                placeholder="https://..."
                            >
                            <p class="mt-1 text-xs text-ink-muted">
                                Empfohlene Größe: 32x32px oder 16x16px
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Öffnungszeiten -->
                <div>
                    <h4 class="text-lg font-semibold text-ink mb-4 flex items-center">
                        <component :is="Clock" class="w-5 h-5 mr-2" />
                        Öffnungszeiten
                    </h4>
                    <p class="text-sm text-ink-muted mb-4">
                        Die Öffnungszeiten werden in der Mitglieder-App angezeigt.
                    </p>
                    <div class="space-y-3">
                        <div v-for="(day, key) in weekdays" :key="key" class="flex items-center justify-between p-3 bg-surface-muted rounded-lg">
                            <div class="flex items-center min-w-[120px]">
                                <span class="text-sm font-medium text-ink">{{ day }}</span>
                            </div>
                            <div class="flex items-center space-x-3">
                                <label class="flex items-center cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="form.opening_hours[key].closed"
                                        class="rounded border-line-strong text-brand-ink mr-2"
                                    >
                                    <span class="text-sm text-ink-secondary">Geschlossen</span>
                                </label>
                                <template v-if="!form.opening_hours[key].closed">
                                    <input
                                        type="time"
                                        v-model="form.opening_hours[key].open"
                                        :aria-label="`${day} geöffnet ab`"
                                        class="field px-2 py-1"
                                    >
                                    <span class="text-ink-muted">-</span>
                                    <input
                                        type="time"
                                        v-model="form.opening_hours[key].close"
                                        :aria-label="`${day} geöffnet bis`"
                                        class="field px-2 py-1"
                                    >
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Social Media -->
                <div>
                    <h4 class="text-lg font-semibold text-ink mb-4 flex items-center">
                        <component :is="Share2" class="w-5 h-5 mr-2" />
                        Social Media
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="pwa-settings-widget-form-social-media-instagram" class="block text-sm font-medium text-ink-secondary mb-2">
                                <component :is="Instagram" class="w-4 h-4 inline mr-1" />
                                Instagram
                            </label>
                            <input id="pwa-settings-widget-form-social-media-instagram"
                                type="url"
                                v-model="form.social_media.instagram"
                                class="field w-full px-3 py-2"
                                placeholder="https://instagram.com/..."
                            >
                        </div>
                        <div>
                            <label for="pwa-settings-widget-form-social-media-facebook" class="block text-sm font-medium text-ink-secondary mb-2">
                                <component :is="Facebook" class="w-4 h-4 inline mr-1" />
                                Facebook
                            </label>
                            <input id="pwa-settings-widget-form-social-media-facebook"
                                type="url"
                                v-model="form.social_media.facebook"
                                class="field w-full px-3 py-2"
                                placeholder="https://facebook.com/..."
                            >
                        </div>
                        <div>
                            <label for="pwa-settings-widget-form-social-media-youtube" class="block text-sm font-medium text-ink-secondary mb-2">
                                <component :is="Youtube" class="w-4 h-4 inline mr-1" />
                                YouTube
                            </label>
                            <input id="pwa-settings-widget-form-social-media-youtube"
                                type="url"
                                v-model="form.social_media.youtube"
                                class="field w-full px-3 py-2"
                                placeholder="https://youtube.com/..."
                            >
                        </div>
                        <div>
                            <label for="pwa-settings-widget-form-social-media-twitter" class="block text-sm font-medium text-ink-secondary mb-2">
                                <component :is="Twitter" class="w-4 h-4 inline mr-1" />
                                X (Twitter)
                            </label>
                            <input id="pwa-settings-widget-form-social-media-twitter"
                                type="url"
                                v-model="form.social_media.twitter"
                                class="field w-full px-3 py-2"
                                placeholder="https://x.com/..."
                            >
                        </div>
                        <div>
                            <label for="pwa-settings-widget-form-social-media-linkedin" class="block text-sm font-medium text-ink-secondary mb-2">
                                <component :is="Linkedin" class="w-4 h-4 inline mr-1" />
                                LinkedIn
                            </label>
                            <input id="pwa-settings-widget-form-social-media-linkedin"
                                type="url"
                                v-model="form.social_media.linkedin"
                                class="field w-full px-3 py-2"
                                placeholder="https://linkedin.com/..."
                            >
                        </div>
                        <div>
                            <label for="pwa-settings-widget-form-social-media-tiktok" class="block text-sm font-medium text-ink-secondary mb-2">
                                <component :is="Globe" class="w-4 h-4 inline mr-1" />
                                TikTok
                            </label>
                            <input id="pwa-settings-widget-form-social-media-tiktok"
                                type="url"
                                v-model="form.social_media.tiktok"
                                class="field w-full px-3 py-2"
                                placeholder="https://tiktok.com/..."
                            >
                        </div>
                    </div>
                </div>

                <!-- PWA-Login & App Store -->
                <div>
                    <h4 class="text-lg font-semibold text-ink mb-4 flex items-center">
                        <component :is="ShieldOff" class="w-5 h-5 mr-2" />
                        Login & App Store
                    </h4>

                    <!-- PWA Login sperren -->
                    <div class="flex items-center justify-between p-4 bg-surface-muted rounded-lg mb-4">
                        <div>
                            <h4 class="text-sm font-medium text-ink">PWA-Login sperren</h4>
                            <p class="text-sm text-ink-muted">
                                Sperrt den Login über die PWA. Der Login über die native App bleibt unberührt.
                            </p>
                        </div>
                        <div class="relative inline-block w-11 h-5">
                            <input v-model="form.pwa_settings.pwa_login_disabled" role="switch" aria-label="Login in der App deaktivieren" id="pwa-login-switch" type="checkbox" class="peer appearance-none w-11 h-5 bg-slate-100 rounded-full checked:bg-rose-600 cursor-pointer transition-colors duration-300" />
                            <label for="pwa-login-switch" class="absolute top-0 left-0 w-5 h-5 bg-surface rounded-full border border-slate-300 shadow-sm transition-transform duration-300 peer-checked:translate-x-6 peer-checked:border-rose-600 cursor-pointer"></label>
                        </div>
                    </div>

                    <!-- App Store Links (nur sichtbar wenn Login gesperrt) -->
                    <div v-if="form.pwa_settings.pwa_login_disabled" class="space-y-4 p-4 bg-warning-subtle border border-warning-line rounded-lg">
                        <div class="flex items-start space-x-2">
                            <component :is="Store" class="w-5 h-5 text-warning mt-0.5 flex-shrink-0" />
                            <div>
                                <p class="text-sm font-medium text-warning-strong">App Store Verlinkung</p>
                                <p class="text-xs text-warning">
                                    Hinterlege Links zu deiner nativen App. Statt dem Login werden die entsprechenden Store-Badges angezeigt, damit Mitglieder die App herunterladen können.
                                </p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="pwa-settings-widget-form-pwa-settings-app-store-url-ios" class="block text-sm font-medium text-ink-secondary mb-2">
                                    <component :is="Apple" class="w-4 h-4 inline mr-1" />
                                    Apple App Store
                                </label>
                                <input id="pwa-settings-widget-form-pwa-settings-app-store-url-ios"
                                    type="url"
                                    v-model="form.pwa_settings.app_store_url_ios"
                                    class="field w-full px-3 py-2"
                                    placeholder="https://apps.apple.com/app/..."
                                >
                                <p class="mt-1 text-xs text-ink-muted">Link zur App im Apple App Store</p>
                            </div>
                            <div>
                                <label for="pwa-settings-widget-form-pwa-settings-app-store-url-android" class="block text-sm font-medium text-ink-secondary mb-2">
                                    <component :is="Play" class="w-4 h-4 inline mr-1" />
                                    Google Play Store
                                </label>
                                <input id="pwa-settings-widget-form-pwa-settings-app-store-url-android"
                                    type="url"
                                    v-model="form.pwa_settings.app_store_url_android"
                                    class="field w-full px-3 py-2"
                                    placeholder="https://play.google.com/store/apps/..."
                                >
                                <p class="mt-1 text-xs text-ink-muted">Link zur App im Google Play Store</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PWA-spezifische Einstellungen -->
                <div>
                    <h4 class="text-lg font-semibold text-ink mb-4 flex items-center">
                        <component :is="Smartphone" class="w-5 h-5 mr-2" />
                        PWA-Funktionen
                    </h4>
                    <div class="space-y-3">
                        <label class="flex items-center justify-between p-3 bg-surface-muted rounded-lg cursor-pointer">
                            <div>
                                <span class="text-sm font-medium text-ink">Installationsaufforderung</span>
                                <p class="text-xs text-ink-muted">Zeigt eine Aufforderung zur App-Installation an</p>
                            </div>
                            <input
                                type="checkbox"
                                v-model="form.pwa_settings.install_prompt_enabled"
                                class="rounded border-line-strong text-brand-ink"
                            >
                        </label>
                        <label class="flex items-center justify-between p-3 bg-surface-muted rounded-lg cursor-pointer">
                            <div>
                                <span class="text-sm font-medium text-ink">Offline-Unterstützung</span>
                                <p class="text-xs text-ink-muted">Ermöglicht die Nutzung der App ohne Internetverbindung</p>
                            </div>
                            <input
                                type="checkbox"
                                v-model="form.pwa_settings.offline_support_enabled"
                                class="rounded border-line-strong text-brand-ink"
                            >
                        </label>
                        <label class="flex items-center justify-between p-3 bg-surface-muted rounded-lg cursor-pointer">
                            <div>
                                <span class="text-sm font-medium text-ink">Push-Benachrichtigungen</span>
                                <p class="text-xs text-ink-muted">Erlaubt das Senden von Push-Benachrichtigungen</p>
                            </div>
                            <input
                                type="checkbox"
                                v-model="form.pwa_settings.push_notifications_enabled"
                                class="rounded border-line-strong text-brand-ink"
                            >
                        </label>
                        <label class="flex items-center justify-between p-3 bg-surface-muted rounded-lg cursor-pointer">
                            <div>
                                <span class="text-sm font-medium text-ink">Hintergrund-Synchronisation</span>
                                <p class="text-xs text-ink-muted">Synchronisiert Daten im Hintergrund</p>
                            </div>
                            <input
                                type="checkbox"
                                v-model="form.pwa_settings.background_sync_enabled"
                                class="rounded border-line-strong text-brand-ink"
                            >
                        </label>
                    </div>
                </div>

                <!-- Custom CSS -->
                <div>
                    <h4 class="text-lg font-semibold text-ink mb-4 flex items-center">
                        <component :is="Code" class="w-5 h-5 mr-2" />
                        Benutzerdefiniertes CSS
                    </h4>
                    <div>
                        <label for="pwa-settings-widget-form-custom-css" class="block text-sm font-medium text-ink-secondary mb-2">Custom CSS</label>
                        <textarea id="pwa-settings-widget-form-custom-css"
                            v-model="form.custom_css"
                            rows="6"
                            class="field w-full px-3 py-2 font-mono"
                            placeholder="/* Eigene CSS-Regeln hier einfügen */&#10;.custom-class {&#10;  color: #333;&#10;}"
                        ></textarea>
                        <p class="mt-1 text-xs text-ink-muted">
                            Füge benutzerdefinierte CSS-Regeln hinzu, um das Aussehen der App anzupassen.
                        </p>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="pt-4 border-t border-line space-y-3">
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
                        class="w-full flex items-center justify-center px-4 py-2 bg-brand text-white rounded-md hover:bg-brand-strong disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
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
import { usePage } from '@inertiajs/vue3'
import {
    Settings, Palette, FileText, Image, Clock, Share2,
    Smartphone, Code, Save, ExternalLink, Globe,
    ShieldOff, Store, Apple, Play
} from '@lucide/vue'
import { Instagram, Facebook, Youtube, Twitter, Linkedin } from '@/Components/Common/brandIcons'

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
    cache_duration_hours: 24,
    pwa_login_disabled: false,
    app_store_url_ios: '',
    app_store_url_android: ''
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

const page = usePage()

const openPwaPreview = () => {
    const previewUrl = `${page.props.pwaUrl}/${props.currentGym.slug}`
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
    background-color: var(--brand);
}

.peer:checked + div:after {
    transform: translateX(100%);
    border-color: white;
}
</style>
