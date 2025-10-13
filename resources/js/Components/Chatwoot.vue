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

onMounted(() => {
  // Chatwoot Einstellungen
  window.chatwootSettings = {
    hideMessageBubble: false,
    position: 'right',
    locale: props.locale,
    type: 'expanded_bubble',
    launcherTitle: 'Feedback'
  }

  // SDK laden
  const script = document.createElement('script')
  script.src = `${props.baseUrl}/packs/js/sdk.js`
  script.defer = true
  script.async = true
  script.onload = () => {
    window.chatwootSDK.run({
      websiteToken: props.websiteToken,
      baseUrl: props.baseUrl
    })
  }
  document.body.appendChild(script)

  // User-Daten hinzufügen falls eingeloggt
  window.addEventListener('chatwoot:ready', function() {
    if (page.props.auth?.user) {
      const userData = {
          email: page.props.auth.user.email,
          name: `${page.props.auth.user.first_name} ${page.props.auth.user.last_name}`,
      };

      // Add HMAC hash for identity validation if provided
      if (props.identityHash) {
        userData.identifier_hash = props.identityHash;
      }

      window.$chatwoot.setUser(`${page.props.auth.user.id}`, userData);
    }
  })
})

onUnmounted(() => {
  // Cleanup bei Bedarf
  if (window.$chatwoot) {
    window.$chatwoot.reset()
  }
})
</script>
