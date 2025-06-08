<!-- resources/js/Pages/Dashboard.vue -->
<template>
  <div class="min-h-screen bg-gray-50">
    <nav class="bg-white shadow">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <div class="flex items-center">
            <h1 class="text-xl font-semibold text-gray-900">Dashboard</h1>
          </div>

          <div class="flex items-center space-x-4">
            <span class="text-gray-700">Willkommen, {{ user.first_name }} {{ user.last_name }}!</span>
            <button
              @click="logout"
              class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium"
            >
              Abmelden
            </button>
          </div>
        </div>
      </div>
    </nav>

    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
      <div class="px-4 py-6 sm:px-0">
        <div class="border-4 border-dashed border-gray-200 rounded-lg h-96 p-8">
          <div class="text-center">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">
              Herzlich willkommen in Ihrem Dashboard!
            </h2>
            <p class="text-gray-600">
              Sie sind erfolgreich angemeldet als {{ user.email }}
            </p>
            <div class="mt-8">
              <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                  <h3 class="text-lg leading-6 font-medium text-gray-900">
                    Benutzerinformationen
                  </h3>
                  <div class="mt-2 max-w-xl text-sm text-gray-500">
                    <p>Hier können Sie Ihre Kontoinformationen verwalten.</p>
                  </div>
                  <div class="mt-5">
                    <div class="rounded-md bg-gray-50 px-6 py-5 sm:flex sm:items-start sm:justify-between">
                      <div class="sm:flex sm:items-start">
                        <div class="mt-3 sm:mt-0 sm:ml-4">
                          <div class="text-sm font-medium text-gray-900">
                            Name: {{ user.name }}
                          </div>
                          <div class="mt-1 text-sm text-gray-600">
                            E-Mail: {{ user.email }}
                          </div>
                          <div class="mt-1 text-sm text-gray-600">
                            Registriert: {{ formatDate(user.created_at) }}
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3'

defineProps({
  user: Object,
})

const logout = () => {
  router.post('/logout')
}

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString('de-DE', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}
</script>
