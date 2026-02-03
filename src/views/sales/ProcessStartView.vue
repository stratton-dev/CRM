<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useRoute, useRouter } from 'vue-router'
import AppIcon from '@/components/AppIcon.vue'
import { api, apiBaseUrl } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useSessionStore } from '@/stores/session'
import { useStructureStore } from '@/stores/structure'
import { useToastStore } from '@/stores/toast'
import { useKnowledgeBaseStore } from '@/stores/knowledgeBase'
import type { KnowledgeFile } from '@/types/models'
import { VueFilesPreview } from 'vue-files-preview'
import 'vue-files-preview/lib/style.css'

const auth = useAuthStore()
const session = useSessionStore()
const structure = useStructureStore()
const toast = useToastStore()
const { currentUser } = storeToRefs(session)
const router = useRouter()
const route = useRoute()
const knowledgeBase = useKnowledgeBaseStore()
const { files: knowledgeFiles } = storeToRefs(knowledgeBase)

const isProcessActive = ref(false)
const step = ref(1)
const sessionId = ref('')

const companyData = ref({
  nip: '',
  name: '',
  street: '',
  zip: '',
  city: '',
})

const contactDraft = ref({
  name: '',
  position: '',
  phone: '',
  email: '',
  is_decision_maker: false,
})
const contactDrafts = ref<
  Array<{
    tempId: string
    name: string
    position?: string
    phone?: string
    email?: string
    is_decision_maker: boolean
    saved?: boolean
  }>
>([])
const existingContacts = ref<
  Array<{ id: string; name: string; position?: string | null; phone?: string | null; email?: string | null; is_decision_maker?: boolean | null }>
>([])
const contactsLoading = ref(false)
const isContactEditOpen = ref(false)
const contactEdit = ref({
  mode: 'existing' as 'existing' | 'draft',
  id: '',
  tempId: '',
  name: '',
  position: '',
  phone: '',
  email: '',
  is_decision_maker: false,
})

const consentList = ref<Array<{ id: string; code: string; title: string; description: string; required: boolean; file_url?: string | null; file_name?: string | null }>>([])
const consentAccepted = ref<Record<string, boolean>>({})

const openConsentFile = async (consent: { id: string; file_name?: string | null }, download = false) => {
  try {
    const { data } = await api.get(`/v1/consents/${consent.id}/file`, {
      params: download ? { download: 1 } : undefined,
      responseType: 'blob',
    })
    const url = window.URL.createObjectURL(data)
    if (download) {
      const a = document.createElement('a')
      a.href = url
      a.download = consent.file_name || 'consent.pdf'
      a.click()
      window.URL.revokeObjectURL(url)
      return
    }
    window.open(url, '_blank')
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się pobrać pliku.'
    toast.warning(message)
  }
}

const analysis = ref({
  industry: '',
  employees: null as number | null,
  contractType: '',
  avgEarnings: null as number | null,
  zusCost: null as number | null,
  isInvesting: null as boolean | null,
  benefits: '',
  projectParticipation: '',
  pastSavings: '',
  currentSavings: '',
  plannedInvestments: '',
  declaredSavings: '',
  debts: '',
  vatModel: '',
})

const userName = computed(() => currentUser.value?.name || 'Użytkowniku')
const isFetchingGus = ref(false)
const isSavingStep = ref(false)
const gusError = ref<string | null>(null)
const nipBlocked = ref(false)
const nipBlockMessage = ref<string | null>(null)
const isCheckingNip = ref(false)
const clientId = ref<string | null>(null)
const meetingId = ref<string | null>(null)
const meetingAnalysisId = ref<string | null>(null)
const isLoadingExisting = ref(false)
const calcTarget = ref<'quick' | 'detailed'>('quick')
const knowledgeSearch = ref('')
const showPreview = ref(false)
const previewUrl = ref('')
const previewName = ref('')
const previewIndex = ref<number | null>(null)
const previewContainer = ref<HTMLElement | null>(null)
const previewFile = ref<File | null>(null)
const previewKey = ref(0)

const safeKnowledgeFiles = computed<KnowledgeFile[]>(() => (Array.isArray(knowledgeFiles.value) ? knowledgeFiles.value : []))
const filteredKnowledgeFiles = computed(() => {
  const query = knowledgeSearch.value.trim().toLowerCase()
  if (!query) return safeKnowledgeFiles.value
  return safeKnowledgeFiles.value.filter((file) => {
    return file.name.toLowerCase().includes(query) || file.description.toLowerCase().includes(query)
  })
})

const buildKnowledgeDownloadUrl = (file: KnowledgeFile) => {
  return `${apiBaseUrl}/v1/crm-knowledge-files/${file.id}/download`
}

const normalizeFileExt = (value?: string | null) => {
  const raw = String(value || '').trim().toLowerCase()
  if (!raw) return ''
  if (raw.startsWith('.')) return raw.slice(1)
  if (raw.includes('/')) {
    const mimeMap: Record<string, string> = {
      'application/pdf': 'pdf',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'docx',
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'xlsx',
      'application/vnd.openxmlformats-officedocument.presentationml.presentation': 'pptx',
      'text/plain': 'txt',
      'text/markdown': 'md',
      'image/jpeg': 'jpg',
      'image/png': 'png',
    }
    return mimeMap[raw] || ''
  }
  return raw
}

const extractExtFromName = (name?: string | null) => {
  const ext = (name || '').split('.').pop()?.toLowerCase() || ''
  return ext === name?.toLowerCase() ? '' : ext
}

const extractExtFromUrl = (url?: string | null) => {
  if (!url) return ''
  const clean = url.split('?')[0] || ''
  const ext = clean.split('.').pop()?.toLowerCase() || ''
  return ext === clean.toLowerCase() ? '' : ext
}

const buildPreviewFilename = (file: KnowledgeFile, ext: string) => {
  const original = file.name || ''
  if (!ext) return original || 'plik'
  if (!original) return `plik.${ext}`
  if (original.toLowerCase().endsWith(`.${ext}`)) return original
  return `${original}.${ext}`
}

const setPreviewUrl = (url: string) => {
  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value)
  }
  previewUrl.value = url
}

const openKnowledgeFile = async (file: KnowledgeFile, index?: number) => {
  if (!file.fileUrl || file.fileUrl === '#') {
    toast.warning('Brak podpiętego pliku do podglądu.')
    return
  }
  const supported = ['pdf', 'docx', 'xlsx', 'pptx', 'txt', 'md', 'markdown', 'png', 'jpg', 'jpeg']
  const typeFromMeta = normalizeFileExt(file.fileType)
  const typeFromName = extractExtFromName(file.name)
  const typeFromUrl = extractExtFromUrl(file.fileUrl)
  const fileType = (typeFromMeta || typeFromName || typeFromUrl || '').toLowerCase()
  if (supported.includes(fileType)) {
    try {
      const mimeMap: Record<string, string> = {
        pdf: 'application/pdf',
        docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        pptx: 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        txt: 'text/plain',
        md: 'text/markdown',
        markdown: 'text/markdown',
        jpg: 'image/jpeg',
        jpeg: 'image/jpeg',
        png: 'image/png',
      }
      const response = await api.get(`/v1/crm-knowledge-files/${file.id}/download`, { responseType: 'blob' })
      const blobData = response.data as Blob
      const mimeType = mimeMap[fileType] || blobData.type || 'application/octet-stream'
      const blob = new Blob([blobData], { type: mimeType })
      const filename = buildPreviewFilename(file, fileType)
      previewFile.value = new File([blob], filename, { type: blob.type })
      setPreviewUrl(URL.createObjectURL(blob))
      previewName.value = file.name
      previewIndex.value = typeof index === 'number' ? index : null
      showPreview.value = true
      previewKey.value += 1
      return
    } catch (error) {
      console.error(error)
      toast.warning('Nie udało się wczytać pliku do podglądu. Użyj opcji Zapisz.')
      return
    }
  }

  toast.warning('Ten format nie jest obsługiwany w podglądzie. Użyj opcji Zapisz.')
}

const downloadKnowledgeFile = (file: KnowledgeFile) => {
  if (!file.fileUrl || file.fileUrl === '#') {
    toast.warning('Brak podpiętego pliku do pobrania.')
    return
  }
  api.get(`/v1/crm-knowledge-files/${file.id}/download`, { responseType: 'blob' })
    .then((response) => {
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
    })
    .catch((error) => {
      console.error(error)
      toast.error('Nie udało się pobrać pliku.')
    })
}


const previewNext = (direction: 1 | -1) => {
  if (previewIndex.value === null) return
  const list = filteredKnowledgeFiles.value
  if (list.length === 0) return
  const nextIndex = (previewIndex.value + direction + list.length) % list.length
  const nextFile = list[nextIndex]
  if (!nextFile) return
  void openKnowledgeFile(nextFile, nextIndex)
}

const togglePreviewFullscreen = async () => {
  const el = previewContainer.value
  if (!el) return
  if (document.fullscreenElement) {
    await document.exitFullscreen()
  } else {
    await el.requestFullscreen()
  }
}

const closePreview = () => {
  showPreview.value = false
  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value)
  }
  previewUrl.value = ''
  previewName.value = ''
  previewFile.value = null
  previewIndex.value = null
}

const normalizeNip = (nip: string) => nip.replace(/\D/g, '')

const checkNipReservation = async (value: string) => {
  if (!auth.enabled) return false
  const nip = normalizeNip(value)
  if (!nip) {
    nipBlocked.value = false
    nipBlockMessage.value = null
    return false
  }
  isCheckingNip.value = true
  try {
    const { data } = await api.get('/v1/clients/check-nip', { params: { nip } })
    const reserved = Boolean(data?.reserved)
    nipBlocked.value = reserved
    if (reserved) {
      const ownerName = data?.owner_name ? ` (${data.owner_name})` : ''
      const validUntil = data?.valid_until ? ` do ${data.valid_until}` : ''
      nipBlockMessage.value = `NIP jest już zarezerwowany${ownerName}${validUntil}.`
    } else {
      nipBlockMessage.value = null
    }
    return reserved
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się sprawdzić rezerwacji NIP.'
    toast.error(message)
    return false
  } finally {
    isCheckingNip.value = false
  }
}

const fetchCompanyByNip = async () => {
  if (!auth.enabled) {
    toast.warning('Tryb API jest wyłączony.')
    return
  }
  const nip = normalizeNip(companyData.value.nip)
  if (!nip) {
    toast.warning('Podaj numer NIP.')
    return
  }
  if (await checkNipReservation(nip)) {
    toast.warning(nipBlockMessage.value || 'NIP jest już zarezerwowany.')
    return
  }
  isFetchingGus.value = true
  gusError.value = null
  try {
    const data = await structure.fetchGusData(nip)
    companyData.value = {
      ...companyData.value,
      nip,
      name: data.name || companyData.value.name,
      street: `${data.street || ''} ${data.houseNr || ''}${data.aptNr ? `/${data.aptNr}` : ''}`.trim(),
      zip: data.zipCode || companyData.value.zip,
      city: data.city || companyData.value.city,
    }
    toast.success('Pobrano dane firmy z GUS.')
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się pobrać danych z GUS.'
    gusError.value = message
    toast.error(message)
  } finally {
    isFetchingGus.value = false
  }
}

const resolveClientIdByNip = async (nip: string) => {
  const { data } = await api.get('/v1/clients', { params: { search: nip, per_page: 1 } })
  const list = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
  return list.length > 0 ? String(list[0].id) : null
}

const fetchClientContacts = async (targetClientId: string) => {
  if (!auth.enabled) return
  contactsLoading.value = true
  try {
    const { data } = await api.get(`/v1/clients/${targetClientId}/contacts`)
    existingContacts.value = Array.isArray(data)
      ? data.map((item: any) => ({
          id: String(item.id),
          name: String(item.name || ''),
          position: item.position || null,
          phone: item.phone || null,
          email: item.email || null,
          is_decision_maker: item.is_decision_maker ?? item.isDecisionMaker ?? null,
        }))
      : []
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się pobrać kontaktów.'
    toast.error(message)
  } finally {
    contactsLoading.value = false
  }
}

const resetContactDraft = () => {
  contactDraft.value = {
    name: '',
    position: '',
    phone: '',
    email: '',
    is_decision_maker: false,
  }
}

const addContactDraft = () => {
  if (!contactDraft.value.name.trim()) {
    toast.warning('Uzupełnij imię i nazwisko kontaktu.')
    return
  }
  contactDrafts.value.push({
    tempId: Math.random().toString(36).slice(2, 9),
    name: contactDraft.value.name.trim(),
    position: contactDraft.value.position || '',
    phone: contactDraft.value.phone || '',
    email: contactDraft.value.email || '',
    is_decision_maker: Boolean(contactDraft.value.is_decision_maker),
  })
  resetContactDraft()
}

const openContactEdit = (contact: { id?: string; tempId?: string; name: string; position?: string | null; phone?: string | null; email?: string | null; is_decision_maker?: boolean | null }, mode: 'existing' | 'draft') => {
  contactEdit.value = {
    mode,
    id: contact.id ? String(contact.id) : '',
    tempId: contact.tempId ? String(contact.tempId) : '',
    name: contact.name || '',
    position: contact.position || '',
    phone: contact.phone || '',
    email: contact.email || '',
    is_decision_maker: Boolean(contact.is_decision_maker),
  }
  isContactEditOpen.value = true
}

const closeContactEdit = () => {
  isContactEditOpen.value = false
}

const saveContactEdit = async () => {
  if (contactEdit.value.mode === 'draft') {
    contactDrafts.value = contactDrafts.value.map((item) =>
      item.tempId === contactEdit.value.tempId
        ? {
            ...item,
            name: contactEdit.value.name.trim(),
            position: contactEdit.value.position || '',
            phone: contactEdit.value.phone || '',
            email: contactEdit.value.email || '',
            is_decision_maker: contactEdit.value.is_decision_maker,
          }
        : item
    )
    closeContactEdit()
    return
  }
  if (!auth.enabled || !contactEdit.value.id || !clientId.value) return
  try {
    await api.patch(`/v1/contacts/${contactEdit.value.id}`, {
      name: contactEdit.value.name.trim(),
      position: contactEdit.value.position || null,
      phone: contactEdit.value.phone || null,
      email: contactEdit.value.email || null,
      is_decision_maker: contactEdit.value.is_decision_maker || false,
    })
    toast.success('Zaktualizowano kontakt.')
    closeContactEdit()
    await fetchClientContacts(clientId.value)
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się zapisać kontaktu.'
    toast.error(message)
  }
}

const saveContactDrafts = async () => {
  if (!auth.enabled) return true
  const pending = contactDrafts.value.filter((item) => !item.saved)
  if (!pending.length) return true
  if (!clientId.value) {
    toast.error('Najpierw zapisz dane firmy, aby dodać kontakty.')
    return false
  }
  try {
    await Promise.all(
      pending.map((item) =>
        api.post(`/v1/clients/${clientId.value}/contacts`, {
          name: item.name,
          position: item.position || null,
          phone: item.phone || null,
          email: item.email || null,
          is_decision_maker: item.is_decision_maker || false,
        })
      )
    )
    contactDrafts.value = contactDrafts.value.map((item) => ({ ...item, saved: true }))
    await fetchClientContacts(clientId.value)
    return true
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się zapisać kontaktów.'
    toast.error(message)
    return false
  }
}

const resolveOpenMeetingId = async (targetClientId: string) => {
  const { data } = await api.get('/v1/meetings', {
    params: { client_id: targetClientId, status: 'open', per_page: 1 },
  })
  const list = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
  return list.length > 0 ? String(list[0].id) : null
}

const saveClientAndMeeting = async () => {
  if (!auth.enabled) return true
  if (isSavingStep.value) return false
  isSavingStep.value = true
  try {
    const nip = normalizeNip(companyData.value.nip)
    if (!nip || !companyData.value.name || !companyData.value.street || !companyData.value.zip || !companyData.value.city) {
      toast.error('Uzupełnij dane firmy przed przejściem dalej.')
      return false
    }
    if (await checkNipReservation(nip)) {
      toast.warning(nipBlockMessage.value || 'NIP jest już zarezerwowany.')
      return false
    }

    if (!clientId.value) {
      const existingId = await resolveClientIdByNip(nip)
      if (existingId) {
        clientId.value = existingId
      } else {
        const payload = {
          nip,
          name: companyData.value.name,
          address_line1: companyData.value.street,
          address_line2: null,
          postal_code: companyData.value.zip,
          city: companyData.value.city,
        }
        try {
          const { data } = await api.post('/v1/clients', payload)
          clientId.value = String(data?.id || '')
        } catch (error: any) {
          const status = error?.response?.status
          const errors = error?.response?.data?.errors
          if (status === 422 && errors?.nip) {
            const reserved = await checkNipReservation(nip)
            if (reserved) {
              toast.error(nipBlockMessage.value || 'NIP jest już zarezerwowany.')
              return false
            }
            const foundId = await resolveClientIdByNip(nip)
            if (foundId) {
              clientId.value = foundId
            } else {
              toast.error('NIP już istnieje w systemie.')
              return false
            }
          } else {
            throw error
          }
        }
      }
    }

    if (!clientId.value) {
      toast.error('Nie udało się ustalić klienta do zapisu kontaktów.')
      return false
    }
    const contactsOk = await saveContactDrafts()
    if (!contactsOk) return false

    if (clientId.value && !meetingId.value) {
      const existingMeetingId = await resolveOpenMeetingId(clientId.value)
      if (existingMeetingId) {
        meetingId.value = existingMeetingId
        sessionId.value = `MEETING-${meetingId.value}`
        toast.warning('Klient ma już aktywne spotkanie. Kontynuuję istniejące.')
        return true
      }
      const validUntil = new Date()
      validUntil.setDate(validUntil.getDate() + 90)
      const payload = {
        client_id: clientId.value,
        user_keycloak_id: currentUser.value?.id,
        status: 'open',
        calculation_shown: false,
        valid_until: validUntil.toISOString().slice(0, 10),
      }
      try {
        const { data } = await api.post('/v1/meetings', payload)
        meetingId.value = String(data?.id || '')
        if (meetingId.value) {
          sessionId.value = `MEETING-${meetingId.value}`
        }
      } catch (error: any) {
        const status = error?.response?.status
        const fallbackId = error?.response?.data?.meeting_id
        if (status === 409 && fallbackId) {
          meetingId.value = String(fallbackId)
          sessionId.value = `MEETING-${meetingId.value}`
          toast.warning('Klient ma już aktywne spotkanie. Kontynuuję istniejące.')
        } else {
          throw error
        }
      }
    }

    return true
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się zapisać danych klienta.'
    toast.error(message)
    return false
  } finally {
    isSavingStep.value = false
  }
}

const saveConsents = async () => {
  if (!auth.enabled || !clientId.value) return true
  if (isSavingStep.value) return false
  isSavingStep.value = true
  try {
    const { data: existingConsents } = await api.get(`/v1/clients/${clientId.value}/consents`)
    const existing = Array.isArray(existingConsents) ? existingConsents : []
    const existingIds = new Set(existing.map((item: any) => String(item?.consent_id || item?.consent?.id || '')))

    const now = new Date().toISOString()
    for (const consent of consentList.value) {
      if (!consentAccepted.value[consent.id]) continue
      if (existingIds.has(consent.id)) continue
      await api.post(`/v1/clients/${clientId.value}/consents`, {
        consent_id: consent.id,
        accepted_at: now,
        source: 'crm',
      })
    }

    return true
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się zapisać zgód.'
    toast.error(message)
    return false
  } finally {
    isSavingStep.value = false
  }
}

const saveMeetingAnalysis = async () => {
  if (!auth.enabled || !meetingId.value) return true
  if (isSavingStep.value) return false
  isSavingStep.value = true
  try {
    const payload = {
      meeting_id: meetingId.value,
      industry: analysis.value.industry || null,
      tax_model: analysis.value.contractType || null,
      zus_cost_level: analysis.value.zusCost ?? null,
      investments_planned: analysis.value.isInvesting ?? null,
      expected_savings: analysis.value.avgEarnings ?? null,
      benefits: analysis.value.benefits || null,
      project_participation: analysis.value.projectParticipation || null,
      past_savings: analysis.value.pastSavings || null,
      current_savings: analysis.value.currentSavings || null,
      planned_investments: analysis.value.plannedInvestments || null,
      declared_savings: analysis.value.declaredSavings || null,
      debts: analysis.value.debts || null,
      vat_model: analysis.value.vatModel || null,
    }

    if (meetingAnalysisId.value) {
      await api.patch(`/v1/meeting-analyses/${meetingAnalysisId.value}`, payload)
    } else {
      const { data } = await api.post('/v1/meeting-analyses', payload)
      meetingAnalysisId.value = String(data?.id || '')
    }

    return true
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się zapisać analizy.'
    toast.error(message)
    return false
  } finally {
    isSavingStep.value = false
  }
}

const finalizeMeeting = async () => {
  if (!auth.enabled || !meetingId.value) return
  try {
    await api.patch(`/v1/meetings/${meetingId.value}`, {
      status: 'completed',
      calculation_shown: true,
    })
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się zakończyć spotkania.'
    toast.error(message)
  }
}

const loadExistingProcess = async (targetClientId: string, targetMeetingId?: string | null) => {
  if (!auth.enabled) return
  isLoadingExisting.value = true
  try {
    const { data: client } = await api.get(`/v1/clients/${targetClientId}`)
    clientId.value = String(client?.id || targetClientId)
    companyData.value = {
      nip: client?.nip || '',
      name: client?.name || '',
      street: client?.address_line1 || '',
      zip: client?.postal_code || '',
      city: client?.city || '',
    }
    await fetchClientContacts(clientId.value)

    let meeting = null
    if (targetMeetingId) {
      const { data } = await api.get(`/v1/meetings/${targetMeetingId}`)
      meeting = data
    } else {
      const { data } = await api.get('/v1/meetings', { params: { client_id: clientId.value, per_page: 1 } })
      const list = Array.isArray(data?.data) ? data.data : []
      meeting = list.length ? list[0] : null
    }

    if (meeting?.id) {
      meetingId.value = String(meeting.id)
      sessionId.value = `MEETING-${meetingId.value}`
    }

    const { data: consentEntries } = await api.get(`/v1/clients/${clientId.value}/consents`)
    const consentsData = Array.isArray(consentEntries) ? consentEntries : []
    const acceptedIds = new Set(consentsData.map((item: any) => String(item?.consent_id || item?.consent?.id || '')))
    consentAccepted.value = consentList.value.reduce<Record<string, boolean>>((acc, consent) => {
      acc[consent.id] = acceptedIds.has(consent.id)
      return acc
    }, {})

    if (meetingId.value) {
      const { data: analysisResp } = await api.get('/v1/meeting-analyses', { params: { meeting_id: meetingId.value, per_page: 1 } })
      const analysisList = Array.isArray(analysisResp?.data) ? analysisResp.data : []
      const analysisItem = analysisList.length ? analysisList[0] : null
      if (analysisItem?.id) {
        meetingAnalysisId.value = String(analysisItem.id)
      }
      analysis.value = {
        industry: analysisItem?.industry || '',
        employees: analysis.value.employees,
        contractType: analysisItem?.tax_model || '',
        avgEarnings: analysisItem?.expected_savings ?? null,
        zusCost: analysisItem?.zus_cost_level ?? null,
        isInvesting: analysisItem?.investments_planned ?? null,
        benefits: analysisItem?.benefits || '',
        projectParticipation: analysisItem?.project_participation || '',
        pastSavings: analysisItem?.past_savings || '',
        currentSavings: analysisItem?.current_savings || '',
        plannedInvestments: analysisItem?.planned_investments || '',
        declaredSavings: analysisItem?.declared_savings || '',
        debts: analysisItem?.debts || '',
        vatModel: analysisItem?.vat_model || '',
      }
    }

    const hasProfileData = Boolean(
      analysis.value.benefits ||
      analysis.value.projectParticipation ||
      analysis.value.pastSavings ||
      analysis.value.currentSavings ||
      analysis.value.plannedInvestments ||
      analysis.value.declaredSavings ||
      analysis.value.debts ||
      analysis.value.vatModel,
    )

    if (!meetingId.value) {
      step.value = 1
    } else if (consentList.value.filter((c) => c.required).some((c) => !consentAccepted.value[c.id])) {
      step.value = 2
    } else if (!analysis.value.industry || !analysis.value.contractType) {
      step.value = 3
    } else if (!hasProfileData) {
      step.value = 4
    } else {
      step.value = 5
    }

    isProcessActive.value = true
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się wczytać procesu.'
    toast.error(message)
  } finally {
    isLoadingExisting.value = false
  }
}

const startNewMeeting = () => {
  sessionId.value = `DRAFT-${Math.floor(100 + Math.random() * 900)}`
  step.value = 1
  isProcessActive.value = true
  clientId.value = null
  meetingId.value = null
  meetingAnalysisId.value = null
  existingContacts.value = []
  contactDrafts.value = []
  resetContactDraft()
  closeContactEdit()
}

const cancelProcess = () => {
  if (window.confirm('Czy na pewno chcesz anulować proces? Utracisz wprowadzone dane.')) {
    isProcessActive.value = false
  }
}

const getCurrentStepName = computed(() => {
  switch (step.value) {
    case 1:
      return 'Dane Firmy'
    case 2:
      return 'Zgody'
    case 3:
      return 'Analiza Potrzeb'
    case 4:
      return 'Profil Firmy'
    case 5:
      return 'Baza Wiedzy'
    case 6:
      return 'Prezentacja Oferty'
    default:
      return ''
  }
})

const isStepValid = computed(() => {
  switch (step.value) {
    case 1:
      return !!companyData.value.nip && !!companyData.value.name && !nipBlocked.value && !isCheckingNip.value
    case 2:
      return consentList.value.filter((c) => c.required).every((c) => consentAccepted.value[c.id])
    case 3:
      return !!analysis.value.industry && !!analysis.value.contractType
    case 4:
      return true
    default:
      return true
  }
})

const nextStep = async () => {
  if (step.value === 1) {
    const ok = await saveClientAndMeeting()
    if (!ok) return
  }
  if (step.value === 2) {
    const ok = await saveConsents()
    if (!ok) return
  }
  if (step.value === 3) {
    const ok = await saveMeetingAnalysis()
    if (!ok) return
  }
  if (step.value === 4) {
    const ok = await saveMeetingAnalysis()
    if (!ok) return
  }

  if (step.value < 6) {
    step.value += 1
    return
  }

  await finalizeMeeting()
  const targetPath = calcTarget.value === 'detailed' ? '/app/calculator' : '/app/quick-calculator'
  router.push({
    path: targetPath,
    query: {
      meetingId: meetingId.value || undefined,
      clientId: clientId.value || undefined,
      industry: analysis.value.industry,
      goal: analysis.value.isInvesting ? 'Inwestycje' : 'Optymalizacja',
      employees: analysis.value.employees || undefined,
      avgWage: analysis.value.avgEarnings || undefined,
      contractType: analysis.value.contractType || undefined,
    },
  })
}

const prevStep = () => {
  if (step.value > 1) {
    step.value -= 1
  }
}

const getButtonLabel = computed(() => {
  switch (step.value) {
    case 1:
      return 'Dalej: Zgody'
    case 2:
      return 'Dalej: Analiza'
    case 3:
      return 'Dalej: Profil firmy'
    case 4:
      return 'Dalej: Baza wiedzy'
    case 5:
      return 'Dalej: Oferta'
    case 6:
      return 'Stwórz Kalkulację'
    default:
      return 'Dalej'
  }
})

onMounted(() => {
  const bootstrap = async () => {
    if (!auth.enabled) return
    const { data } = await api.get('/v1/consents', { params: { per_page: 200 } })
    const list = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
    consentList.value = list.map((item: any) => ({
      id: String(item.id),
      code: String(item.code || ''),
      title: String(item.title || item.code || ''),
      description: String(item.description || ''),
      required: Boolean(item.required),
      file_url: item.file_url || null,
      file_name: item.file_name || null,
    }))
    consentAccepted.value = consentList.value.reduce<Record<string, boolean>>((acc, consent) => {
      acc[consent.id] = false
      return acc
    }, {})
    knowledgeBase.fetchFiles()
  }

  if (route.query.mode === 'new') {
    startNewMeeting()
    void bootstrap()
    return
  }
  const targetClientId = Array.isArray(route.query.clientId) ? route.query.clientId[0] : route.query.clientId
  const targetMeetingId = Array.isArray(route.query.meetingId) ? route.query.meetingId[0] : route.query.meetingId
  void bootstrap().then(() => {
    if (targetClientId) {
      loadExistingProcess(targetClientId, targetMeetingId)
    }
  })
})
</script>

<template>
  <div v-if="!isProcessActive" class="flex flex-col items-center justify-center bg-white relative overflow-hidden h-full py-10">
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-slate-50 rounded-full blur-3xl -z-10 opacity-60"></div>

    <div class="text-center mb-12 animate-fade-in-up">
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-white border border-slate-100 shadow-sm mb-6 relative">
        <span class="font-serif text-2xl text-stratton-gold font-bold">{{ userName.slice(0, 1) }}</span>
        <div class="absolute -bottom-1 -right-1 w-5 h-5 bg-stratton-gold rounded-full flex items-center justify-center border-2 border-white">
          <AppIcon name="check-circle" class="w-3 h-3 text-white" />
        </div>
      </div>
      <h1 class="text-3xl font-serif font-bold text-slate-900 mb-2 tracking-tight">Witaj, {{ userName }}</h1>
      <p class="text-slate-400 text-[10px] font-bold uppercase tracking-[0.2em]">Rozpocznij proces sprzedażowy</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-10 w-full max-w-4xl px-4 relative z-10">
      <div class="space-y-6 animate-fade-in-up" style="animation-delay: 150ms">
        <div class="flex items-center gap-4 mb-2">
          <div class="h-px flex-1 bg-slate-200"></div>
          <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Spotkania</span>
          <div class="h-px flex-1 bg-slate-200"></div>
        </div>

        <button type="button" class="w-full group relative overflow-hidden bg-white border border-slate-200 hover:border-stratton-gold/50 rounded-xl p-8 text-left transition-all duration-300 hover:shadow-xl hover:shadow-stratton-gold/5" @click="startNewMeeting">
          <div class="absolute top-0 right-0 w-24 h-24 bg-stratton-gold/5 rounded-bl-[100px] transition-transform group-hover:scale-150 duration-500"></div>
          <div class="relative z-10 flex justify-between items-start">
            <div>
              <div class="w-12 h-12 rounded-lg bg-slate-50 flex items-center justify-center mb-4 group-hover:bg-stratton-gold group-hover:text-white transition-colors duration-300">
                <AppIcon name="calendar" class="w-6 h-6 text-slate-400 group-hover:text-white" />
              </div>
              <h3 class="text-xl font-bold text-slate-800 mb-2 group-hover:text-stratton-gold transition-colors">Nowe spotkanie</h3>
              <p class="text-sm text-slate-500 leading-relaxed">Rozpocznij nową ścieżkę sprzedażową z klientem.</p>
            </div>
            <AppIcon name="arrow-right" class="w-4 h-4 text-slate-300 group-hover:text-stratton-gold group-hover:translate-x-1 transition-all" />
          </div>
        </button>

        <RouterLink to="/app/clients" class="w-full group bg-white border border-slate-100 hover:border-slate-300 rounded-xl p-5 flex items-center justify-between transition-all hover:bg-slate-50">
          <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded bg-slate-50 flex items-center justify-center text-slate-400 group-hover:text-slate-600 transition">
              <AppIcon name="folder" class="w-5 h-5" />
            </div>
            <span class="font-bold text-slate-600 text-sm group-hover:text-slate-800">Wczytaj istniejące</span>
          </div>
          <AppIcon name="chevron-right" class="w-4 h-4 text-slate-300 group-hover:text-slate-500" />
        </RouterLink>
      </div>

      <div class="space-y-4 animate-fade-in-up" style="animation-delay: 300ms">
        <div class="flex items-center gap-4 mb-2">
          <div class="h-px flex-1 bg-slate-200"></div>
          <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Wsparcie</span>
          <div class="h-px flex-1 bg-slate-200"></div>
        </div>

        <RouterLink to="/app/knowledge-base" class="w-full bg-white border border-slate-100 hover:border-slate-300 rounded-xl p-4 flex items-center gap-4 hover:shadow-md transition-all group">
          <div class="w-12 h-12 rounded bg-slate-50 flex items-center justify-center border border-slate-100 group-hover:border-slate-200 group-hover:bg-white transition">
            <AppIcon name="book-open" class="w-6 h-6 text-slate-400 group-hover:text-stratton-blue" />
          </div>
          <div class="text-left">
            <h4 class="font-bold text-slate-700 text-sm group-hover:text-stratton-blue transition">Baza Wiedzy</h4>
            <p class="text-[10px] text-slate-400 font-medium uppercase tracking-wide">Dokumenty, procedury</p>
          </div>
        </RouterLink>

        <RouterLink to="/app/quick-calculator" class="w-full bg-white border border-slate-100 hover:border-slate-300 rounded-xl p-4 flex items-center gap-4 hover:shadow-md transition-all group">
          <div class="w-12 h-12 rounded bg-slate-50 flex items-center justify-center border border-slate-100 group-hover:border-slate-200 group-hover:bg-white transition">
            <AppIcon name="calculator" class="w-6 h-6 text-slate-400 group-hover:text-green-600" />
          </div>
          <div class="text-left">
            <h4 class="font-bold text-slate-700 text-sm group-hover:text-green-600 transition">Szybka Kalkulacja</h4>
            <p class="text-[10px] text-slate-400 font-medium uppercase tracking-wide">Dla klienta</p>
          </div>
        </RouterLink>
        <RouterLink to="/app/calculator" class="w-full bg-white border border-slate-100 hover:border-slate-300 rounded-xl p-4 flex items-center gap-4 hover:shadow-md transition-all group">
          <div class="w-12 h-12 rounded bg-slate-50 flex items-center justify-center border border-slate-100 group-hover:border-slate-200 group-hover:bg-white transition">
            <AppIcon name="chart-line" class="w-6 h-6 text-slate-400 group-hover:text-stratton-blue" />
          </div>
          <div class="text-left">
            <h4 class="font-bold text-slate-700 text-sm group-hover:text-stratton-blue transition">Szczegółowa Kalkulacja</h4>
            <p class="text-[10px] text-slate-400 font-medium uppercase tracking-wide">Pełny raport</p>
          </div>
        </RouterLink>
      </div>
    </div>

    <div class="mt-20 text-center">
      <p class="text-[10px] text-slate-300 font-medium uppercase tracking-widest">Stratton Financial Services &copy; 2026</p>
    </div>
  </div>

  <div v-else class="w-full flex flex-col animate-fade-in pb-10">
    <header class="w-full border-b border-slate-100 py-6 mb-8">
      <div class="max-w-6xl mx-auto px-6 flex items-center justify-between">
        <div class="flex items-center gap-4">
          <button type="button" class="w-10 h-10 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-400 hover:text-slate-600 hover:border-slate-300 transition shadow-sm" @click="cancelProcess">
            <AppIcon name="arrow-left" class="w-4 h-4" />
          </button>
          <div>
            <h1 class="font-serif font-bold text-2xl text-slate-900 tracking-wide flex items-center gap-3">
              Nowe Spotkanie
              <span class="text-xs bg-emerald-100 text-emerald-700 font-bold px-2 py-0.5 rounded border border-emerald-200 uppercase tracking-wider">Sesja Aktywna</span>
            </h1>
            <p class="text-xs text-slate-400 font-mono mt-1">ID: {{ sessionId }}</p>
          </div>
        </div>
        <div class="hidden md:flex items-center gap-2">
          <div v-for="i in [1, 2, 3, 4, 5, 6]" :key="i" class="h-1.5 w-12 rounded-full" :class="step >= i ? 'bg-slate-800' : 'bg-slate-200'"></div>
        </div>
      </div>
    </header>

    <main class="w-full max-w-6xl mx-auto px-6 grid grid-cols-1 lg:grid-cols-12 gap-12 items-start">
      <div class="lg:col-span-4 space-y-6 sticky top-8">
        <div class="bg-slate-900 text-white p-8 rounded-2xl shadow-xl relative overflow-hidden">
          <div class="absolute top-0 right-0 w-32 h-32 bg-white/5 rounded-bl-full"></div>
          <span class="text-stratton-gold font-bold text-6xl font-serif opacity-20 absolute bottom-4 right-4">{{ step }}</span>
          <h2 class="text-2xl font-bold font-serif mb-2 relative z-10">{{ getCurrentStepName }}</h2>
          <p class="text-slate-400 text-sm relative z-10 mb-8 leading-relaxed">Wprowadź wymagane informacje, aby przejść do kolejnego etapu procesu sprzedażowego.</p>
          <div class="space-y-4 relative z-10">
            <div class="flex items-center gap-3 text-sm">
              <div class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center text-stratton-gold">
                <AppIcon :name="isStepValid ? 'check-circle' : 'pencil-square'" class="w-4 h-4" />
              </div>
              <span :class="isStepValid ? 'text-emerald-400' : 'text-slate-300'">
                {{ isStepValid ? 'Krok kompletny' : 'Wymaga uzupełnienia' }}
              </span>
            </div>
          </div>
        </div>

        <div class="bg-white border border-slate-200 p-6 rounded-2xl shadow-sm">
          <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Twój Klient</h3>
          <div class="flex items-center gap-4 mb-4">
            <div class="w-12 h-12 rounded-lg bg-slate-100 flex items-center justify-center text-slate-400">
              <AppIcon name="building" class="w-6 h-6" />
            </div>
            <div>
              <p class="font-bold text-slate-800">{{ companyData.name || 'Nowa Firma' }}</p>
              <p class="text-xs text-slate-500 font-mono">{{ companyData.nip || 'NIP: ---' }}</p>
            </div>
          </div>
        </div>
      </div>

      <div class="lg:col-span-8">
        <div class="bg-white rounded-2xl shadow-lg border border-slate-100 p-8 md:p-10 min-h-[500px] relative">
          <div v-if="step === 1" class="space-y-8 animate-fade-in-up">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
              <h3 class="font-serif font-bold text-xl text-slate-800">Dane Rejestrowe</h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
              <div class="space-y-6">
                <div>
                  <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide mb-2">Numer NIP</label>
                  <div class="flex">
                    <input v-model="companyData.nip" type="text" class="flex-1 bg-slate-50 border border-slate-200 rounded-l-lg px-4 py-3 text-slate-900 font-mono font-bold focus:ring-1 focus:ring-stratton-gold focus:border-stratton-gold outline-none transition-all" @blur="checkNipReservation(companyData.nip)" />
                    <button type="button" class="bg-slate-800 text-white px-4 rounded-r-lg hover:bg-slate-700 transition" :disabled="isFetchingGus" @click="fetchCompanyByNip">
                      <span v-if="isFetchingGus">...</span>
                      <span v-else>Pobierz</span>
                    </button>
                  </div>
                  <p v-if="gusError" class="text-xs text-red-500 mt-1">{{ gusError }}</p>
                  <p v-else-if="nipBlockMessage" class="text-xs text-amber-600 mt-1">{{ nipBlockMessage }}</p>
                </div>
                <div>
                  <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide mb-2">Pełna Nazwa Firmy</label>
                  <input v-model="companyData.name" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-slate-800 font-bold focus:ring-1 focus:ring-stratton-gold focus:border-stratton-gold outline-none transition-all" />
                </div>
              </div>
              <div class="space-y-6">
                <div>
                  <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide mb-2">Ulica i numer</label>
                  <input v-model="companyData.street" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-slate-800 font-bold focus:ring-1 focus:ring-stratton-gold focus:border-stratton-gold outline-none transition-all" />
                </div>
                <div class="grid grid-cols-3 gap-4">
                  <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide mb-2">Kod</label>
                    <input v-model="companyData.zip" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-slate-800 font-bold font-mono text-center focus:ring-1 focus:ring-stratton-gold focus:border-stratton-gold outline-none transition-all" />
                  </div>
                  <div class="col-span-2">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide mb-2">Miejscowość</label>
                    <input v-model="companyData.city" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-slate-800 font-bold focus:ring-1 focus:ring-stratton-gold focus:border-stratton-gold outline-none transition-all" />
                  </div>
                </div>
              </div>
            </div>

            <div class="border-t border-slate-100 pt-6 space-y-4">
              <div class="flex items-center justify-between">
                <h3 class="font-serif font-bold text-xl text-slate-800">Osoby Kontaktowe</h3>
                <span class="text-xs text-slate-400">Liczba: {{ existingContacts.length + contactDrafts.length }}</span>
              </div>

              <div v-if="contactsLoading" class="text-sm text-slate-400">Ładowanie kontaktów...</div>
              <div v-else-if="existingContacts.length === 0 && contactDrafts.length === 0" class="text-sm text-slate-400">Brak kontaktów dla tego klienta.</div>
              <div v-else class="space-y-3">
                <button
                  v-for="contact in existingContacts"
                  :key="`existing-${contact.id}`"
                  type="button"
                  class="w-full text-left border border-slate-200 rounded-xl p-4 bg-white hover:border-stratton-gold/40 hover:bg-amber-50/30 transition"
                  @click="openContactEdit(contact, 'existing')"
                >
                  <div class="flex items-start justify-between gap-4">
                    <div>
                      <div class="flex items-center gap-2">
                        <p class="font-semibold text-slate-900">{{ contact.name }}</p>
                        <span v-if="contact.is_decision_maker" class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider">Decyzyjna</span>
                      </div>
                      <p v-if="contact.position" class="text-xs text-slate-500">{{ contact.position }}</p>
                    </div>
                    <div class="text-xs text-slate-500 text-right">
                      <div v-if="contact.phone">{{ contact.phone }}</div>
                      <div v-if="contact.email">{{ contact.email }}</div>
                    </div>
                  </div>
                </button>

                <button
                  v-for="contact in contactDrafts"
                  :key="`draft-${contact.tempId}`"
                  type="button"
                  class="w-full text-left border border-dashed border-slate-200 rounded-xl p-4 bg-slate-50/60 hover:border-stratton-gold/40 hover:bg-amber-50/30 transition"
                  @click="openContactEdit(contact, 'draft')"
                >
                  <div class="flex items-start justify-between gap-4">
                    <div>
                      <div class="flex items-center gap-2">
                        <p class="font-semibold text-slate-800">{{ contact.name }}</p>
                        <span v-if="contact.is_decision_maker" class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider">Decyzyjna</span>
                      </div>
                      <p v-if="contact.position" class="text-xs text-slate-500">{{ contact.position }}</p>
                    </div>
                    <div class="text-xs text-slate-500 text-right">
                      <div v-if="contact.phone">{{ contact.phone }}</div>
                      <div v-if="contact.email">{{ contact.email }}</div>
                    </div>
                  </div>
                </button>
              </div>

              <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5">
                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-4">Dodaj kontakt</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div class="space-y-1">
                    <label class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Imię i nazwisko</label>
                    <input v-model="contactDraft.name" type="text" placeholder="Jan Kowalski" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-stratton-gold focus:ring-2 focus:ring-amber-200 outline-none" />
                  </div>
                  <div class="space-y-1">
                    <label class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Stanowisko</label>
                    <input v-model="contactDraft.position" type="text" placeholder="Dyrektor finansowy" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-stratton-gold focus:ring-2 focus:ring-amber-200 outline-none" />
                  </div>
                  <div class="space-y-1">
                    <label class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Telefon</label>
                    <input v-model="contactDraft.phone" type="text" placeholder="+48 600 100 200" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-stratton-gold focus:ring-2 focus:ring-amber-200 outline-none" />
                  </div>
                  <div class="space-y-1">
                    <label class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Email</label>
                    <input v-model="contactDraft.email" type="email" placeholder="jan.kowalski@firma.pl" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-stratton-gold focus:ring-2 focus:ring-amber-200 outline-none" />
                  </div>
                </div>
                <div class="mt-4 flex items-center justify-between">
                  <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                    <input v-model="contactDraft.is_decision_maker" type="checkbox" class="h-4 w-4 text-stratton-gold border-slate-300 rounded focus:ring-stratton-gold" />
                    Osoba decyzyjna
                  </label>
                  <button type="button" class="bg-slate-900 text-white text-xs font-semibold px-5 py-2.5 rounded-lg shadow-sm hover:bg-slate-800" @click="addContactDraft">
                    Dodaj kontakt
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div v-else-if="step === 2" class="space-y-6 animate-fade-in-up">
            <div class="bg-amber-50 border-l-4 border-amber-400 p-4 rounded-r-lg">
              <p class="text-sm text-amber-700">Wszystkie zgody oznaczone gwiazdką (*) są wymagane do kontynuowania procesu.</p>
            </div>
            <div class="space-y-4">
              <label
                v-for="consent in consentList"
                :key="consent.id"
                class="flex items-start gap-3 p-4 rounded-xl border-2"
                :class="consentAccepted[consent.id] ? 'border-emerald-500 bg-emerald-50' : 'border-slate-100'"
              >
                <input v-model="consentAccepted[consent.id]" type="checkbox" class="h-5 w-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600" />
                <div>
                  <span class="font-bold text-slate-900">{{ consent.title }}<span v-if="consent.required"> *</span></span>
                  <p class="text-slate-500 text-sm">{{ consent.description }}</p>
                  <div v-if="consent.file_url" class="mt-2 flex items-center gap-3 text-xs">
                    <button type="button" class="text-sky-600 hover:underline" @click.stop="openConsentFile(consent, false)">Podgląd</button>
                    <button type="button" class="text-sky-600 hover:underline" @click.stop="openConsentFile(consent, true)">Pobierz</button>
                  </div>
                </div>
              </label>
            </div>
          </div>

          <div v-else-if="step === 3" class="space-y-6 animate-fade-in-up">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide mb-1">Branża</label>
                <select v-model="analysis.industry" class="w-full bg-slate-50 border-b-2 border-slate-200 px-4 py-3 text-slate-800 font-bold focus:border-stratton-gold focus:bg-white outline-none transition-all">
                  <option value="" disabled>Wybierz...</option>
                  <option value="IT">IT / Programowanie</option>
                  <option value="Construction">Budownictwo</option>
                  <option value="Transport">Transport / Logistyka</option>
                  <option value="Medical">Medycyna</option>
                  <option value="Finance">Finanse / Prawo</option>
                  <option value="Other">Inne</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide mb-1">Liczba Pracowników</label>
                <input v-model.number="analysis.employees" type="number" class="w-full bg-slate-50 border-b-2 border-slate-200 px-4 py-3 text-slate-800 font-bold focus:border-stratton-gold focus:bg-white outline-none transition-all" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide mb-1">Dominująca forma współpracy</label>
                <select v-model="analysis.contractType" class="w-full bg-slate-50 border-b-2 border-slate-200 px-4 py-3 text-slate-800 font-bold focus:border-stratton-gold focus:bg-white outline-none transition-all">
                  <option value="UoP">Umowa o Pracę</option>
                  <option value="UZ">Umowa Zlecenie</option>
                  <option value="B2B">Kontrakt B2B</option>
                  <option value="Mix">Mieszane</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide mb-1">Średnie Zarobki Brutto</label>
                <input v-model.number="analysis.avgEarnings" type="number" class="w-full bg-slate-50 border-b-2 border-slate-200 px-4 py-3 text-slate-800 font-bold focus:border-stratton-gold focus:bg-white outline-none transition-all" />
              </div>
            </div>
          </div>

          <div v-else-if="step === 4" class="space-y-6 animate-fade-in-up">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide mb-1">Benefity</label>
                <input v-model="analysis.benefits" type="text" class="w-full bg-slate-50 border-b-2 border-slate-200 px-4 py-3 text-slate-800 font-bold focus:border-stratton-gold focus:bg-white outline-none transition-all" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide mb-1">Udział w projekcie</label>
                <input v-model="analysis.projectParticipation" type="text" class="w-full bg-slate-50 border-b-2 border-slate-200 px-4 py-3 text-slate-800 font-bold focus:border-stratton-gold focus:bg-white outline-none transition-all" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide mb-1">Przeszłe oszczędności</label>
                <input v-model="analysis.pastSavings" type="text" class="w-full bg-slate-50 border-b-2 border-slate-200 px-4 py-3 text-slate-800 font-bold focus:border-stratton-gold focus:bg-white outline-none transition-all" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide mb-1">Aktualne oszczędności</label>
                <input v-model="analysis.currentSavings" type="text" class="w-full bg-slate-50 border-b-2 border-slate-200 px-4 py-3 text-slate-800 font-bold focus:border-stratton-gold focus:bg-white outline-none transition-all" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide mb-1">Planowane inwestycje</label>
                <input v-model="analysis.plannedInvestments" type="text" class="w-full bg-slate-50 border-b-2 border-slate-200 px-4 py-3 text-slate-800 font-bold focus:border-stratton-gold focus:bg-white outline-none transition-all" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide mb-1">Deklarowana kwota oszczędności</label>
                <input v-model="analysis.declaredSavings" type="text" class="w-full bg-slate-50 border-b-2 border-slate-200 px-4 py-3 text-slate-800 font-bold focus:border-stratton-gold focus:bg-white outline-none transition-all" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide mb-1">Zadłużenia</label>
                <input v-model="analysis.debts" type="text" class="w-full bg-slate-50 border-b-2 border-slate-200 px-4 py-3 text-slate-800 font-bold focus:border-stratton-gold focus:bg-white outline-none transition-all" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide mb-1">Ryczałt / VAT</label>
                <input v-model="analysis.vatModel" type="text" class="w-full bg-slate-50 border-b-2 border-slate-200 px-4 py-3 text-slate-800 font-bold focus:border-stratton-gold focus:bg-white outline-none transition-all" />
              </div>
            </div>
          </div>

          <div v-else-if="step === 5" class="space-y-6 animate-fade-in-up">
            <div>
              <h3 class="text-lg font-bold text-slate-800">Materiały z bazy wiedzy</h3>
              <p class="text-xs text-slate-500">Wyszukaj i otwórz potrzebne dokumenty przed zakończeniem spotkania.</p>
            </div>
            <div class="flex items-center gap-3">
              <div class="relative flex-1">
                <AppIcon name="magnifying-glass" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                <input v-model="knowledgeSearch" type="text" placeholder="Szukaj plików po nazwie lub opisie..." class="w-full pl-9 pr-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-slate-400 focus:border-slate-400" />
              </div>
              <button type="button" class="px-4 py-2 text-xs font-semibold rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50" @click="knowledgeBase.fetchFiles(knowledgeSearch)">
                Odśwież
              </button>
            </div>
            <div v-if="knowledgeBase.loading" class="text-xs text-slate-400">Ładowanie plików...</div>
            <div v-else-if="filteredKnowledgeFiles.length === 0" class="text-sm text-slate-500">
              Brak plików spełniających kryteria wyszukiwania.
            </div>
            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div v-for="(file, index) in filteredKnowledgeFiles" :key="file.id" class="border border-slate-200 rounded-xl p-4 bg-white hover:border-stratton-gold/50 transition">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <p class="font-semibold text-slate-900">{{ file.name }}</p>
                    <p class="text-xs text-slate-500 line-clamp-2">{{ file.description || 'Brak opisu' }}</p>
                    <p class="text-[11px] text-slate-400 mt-1">{{ file.category }}</p>
                  </div>
                  <div class="flex flex-col items-end gap-2">
                    <button type="button" class="text-xs font-semibold text-stratton-gold hover:underline" @click="openKnowledgeFile(file, index)">
                      Otwórz
                    </button>
                    <button type="button" class="text-[11px] font-semibold text-slate-500 hover:text-slate-700" @click="downloadKnowledgeFile(file)">
                      Zapisz
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div v-else class="space-y-6 animate-fade-in-up">
            <div class="text-center">
              <AppIcon name="signature" class="w-10 h-10 text-stratton-gold mb-4 mx-auto" />
              <h3 class="text-xl font-bold text-slate-800">Rekomendowane Rozwiązania</h3>
              <p class="text-sm text-slate-500">Na podstawie analizy system dobrał pakiet dla {{ companyData.name }}</p>
            </div>
            <div class="border border-stratton-gold bg-amber-50/30 rounded-xl p-6">
              <h4 class="font-bold text-lg text-slate-900">Eliton Prime <span class="text-stratton-gold">Standard</span></h4>
              <p class="text-sm text-slate-600 mt-2">Kompleksowe rozwiązanie optymalizujące koszty pracy.</p>
              <div class="mt-4 flex flex-col sm:flex-row items-stretch gap-3">
                <button
                  type="button"
                  class="px-6 py-2 rounded-lg font-bold text-sm shadow-lg transition flex-1"
                  :class="calcTarget === 'quick' ? 'bg-stratton-gold text-white shadow-stratton-gold/30' : 'bg-white text-slate-600 border border-slate-200'"
                  @click="calcTarget = 'quick'"
                >
                  Szybka Kalkulacja
                </button>
                <button
                  type="button"
                  class="px-6 py-2 rounded-lg font-bold text-sm shadow-lg transition flex-1"
                  :class="calcTarget === 'detailed' ? 'bg-slate-900 text-white shadow-slate-900/30' : 'bg-white text-slate-600 border border-slate-200'"
                  @click="calcTarget = 'detailed'"
                >
                  Szczegółowa Kalkulacja
                </button>
              </div>
            </div>
          </div>
          <div class="mt-10 pt-6 border-t border-slate-100 flex items-center justify-between">
            <button
              type="button"
              class="px-6 py-3 rounded-xl font-bold text-xs uppercase tracking-wide border border-slate-200 text-slate-600 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="step <= 1"
              @click="prevStep"
            >
              Wstecz
            </button>
            <button type="button" class="bg-indigo-600 text-white px-8 py-4 rounded-xl font-bold uppercase tracking-wide hover:bg-indigo-700 transition flex items-center gap-3 shadow-lg shadow-indigo-200 hover:shadow-xl hover:-translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed" :disabled="!isStepValid" @click="nextStep">
              {{ getButtonLabel }}
              <AppIcon :name="isStepValid ? 'arrow-right' : 'lock-closed'" class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </main>

    <div v-if="showPreview" class="fixed inset-0 z-[60] flex items-center justify-center">
      <div class="absolute inset-0 bg-black/50" @click="closePreview"></div>
      <div ref="previewContainer" class="relative bg-white w-[90vw] max-w-5xl h-[80vh] rounded-2xl shadow-2xl border border-slate-200 p-4 flex flex-col">
        <div class="flex items-center justify-between mb-2">
          <div class="text-sm font-semibold text-slate-700">{{ previewName || 'Podgląd pliku' }}</div>
          <div class="flex items-center gap-2">
            <button type="button" class="text-xs font-semibold text-slate-500 hover:text-slate-700" @click="previewNext(-1)">
              Poprzedni
            </button>
            <button type="button" class="text-xs font-semibold text-slate-500 hover:text-slate-700" @click="previewNext(1)">
              Następny
            </button>
            <button type="button" class="text-xs font-semibold text-slate-500 hover:text-slate-700" @click="togglePreviewFullscreen">
              Pełny ekran
            </button>
            <button type="button" class="text-xs font-semibold text-slate-500 hover:text-slate-700" @click="closePreview">
              Zamknij
            </button>
          </div>
        </div>
        <div class="flex-1 overflow-hidden">
          <VueFilesPreview :key="previewKey" :file="previewFile" :url="previewUrl" class="w-full h-full" />
        </div>
      </div>
    </div>

    <div v-if="isContactEditOpen" class="fixed inset-0 z-[60] flex items-center justify-center">
      <div class="absolute inset-0 bg-black/40" @click="closeContactEdit"></div>
      <div class="relative bg-white w-full max-w-md rounded-2xl shadow-2xl border border-slate-200 p-6">
        <h3 class="text-lg font-bold text-slate-900 mb-2">Edytuj kontakt</h3>
        <p class="text-sm text-slate-500 mb-4">Zaktualizuj dane osoby kontaktowej.</p>
        <div class="space-y-3">
          <div class="space-y-1">
            <label class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Imię i nazwisko</label>
            <input v-model="contactEdit.name" type="text" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-900 focus:border-stratton-gold focus:ring-2 focus:ring-amber-200 outline-none" />
          </div>
          <div class="space-y-1">
            <label class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Stanowisko</label>
            <input v-model="contactEdit.position" type="text" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-900 focus:border-stratton-gold focus:ring-2 focus:ring-amber-200 outline-none" />
          </div>
          <div class="space-y-1">
            <label class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Telefon</label>
            <input v-model="contactEdit.phone" type="text" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-900 focus:border-stratton-gold focus:ring-2 focus:ring-amber-200 outline-none" />
          </div>
          <div class="space-y-1">
            <label class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Email</label>
            <input v-model="contactEdit.email" type="email" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-900 focus:border-stratton-gold focus:ring-2 focus:ring-amber-200 outline-none" />
          </div>
        </div>
        <label class="mt-4 flex items-center gap-2 text-xs text-slate-600">
          <input v-model="contactEdit.is_decision_maker" type="checkbox" class="h-4 w-4 text-stratton-gold border-slate-300 rounded focus:ring-stratton-gold" />
          Osoba decyzyjna
        </label>
        <div class="mt-6 flex justify-end gap-2">
          <button type="button" class="px-4 py-2 text-sm rounded border border-slate-300 text-slate-700 hover:bg-slate-100" @click="closeContactEdit">
            Anuluj
          </button>
          <button type="button" class="px-4 py-2 text-sm rounded bg-slate-900 text-white hover:bg-slate-800" @click="saveContactEdit">
            Zapisz
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
