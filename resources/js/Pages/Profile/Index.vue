<template>
    <AppLayout title="Profil">
        <template #header>
            Profil
        </template>

        <!-- Toast Notification -->
        <Transition
            enter-active-class="transition ease-out duration-300"
            enter-from-class="opacity-0 translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-2"
        >
            <div v-if="toast.show" class="fixed top-4 right-4 z-50 max-w-md">
                <div :class="[
                    'flex items-center p-4 rounded-lg shadow-lg',
                    toast.type === 'error' ? 'bg-danger-solid text-white' : 'bg-success-solid text-white'
                ]">
                    <div class="flex-shrink-0 mr-3">
                        <svg v-if="toast.type === 'error'" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        <svg v-else class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="flex-1 text-sm font-medium">
                        {{ toast.message }}
                    </div>
                    <button @click="toast.show = false" class="flex-shrink-0 ml-3 hover:opacity-75">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            </div>
        </Transition>

        <div class="max-w-2xl mx-auto space-y-6">

            <!-- Profile Information Section -->
            <div class="bg-surface shadow-sm rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-semibold text-ink mb-2">
                        Profilinformationen
                    </h3>
                    <p class="text-sm text-ink-secondary mb-6">
                        Ihre persönlichen Kontoinformationen.
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-ink-secondary">
                                Vorname
                            </label>
                            <div class="mt-1 block w-full rounded-md bg-surface-muted px-3 py-2 text-base text-ink-secondary border border-line sm:text-sm">
                                {{ user.first_name }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-ink-secondary">
                                Nachname
                            </label>
                            <div class="mt-1 block w-full rounded-md bg-surface-muted px-3 py-2 text-base text-ink-secondary border border-line sm:text-sm">
                                {{ user.last_name }}
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-ink-secondary">
                                E-Mail-Adresse
                            </label>
                            <div class="mt-1 block w-full rounded-md bg-surface-muted px-3 py-2 text-base text-ink-secondary border border-line sm:text-sm">
                                {{ user.email }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Password Change Section -->
            <div class="bg-surface shadow-sm rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-semibold text-ink mb-2">
                        Passwort ändern
                    </h3>
                    <p class="text-sm text-ink-secondary mb-6">
                        Stellen Sie sicher, dass Ihr Konto ein langes, zufälliges Passwort verwendet, um sicher zu bleiben.
                    </p>

                    <form @submit.prevent="updatePassword" class="space-y-4">
                        <div>
                            <label for="current_password" class="block text-sm font-medium text-ink-secondary">
                                Aktuelles Passwort
                            </label>
                            <input
                                id="current_password"
                                v-model="passwordForm.current_password"
                                type="password"
                                autocomplete="current-password"
                                class="field mt-1 block w-full px-3 py-1.5"
                                :class="{ 'border-danger': passwordForm.errors.current_password }"
                            />
                            <p v-if="passwordForm.errors.current_password" class="mt-1 text-sm text-danger">
                                {{ passwordForm.errors.current_password }}
                            </p>
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-ink-secondary">
                                Neues Passwort
                            </label>
                            <input
                                id="password"
                                v-model="passwordForm.password"
                                type="password"
                                autocomplete="new-password"
                                class="field mt-1 block w-full px-3 py-1.5"
                                :class="{ 'border-danger': passwordForm.errors.password }"
                            />
                            <p v-if="passwordForm.errors.password" class="mt-1 text-sm text-danger">
                                {{ passwordForm.errors.password }}
                            </p>
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-ink-secondary">
                                Passwort bestätigen
                            </label>
                            <input
                                id="password_confirmation"
                                v-model="passwordForm.password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                class="field mt-1 block w-full px-3 py-1.5"
                            />
                        </div>

                        <div class="flex justify-end">
                            <button
                                type="submit"
                                :disabled="passwordForm.processing"
                                class="bg-brand hover:bg-brand-strong text-white font-medium py-2 px-4 rounded-md disabled:opacity-50"
                            >
                                <span v-if="passwordForm.processing">Speichern...</span>
                                <span v-else>Passwort ändern</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Account Deletion Section -->
            <div class="bg-surface shadow-sm rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-semibold text-ink mb-2">
                        Konto löschen
                    </h3>

                    <div class="bg-danger-subtle border border-danger-line rounded-lg p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-danger" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-danger">
                                    Sobald Ihr Konto gelöscht ist, werden alle zugehörigen Ressourcen und Daten endgültig gelöscht. Bitte laden Sie vor der Kontolöschung alle Daten und Informationen herunter, die Sie behalten möchten.
                                </p>
                            </div>
                        </div>
                    </div>

                    <button
                        @click="showDeleteModal = true"
                        type="button"
                        class="bg-danger-solid hover:bg-danger-solid-strong text-white font-medium py-2 px-4 rounded-md"
                    >
                        Konto löschen
                    </button>
                </div>
            </div>
        </div>

        <!-- Delete Account Modal -->
        <div v-if="showDeleteModal" class="fixed inset-0 bg-gray-500/75 overflow-y-auto h-full w-full z-50" @click="showDeleteModal = false">
            <div class="relative top-20 mx-auto p-5 border border-line w-full max-w-md shadow-lg rounded-lg bg-surface" @click.stop>
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-danger-soft mb-4">
                        <svg class="h-6 w-6 text-danger" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                    </div>

                    <h3 class="text-lg font-semibold text-ink mb-2">
                        Konto löschen
                    </h3>

                    <p class="text-sm text-ink-secondary mb-6">
                        Sind Sie sicher, dass Sie Ihr Konto löschen möchten? Dieser Vorgang kann nicht rückgängig gemacht werden.
                    </p>

                    <form @submit.prevent="deleteAccount" class="space-y-4">
                        <div class="text-left">
                            <label for="delete_password" class="block text-sm font-medium text-ink-secondary">
                                Passwort zur Bestätigung
                            </label>
                            <input
                                id="delete_password"
                                v-model="deleteForm.password"
                                type="password"
                                placeholder="Geben Sie Ihr Passwort ein"
                                class="field mt-1 block w-full px-3 py-1.5"
                                :class="{ 'border-danger': deleteForm.errors.password }"
                            />
                            <p v-if="deleteForm.errors.password" class="mt-1 text-sm text-danger">
                                {{ deleteForm.errors.password }}
                            </p>
                        </div>

                        <div class="flex justify-end space-x-3">
                            <button
                                type="button"
                                @click="showDeleteModal = false"
                                class="bg-surface hover:bg-surface-muted text-ink-secondary font-medium py-2 px-4 rounded-md border border-line-strong"
                            >
                                Abbrechen
                            </button>
                            <button
                                type="submit"
                                :disabled="deleteForm.processing"
                                class="bg-danger-solid hover:bg-danger-solid-strong text-white font-medium py-2 px-4 rounded-md disabled:opacity-50"
                            >
                                <span v-if="deleteForm.processing">Löschen...</span>
                                <span v-else>Konto endgültig löschen</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppShell from '@/Layouts/AppShell.vue'

defineOptions({ layout: AppShell })

defineProps({
    user: Object,
})

const showDeleteModal = ref(false)

// Toast state
const toast = reactive({
    show: false,
    message: '',
    type: 'success'
})

const showToast = (message, type = 'success') => {
    toast.message = message
    toast.type = type
    toast.show = true

    setTimeout(() => {
        toast.show = false
    }, 5000)
}

// Password change form
const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

const updatePassword = () => {
    passwordForm.put(route('profile.password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset()
        },
    })
}

// Delete account form
const deleteForm = useForm({
    password: '',
})

const deleteAccount = () => {
    deleteForm.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false
        },
        onError: (errors) => {
            // Show subscription error as toast
            if (errors.subscription) {
                showToast(errors.subscription, 'error')
                showDeleteModal.value = false
            }
            // Show deletion error as toast
            if (errors.deletion) {
                showToast(errors.deletion, 'error')
                showDeleteModal.value = false
            }
        },
    })
}
</script>
