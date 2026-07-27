import { ref } from 'vue'
import axios from 'axios'

/**
 * Global toast notification system.
 *
 * Module-level state keeps a single shared list of toasts across the whole app
 * (equivalent to a Pinia store, without the extra dependency). Render the list
 * once via <NotificationContainer> in the root layout.
 *
 * Not to be confused with useNotifications(), which handles the real-time
 * WebSocket notification center (the bell dropdown).
 */

const AUTO_DISMISS_MS = 5000

// Shared reactive list — every consumer of this composable sees the same array.
const toasts = ref([])

function removeToast(uuid) {
  const index = toasts.value.findIndex((toast) => toast.uuid === uuid)

  if (index !== -1) {
    toasts.value.splice(index, 1)
  }
}

/**
 * Push a toast onto the stack. It auto-dismisses after AUTO_DISMISS_MS.
 *
 * @param {'success'|'error'|'warning'|'info'} type
 * @param {string} title
 * @param {string} [message]
 */
function addToast(type, title, message = '') {
  const uuid = `${Date.now()}-${toasts.value.length}-${title}`

  toasts.value.push({ uuid, type, title, message })

  setTimeout(() => {
    removeToast(uuid)
  }, AUTO_DISMISS_MS)
}

const success = (title, message) => addToast('success', title, message)
const error = (title, message) => addToast('error', title, message)
const warning = (title, message) => addToast('warning', title, message)
const info = (title, message) => addToast('info', title, message)

/**
 * Wrap an async API call and turn its outcome into toasts.
 *
 * On success it shows successMessage (if given) and runs onSuccess.
 * On failure it maps common HTTP statuses to a helpful error toast.
 *
 * @template T
 * @param {() => Promise<T>} apiRequest
 * @param {string} [successMessage]
 * @param {string} [errorMessage]
 * @param {(response: T) => void} [onSuccess]
 * @returns {Promise<T|undefined>}
 */
async function handleApiRequestNotifications(apiRequest, successMessage, errorMessage, onSuccess) {
  try {
    const response = await apiRequest()

    if (successMessage) {
      success(successMessage)
    }

    if (onSuccess) {
      onSuccess(response)
    }

    return response
  } catch (err) {
    if (axios.isAxiosError(err)) {
      const status = err.response?.status
      const data = err.response?.data

      if (status === 422) {
        // Laravel validation error
        error(data?.message ?? 'Bitte überprüfe deine Eingaben.')
      } else if (status === 403) {
        error(errorMessage ?? 'Keine Berechtigung für diese Aktion.')
      } else {
        error(
          errorMessage ?? 'Aktion fehlgeschlagen',
          data?.message ?? 'Bitte versuche es später erneut.'
        )
      }
    } else {
      error(errorMessage ?? 'Aktion fehlgeschlagen', 'Bitte versuche es später erneut.')
    }

    return undefined
  }
}

export function useToast() {
  return {
    toasts,
    addToast,
    removeToast,
    success,
    error,
    warning,
    info,
    handleApiRequestNotifications,
  }
}
