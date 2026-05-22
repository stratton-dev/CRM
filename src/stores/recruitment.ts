import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useSessionStore } from '@/stores/session'

export interface CandidateAddress {
  street: string
  house_number: string
  apartment_number?: string
  postal_code: string
  city: string
}

export interface CandidateDocument {
  id: number
  type: string
  status: string
}

export interface Candidate {
  id: number
  type: 'person' | 'sole_proprietorship' | 'company'
  first_name?: string
  last_name?: string
  company_name?: string
  nip?: string
  regon?: string
  krs?: string
  pesel?: string
  email: string
  phone: string
  address_json?: CandidateAddress
  status: string
  documents?: CandidateDocument[]
  created_at: string
}

export const useRecruitmentStore = defineStore('recruitment', () => {
  const auth = useAuthStore()
  const session = useSessionStore()
  const candidates = ref<Candidate[]>([])
  const loading = ref(false)
  const gusLoading = ref(false)

  const fetchCandidates = async () => {
    if (session.isLeadowiec || auth.user?.role === 'LEADOWIEC') return
    loading.value = true
    try {
      const { data } = await api.get('/v1/candidates')
      candidates.value = data.data // Paginated response
    } catch (e) {
      console.error(e)
    } finally {
      loading.value = false
    }
  }

  const addCandidate = async (candidate: Partial<Candidate>, documents: string[]) => {
    loading.value = true
    try {
      const { data } = await api.post('/v1/candidates', {
        ...candidate,
        documents
      })
      candidates.value.unshift(data)
      return data
    } finally {
      loading.value = false
    }
  }

  const updateCandidate = async (id: number, data: Partial<Candidate>) => {
    loading.value = true
    try {
      const response = await api.put(`/v1/candidates/${id}`, data)
      const updated = response.data
      const index = candidates.value.findIndex(c => c.id === id)
      if (index !== -1) {
        // Preserve documents as the update endpoint doesn't return them or update them logic wise here
        candidates.value[index] = { ...candidates.value[index], ...updated }
      }
      return updated
    } finally {
        loading.value = false
    }
  }

  const deleteCandidate = async (id: number) => {
    await api.delete(`/v1/candidates/${id}`)
    candidates.value = candidates.value.filter(c => c.id !== id)
  }

  const fetchGusData = async (nip: string) => {
      gusLoading.value = true
      try {
          const { data } = await api.get(`/v1/gus?nip=${nip}`)
          return data
      } finally {
          gusLoading.value = false
      }
  }

  return {
    candidates,
    loading,
    gusLoading,
    fetchCandidates,
    addCandidate,
    updateCandidate,
    deleteCandidate,
    fetchGusData
  }
})
