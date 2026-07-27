<template>
  <div
    aria-live="assertive"
    class="pointer-events-none fixed inset-0 flex items-end px-4 py-6 sm:items-start sm:p-6 z-[70]"
  >
    <div class="flex w-full flex-col items-center space-y-4 sm:items-end">
      <Notification
        v-for="toast in toasts"
        :key="toast.uuid"
        :type="toast.type"
        :title="toast.title"
        :message="toast.message"
        @dismiss="removeToast(toast.uuid)"
      />
    </div>
  </div>
</template>

<script setup>
import Notification from '@/Components/Common/Notification.vue'
import { useToast } from '@/composables/useToast'

const { toasts, removeToast } = useToast()
</script>
