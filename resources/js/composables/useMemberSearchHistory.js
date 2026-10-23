import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { addToHistory, historyKey, readHistory, writeHistory } from '@/utils/memberSearchHistory'

function storage() {
  try {
    return typeof window !== 'undefined' ? window.localStorage : null
  } catch {
    return null
  }
}

/**
 * Recently viewed members of the signed-in user in the current gym.
 */
export function useMemberSearchHistory() {
  const page = usePage()

  const key = computed(() => {
    const user = page.props.auth?.user
    const gymId = user?.current_gym?.id

    return user?.id && gymId ? historyKey(user.id, gymId) : null
  })

  function read() {
    return key.value ? readHistory(storage(), key.value) : []
  }

  function remember(memberId) {
    if (!key.value || !Number.isInteger(memberId)) return

    writeHistory(storage(), key.value, addToHistory(read(), memberId))
  }

  return { read, remember }
}
