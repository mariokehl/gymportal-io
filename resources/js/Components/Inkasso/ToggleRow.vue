<template>
  <div class="flex items-center gap-3 py-2 cursor-pointer" @click="$emit('update:modelValue', !modelValue)">
    <span
      :class="modelValue ? 'bg-indigo-600' : 'bg-gray-300'"
      class="relative w-[42px] h-6 rounded-full flex-none transition-colors"
    >
      <span
        :class="modelValue ? 'left-[21px]' : 'left-[3px]'"
        class="absolute top-[3px] w-[18px] h-[18px] rounded-full bg-white transition-all"
      />
    </span>
    <span class="text-sm text-gray-700">{{ label }}</span>
  </div>
</template>

<script setup>
defineProps({
  modelValue: { type: Boolean, default: false },
  label: { type: String, default: '' },
})

defineEmits(['update:modelValue'])
</script>
