<template>
  <a
    :href="releaseUrl"
    target="_blank"
    rel="noopener noreferrer"
    class="version-link"
    title="Changelog auf GitHub ansehen"
  >
    <GitBranch class="h-3 w-3" :stroke-width="2" />
    <span>Version {{ version }}</span>
  </a>
</template>

<script setup>
import { GitBranch } from 'lucide-vue-next'

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
.version-link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.375rem;
  color: var(--color-gray-400, #9ca3af);
  font-size: 0.6875rem;
  line-height: 1;
  font-weight: 500;
  letter-spacing: 0.01em;
  text-decoration: none;
  transition: color 150ms cubic-bezier(0.4, 0, 0.2, 1);
}

.version-link:hover {
  color: var(--color-gray-600, #4b5563);
}
</style>
