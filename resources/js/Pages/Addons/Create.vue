<template>
  <AppLayout title="Neues Add-on">
    <template #header>
      Neues Add-on erstellen
    </template>

    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm">
      <Link :href="route('contracts.index')" class="text-brand-ink underline decoration-brand-line underline-offset-4 hover:text-brand-ink-strong hover:decoration-current">
        Verträge
      </Link>
      <span class="text-ink-muted mx-2">/</span>
      <Link :href="route('contracts.addons.index')" class="text-brand-ink hover:text-brand-ink-strong">
        Add-ons
      </Link>
      <span class="text-ink-muted mx-2">/</span>
      <span class="text-ink">Neu</span>
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
import AppShell from '@/Layouts/AppShell.vue'
import AddonForm from './AddonForm.vue'

defineOptions({ layout: AppShell })

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
  price_display: 'monthly',
  payment_method: '',
  service_type: 'additional',
  billing_type: 'one_time',
  trial_rest_of_month: false,
  usage_period: null,
  usage_duration: null,
  usage_duration_unit: null,
  quota_amount: null,
  quota_interval: null,
  settled_via_device: false,
  is_active: true,
  sort_order: 0,
  plan_modes: planModes
})

const submit = () => {
  form.post(route('contracts.addons.store'))
}
</script>
