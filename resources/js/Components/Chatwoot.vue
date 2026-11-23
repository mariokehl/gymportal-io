<template>
  <div></div>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue'
import { usePage } from '@inertiajs/vue3'

const props = defineProps({
  websiteToken: String,
  baseUrl: {
    type: String,
    default: 'https://app.chatwoot.com'
  },
  identityHash: String,
  locale: {
    type: String,
    default: 'de'
  }
})

const page = usePage()

function identifyUser() {
  const user = page.props.auth?.user
  if (!user || !window.$chatwoot) return

  const userData = {
    email: user.email,
    name: `${user.first_name} ${user.last_name}`,
  }

  // Add HMAC hash for identity validation if provided
  if (props.identityHash) {
    userData.identifier_hash = props.identityHash
  }

  window.$chatwoot.setUser(`${user.id}`, userData)
}

function loadSdk() {
  window.chatwootSettings = {
    hideMessageBubble: false,
    position: 'right',
    locale: props.locale,
    type: 'expanded_bubble',
    launcherTitle: 'Feedback'
  }

  const script = document.createElement('script')
  script.src = `${props.baseUrl}/packs/js/sdk.js`
  script.defer = true
  script.async = true
  script.dataset.chatwootSdk = ''
  script.onload = () => {
    window.chatwootSDK.run({
      websiteToken: props.websiteToken,
      baseUrl: props.baseUrl
    })
  }
  document.body.appendChild(script)
}

onMounted(() => {
  window.addEventListener('chatwoot:ready', identifyUser)

  // The shell persists across visits, but it remounts after a page without
  // it (e.g. billing). Load the SDK only once per document and re-show the
  // bubble that was hidden on unmount.
  if (window.$chatwoot) {
    window.$chatwoot.toggleBubbleVisibility('show')
    identifyUser()
  } else if (!document.querySelector('script[data-chatwoot-sdk]')) {
    loadSdk()
  }
})

onUnmounted(() => {
  window.removeEventListener('chatwoot:ready', identifyUser)
  if (!window.$chatwoot) return

  // Only end the chat session when the user is gone (logout); otherwise just
  // hide the bubble on pages that render without the shell.
  if (page.props.auth?.user) {
    window.$chatwoot.toggleBubbleVisibility('hide')
  } else {
    window.$chatwoot.reset()
  }
})
</script>
