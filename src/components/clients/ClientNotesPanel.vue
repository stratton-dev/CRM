<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { api } from '@/api/client'
import { useToastStore } from '@/stores/toast'
import { useSessionStore } from '@/stores/session'
import { useClientStore } from '@/stores/client'

type ActivityType = 'NOTE' | 'CALL' | 'EMAIL' | 'MEETING'

type Activity = {
  id: number
  description: string
  occurred_at: string
  type: ActivityType
  user_id?: number
  user?: { id: number; name?: string }
}

const props = defineProps<{
  clientId: string | number | null
  clientName?: string
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'added', activity: Activity): void
}>()

const toast = useToastStore()
const session = useSessionStore()
const clientStore = useClientStore()
const { currentUser } = storeToRefs(session)

const ACTIVITY_TYPES: Array<{ value: ActivityType; label: string }> = [
  { value: 'NOTE', label: 'Notatka' },
  { value: 'CALL', label: 'Telefon' },
  { value: 'EMAIL', label: 'Email' },
]

const TYPE_LABELS: Record<ActivityType, string> = {
  NOTE: 'Notatka',
  CALL: 'Telefon',
  EMAIL: 'Email',
  MEETING: 'Spotkanie',
}

const TYPE_BADGE: Record<ActivityType, string> = {
  NOTE: 'bg-amber-100 text-amber-800',
  CALL: 'bg-emerald-100 text-emerald-800',
  EMAIL: 'bg-violet-100 text-violet-800',
  MEETING: 'bg-blue-100 text-blue-800',
}

type OfferPdf = {
  id: number
  name: string
  size_bytes?: number | null
  valid_until?: string | null
  calculation_id?: number | null
  created_at: string
  user?: { id: number; name?: string; email?: string }
}

type SavedOffer = {
  id: number
  name: string
  estimated_savings?: number | null
  employees_uop?: number | null
  created_at: string
}

const notes = ref<Activity[]>([])
const offerPdfs = ref<OfferPdf[]>([])
const savedOffers = ref<SavedOffer[]>([])
const isLoading = ref(false)
const isLoadingOffers = ref(false)
const isSaving = ref(false)
const draft = ref('')
const draftType = ref<ActivityType>('NOTE')

const sortedNotes = computed(() => {
  return [...notes.value].sort((a, b) => {
    const ta = a.occurred_at ? new Date(a.occurred_at).getTime() : 0
    const tb = b.occurred_at ? new Date(b.occurred_at).getTime() : 0
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
    const { data } = await api.get('/v1/crm-client-activities', {
      params: { client_id: props.clientId, per_page: 500 },
    })
    const rows = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
    notes.value = rows as Activity[]
  } catch (error: any) {
    const status = error?.response?.status
    if (status === 403) {
      toast.error('Brak uprawnień do aktywności tego klienta.')
    } else {
      toast.error('Nie udało się pobrać aktywności.')
    }
    notes.value = []
  } finally {
    isLoading.value = false
  }
}

const submit = async () => {
  const content = draft.value.trim()
  if (!content || !props.clientId) return
  const author = currentUser.value
  if (!author?.id) {
    toast.error('Brak zalogowanego użytkownika.')
    return
  }
  isSaving.value = true
  try {
    const { data } = await api.post('/v1/crm-client-activities', {
      client_id: props.clientId,
      user_id: author.id,
      type: draftType.value,
      description: content,
      occurred_at: new Date().toISOString(),
    })
    notes.value = [...notes.value, data as Activity]
    draft.value = ''
    toast.success('Aktywność zapisana.')
    void clientStore.refreshApiData()
    emit('added', data as Activity)
  } catch (error: any) {
    const status = error?.response?.status
    if (status === 403) {
      toast.error('Brak uprawnień do dodawania aktywności.')
    } else if (status === 422) {
      toast.error('Opis i typ są wymagane.')
    } else {
      toast.error('Nie udało się zapisać aktywności.')
    }
  } finally {
    isSaving.value = false
  }
}

const fetchOffers = async () => {
  if (!props.clientId) {
    offerPdfs.value = []
    savedOffers.value = []
    return
  }
  isLoadingOffers.value = true
  try {
    const [pdfRes, savedRes] = await Promise.allSettled([
      api.get('/v1/crm-offer-pdfs', { params: { client_id: props.clientId, per_page: 200 } }),
      api.get('/v1/crm-saved-offers', { params: { client_id: props.clientId, per_page: 200 } }),
    ])
    if (pdfRes.status === 'fulfilled') {
      const d = pdfRes.value.data
      const rows = Array.isArray(d?.data) ? d.data : Array.isArray(d) ? d : []
      offerPdfs.value = rows as OfferPdf[]
    } else {
      offerPdfs.value = []
    }
    if (savedRes.status === 'fulfilled') {
      const d = savedRes.value.data
      const rows = Array.isArray(d?.data) ? d.data : Array.isArray(d) ? d : []
      savedOffers.value = rows as SavedOffer[]
    } else {
      savedOffers.value = []
    }
  } finally {
    isLoadingOffers.value = false
  }
}

const downloadOfferPdf = async (offer: OfferPdf) => {
  try {
    const { data } = await api.get(`/v1/crm-offer-pdfs/${offer.id}`)
    const base64 = data?.pdf_base64
    if (!base64) {
      toast.error('Brak pliku PDF do pobrania.')
      return
    }
    const href = String(base64).startsWith('data:') ? String(base64) : `data:application/pdf;base64,${base64}`
    const link = document.createElement('a')
    link.href = href
    link.download = offer.name.endsWith('.pdf') ? offer.name : `${offer.name}.pdf`
    document.body.appendChild(link)
    link.click()
    link.remove()
  } catch {
    toast.error('Nie udało się pobrać oferty.')
  }
}

const formatBytes = (bytes?: number | null) => {
  if (!bytes) return ''
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

watch(() => props.clientId, (id) => {
  draft.value = ''
  draftType.value = 'NOTE'
  if (id) {
    fetchNotes()
    fetchOffers()
  } else {
    notes.value = []
    offerPdfs.value = []
    savedOffers.value = []
  }
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
          <h3 class="text-sm font-bold text-slate-800">Aktywności klienta <span v-if="clientName" class="text-indigo-700">• {{ clientName }}</span></h3>
          <p class="text-[10px] text-slate-500">Pełna historia: notatki, telefony, maile, spotkania · Dane bezpieczne i audytowalne</p>
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

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_260px_300px] gap-3 p-3">
      <div class="bg-white rounded-md border border-slate-200 overflow-y-auto max-h-[280px]">
        <div v-if="isLoading" class="p-4 text-xs text-slate-500">Ładowanie aktywności...</div>
        <div v-else-if="sortedNotes.length === 0" class="p-4 text-xs text-slate-400 italic">Brak aktywności. Dodaj pierwszą po prawej stronie.</div>
        <ul v-else class="divide-y divide-slate-100">
          <li v-for="note in sortedNotes" :key="note.id" class="p-3">
            <div class="flex items-center justify-between gap-2 mb-1">
              <div class="flex items-center gap-2 min-w-0">
                <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider shrink-0" :class="TYPE_BADGE[note.type] || 'bg-slate-100 text-slate-600'">
                  {{ TYPE_LABELS[note.type] || note.type }}
                </span>
                <span class="text-xs font-bold text-indigo-700 truncate">{{ note.user?.name || 'Użytkownik' }}</span>
              </div>
              <span class="text-[10px] text-slate-400 font-mono shrink-0">{{ formatTimestamp(note.occurred_at) }}</span>
            </div>
            <p class="text-xs text-slate-700 whitespace-pre-wrap break-words">{{ note.description }}</p>
          </li>
        </ul>
      </div>

      <div class="bg-white rounded-md border border-slate-200 overflow-y-auto max-h-[280px]">
        <div class="px-3 py-2 border-b border-slate-100 sticky top-0 bg-white">
          <h4 class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Oferty PDF klienta</h4>
        </div>
        <div v-if="isLoadingOffers" class="p-3 text-xs text-slate-500">Ładowanie ofert...</div>
        <div v-else-if="offerPdfs.length === 0 && savedOffers.length === 0" class="p-3 text-xs text-slate-400 italic">Brak ofert. Wygeneruj PDF z kalkulatora.</div>
        <ul v-else class="divide-y divide-slate-100">
          <li v-for="pdf in offerPdfs" :key="`pdf-${pdf.id}`" class="p-2.5">
            <div class="flex items-start justify-between gap-2 mb-1">
              <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-rose-100 text-rose-800 shrink-0">PDF</span>
              <span class="text-[10px] text-slate-400 font-mono shrink-0">{{ formatTimestamp(pdf.created_at) }}</span>
            </div>
            <p class="text-xs text-slate-700 font-semibold truncate" :title="pdf.name">{{ pdf.name }}</p>
            <div class="flex items-center justify-between mt-1.5">
              <span class="text-[10px] text-slate-400">
                {{ formatBytes(pdf.size_bytes) }}<span v-if="pdf.valid_until"> · ważna do {{ pdf.valid_until }}</span>
              </span>
              <button
                type="button"
                class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100"
                @click="downloadOfferPdf(pdf)"
              >
                Pobierz
              </button>
            </div>
          </li>
          <li v-for="offer in savedOffers" :key="`saved-${offer.id}`" class="p-2.5">
            <div class="flex items-start justify-between gap-2 mb-1">
              <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-slate-200 text-slate-600 shrink-0">Archiwum</span>
              <span class="text-[10px] text-slate-400 font-mono shrink-0">{{ formatTimestamp(offer.created_at) }}</span>
            </div>
            <p class="text-xs text-slate-700 font-semibold truncate" :title="offer.name">{{ offer.name }}</p>
            <div class="text-[10px] text-slate-400 mt-1">
              <span v-if="offer.estimated_savings">osz. {{ Number(offer.estimated_savings).toLocaleString('pl-PL') }} PLN</span>
              <span v-if="offer.employees_uop"> · {{ offer.employees_uop }} prac.</span>
              <span class="ml-1 italic">— bez PDF</span>
            </div>
          </li>
        </ul>
      </div>

      <div class="bg-white rounded-md border border-slate-200 p-3 flex flex-col gap-2">
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Dodaj aktywność</label>
        <select
          v-model="draftType"
          class="w-full border border-slate-300 rounded-md px-2 py-1.5 text-xs bg-white focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
        >
          <option v-for="t in ACTIVITY_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
        </select>
        <textarea
          v-model="draft"
          rows="4"
          maxlength="5000"
          placeholder="Opis aktywności (treść notatki, przebieg rozmowy, podsumowanie maila...)"
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
            <span v-else>Zapisz aktywność</span>
          </button>
        </div>
      </div>
    </div>
  </section>
</template>
