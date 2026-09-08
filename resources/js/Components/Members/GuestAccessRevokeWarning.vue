<template>
  <div class="bg-orange-50 border border-orange-200 rounded-lg p-3">
    <div class="flex items-start gap-2">
      <AlertCircle class="w-5 h-5 text-orange-500 flex-shrink-0 mt-0.5" />
      <div class="text-sm">
        <p class="font-medium text-orange-800">Gastzugang wird entzogen</p>
        <p class="text-orange-700">
          Mitglied hat aktuell einen unbegrenzten Gastzugang und keine aktive Mitgliedschaft.
          Mit dem Hinzufügen wird der Zugang zeitlich auf diesen Zeitraum begrenzt und der
          Gastzugang automatisch entzogen.
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { AlertCircle } from 'lucide-vue-next'
</script>
