<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useKnowledgeBaseStore } from '@/stores/knowledgeBase'
import { useToastStore } from '@/stores/toast'
import { useSessionStore } from '@/stores/session'
import { api } from '@/api/client'
import AppIcon from '@/components/AppIcon.vue'
import type { FileCategory, KnowledgeFile } from '@/types/models'

const knowledgeBase = useKnowledgeBaseStore()
const { files: knowledgeFiles } = storeToRefs(knowledgeBase)
const toast = useToastStore()
const session = useSessionStore()
const searchQuery = ref('')
const activeCategory = ref<FileCategory | null>(null)
const showUpload = ref(false)
const uploadName = ref('')
const uploadDescription = ref('')
const uploadCategory = ref<FileCategory>('CASH_FLOW')
const uploadFile = ref<File | null>(null)
const uploadError = ref('')
const uploadLoading = ref(false)

const categoryOrder: FileCategory[] = ['CASH_FLOW', 'LEGAL', 'GRAPHIC', 'VIDEO']
const categoryNames: Record<FileCategory, string> = {
  UMOWY: 'Umowy',
  PROCESY: 'Procesy',
  PRAWO: 'Prawo',
  MARKETING: 'Marketing',
  CASH_FLOW: 'Analiza Cash Flow',
  LEGAL: 'Kwestie Prawne',
  GRAPHIC: 'Graficzne Przedstawienie',
  VIDEO: 'Film Wideo',
}

const presentationTypes = [
  { id: 'CASH_FLOW', title: 'Dokumenty do pobrania', desc: 'Analiza finansowa', icon: 'chart-pie', color: 'text-emerald-600', bg: 'bg-emerald-50', border: 'hover:border-emerald-500' },
  { id: 'LEGAL', title: 'Podstawa prawna', desc: 'Bezpieczeństwo i przepisy', icon: 'scale', color: 'text-blue-600', bg: 'bg-blue-50', border: 'hover:border-blue-500' },
  { id: 'GRAPHIC', title: 'Schemat działania usługi', desc: 'Wizualizacja modelu', icon: 'presentation-chart-line', color: 'text-indigo-600', bg: 'bg-indigo-50', border: 'hover:border-indigo-500' },
  { id: 'VIDEO', title: 'Materiały wideo', desc: 'Materiał multimedialny', icon: 'video-camera', color: 'text-red-600', bg: 'bg-red-50', border: 'hover:border-red-500' },
]

const safeFiles = computed<KnowledgeFile[]>(() => (Array.isArray(knowledgeFiles.value) ? knowledgeFiles.value : []))
const canDeleteKnowledge = computed(() => session.isRole(['ADMIN']))

const downloadKnowledgeFile = async (file: KnowledgeFile) => {
  try {
    const response = await api.get(`/v1/crm-knowledge-files/${file.id}/download`, { responseType: 'blob' })
    const blob = new Blob([response.data], { type: response.data?.type || 'application/octet-stream' })
    const url = URL.createObjectURL(blob)
    window.open(url, '_blank', 'noopener,noreferrer')
    const link = document.createElement('a')
    link.href = url
    link.download = file.name || 'plik'
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
  } catch (error: any) {
    toast.error(error?.response?.data?.message || error?.message || 'Nie udało się pobrać pliku.')
  }
}

const filteredFiles = computed(() => {
  const categoryFilter = activeCategory.value
  const query = searchQuery.value.toLowerCase()
  return safeFiles.value.filter((file) => {
    const matchesCategory = categoryFilter ? file.category === categoryFilter : true
    if (!matchesCategory) return false
    if (!query) return true
    return file.name.toLowerCase().includes(query) || file.description.toLowerCase().includes(query)
  })
})

const categorizedFiles = computed(() => {
  const grouped = new Map<FileCategory, KnowledgeFile[]>()
  categoryOrder.forEach((category) => grouped.set(category, []))

  filteredFiles.value.forEach((file) => {
    if (grouped.has(file.category)) grouped.get(file.category)!.push(file)
  })

  return grouped
})

const getFileTypeIcon = (fileType: string) => {
  switch (fileType) {
    case 'pdf':
      return 'document-text'
    case 'docx':
      return 'document-text'
    case 'xlsx':
      return 'chart-bar'
    case 'pptx':
      return 'chart-bar'
    default:
      return 'folder'
  }
}

const allowedExtensions = ['pdf', 'docx', 'xlsx', 'pptx']

const pickFile = (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0] || null
  uploadFile.value = file
  uploadError.value = ''
  if (file && !uploadName.value) {
    uploadName.value = file.name
  }
}

const resetUpload = () => {
  uploadName.value = ''
  uploadDescription.value = ''
  uploadCategory.value = 'CASH_FLOW'
  uploadFile.value = null
  uploadError.value = ''
  uploadLoading.value = false
}

const openUpload = () => {
  resetUpload()
  showUpload.value = true
}

const closeUpload = () => {
  showUpload.value = false
}

const handleUpload = async () => {
  if (!uploadFile.value) {
    uploadError.value = 'Wybierz plik do wgrania.'
    return
  }
  if (!uploadName.value.trim()) {
    uploadError.value = 'Podaj nazwę pliku.'
    return
  }
  const ext = uploadFile.value.name.split('.').pop()?.toLowerCase() || ''
  if (!allowedExtensions.includes(ext)) {
    uploadError.value = `Obsługiwane formaty: ${allowedExtensions.join(', ')}.`
    return
  }
  uploadLoading.value = true
  uploadError.value = ''
  try {
    await knowledgeBase.uploadFile({
      file: uploadFile.value,
      name: uploadName.value.trim(),
      description: uploadDescription.value.trim(),
      category: uploadCategory.value,
    })
    toast.success('Plik został dodany.')
    closeUpload()
  } catch (error: any) {
    uploadError.value = error?.response?.data?.message || error?.message || 'Nie udało się wgrać pliku.'
  } finally {
    uploadLoading.value = false
  }
}

const deleteKnowledgeFile = async (file: KnowledgeFile) => {
  if (!canDeleteKnowledge.value) {
    toast.warning('Tylko administrator może usuwać pliki z bazy wiedzy.')
    return
  }
  if (!window.confirm(`Czy na pewno usunąć plik "${file.name}"?`)) return
  try {
    await knowledgeBase.deleteFile(file.id)
    toast.success('Plik został usunięty.')
  } catch (error: any) {
    toast.error(error?.response?.data?.message || error?.message || 'Nie udało się usunąć pliku.')
  }
}

onMounted(() => {
  knowledgeBase.fetchFiles()
})
</script>

<template>
  <div class="space-y-6">
    <div class="mb-4">
      <RouterLink to="/app/dashboard" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-all shadow-sm group">
        <AppIcon name="arrow-left" class="w-4 h-4 transition-transform group-hover:-translate-x-1" />
        <span class="text-xs font-bold uppercase tracking-widest">Powrót</span>
      </RouterLink>
    </div>
    <div class="flex justify-between items-center">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Baza Wiedzy</h1>
        <p class="text-sm text-gray-500">Centralne repozytorium plików i dokumentów.</p>
      </div>
      <div class="flex items-center gap-3">
        <div v-if="activeCategory" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-sky-50 border border-sky-200 text-sky-700 text-xs font-semibold">
          <span>{{ categoryNames[activeCategory] }}</span>
          <button type="button" class="text-sky-500 hover:text-sky-700" @click="activeCategory = null">✕</button>
        </div>
        <div class="relative w-full max-w-sm">
          <input v-model="searchQuery" type="text" placeholder="Szukaj w plikach..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-sky-500 focus:border-sky-500" />
          <AppIcon name="search" class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
        </div>
        <button type="button" class="px-4 py-2 bg-sky-600 text-white rounded-md text-sm font-semibold shadow hover:bg-sky-700" @click="openUpload">
          Dodaj plik
        </button>
      </div>
    </div>

    <!-- Presentation Tiles Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
      <div 
        v-for="tile in presentationTypes" 
        :key="tile.id"
        class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 cursor-pointer transition-all hover:-translate-y-1 hover:shadow-lg group"
        :class="searchQuery ? 'opacity-50 hover:opacity-100' : ''"
        @click="activeCategory = tile.id as FileCategory"
      >
         <div class="flex items-start justify-between mb-4">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center transition-colors" :class="[tile.bg, tile.color]">
               <AppIcon :name="tile.icon" class="w-6 h-6" />
            </div>
            <div class="w-8 h-8 rounded-full bg-slate-50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
               <AppIcon name="arrow-right" class="w-4 h-4 text-slate-400" />
            </div>
         </div>
         <h3 class="font-bold text-slate-800 text-lg mb-1">{{ tile.title }}</h3>
         <p class="text-xs text-slate-400">{{ tile.desc }}</p>
      </div>
    </div>

    <div class="space-y-8">
      <section v-for="category in categoryOrder" :key="category">
        <template v-if="categorizedFiles.get(category)?.length">
          <h2 class="text-lg font-semibold text-gray-800 border-b-2 border-sky-500 pb-2 mb-4">
            {{ categoryNames[category] }}
          </h2>
          <div class="bg-white shadow rounded-lg border border-gray-200 overflow-hidden">
            <ul class="divide-y divide-gray-200">
              <li v-for="file in categorizedFiles.get(category)" :key="file?.id" class="p-4 hover:bg-gray-50 flex items-center justify-between">
                <div class="flex items-center">
                  <AppIcon :name="getFileTypeIcon(file.fileType)" class="w-6 h-6 mr-4 text-sky-600" />
                  <div>
                    <p class="text-sm font-bold text-gray-900">{{ file.name }}</p>
                    <p class="text-xs text-gray-500">{{ file.description }}</p>
                  </div>
                </div>
                <div class="flex items-center space-x-4">
                  <span class="text-xs text-gray-400 font-mono">{{ file.size }}</span>
                  <button type="button" class="px-3 py-1.5 bg-sky-600 text-white rounded-md text-xs font-bold hover:bg-sky-700 transition-colors shadow-sm" @click="downloadKnowledgeFile(file)">
                    Pobierz
                  </button>
                  <button
                    v-if="canDeleteKnowledge"
                    type="button"
                    class="px-3 py-1.5 bg-red-500 text-white rounded-md text-xs font-bold hover:bg-red-600 transition-colors shadow-sm"
                    @click="deleteKnowledgeFile(file)"
                  >
                    Usuń
                  </button>
                </div>
              </li>
            </ul>
          </div>
        </template>
      </section>
    </div>

    <div v-if="showUpload" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="closeUpload"></div>
      <div class="relative w-full max-w-xl bg-white rounded-xl shadow-2xl border border-gray-200">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
          <h3 class="text-lg font-bold text-gray-800">Dodaj plik do bazy wiedzy</h3>
          <button type="button" class="text-gray-400 hover:text-gray-700" @click="closeUpload">✕</button>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="text-xs font-semibold text-gray-500 uppercase">Nazwa pliku</label>
            <input v-model="uploadName" type="text" class="mt-1 w-full border border-gray-300 rounded px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="text-xs font-semibold text-gray-500 uppercase">Opis</label>
            <textarea v-model="uploadDescription" rows="3" class="mt-1 w-full border border-gray-300 rounded px-3 py-2 text-sm"></textarea>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="text-xs font-semibold text-gray-500 uppercase">Kategoria</label>
              <select v-model="uploadCategory" class="mt-1 w-full border border-gray-300 rounded px-3 py-2 text-sm">
                <option v-for="category in categoryOrder" :key="category" :value="category">
                  {{ categoryNames[category] }}
                </option>
              </select>
            </div>
            <div>
              <label class="text-xs font-semibold text-gray-500 uppercase">Plik</label>
              <input type="file" accept=".pdf,.docx,.xlsx,.pptx" class="mt-1 w-full text-sm" @change="pickFile" />
              <p v-if="uploadFile" class="text-xs text-gray-500 mt-1">{{ uploadFile.name }}</p>
            </div>
          </div>
          <p v-if="uploadError" class="text-sm text-red-600">{{ uploadError }}</p>
        </div>
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50">
          <button type="button" class="px-4 py-2 text-sm border border-gray-300 rounded bg-white hover:bg-gray-100" @click="closeUpload">Anuluj</button>
          <button type="button" class="px-4 py-2 text-sm bg-sky-600 text-white rounded font-semibold hover:bg-sky-700 disabled:opacity-60" :disabled="uploadLoading" @click="handleUpload">
            {{ uploadLoading ? 'Wgrywam...' : 'Zapisz plik' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
