<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { api } from '@/api/client'

interface KbDocument {
  id: number
  title: string
  original_filename: string
  status: 'pending' | 'processing' | 'ready' | 'failed'
  chunks_count: number
  file_size: number
  error_message: string | null
  created_at: string
  uploader?: { id: number; name: string }
}

const documents   = ref<KbDocument[]>([])
const isLoading   = ref(false)
const isUploading = ref(false)
const uploadTitle = ref('')
const fileInput   = ref<HTMLInputElement | null>(null)
const selectedFile = ref<File | null>(null)
const error       = ref<string | null>(null)
const success     = ref<string | null>(null)

onMounted(() => loadDocuments())

async function loadDocuments() {
  isLoading.value = true
  try {
    const { data } = await api.get('/v1/admin/knowledge-base')
    documents.value = data.data ?? []
  } catch (e) {
    error.value = 'Nie udało się załadować dokumentów.'
  } finally {
    isLoading.value = false
  }
}

async function uploadDocument() {
  const file = selectedFile.value
  if (!file) return

  isUploading.value = true
  error.value = null
  success.value = null

  const formData = new FormData()
  formData.append('file', file)
  if (uploadTitle.value) formData.append('title', uploadTitle.value)

  try {
    await api.post('/v1/admin/knowledge-base', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      timeout: 300000,
    })
    success.value = 'Dokument przetworzony i dodany do bazy wiedzy.'
    uploadTitle.value = ''
    selectedFile.value = null
    if (fileInput.value) fileInput.value.value = ''
    await loadDocuments()
  } catch (e: any) {
    const msg = e?.response?.data?.message ?? e?.message ?? 'Błąd uploadu dokumentu.'
    error.value = msg
  } finally {
    isUploading.value = false
  }
}

async function deleteDocument(id: number, title: string) {
  if (!confirm(`Usunąć dokument "${title}" z bazy wiedzy?`)) return
  try {
    await api.delete(`/v1/admin/knowledge-base/${id}`)
    documents.value = documents.value.filter(d => d.id !== id)
    success.value = 'Dokument usunięty.'
  } catch (e) {
    error.value = 'Nie udało się usunąć dokumentu.'
  }
}

async function refreshStatus(doc: KbDocument) {
  try {
    const { data } = await api.get(`/v1/admin/knowledge-base/${doc.id}/status`)
    const idx = documents.value.findIndex(d => d.id === doc.id)
    if (idx !== -1) {
      documents.value[idx] = { ...documents.value[idx], ...data.data }
    }
  } catch {}
}

function formatSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1048576).toFixed(1)} MB`
}

function statusLabel(status: string): string {
  return { pending: 'Oczekuje', processing: 'Przetwarza...', ready: 'Gotowy', failed: 'Błąd' }[status] ?? status
}

function statusColor(status: string): string {
  return {
    pending:    'bg-yellow-100 text-yellow-700',
    processing: 'bg-blue-100 text-blue-700',
    ready:      'bg-green-100 text-green-700',
    failed:     'bg-red-100 text-red-700',
  }[status] ?? 'bg-gray-100 text-gray-700'
}
</script>

<template>
  <div class="p-6 max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Baza Wiedzy AI</h1>
    <p class="text-gray-600 mb-6">
      Dokumenty PDF załadowane tutaj będą używane przez asystenta AI do odpowiadania na pytania
      handlowców i klientów. Tylko administratorzy mogą zarządzać bazą wiedzy.
    </p>

    <!-- Alert sukces/błąd -->
    <div v-if="success" class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm flex items-center justify-between">
      {{ success }}
      <button @click="success = null" class="text-green-500 hover:text-green-700">✕</button>
    </div>
    <div v-if="error" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm flex items-center justify-between">
      {{ error }}
      <button @click="error = null" class="text-red-500 hover:text-red-700">✕</button>
    </div>

    <!-- Upload -->
    <div class="bg-white rounded-xl border border-gray-200 p-5 mb-6">
      <h2 class="font-semibold text-gray-700 mb-4">Dodaj dokument PDF</h2>
      <div class="space-y-3">
        <div>
          <label class="block text-sm text-gray-600 mb-1">Tytuł dokumentu (opcjonalnie)</label>
          <input
            v-model="uploadTitle"
            type="text"
            placeholder="np. Podstawy prawne Eliton Prime™"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400"
          />
        </div>
        <div>
          <label class="block text-sm text-gray-600 mb-1">Plik PDF (max 20 MB)</label>
          <input
            ref="fileInput"
            type="file"
            accept=".pdf"
            @change="selectedFile = ($event.target as HTMLInputElement).files?.[0] ?? null"
            class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
          />
        </div>
        <button
          @click="uploadDocument"
          :disabled="isUploading || !selectedFile"
          class="w-full py-2 px-4 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 transition-colors"
        >
          {{ isUploading ? 'Przetwarzam PDF... (może zająć do 1 min)' : 'Załaduj do bazy wiedzy' }}
        </button>
      </div>
    </div>

    <!-- Lista dokumentów -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="font-semibold text-gray-700">Załadowane dokumenty ({{ documents.length }})</h2>
        <button @click="loadDocuments" class="text-sm text-blue-600 hover:text-blue-800">Odśwież</button>
      </div>

      <div v-if="isLoading" class="p-8 text-center text-gray-400">Ładowanie...</div>
      <div v-else-if="documents.length === 0" class="p-8 text-center text-gray-400">
        Baza wiedzy jest pusta. Załaduj pierwsze dokumenty PDF.
      </div>

      <div v-else class="divide-y divide-gray-100">
        <div
          v-for="doc in documents"
          :key="doc.id"
          class="px-5 py-4 flex items-start gap-4"
        >
          <!-- Ikona PDF -->
          <div class="w-10 h-10 bg-red-50 rounded-lg flex items-center justify-center flex-shrink-0">
            <svg class="w-6 h-6 text-red-500" fill="currentColor" viewBox="0 0 24 24">
              <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
            </svg>
          </div>

          <!-- Info -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="font-medium text-gray-800 text-sm">{{ doc.title }}</span>
              <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', statusColor(doc.status)]">
                {{ statusLabel(doc.status) }}
              </span>
            </div>
            <div class="text-xs text-gray-400 mt-0.5">
              {{ doc.original_filename }} · {{ formatSize(doc.file_size) }}
              · {{ doc.chunks_count }} fragmentów
              <span v-if="doc.uploader"> · dodał: {{ doc.uploader.name }}</span>
            </div>
            <div v-if="doc.error_message" class="text-xs text-red-500 mt-1">{{ doc.error_message }}</div>
          </div>

          <!-- Akcje -->
          <div class="flex items-center gap-2 flex-shrink-0">
            <button
              v-if="doc.status === 'processing' || doc.status === 'pending'"
              @click="refreshStatus(doc)"
              class="text-xs text-blue-600 hover:text-blue-800"
            >
              ↻
            </button>
            <button
              @click="deleteDocument(doc.id, doc.title)"
              class="text-xs text-red-500 hover:text-red-700 px-2 py-1 rounded hover:bg-red-50 transition-colors"
            >
              Usuń
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
