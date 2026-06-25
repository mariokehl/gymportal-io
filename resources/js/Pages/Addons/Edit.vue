<template>
  <AppLayout title="Add-on bearbeiten">
    <template #header>
      Add-on bearbeiten
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
      <span class="text-gray-900">{{ addon.name }}</span>
    </nav>

    <AddonForm
      :form="form"
      :membership-plans="membershipPlans"
      :payment-method-options="paymentMethodOptions"
      submit-label="Änderungen speichern"
      @submit="submit"
    />
  </AppLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AddonForm from './AddonForm.vue'

const props = defineProps({
  addon: { type: Object, required: true },
  planModes: { type: Object, default: () => ({}) },
  membershipPlans: { type: Array, default: () => [] },
  paymentMethodOptions: { type: Array, default: () => [] }
})

// Initialise the plan_modes map: existing assignments win, otherwise null.
const planModes = {}
props.membershipPlans.forEach((plan) => {
  planModes[plan.id] = props.planModes[plan.id] ?? null
})

const form = useForm({
  name: props.addon.name,
  description: props.addon.description ?? '',
  price: props.addon.price,
  payment_method: props.addon.payment_method ?? '',
  is_active: props.addon.is_active,
  sort_order: props.addon.sort_order ?? 0,
  plan_modes: planModes
})

const submit = () => {
  form.put(route('contracts.addons.update', props.addon.id))
}
</script>
