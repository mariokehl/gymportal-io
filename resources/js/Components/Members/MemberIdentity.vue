<template>
  <component
    :is="memberLink ? Link : 'div'"
    :href="memberLink"
    class="flex items-center min-w-0"
    :class="memberLink ? 'group' : null"
  >
    <MemberAvatar
      :initials="initials"
      :age-verified="!!member?.age_verified"
      :verified-at="member?.age_verified_at || null"
      :is-guest="!!member?.guest_access"
      :size="size"
      class="shrink-0"
    />
    <div class="ml-3 min-w-0" :style="maxWidth ? { maxWidth } : null">
      <p
        class="text-sm font-medium text-gray-900 truncate"
        :class="memberLink ? 'group-hover:text-indigo-600 transition-colors' : null"
      >
        {{ displayName }}
      </p>
      <p v-if="showEmail && member?.email" class="text-xs text-gray-500 truncate">
        {{ member.email }}
      </p>
      <slot name="meta" />
    </div>
  </component>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import MemberAvatar from '@/Components/MemberAvatar.vue'

const props = defineProps({
  // Accepts either a full member object or one with precomputed name/initials.
  member: {
    type: Object,
    default: null
  },
  size: {
    type: String,
    default: 'sm'
  },
  showEmail: {
    type: Boolean,
    default: true
  },
  // Set to false to render without a link (e.g. on the member detail page itself).
  linked: {
    type: Boolean,
    default: true
  },
  maxWidth: {
    type: String,
    default: '200px'
  }
})

const displayName = computed(() => {
  if (!props.member) return '–'
  if (props.member.name) return props.member.name

  return [props.member.first_name, props.member.last_name].filter(Boolean).join(' ') || '–'
})

const initials = computed(() => {
  if (!props.member) return '??'
  if (props.member.initials) return props.member.initials

  const first = props.member.first_name?.charAt(0) || ''
  const last = props.member.last_name?.charAt(0) || ''

  return (first + last).toUpperCase() || '??'
})

const memberLink = computed(() => {
  if (!props.linked || !props.member?.id) return null

  return route('members.show', props.member.id)
})
</script>
