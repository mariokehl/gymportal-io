<template>
  <span
    :class="[
      'inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold rounded-full',
      statusClasses
    ]"
  >
    <component v-if="showIcon" :is="statusIcon" class="w-3 h-3" />
    {{ statusText }}
  </span>
</template>

<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { getStatusText, getStatusBadgeClass, getStatusIcon } from '@/utils/memberStatus'

const props = defineProps({
  status: {
    type: String,
    default: '',
  },
  showIcon: {
    type: Boolean,
    default: false,
  },
})

// Acceso a las props compartidas por Inertia (app.locale, app.translations, etc.)
const page = usePage()

const membersTranslations = computed(() => {
  return page.props.app?.translations?.members ?? {}
})

// Clases e iconos siguen usando la lógica actual
const statusClasses = computed(() => getStatusBadgeClass(props.status))
const statusIcon = computed(() => getStatusIcon(props.status))

// Texto: primero intenta usar traducción, si no existe, usa la lógica antigua
const statusText = computed(() => {
  const key = props.status || ''

  const translated =
    membersTranslations.value?.status?.[key]

  // 1) Usa traducción si existe
  // 2) Si no existe, fallback a getStatusText (comportamiento actual)
  return translated ?? getStatusText(key)
})
</script>

