import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useUiStore = defineStore('ui', () => {
  const showCommandPalette = ref(false)
  const showNotifications = ref(false)
  const commandQuery = ref('')

  const toggleCommandPalette = () => {
    showCommandPalette.value = !showCommandPalette.value
    if (!showCommandPalette.value) commandQuery.value = ''
  }

  const toggleNotifications = () => {
    showNotifications.value = !showNotifications.value
  }

  const setCommandQuery = (query: string) => {
    commandQuery.value = query
  }

  return {
    showCommandPalette,
    showNotifications,
    commandQuery,
    toggleCommandPalette,
    toggleNotifications,
    setCommandQuery
  }
})
