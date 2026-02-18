import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '@/api/client'

export interface Announcement {
  id: number
  title: string
  content: string
  category: string
  target_roles: string[] | null
  expires_at: string | null
  author?: { id: number; name: string }
  created_at: string
  image_url: string | null
  attachment_url: string | null
  attachment_name: string | null
}

export const useNewsStore = defineStore('news', () => {
  const feed = ref<Announcement[]>([])
  const adminItems = ref<Announcement[]>([])
  const isLoading = ref(false)

  const fetchFeed = async () => {
    isLoading.value = true
    try {
      const res = await api.get('/v1/announcements')
      feed.value = res.data
    } catch (e) {
      console.error('Failed to fetch news feed', e)
    } finally {
      isLoading.value = false
    }
  }

  const fetchAdminItems = async () => {
    isLoading.value = true
    try {
      const res = await api.get('/v1/announcements/manage')
      adminItems.value = res.data
    } catch (e) {
      console.error('Failed to fetch admin news', e)
    } finally {
      isLoading.value = false
    }
  }

  const create = async (payload: Partial<Announcement> | FormData) => {
    // If payload is FormData, axios handles headers automatically
    const config = payload instanceof FormData ? { headers: { 'Content-Type': 'multipart/form-data' } } : {}
    await api.post('/v1/announcements', payload, config)
    await fetchAdminItems()
  }

  const update = async (id: number, payload: Partial<Announcement> | FormData) => {
    const config = payload instanceof FormData ? { headers: { 'Content-Type': 'multipart/form-data' } } : {}
    // If updating with files, we must use POST with _method=PATCH/PUT because PHP/Laravel cannot read multipart form data in PUT requests
    if (payload instanceof FormData) {
        payload.append('_method', 'PATCH')
        await api.post(`/v1/announcements/${id}`, payload, config)
    } else {
        await api.patch(`/v1/announcements/${id}`, payload)
    }
    await fetchAdminItems()
  }

  const remove = async (id: number) => {
    await api.delete(`/v1/announcements/${id}`)
    await fetchAdminItems()
  }

  return {
    feed,
    adminItems,
    isLoading,
    fetchFeed,
    fetchAdminItems,
    create,
    update,
    remove
  }
})
