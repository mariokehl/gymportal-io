<template>
  <a
    :href="releaseUrl"
    target="_blank"
    rel="noopener noreferrer"
    class="version-badge"
    title="Changelog auf GitHub ansehen"
  >
    {{ version }}
  </a>
</template>

<script setup>
// __APP_VERSION__ / __GITHUB_REPO_URL__ werden von Vite zur Build-Zeit ersetzt
// (siehe vite.config.js → define). Fallbacks für den Fall, dass sie fehlen.
const version =
  typeof __APP_VERSION__ !== 'undefined' ? __APP_VERSION__ : 'dev'
const repoUrl =
  typeof __GITHUB_REPO_URL__ !== 'undefined'
    ? __GITHUB_REPO_URL__
    : 'https://github.com/mariokehl/gymportal-io'

const releaseUrl = `${repoUrl}/releases`
</script>

<style scoped>
.version-badge {
  display: inline-flex;
  align-items: center;
  background: linear-gradient(45deg, rgb(245, 158, 11), rgb(249, 115, 22));
  color: white;
  font-size: 0.625rem;
  line-height: 1;
  font-weight: 600;
  padding: 0.125rem 0.375rem;
  border-radius: 0.25rem;
  letter-spacing: 0.05em;
  box-shadow: rgba(245, 158, 11, 0.2) 0px 2px 4px;
  animation: 2s infinite pulse;
}

@keyframes pulse {
  0%,
  100% {
    box-shadow: rgba(245, 158, 11, 0.2) 0px 2px 4px;
  }
  50% {
    box-shadow: rgba(245, 158, 11, 0.5) 0px 2px 10px;
  }
}
</style>
