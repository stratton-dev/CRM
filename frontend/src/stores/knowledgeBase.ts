import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '@/api/client'
import { apiBaseUrl } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useDataStore } from '@/stores/data'
import type { KnowledgeFile } from '@/types/models'

type ApiKnowledgeFile = {
  id: number | string
  name: string
  description?: string | null
  category: string
  file_type: string
  file_url: string
  size?: string | null
  added_at?: string | null
}

export const useKnowledgeBaseStore = defineStore('knowledge-base', () => {
  const auth = useAuthStore()
  const dataStore = useDataStore()
  const files = ref<KnowledgeFile[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const resolveFileUrl = (url: string) => {
    if (!url) return ''
    if (url.startsWith('http://') || url.startsWith('https://')) return url
    if (url.startsWith('//')) return `${window.location.protocol}${url}`
    const apiOrigin = apiBaseUrl.replace(/\/api\/?$/, '')
    return `${apiOrigin}${url.startsWith('/') ? '' : '/'}${url}`
  }

  const mapApiFile = (file: ApiKnowledgeFile): KnowledgeFile => ({
    id: String(file.id),
    name: file.name,
    description: file.description || '',
    category: file.category as any,
    fileType: file.file_type as any,
    fileUrl: resolveFileUrl(file.file_url),
    addedDate: file.added_at || new Date().toISOString(),
    size: file.size || '',
  })

  const fetchFiles = async (search?: string) => {
    if (!auth.enabled) {
      files.value = Array.isArray(dataStore.knowledgeFiles) ? dataStore.knowledgeFiles : []
      return
    }
    loading.value = true
    error.value = null
    try {
      const { data } = await api.get('/v1/crm-knowledge-files', {
        params: { per_page: 200, search: search || undefined },
      })
      const list = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
      files.value = list.map(mapApiFile)
    } catch (err: any) {
      error.value = err?.message || 'Nie udało się pobrać plików.'
    } finally {
      loading.value = false
    }
  }

  const uploadFile = async (payload: { file: File; name: string; description?: string; category: string }) => {
    if (!auth.enabled) {
      const ext = payload.file.name.split('.').pop()?.toLowerCase() || 'pdf'
      const newFile: KnowledgeFile = {
        id: `kf_${Date.now()}`,
        name: payload.name,
        description: payload.description || '',
        category: payload.category as any,
        fileType: ext as any,
        fileUrl: '#',
        addedDate: new Date().toISOString(),
        size: `${Math.max(1, Math.round(payload.file.size / 1024))} KB`,
      }
      if (Array.isArray(dataStore.knowledgeFiles)) {
        dataStore.knowledgeFiles = [newFile, ...dataStore.knowledgeFiles]
      }
      files.value = [newFile, ...files.value]
      return
    }

    const form = new FormData()
    form.append('file', payload.file)
    form.append('name', payload.name)
    form.append('category', payload.category)
    if (payload.description) form.append('description', payload.description)

    const { data } = await api.post('/v1/crm-knowledge-files', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    const mapped = mapApiFile(data)
    files.value = [mapped, ...files.value]
  }

  const deleteFile = async (id: string) => {
    if (!auth.enabled) {
      files.value = files.value.filter((item) => item.id !== id)
      if (Array.isArray(dataStore.knowledgeFiles)) {
        dataStore.knowledgeFiles = dataStore.knowledgeFiles.filter((item) => String(item.id) !== id)
      }
      return
    }
    await api.delete(`/v1/crm-knowledge-files/${id}`)
    files.value = files.value.filter((item) => item.id !== id)
  }

  return { files, loading, error, fetchFiles, uploadFile, deleteFile }
})
