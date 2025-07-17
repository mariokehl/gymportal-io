<!-- resources/js/Layouts/AuthLayout.vue -->
<template>
<div class="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
<Head :title="pageTitle || appName" />
<div class="sm:mx-auto sm:w-full sm:max-w-md">
  <!-- App Name Header -->
  <div class="text-center">
    <h1 class="text-4xl font-bold text-blue-600 mb-2">
      {{ appName }}
    </h1>
    <p class="text-sm text-gray-600 mb-6">Gym Management System</p>
  </div>

  <!-- Page Title -->
  <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
    <slot name="title"></slot>
  </h2>
</div>
<div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
  <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
    <slot></slot>
  </div>
</div>
</div>
</template>

<script setup>
import { computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'

// Props
const props = defineProps({
title: {
  type: String,
  default: null
}
})

// Get app name from Laravel config
const page = usePage()
const appName = computed(() => {
return page.props.app?.name || 'gymportal.io'
})

// Computed properties
const pageTitle = computed(() => {
return props.title ? `${props.title} - ${appName.value}` : appName.value
})
</script>
