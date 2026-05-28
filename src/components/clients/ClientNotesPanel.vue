<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { api } from '@/api/client'
import { useToastStore } from '@/stores/toast'

type Note = {
  id: number
  content: string
  created_at: string
  user_id: number
  author?: { id: number; name?: string }
}

const props = defineProps<{
  clientId: string | number | null
  clientName?: string
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'added', note: Note): void
}>()

const toast = useToastStore()
const notes = ref<Note[]>([])
const isLoading = ref(false)
const isSaving = ref(false)
const draft = ref('')

const sortedNotes = computed(() => {
  return [...notes.value].sort((a, b) => {
    const ta = a.created_at ? new Date(a.created_at).getTime() : 0
    const tb = b.created_at ? new Date(b.created_at).getTime() : 0
    return ta - tb
  })
})

const formatTimestamp = (iso: string) => {
  if (!iso) return ''
  const d = new Date(iso)
  const dd = String(d.getDate()).padStart(2, '0')
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const yyyy = d.getFullYear()
  const hh = String(d.getHours()).padStart(2, '0')
  const mi = String(d.getMinutes()).padStart(2, '0')
  return `${dd}.${mm}.${yyyy}, ${hh}:${mi}`
}

const fetchNotes = async () => {
  if (!props.clientId) {
    notes.value = []
    return
  }
  isLoading.value = true
  try {
    const { data } = await api.get(`/v1/clients/${props.clientId}/notes`)
    notes.value = Array.isArray(data) ? data : []
  } catch (error: any) {
    const status = error?.response?.status
    if (status === 403) {
      toast.error('Brak uprawnień do notatek tego klienta.')
    } else {
      toast.error('Nie udało się pobrać notatek.')
    }
    notes.value = []
  } finally {
    isLoading.value = false
  }
}

const submit = async () => {
  const content = draft.value.trim()
  if (!content || !props.clientId) return
  isSaving.value = true
  try {
    const { data } = await api.post(`/v1/clients/${props.clientId}/notes`, { content })
    notes.value = [...notes.value, data as Note]
    draft.value = ''
    toast.success('Notatka zapisana.')
    emit('added', data as Note)
  } catch (error: any) {
    const status = error?.response?.status
    if (status === 403) {
      toast.error('Brak uprawnień do dodawania notatek.')
    } else if (status === 422) {
      toast.error('Treść notatki jest wymagana (max 5000 znaków).')
    } else {
      toast.error('Nie udało się zapisać notatki.')
    }
  } finally {
    isSaving.value = false
  }
}

watch(() => props.clientId, (id) => {
  draft.value = ''
  if (id) fetchNotes()
  else notes.value = []
}, { immediate: true })
</script>

<template>
  <section
    v-if="clientId"
    class="bg-indigo-50/60 border border-indigo-200 rounded-card shadow-sm overflow-hidden mb-4"
  >
    <header class="px-4 py-2.5 bg-white/60 border-b border-indigo-200 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span class="inline-flex w-7 h-7 items-center justify-center rounded-md bg-indigo-100 text-indigo-700">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
          </svg>
        </span>
        <div>
          <h3 class="text-sm font-bold text-slate-800">Karta notatek interakcji CRM <span v-if="clientName" class="text-indigo-700">• {{ clientName }}</span></h3>
          <p class="text-[10px] text-slate-500">Rejestr kontaktów handlowych · Dane bezpieczne i audytowalne</p>
        </div>
      </div>
      <button
        type="button"
        class="text-xs text-slate-500 hover:text-slate-800 font-medium"
        @click="emit('close')"
      >
        × Zamknij podgląd
      </button>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-3 p-3">
      <div class="bg-white rounded-md border border-slate-200 overflow-y-auto max-h-[280px]">
        <div v-if="isLoading" class="p-4 text-xs text-slate-500">Ładowanie notatek...</div>
        <div v-else-if="sortedNotes.length === 0" class="p-4 text-xs text-slate-400 italic">Brak notatek. Dodaj pierwszą po prawej stronie.</div>
        <ul v-else class="divide-y divide-slate-100">
          <li v-for="note in sortedNotes" :key="note.id" class="p-3">
            <div class="flex items-start justify-between gap-2 mb-1">
              <span class="text-xs font-bold text-indigo-700">{{ note.author?.name || 'Użytkownik' }}</span>
              <span class="text-[10px] text-slate-400 font-mono shrink-0">{{ formatTimestamp(note.created_at) }}</span>
            </div>
            <p class="text-xs text-slate-700 whitespace-pre-wrap break-words">{{ note.content }}</p>
          </li>
        </ul>
      </div>

      <div class="bg-white rounded-md border border-slate-200 p-3 flex flex-col gap-2">
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Dodaj nową notatkę z rozmowy</label>
        <textarea
          v-model="draft"
          rows="5"
          maxlength="5000"
          placeholder="Przebieg rozmowy, status decyzyjny, uzgodniony budżet..."
          class="w-full border border-slate-300 rounded-md px-2 py-1.5 text-xs resize-none focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
        ></textarea>
        <div class="flex items-center justify-between">
          <span class="text-[10px] text-slate-400">{{ draft.length }} / 5000</span>
          <button
            type="button"
            class="px-3 py-1.5 rounded-md bg-indigo-600 text-white text-xs font-bold shadow-sm hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="isSaving || !draft.trim()"
            @click="submit"
          >
            <span v-if="isSaving">Zapisywanie...</span>
            <span v-else>Zapisz w bazie CRM</span>
          </button>
        </div>
      </div>
    </div>
  </section>
</template>
