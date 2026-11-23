import { onScopeDispose, ref, watch } from 'vue'

// Follows a boolean source, but only turns on once the source has stayed
// true for `delay` ms; it turns off immediately. Keeps loading skeletons
// from flashing up for responses that arrive almost instantly.
export function useDelayedFlag(source, delay = 150) {
  const flag = ref(false)
  let timer = null

  const clear = () => {
    if (timer !== null) clearTimeout(timer)
    timer = null
  }

  watch(source, active => {
    clear()

    if (!active) {
      flag.value = false
      return
    }

    timer = setTimeout(() => {
      timer = null
      flag.value = true
    }, delay)
  }, { immediate: true })

  onScopeDispose(clear)

  return flag
}
