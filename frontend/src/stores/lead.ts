import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/api/client'
import { useToastStore } from '@/stores/toast'

export interface Lead {
  id: number
  name: string
  nip: string
  contact_person?: string
  phone?: string
  email?: string
  notes?: string
  status: 'new' | 'processing' | 'qualified' | 'converted' | 'rejected'
  source: string
  created_at: string
  user_id?: number
}

export const useLeadStore = defineStore('lead', () => {
  const leads = ref<Lead[]>([])
  const loading = ref(false)
  const toast = useToastStore()

  const fetchLeads = async (params: any = {}) => {
    loading.value = true
    try {
      const response = await api.get('/leads', { params })
      leads.value = response.data.data // Pagination structure
    } catch (error) {
      console.error('Failed to fetch leads', error)
      toast.error('Błąd pobierania leadów')
    } finally {
      loading.value = false
    }
  }

  const createLead = async (leadData: Partial<Lead>) => {
    try {
      const response = await api.post('/leads', leadData)
      leads.value.unshift(response.data)
      toast.success('Lead dodany pomyślnie')
      return response.data
    } catch (error: any) {
      console.error('Failed to create lead', error)
      if (error.response?.status === 409) {
          toast.warning(error.response.data.message || 'Lead już istnieje')
      } else {
          toast.error('Błąd dodawania leada')
      }
      throw error
    }
  }

  const updateLead = async (id: number, leadData: Partial<Lead>) => {
    try {
      const response = await api.put(`/leads/${id}`, leadData)
      const index = leads.value.findIndex(l => l.id === id)
      if (index !== -1) {
        leads.value[index] = response.data
      }
      toast.success('Lead zaktualizowany')
    } catch (error) {
      console.error('Failed to update lead', error)
      toast.error('Błąd aktualizacji leada')
    }
  }

  const convertLead = async (id: number) => {
    try {
      const response = await api.post(`/leads/${id}/convert`)
      // Remove from list or update status
      const index = leads.value.findIndex(l => l.id === id)
      if (index !== -1) {
         leads.value[index].status = 'converted'
      }
      toast.success('Lead przekonwertowany do Spotkania')
      return response.data
    } catch (error) {
      console.error('Failed to convert lead', error)
      toast.error('Błąd konwersji leada')
      throw error
    }
  }
  
  const qualifyLead = async (id: number, note: string) => {
      // Custom logic if separate endpoint exists, typically update status + note
      // For now using update
      try {
        const response = await api.put(`/leads/${id}`, { status: 'qualified', notes: note })
        const index = leads.value.findIndex(l => l.id === id)
        if (index !== -1) {
          leads.value[index] = response.data
        }
        toast.success('Lead zakwalifikowany')
        return response.data
      } catch (error) {
         console.error('Failed to qualify lead', error)
         toast.error('Błąd kwalifikacji leada')
      }
  }

  const deleteLead = async (id: number) => {
      try {
          await api.delete(`/leads/${id}`)
          leads.value = leads.value.filter(l => l.id !== id)
          toast.success('Lead usunięty')
      } catch (error) {
          toast.error('Błąd usuwania leada')
      }
  }

  return {
    leads,
    loading,
    fetchLeads,
    createLead,
    updateLead,
    convertLead,
    qualifyLead,
    deleteLead
  }
})
