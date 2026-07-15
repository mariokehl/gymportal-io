<template>
  <div class="flex flex-col gap-3.5">
    <!-- Check-ins list -->
    <div v-if="member.check_ins && member.check_ins.length > 0" class="rounded-lg border border-gray-200 bg-white overflow-hidden">
      <!-- Horizontally scrollable table on narrow viewports -->
      <div class="overflow-x-auto">
        <table class="min-w-[640px] w-full border-collapse">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3.5 py-3 text-left text-[11px] font-medium tracking-wide text-gray-500 uppercase whitespace-nowrap">Datum</th>
              <th class="px-3.5 py-3 text-left text-[11px] font-medium tracking-wide text-gray-500 uppercase whitespace-nowrap">Check-In</th>
              <th class="px-3.5 py-3 text-left text-[11px] font-medium tracking-wide text-gray-500 uppercase whitespace-nowrap">Check-Out</th>
              <th class="px-3.5 py-3 text-left text-[11px] font-medium tracking-wide text-gray-500 uppercase whitespace-nowrap">Dauer</th>
              <th class="px-3.5 py-3 text-left text-[11px] font-medium tracking-wide text-gray-500 uppercase whitespace-nowrap">Methode</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="checkin in member.check_ins" :key="checkin.id" class="hover:bg-gray-50">
              <td class="px-3.5 py-3.5 text-sm font-medium text-gray-900 whitespace-nowrap">{{ formatDate(checkin.check_in_time) }}</td>
              <td class="px-3.5 py-3.5 text-sm text-gray-700 whitespace-nowrap">{{ formatTime(checkin.check_in_time) }}</td>
              <td class="px-3.5 py-3.5 text-sm text-gray-700 whitespace-nowrap">
                {{ checkin.check_out_time ? formatTime(checkin.check_out_time) : '-' }}
              </td>
              <td class="px-3.5 py-3.5 text-sm text-gray-700 whitespace-nowrap">
                {{ checkin.check_out_time ? calculateDuration(checkin.check_in_time, checkin.check_out_time) : '-' }}
              </td>
              <td class="px-3.5 py-3.5 whitespace-nowrap">
                <span
                  :class="[
                    'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                    checkin.check_in_method === 'nfc_card'
                      ? 'bg-purple-100 text-purple-800'
                      : checkin.check_in_method === 'manual'
                        ? 'bg-gray-100 text-gray-800'
                        : 'bg-blue-100 text-blue-800'
                  ]"
                >
                  <CreditCard v-if="checkin.check_in_method === 'nfc_card'" class="w-3 h-3 mr-1" />
                  <Edit v-else-if="checkin.check_in_method === 'manual'" class="w-3 h-3 mr-1" />
                  <QrCode v-else class="w-3 h-3 mr-1" />
                  {{ checkin.check_in_method_text || 'Unbekannt' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Scroll hint (mobile only; the table needs horizontal scrolling there) -->
      <div class="sm:hidden flex items-center gap-1.5 px-3.5 py-2.5 text-xs text-gray-400 border-t border-gray-100">
        <MoveHorizontal class="w-3.5 h-3.5" />
        Zum Scrollen wischen
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="rounded-lg border border-gray-200 bg-white text-center py-12">
      <Clock class="w-12 h-12 text-gray-400 mx-auto mb-4" />
      <p class="text-gray-500">Keine Check-Ins vorhanden</p>
    </div>
  </div>
</template>

<script setup>
import { Clock, CreditCard, Edit, QrCode, MoveHorizontal } from 'lucide-vue-next'
import { formatDate, formatTime } from '@/utils/formatters'

defineProps({
  member: {
    type: Object,
    required: true,
  },
})

const calculateDuration = (checkIn, checkOut) => {
  if (!checkIn || !checkOut) return '-'
  const duration = new Date(checkOut) - new Date(checkIn)
  const hours = Math.floor(duration / (1000 * 60 * 60))
  const minutes = Math.floor((duration % (1000 * 60 * 60)) / (1000 * 60))
  return `${hours}h ${minutes}m`
}
</script>
