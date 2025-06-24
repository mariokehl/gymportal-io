<template>
  <AppLayout title="Mitglied ansehen">
    <template #header>
      <div class="flex items-center">
        <Link
          :href="route('members.index')"
          class="text-gray-500 hover:text-gray-700 mr-4"
        >
          <ArrowLeft class="w-5 h-5" />
        </Link>
        Mitglied ansehen: {{ member.first_name }} {{ member.last_name }}
      </div>
    </template>

    <div class="max-w-4xl mx-auto">
        <div class="space-y-6">
            <!-- Vertrag -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Vertrag</h3>
            </div>

            <!-- Check-Ins -->
            <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Check-Ins</h3>
            </div>
        </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { ArrowLeft } from 'lucide-vue-next'

// Props
const props = defineProps({
  member: Object,
})
</script>
