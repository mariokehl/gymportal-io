<template>
  <AppLayout title="Neues Add-on">
    <template #header>
      Neues Add-on erstellen
    </template>

    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm">
      <Link :href="route('contracts.index')" class="text-indigo-600 hover:text-indigo-800">
        Verträge
      </Link>
      <span class="text-gray-500 mx-2">/</span>
      <Link :href="route('contracts.addons.index')" class="text-indigo-600 hover:text-indigo-800">
        Add-ons
      </Link>
      <span class="text-gray-500 mx-2">/</span>
      <span class="text-gray-900">Neu</span>
    </nav>

    <AddonForm
      :form="form"
      :membership-plans="membershipPlans"
      :payment-method-options="paymentMethodOptions"
      submit-label="Add-on erstellen"
      @submit="submit"
    />
  </AppLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AddonForm from './AddonForm.vue'

const props = defineProps({
  membershipPlans: { type: Array, default: () => [] },
  paymentMethodOptions: { type: Array, default: () => [] }
})

// Initialise the plan_modes map with null for every plan.
const planModes = {}
props.membershipPlans.forEach((plan) => {
  planModes[plan.id] = null
})

const form = useForm({
  name: '',
  description: '',
  price: '',
  payment_method: '',
  is_active: true,
  sort_order: 0,
  plan_modes: planModes
})

const submit = () => {
  form.post(route('contracts.addons.store'))
}
</script>
