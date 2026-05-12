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
  { id: 'CASH_FLOW', title: 'Dokumenty do pobrania', desc: 'wzory dokumentów i inne', icon: 'document-text', color: 'text-emerald-600', bg: 'bg-emerald-50', border: 'hover:border-emerald-500', image: 'https://images.unsplash.com/photo-1618044733300-9472054094ee?q=80&w=2671&auto=format&fit=crop' },
  { id: 'LEGAL', title: 'Podstawa prawna', desc: 'Bezpieczeństwo i przepisy', icon: 'scale', color: 'text-blue-600', bg: 'bg-blue-50', border: 'hover:border-blue-500', image: 'https://images.unsplash.com/photo-1589829085413-56de8ae18c73?q=80&w=2670&auto=format&fit=crop' },
  { id: 'GRAPHIC', title: 'Schemat działania usługi', desc: 'Wizualizacja modelu', icon: 'presentation-chart-line', color: 'text-indigo-600', bg: 'bg-indigo-50', border: 'hover:border-indigo-500', image: 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?q=80&w=2670&auto=format&fit=crop' },
  { id: 'VIDEO', title: 'Materiały wideo', desc: 'Materiał multimedialny', icon: 'video-camera', color: 'text-red-600', bg: 'bg-red-50', border: 'hover:border-red-500', image: 'https://images.unsplash.com/photo-1492691527719-9d1e07e534b4?q=80&w=2670&auto=format&fit=crop' },
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
  <div class="space-y-3 md:space-y-6">
    <div class="rounded-card p-4 md:p-8 mb-2 md:mb-4 shadow-card-hover border relative overflow-hidden group" style="background: linear-gradient(135deg, #001f3d 0%, #002a52 50%, #003366 100%); border-color: #003366;">
      <!-- Decor -->
      <div class="absolute top-0 right-0 w-64 h-64 bg-stratton-800 rounded-full mix-blend-overlay filter blur-3xl opacity-20 -translate-y-1/2 translate-x-1/2 pointer-events-none"></div>

      <div class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-3 md:gap-6">
        <div class="flex items-center gap-3 md:gap-6">
          <RouterLink to="/app/dashboard" class="hidden md:inline-flex items-center justify-center w-12 h-12 bg-slate-800 border border-slate-700 rounded-md text-slate-400 hover:bg-slate-700 hover:text-white transition-all shadow-sm group">
            <AppIcon name="arrow-left" class="w-5 h-5 transition-transform group-hover:-translate-x-1" />
          </RouterLink>

          <div>
            <h1 class="text-xl md:text-4xl font-serif font-bold text-white tracking-wide leading-tight">Baza Wiedzy</h1>
            <p class="text-slate-400 max-w-xl text-xs md:text-lg mt-1 tracking-tight">Pliki i dokumenty</p>
          </div>
        </div>

        <div class="flex items-center gap-2 md:gap-4 shrink-0 w-full md:w-auto">
          <div v-if="activeCategory" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-800/50 border border-slate-700/50 text-stratton-gold text-xs font-semibold backdrop-blur-sm shrink-0">
            <span>{{ categoryNames[activeCategory] }}</span>
            <button type="button" class="text-slate-400 hover:text-white" @click="activeCategory = null">✕</button>
          </div>
          <div class="relative flex-1 md:w-72 lg:w-96">
            <input v-model="searchQuery" type="text" placeholder="Szukaj w plikach..." class="w-full pl-9 md:pl-12 pr-4 md:pr-6 py-2 md:py-4 bg-slate-800/50 border border-slate-700/50 rounded-xl text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-stratton-gold/50 backdrop-blur-sm transition-all text-sm md:text-base font-bold text-right" />
            <AppIcon name="search" class="absolute left-3 md:left-4 top-1/2 -translate-y-1/2 h-4 md:h-5 w-4 md:w-5 text-slate-400 pointer-events-none" />
          </div>
          <button type="button" class="px-4 md:px-8 py-2 md:py-4 bg-stratton-gold hover:bg-white text-stratton-900 rounded-xl font-bold transition-all shadow-lg shadow-stratton-gold/10 whitespace-nowrap shrink-0 text-sm" @click="openUpload">
            Dodaj plik
          </button>
        </div>
      </div>
    </div>

    <!-- Presentation Tiles Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-8 mb-4 md:mb-8">
      <div
        v-for="tile in presentationTypes"
        :key="tile.id"
        class="crm-tile h-44 group relative overflow-hidden bg-slate-100 border border-slate-200 cursor-pointer"
        :class="searchQuery ? 'opacity-50 hover:opacity-100' : ''"
        @click="activeCategory = tile.id as FileCategory"
      >
        <div class="absolute inset-0 z-0">
          <img :src="(tile as any).image" class="w-full h-full object-cover opacity-20 transition-transform duration-700 group-hover:scale-105" :alt="(tile as any).title" />
          <div class="absolute inset-0 bg-gradient-to-t from-slate-100/90 via-slate-100/40 to-slate-100/20"></div>
        </div>
        <div class="relative z-10 w-full px-4 pt-6 pb-4 h-full flex flex-col justify-between">
          <div class="text-stratton-gold">
            <AppIcon :name="(tile as any).icon" class="w-8 h-8" />
          </div>
          <div>
            <h3 class="crm-tile-title text-xl text-slate-800 mb-1">{{ (tile as any).title }}</h3>
            <p class="crm-tile-desc text-xs text-slate-500 font-medium">{{ (tile as any).desc }}</p>
          </div>
        </div>
      </div>
    </div>

    <div class="space-y-4 md:space-y-8">
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
