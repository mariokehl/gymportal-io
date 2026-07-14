<template>
  <div class="flex flex-col gap-6">
    <!-- Loading State -->
    <div v-if="loading" class="bg-white rounded-lg border border-gray-200 shadow-sm text-center py-12">
      <Loader2 class="w-8 h-8 text-indigo-600 mx-auto mb-4 animate-spin" />
      <p class="text-gray-500">Dokumente werden geladen...</p>
    </div>

    <!-- Content -->
    <template v-else>
      <!-- Success Message -->
      <div v-if="successMessage" class="bg-green-50 border border-green-200 rounded-lg p-4">
        <div class="flex">
          <FileText class="w-5 h-5 text-green-400 mr-2 flex-shrink-0" />
          <div class="text-sm text-green-800">{{ successMessage }}</div>
        </div>
      </div>

      <!-- Error Message -->
      <div v-if="errorMessage" class="bg-red-50 border border-red-200 rounded-lg p-4">
        <div class="flex">
          <FileText class="w-5 h-5 text-red-400 mr-2 flex-shrink-0" />
          <div class="text-sm text-red-800">{{ errorMessage }}</div>
        </div>
      </div>

      <!-- Existing Documents -->
      <div v-if="documents.length > 0" class="flex flex-col gap-3.5">
        <h3 class="text-lg font-bold text-gray-900">Dokumente</h3>

        <div class="rounded-lg border border-gray-200 bg-white overflow-hidden">
          <!-- Horizontally scrollable table on narrow viewports -->
          <div class="overflow-x-auto">
            <table class="min-w-[620px] w-full border-collapse">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3.5 py-3 text-left text-[11px] font-medium tracking-wide text-gray-500 uppercase whitespace-nowrap">Typ</th>
                  <th class="px-3.5 py-3 text-left text-[11px] font-medium tracking-wide text-gray-500 uppercase whitespace-nowrap">Name</th>
                  <th class="px-3.5 py-3 text-left text-[11px] font-medium tracking-wide text-gray-500 uppercase whitespace-nowrap">Plan</th>
                  <th class="px-3.5 py-3 text-left text-[11px] font-medium tracking-wide text-gray-500 uppercase whitespace-nowrap">Erstellt am</th>
                  <th class="px-3.5 py-3 text-right text-[11px] font-medium tracking-wide text-gray-500 uppercase whitespace-nowrap">Aktion</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="doc in documents" :key="doc.id" class="hover:bg-gray-50">
                  <td class="px-3.5 py-3.5">
                    <FileText class="w-[22px] h-[22px] text-indigo-500" />
                  </td>
                  <td class="px-3.5 py-3.5 text-sm font-medium text-gray-900 whitespace-nowrap">{{ doc.name }}</td>
                  <td class="px-3.5 py-3.5 text-sm text-gray-700 whitespace-nowrap">{{ doc.plan_name }}</td>
                  <td class="px-3.5 py-3.5 text-sm text-gray-700 whitespace-nowrap">{{ doc.created_at }}</td>
                  <td class="px-3.5 py-3.5 text-right whitespace-nowrap">
                    <a
                      :href="`/members/${member.id}/documents/${doc.membership_id}/download`"
                      class="inline-flex items-center gap-1.5 text-indigo-600 hover:text-indigo-800 text-sm font-semibold"
                    >
                      <Download class="w-4 h-4" />
                      Download
                    </a>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Scroll hint (mobile only; the table needs horizontal scrolling there) -->
          <div class="sm:hidden flex items-center gap-1.5 px-3.5 py-2.5 text-xs text-gray-400 border-t border-gray-100">
            <MoveHorizontal class="w-3.5 h-3.5" />
            Zum Scrollen wischen
          </div>
        </div>
      </div>

      <!-- Memberships Without Contract -->
      <div v-if="membershipsWithoutContract.length > 0" class="flex flex-col gap-3.5">
        <h3 class="text-lg font-bold text-gray-900">Mitgliedschaften ohne Vertrag</h3>
        <div class="flex flex-col gap-3">
          <div
            v-for="membership in membershipsWithoutContract"
            :key="membership.id"
            class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-lg border border-gray-200 bg-white p-4"
          >
            <div class="min-w-0">
              <p class="text-sm font-medium text-gray-900">{{ membership.plan_name }}</p>
              <p class="text-xs text-gray-500">Mitgliedschaft #{{ membership.id }}</p>
            </div>
            <button
              @click="generateContract(membership)"
              :disabled="generatingIds[membership.id]"
              type="button"
              class="inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2.5 px-4 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap"
            >
              <Loader2 v-if="generatingIds[membership.id]" class="w-4 h-4 animate-spin" />
              <Plus v-else class="w-4 h-4" />
              Vertrag erstellen
            </button>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div
        v-if="documents.length === 0 && membershipsWithoutContract.length === 0"
        class="rounded-lg border border-gray-200 bg-white text-center py-12"
      >
        <FolderOpen class="w-12 h-12 text-gray-400 mx-auto mb-4" />
        <p class="text-gray-500">Keine Dokumente vorhanden</p>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { FileText, Download, Plus, FolderOpen, Loader2, MoveHorizontal } from 'lucide-vue-next'

const props = defineProps({
  member: Object
})

const emit = defineEmits(['documents-loaded'])

const loading = ref(true)
const documents = ref([])
const membershipsWithoutContract = ref([])
const contractsEnabled = ref(false)
const generatingIds = ref({})
const successMessage = ref('')
const errorMessage = ref('')

function clearMessages() {
  successMessage.value = ''
  errorMessage.value = ''
}

async function fetchDocuments() {
  loading.value = true
  try {
    const response = await axios.get(`/members/${props.member.id}/documents`)
    documents.value = response.data.documents || []
    membershipsWithoutContract.value = response.data.memberships_without_contract || []
    contractsEnabled.value = response.data.contracts_enabled || false
    emit('documents-loaded', documents.value.length)
  } catch (error) {
    errorMessage.value = 'Fehler beim Laden der Dokumente.'
    console.error('Error fetching documents:', error)
  } finally {
    loading.value = false
  }
}

async function generateContract(membership) {
  clearMessages()
  generatingIds.value[membership.id] = true

  try {
    await axios.post(`/members/${props.member.id}/documents/${membership.id}/generate`)
    successMessage.value = `Vertrag für "${membership.plan_name}" wurde erfolgreich erstellt.`
    await fetchDocuments()
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Fehler beim Erstellen des Vertrags.'
    console.error('Error generating contract:', error)
  } finally {
    generatingIds.value[membership.id] = false
  }
}

onMounted(() => {
  fetchDocuments()
})

defineExpose({ fetchDocuments })
</script>
