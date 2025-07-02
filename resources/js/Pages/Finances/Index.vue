<template>
    <AppLayout title="Finanzen">
        <template #header>
            Finanzen
        </template>
        <MollieSetupWizard :organization="currentOrganization" />
    </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import MollieSetupWizard from '@/Components/MollieSetupWizard.vue'

// Shared data
const page = usePage()

// Computed properties
const currentOrganization = computed(() => {
    return page.props.auth.user.current_gym;
})
</script>
