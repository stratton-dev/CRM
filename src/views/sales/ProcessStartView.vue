<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useRoute, useRouter } from 'vue-router'
import AppIcon from '@/components/AppIcon.vue'
import { api } from '@/api/client'
import ConsentTextModal from '@/components/ConsentTextModal.vue'
import PresentationModal from '@/components/PresentationModal.vue'
import { useAuthStore } from '@/stores/auth'
import { useSessionStore } from '@/stores/session'
import { useStructureStore } from '@/stores/structure'
import { useToastStore } from '@/stores/toast'
import { useClientStore } from '@/stores/client'
import { useKnowledgeBaseStore } from '@/stores/knowledgeBase'
import type { FileCategory, KnowledgeFile } from '@/types/models'
import { VueFilesPreview } from 'vue-files-preview'
import 'vue-files-preview/lib/style.css'

const auth = useAuthStore()
const session = useSessionStore()
const structure = useStructureStore()
const toast = useToastStore()
const clientStore = useClientStore()
const { currentUser } = storeToRefs(session)
const { prospects } = storeToRefs(clientStore)
const router = useRouter()
const route = useRoute()
const knowledgeBase = useKnowledgeBaseStore()
const { files: knowledgeFiles } = storeToRefs(knowledgeBase)

const isProcessActive = ref(false)
const stepFromQuery = parseInt(String(route.query.step))
const step = ref(!isNaN(stepFromQuery) && stepFromQuery >= 1 && stepFromQuery <= 5 ? stepFromQuery : 1)
const sessionId = ref('')

const companyData = ref({
  nip: '',
  name: '',
  street: '',
  zip: '',
  city: '',
})

const industrySearch = ref('')

const contactDraft = ref({
  name: '',
  position: '',
  phone: '',
  email: '',
  is_decision_maker: false,
})

const showFetchMeetingModal = ref(false)
const selectMeetingSearch = ref('')

const filteredProspects = computed(() => {
  if (!selectMeetingSearch.value) return prospects.value
  const s = selectMeetingSearch.value.toLowerCase()
  return prospects.value.filter(p => 
    (p.name && p.name.toLowerCase().includes(s)) ||
    (p.nip && p.nip.includes(s))
  )
})

const handleSelectMeeting = (prospect: any) => {
  companyData.value.nip = prospect.nip || ''
  companyData.value.name = prospect.name || ''
  companyData.value.street = prospect.street || ''
  companyData.value.zip = prospect.zip || ''
  companyData.value.city = prospect.city || ''
  
  // Set context IDs to update existing record
  clientId.value = prospect.id
  if (prospect.meetingId) {
    meetingId.value = prospect.meetingId
  }

  if (prospect.contactName) {
    contactDraft.value.name = prospect.contactName
    contactDraft.value.position = prospect.contactPosition || ''
    contactDraft.value.phone = prospect.contactPhone || ''
    contactDraft.value.email = prospect.contactEmail || ''
    contactDraft.value.is_decision_maker = !!prospect.isDecisionMaker
  }
  
  showFetchMeetingModal.value = false
  toast.success('Pobrano dane ze spotkania')
}

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

const showConsentModal = ref(false)
const selectedConsentCode = ref('')
const selectedConsentTitle = ref('')

// Presentation Modal State
const showPresentationModal = ref(false)
const selectedPresentationType = ref('')
const selectedPresentationTitle = ref('')
const selectedPresentationFile = ref<any>(null)

const consentTexts: Record<string, string> = {
  'marketing': `ZGODA MARKETINGOWA

Ja, niżej podpisany/a, działając w imieniu [NAZWA FIRMY], NIP: [NIP], wyrażam zgodę na przetwarzanie moich danych osobowych oraz danych firmy w celach marketingowych, w tym na otrzymywanie informacji handlowych i marketingowych dotyczących produktów i usług oferowanych przez [TWOJA FIRMA].

Zgoda obejmuje:
- Przetwarzanie danych w celu przedstawienia oferty handlowej
- Kontakt w celach marketingowych
- Analizę potrzeb biznesowych firmy

Podstawa prawna: Art. 6 ust. 1 lit. a) RODO - zgoda osoby, której dane dotyczą.`,

  'phone': `ZGODA NA KONTAKT TELEFONICZNY

Ja, niżej podpisany/a, wyrażam zgodę na kontaktowanie się ze mną przez [TWOJA FIRMA] za pomocą telefonu pod numerem: [NUMER TELEFONU] w celu:
- Prezentacji oferty handlowej
- Udzielania informacji o produktach i usługach
- Przeprowadzania badań satysfakcji
- Obsługi posprzedażowej

Podstawa prawna: Art. 172 ustawy Prawo telekomunikacyjne.`,

  'email': `ZGODA NA KONTAKT EMAILOWY

Ja, niżej podpisany/a, wyrażam zgodę na kontaktowanie się ze mną przez [TWOJA FIRMA] za pomocą poczty elektronicznej na adres: [EMAIL] w celu:
- Wysyłania ofert handlowych
- Przekazywania informacji o produktach i usługach
- Wysyłania newsletterów i materiałów informacyjnych

Podstawa prawna: Art. 10 ustawy o świadczeniu usług drogą elektroniczną.`,

  'offer': `ZGODA NA WYSŁANIE OFERTY

Ja, niżej podpisany/a, działając w imieniu [NAZWA FIRMY], NIP: [NIP], wyrażam zgodę na przygotowanie i wysłanie indywidualnej oferty handlowej na podstawie przeprowadzonej analizy potrzeb firmy.

Zgoda obejmuje:
- Analizę danych finansowych i organizacyjnych firmy
- Przygotowanie spersonalizowanej oferty
- Wysłanie oferty na adres email: [EMAIL]
- Kontakt followup w sprawie oferty

Oświadczam, że jestem upoważniony/a do wyrażenia niniejszej zgody w imieniu firmy.`,

  'dataStorage': `ZGODA NA PRZETWARZANIE I PRZECHOWYWANIE DANYCH OSOBOWYCH

Ja, niżej podpisany/a, wyrażam zgodę na przetwarzanie i przechowywanie moich danych osobowych oraz danych firmy przez [TWOJA FIRMA] w zakresie:

1. Danych identyfikacyjnych: imię, nazwisko, stanowisko
2. Danych kontaktowych: numer telefonu, adres email
3. Danych firmy: nazwa, NIP, REGON, adres, liczba pracowników
4. Danych dotyczących prowadzonej działalności gospodarczej

Cel przetwarzania:
- Kontakt telefoniczny, mailowy i marketingowy
- Przygotowanie i wysłanie oferty handlowej
- Obsługa relacji biznesowej
- Archiwizacja dokumentacji`,

  'employeeData': `ZGODA NA UDOSTĘPNIENIE DANYCH PRACOWNIKÓW

Ja, niżej podpisany/a, działając w imieniu [NAZWA FIRMY], NIP: [NIP], wyrażam zgodę na udostępnienie danych pracowników firmy w zakresie niezbędnym do przygotowania oferty benefitów pracowniczych:

Zakres udostępnianych danych:
- Liczba pracowników
- Rodzaje umów (umowa o pracę, umowa zlecenie)
- Wysokość wynagrodzeń (w formie zanonimizowanej)
- Struktura zatrudnienia

WYŁĄCZENIA: Zgoda NIE obejmuje danych wrażliwych w rozumieniu art. 9 RODO`,

  'benefits': `OŚWIADCZENIE O WIEDZY NA TEMAT BENEFITÓW PRACOWNICZYCH

Ja, niżej podpisany/a, działając w imieniu [NAZWA FIRMY], NIP: [NIP], oświadczam, że:

1. Posiadam podstawową wiedzę na temat benefitów pracowniczych i ich wpływu na:
   - Motywację pracowników
   - Optymalizację kosztów pracodawcy
   - Obowiązki podatkowe i składkowe

2. Rozumiem, że benefity pracownicze mogą obejmować m.in.:
   - Programy kafeteryjne
   - Ubezpieczenia grupowe
   - Prywatną opiekę medyczną`,

  'political': `OŚWIADCZENIE O PEŁNIENIU FUNKCJI POLITYCZNYCH I SAMORZĄDOWYCH

Ja, niżej podpisany/a, działając w imieniu [NAZWA FIRMY], NIP: [NIP], oświadczam, że:

□ NIE pełnię funkcji politycznych ani samorządowych

□ Pełnię następujące funkcje: _________________________________

Funkcje, o których mowa w oświadczeniu, to w szczególności:
- Posłowie, senatorowie, posłowie do Parlamentu Europejskiego
- Członkowie Rady Ministrów i sekretarze stanu
- Wojewodowie i wicewojewodowie
- Członkowie zarządów województw, powiatów, gmin`
}

const getConsentContent = (code: string) => {
  let text = consentTexts[code] || consentTexts['marketing'] || '' // Fallback
  // Replace placeholders
  text = text
    .replace('[NAZWA FIRMY]', companyData.value.name || '___________')
    .replace('[NIP]', companyData.value.nip || '___________')
    .replace('[EMAIL]', contactDraft.value.email || '___________')
    .replace('[NUMER TELEFONU]', contactDraft.value.phone || '___________')
    .replace('[TWOJA FIRMA]', 'Stratton Prime')
    
  return text
}

const openConsentText = (code: string, title: string) => {
  selectedConsentCode.value = code
  selectedConsentTitle.value = title
  showConsentModal.value = true
}

const openPresentation = (type: string, title: string, file: any = null) => {
  selectedPresentationType.value = type
  selectedPresentationTitle.value = title
  selectedPresentationFile.value = file
  showPresentationModal.value = true
}

const analysis = ref({
  industry: '',
  taxationType: 'vat' as 'ryczalt' | 'vat',
  hasBenefits: null as boolean | null,
  employeeCount: null as number | null,
  
  contractType: '', // legacy
  avgEarnings: null as number | null, // legacy
  uopCount: null as number | null,
  uopSalaryNet: null as number | null,
  uzCount: null as number | null,
  uzSalaryNet: null as number | null,
  zusCost: null as number | null,
  // New fields
  planningInvestments: null as boolean | null,
  highZUSPayments: null as boolean | null,
  implementingSavings: null as boolean | null,
  desiredSavingsAmount: null as number | null,
  hasDebts: null as boolean | null,
  needsFinancing: null as boolean | null,
  financingPurpose: '',

  // Legacy fields
  isInvesting: null as boolean | null, // Legacy name for planningInvestments
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
const crmProfileId = ref<string | null>(null)
const isLoadingExisting = ref(false)
const calcTarget = ref<'quick' | 'detailed'>('quick')
const knowledgeSearch = ref('')
const selectedKnowledgeCategory = ref('all')
const showPreview = ref(false)
const previewUrl = ref('')
const previewName = ref('')
const previewIndex = ref<number | null>(null)
const previewContainer = ref<HTMLElement | null>(null)
const previewFile = ref<File | null>(null)
const previewKey = ref(0)

const categoryOrder: FileCategory[] = ['UMOWY', 'PROCESY', 'PRAWO', 'MARKETING', 'CASH_FLOW', 'LEGAL', 'GRAPHIC', 'VIDEO']
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

const safeKnowledgeFiles = computed<KnowledgeFile[]>(() => (Array.isArray(knowledgeFiles.value) ? knowledgeFiles.value : []))
const knowledgeCategories = computed(() => {
  const categories = new Set<string>()
  for (const file of safeKnowledgeFiles.value) {
    if (file.category) {
      categories.add(String(file.category))
    }
  }
  const ordered: string[] = []
  categoryOrder.forEach((category) => {
    if (categories.has(category)) ordered.push(category)
  })
  const remaining = Array.from(categories).filter((cat) => !ordered.includes(cat))
  remaining.sort((a, b) => a.localeCompare(b, 'pl'))
  return [...ordered, ...remaining]
})
const filteredKnowledgeFiles = computed(() => {
  const query = knowledgeSearch.value.trim().toLowerCase()
  const category = selectedKnowledgeCategory.value
  return safeKnowledgeFiles.value.filter((file) => {
    const matchesCategory = category === 'all' ? true : String(file.category || '') === category
    if (!matchesCategory) return false
    if (!query) return true
    return file.name.toLowerCase().includes(query) || file.description.toLowerCase().includes(query)
  })
})

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
    const { data } = await api.get('/v1/clients/check-nip', { params: { nip }, timeout: 60000 })
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
  const { data } = await api.get('/v1/clients', { params: { search: nip, per_page: 1 }, timeout: 60000 })
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
        }, { timeout: 60000 })
      )
    )
    // Clear drafts that were successfully saved
    contactDrafts.value = contactDrafts.value.filter((item) => !pending.includes(item))
    
    // We try to fetch contacts, but if it fails, we shouldn't block the whole process, 
    // as contacts are likely saved. We just proceed.
    try {
        await fetchClientContacts(clientId.value)
    } catch (e) {
        console.warn('Failed to refresh contacts after save', e)
    }
    
    return true
  } catch (error: any) {
    console.error(error)
    const message = error?.response?.data?.message || error?.message || 'Nie udało się zapisać kontaktów.'
    toast.error(message)
    return false
  }
}

const resolveOpenMeetingId = async (targetClientId: string) => {
  const { data } = await api.get('/v1/meetings', {
    params: { client_id: targetClientId, status: 'open', per_page: 1 },
    timeout: 60000
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
          const { data } = await api.post('/v1/clients', payload, { timeout: 60000 })
          clientId.value = String(data?.id || '')
        } catch (error: any) {
          const status = error?.response?.status
          const errors = error?.response?.data?.errors
          if (status === 422 && errors?.nip) {
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

    // MANDATORY CONTACT CHECK
    if (existingContacts.value.length === 0 && contactDrafts.value.length === 0) {
      toast.warning('Musisz dodać co najmniej jedną osobę kontaktową przed przejściem dalej. Kliknij "Dodaj kontakt".')
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
        const { data } = await api.post('/v1/meetings', payload, { timeout: 60000 })
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

    await updateCrmReservationAndStatus()
    return true
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się zapisać zgód.'
    toast.error(message)
    return false
  } finally {
    isSavingStep.value = false
  }
}

const updateCrmReservationAndStatus = async () => {
  if (!auth.enabled || !clientId.value) return
  try {
    let profile: any = null
    if (!crmProfileId.value) {
      const { data } = await api.get('/v1/crm-client-profiles', { params: { client_id: clientId.value, per_page: 1 } })
      const list = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
      profile = list.length ? list[0] : null
      crmProfileId.value = profile?.id ? String(profile.id) : null
    }

    let reservationEndDate: string | null = null
    if (meetingId.value) {
      const { data: meeting } = await api.get(`/v1/meetings/${meetingId.value}`)
      reservationEndDate = meeting?.valid_until || null
    }

    const currentStatus = profile?.status || null
    const nextStatus =
      currentStatus && !['NEW', 'IN_TALKS'].includes(currentStatus) ? currentStatus : 'IN_TALKS'

    const payload: Record<string, any> = {
      client_id: clientId.value,
      owner_user_id: currentUser.value?.id || null,
      status: nextStatus,
    }
    if (reservationEndDate) {
      payload.reservation_end_date = reservationEndDate
    }

    if (crmProfileId.value) {
      await api.patch(`/v1/crm-client-profiles/${crmProfileId.value}`, payload)
    } else {
      const { data: created } = await api.post('/v1/crm-client-profiles', payload)
      crmProfileId.value = created?.id ? String(created.id) : null
    }
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się zaktualizować rezerwacji.'
    toast.warning(message)
  }
}

const updateCrmStatus = async (
  status: 'NEW' | 'IN_TALKS' | 'OFFER_PREPARING' | 'OFFER_GENERATED' | 'CALCULATION_SENT' | 'SPECIAL_OFFER' | 'RESIGNED' | 'SIGNED' | 'TERMINATED'
) => {
  if (!auth.enabled || !clientId.value) return
  try {
    let profile: any = null
    if (!crmProfileId.value) {
      const { data } = await api.get('/v1/crm-client-profiles', { params: { client_id: clientId.value, per_page: 1 } })
      const list = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
      profile = list.length ? list[0] : null
      crmProfileId.value = profile?.id ? String(profile.id) : null
    }

    let reservationEndDate: string | null = null
    if (meetingId.value) {
      const { data: meeting } = await api.get(`/v1/meetings/${meetingId.value}`)
      reservationEndDate = meeting?.valid_until || null
    }

    const payload: Record<string, any> = {
      client_id: clientId.value,
      owner_user_id: currentUser.value?.id || null,
      status,
    }
    if (reservationEndDate) {
      payload.reservation_end_date = reservationEndDate
    }

    if (crmProfileId.value) {
      await api.patch(`/v1/crm-client-profiles/${crmProfileId.value}`, payload)
    } else {
      const { data: created } = await api.post('/v1/crm-client-profiles', payload)
      crmProfileId.value = created?.id ? String(created.id) : null
    }
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się zaktualizować statusu.'
    toast.warning(message)
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
      tax_model: analysis.value.taxationType || analysis.value.contractType || null,
      zus_cost_level:
        analysis.value.zusCost ?? (analysis.value.highZUSPayments === null ? null : analysis.value.highZUSPayments ? 1 : 0),
      investments_planned: analysis.value.planningInvestments ?? analysis.value.isInvesting ?? null,
      expected_savings: analysis.value.desiredSavingsAmount ?? analysis.value.avgEarnings ?? null,
      debt_level: analysis.value.hasDebts === null ? null : analysis.value.hasDebts ? 'yes' : 'no',
      financing_needed: analysis.value.needsFinancing ?? null,
      financing_purpose: analysis.value.financingPurpose || null,
      employees_count: analysis.value.uopCount ?? null,
      benefits: analysis.value.hasBenefits === null ? null : analysis.value.hasBenefits ? 'Tak' : 'Nie',
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
    // Return true to allow proceeding even if save fails (optional, but safer to block)
    // For now, let's block to find the error if it persists
    return false
  } finally {
    isSavingStep.value = false
  }
}

const finalizeMeeting = async () => {
  if (!auth.enabled || !meetingId.value) return
  
  // Ostatnie sprawdzenie rezerwacji NIP przed finalizacją (krok 4)
  const nip = normalizeNip(companyData.value.nip)
  if (nip) {
    const isReserved = await checkNipReservation(nip)
    if (isReserved) {
      toast.error(nipBlockMessage.value || 'NIP został w międzyczasie zarezerwowany przez innego handlowca.')
      // Cofamy do kroku 1, aby handlowiec widział błąd rezerwacji
      step.value = 1
      throw new Error('NIP_RESERVED')
    }
  }

  try {
    await api.patch(`/v1/meetings/${meetingId.value}`, {
      status: 'completed',
      calculation_shown: true,
      reserve_nip: true // Wysyłamy flagę do backendu, aby dokonał rezerwacji przy pierwszej ofercie
    })
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się zakończyć spotkania.'
    toast.error(message)
    throw error
  }
}

const loadExistingProcess = async (targetClientId: string, targetMeetingId?: string | null) => {
  if (!auth.enabled) return
  isLoadingExisting.value = true
  isProcessActive.value = true
  try {
    const { data: client } = await api.get(`/v1/clients/${targetClientId}`)
    clientId.value = String(client?.id || targetClientId)
    crmProfileId.value = client?.crm_profile?.id ? String(client.crm_profile.id) : null
    const profileStatus = client?.crm_profile?.status || null
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

    if (!route.query.step && profileStatus === 'OFFER_PREPARING' && meetingId.value) {
      const targetPath = calcTarget.value === 'detailed' ? '/app/calculator' : '/app/quick-calculator'
      router.push({
        path: targetPath,
        query: {
          meetingId: meetingId.value || undefined,
          clientId: clientId.value || undefined,
        },
      })
      return
    }

    if (!route.query.step && profileStatus === 'OFFER_GENERATED' && meetingId.value) {
      const targetPath = calcTarget.value === 'detailed' ? '/app/calculator' : '/app/quick-calculator'
      router.push({
        path: targetPath,
        query: {
          meetingId: meetingId.value || undefined,
          clientId: clientId.value || undefined,
          step: 'summary',
        },
      })
      return
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
        ...analysis.value,
        industry: analysisItem?.industry || '',
        taxationType: analysisItem?.tax_model || analysis.value.taxationType,
        contractType: analysisItem?.tax_model || '',
        desiredSavingsAmount: analysisItem?.expected_savings ?? null,
        avgEarnings: analysisItem?.expected_savings ?? null,
        highZUSPayments:
          analysisItem?.zus_cost_level === null || typeof analysisItem?.zus_cost_level === 'undefined'
            ? null
            : Boolean(Number(analysisItem?.zus_cost_level)),
        zusCost: analysisItem?.zus_cost_level ?? null,
        planningInvestments: typeof analysisItem?.investments_planned === 'boolean' ? analysisItem?.investments_planned : null,
        isInvesting: typeof analysisItem?.investments_planned === 'boolean' ? analysisItem?.investments_planned : null,
        hasDebts:
          analysisItem?.debt_level === 'yes' ? true : analysisItem?.debt_level === 'no' ? false : null,
        needsFinancing:
          typeof analysisItem?.financing_needed === 'boolean' ? analysisItem?.financing_needed : null,
        financingPurpose: analysisItem?.financing_purpose || '',
        hasBenefits:
          typeof analysisItem?.benefits === 'boolean'
            ? analysisItem?.benefits
            : analysisItem?.benefits === 'yes'
              ? true
              : analysisItem?.benefits === 'no'
                ? false
                : analysisItem?.benefits === 'Tak'
                  ? true
                  : analysisItem?.benefits === 'Nie'
                    ? false
                : null,
      }
    }

    if (!route.query.step) {
      if (!meetingId.value) {
        step.value = 1
      } else if (consentList.value.filter((c) => c.required).some((c) => !consentAccepted.value[c.id])) {
        step.value = 2
      } else if (!meetingAnalysisId.value) {
        step.value = 3
      } else {
        step.value = 4
      }
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
  crmProfileId.value = null
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

const goBack = () => {
  if (step.value > 1) {
    step.value--
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } else {
    cancelProcess()
  }
}

const getCurrentStepName = computed(() => {
  switch (step.value) {
    case 1:
      return 'Dane Firmy'
    case 2:
      return 'Zgody i oświadczenia'
    case 3:
      return 'Analiza'
    case 4:
      return 'Prezentacja'
    default:
      return ''
  }
})

const isStepValid = computed(() => {
  switch (step.value) {
    case 1:
      // Wymagamy: NIP, Nazwa, pełny adres oraz brak blokady NIP
      // ORAZ przynajmniej jednego kontaktu (existing lub draft)
      const hasContact = existingContacts.value.length > 0 || contactDrafts.value.length > 0
      return (
        !!companyData.value.nip &&
        !!companyData.value.name &&
        !!companyData.value.street &&
        !!companyData.value.zip &&
        !!companyData.value.city &&
        !nipBlocked.value &&
        !isCheckingNip.value &&
        hasContact
      )
    case 2:
      return consentList.value.filter((c) => c.required).every((c) => consentAccepted.value[c.id])
    case 3:
      return !!analysis.value.industry && !!(analysis.value.taxationType || analysis.value.contractType)
    case 4:
      return true
    default:
      return true
  }
})

const nextStep = async () => {
  if (step.value === 1) {
    // Validacja jest wewnątrz saveClientAndMeeting
    const ok = await saveClientAndMeeting()
    if (!ok) return
  }
  if (step.value === 2) {
    if (!isStepValid.value) {
      toast.warning('Proszę zaakceptować wymagane zgody.')
      return
    }
    const ok = await saveConsents()
    if (!ok) return
  }
  if (step.value === 3) {
    if (!isStepValid.value) {
      toast.warning('Proszę uzupełnić wymagane pola analizy.')
      return
    }
    const ok = await saveMeetingAnalysis()
    if (!ok) return
  }

  if (step.value < 4) {
    step.value += 1
    return
  }

  try {
    await updateCrmStatus('OFFER_PREPARING')
    await finalizeMeeting()
  } catch (err) {
    // błąd obsłużony wewnątrz finalizeMeeting
    return 
  }

  const targetPath = calcTarget.value === 'detailed' ? '/app/calculator' : '/app/quick-calculator'
  router.push({
    path: targetPath,
    query: {
      meetingId: meetingId.value || undefined,
      clientId: clientId.value || undefined,
      industry: analysis.value.industry,
      goal: (analysis.value.planningInvestments ?? analysis.value.isInvesting) ? 'Inwestycje' : 'Optymalizacja',
      // Pass split data
      uopCount: analysis.value.uopCount || undefined,
      uopSalaryNet: analysis.value.uopSalaryNet || undefined,
      uzCount: analysis.value.uzCount || undefined,
      uzSalaryNet: analysis.value.uzSalaryNet || undefined,
      // Fallback for legacy calculators
      employees: (analysis.value.uopCount || 0) + (analysis.value.uzCount || 0) || undefined,
      source: 'process'
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
      return 'Dalej: Prezentacja'
    case 4:
      return 'Stwórz Kalkulację'
    default:
      return 'Dalej'
  }
})

watch(
  () => analysis.value.industry,
  (value) => {
    if (value && industrySearch.value !== value) {
      industrySearch.value = value
    }
  }
)

onMounted(() => {
  clientStore.fetchClients() 
  const bootstrap = async () => {
    // Fallback defaults if API is empty or disabled
    const defaults = [
      { id: '1', code: 'RODO', title: 'Przetwarzanie Danych (RODO)', description: 'Wyrażam zgodę na przetwarzanie danych osobowych w celach realizacji usługi.', required: true },
      { id: '2', code: 'MARKETING', title: 'Kontakt Marketingowy', description: 'Wyrażam zgodę na kontakt telefoniczny i mailowy w celach marketingowych.', required: true },
      { id: '3', code: 'OFFER', title: 'Przesyłanie Ofert', description: 'Wyrażam zgodę na przesyłanie ofert handlowych drogą elektroniczną.', required: true },
    ]

    let list = []
    if (auth.enabled) {
      try {
        const { data } = await api.get('/v1/consents', { params: { per_page: 200 } })
        list = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
      } catch (e) {
        console.warn('Failed to fetch consents/files, using defaults', e)
      }
    }
    
    if (list.length === 0) {
      list = defaults
    }

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
  
  if (targetClientId) {
    isProcessActive.value = true
  }

  void bootstrap().then(() => {
    if (targetClientId) {
      loadExistingProcess(targetClientId, targetMeetingId)
    }
  })
})
</script>


<template>
  <div v-if="!isProcessActive" class="view-transition pb-20 space-y-8">
    <div class="w-full pt-6">
      <div class="bg-slate-900 rounded-3xl shadow-xl border border-slate-800 p-8 mb-8 flex flex-col md:flex-row justify-between items-center gap-6">
        <div class="flex-1">
          <div class="flex items-center gap-4 mb-3">
             <RouterLink to="/app/dashboard" class="w-10 h-10 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-700 transition shadow-sm">
                <AppIcon name="arrow-left" class="w-5 h-5" />
             </RouterLink>
             <h1 class="font-serif font-bold text-4xl text-white tracking-tight">Dzień dobry, {{ userName }}</h1>
          </div>
          <p class="text-slate-400 font-medium ml-14">Panel Procesu Sprzedażowego</p>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6 mb-12">
        <div @click="startNewMeeting" class="relative bg-white/80 backdrop-blur-sm rounded-2xl p-8 flex flex-col items-center justify-center gap-4 text-center transition-all duration-500 group h-48 overflow-hidden hover:shadow-2xl hover:shadow-emerald-500/20 hover:-translate-y-2 border border-white/50 hover:border-emerald-200/50 cursor-pointer">
          <div class="absolute inset-0 bg-gradient-to-br from-white/40 to-white/10 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
          <div class="absolute -inset-full top-0 block h-full w-1/2 -skew-x-12 bg-gradient-to-r from-transparent to-white opacity-40 group-hover:animate-shine" />
          <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-emerald-400 to-emerald-600 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left"></div>

          <div class="relative w-16 h-16 min-w-16 min-h-16 aspect-square shrink-0 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-3xl group-hover:scale-110 group-hover:rotate-6 transition-all duration-500 shadow-sm group-hover:shadow-emerald-500/30 group-hover:bg-emerald-500 group-hover:text-white ring-1 ring-emerald-100 group-hover:ring-emerald-400">
            <AppIcon name="calendar" class="w-8 h-8" />
          </div>
          <div class="relative z-10">
            <h3 class="font-bold text-slate-800 text-lg group-hover:text-emerald-700 transition-colors duration-300">Nowa Sprzedaż</h3>
            <p class="text-xs text-slate-400 mt-1 font-medium tracking-wide group-hover:text-slate-600 transition-colors">Rozpocznij proces</p>
          </div>
        </div>

        <RouterLink to="/app/clients" class="relative bg-white/80 backdrop-blur-sm rounded-2xl p-8 flex flex-col items-center justify-center gap-4 text-center transition-all duration-500 group h-48 overflow-hidden hover:shadow-2xl hover:shadow-indigo-500/20 hover:-translate-y-2 border border-white/50 hover:border-indigo-200/50 cursor-pointer">
          <div class="absolute inset-0 bg-gradient-to-br from-white/40 to-white/10 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
          <div class="absolute -inset-full top-0 block h-full w-1/2 -skew-x-12 bg-gradient-to-r from-transparent to-white opacity-40 group-hover:animate-shine" />
          <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-indigo-400 to-indigo-600 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left"></div>

          <div class="relative w-16 h-16 min-w-16 min-h-16 aspect-square shrink-0 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-3xl group-hover:scale-110 group-hover:rotate-6 transition-all duration-500 shadow-sm group-hover:shadow-indigo-500/30 group-hover:bg-indigo-500 group-hover:text-white ring-1 ring-indigo-100 group-hover:ring-indigo-400">
            <AppIcon name="folder" class="w-8 h-8" />
          </div>
          <div class="relative z-10">
            <h3 class="font-bold text-slate-800 text-lg group-hover:text-indigo-700 transition-colors duration-300">Wczytaj Spotkanie</h3>
            <p class="text-xs text-slate-400 mt-1 font-medium tracking-wide group-hover:text-slate-600 transition-colors">Kontynuuj pracę</p>
          </div>
        </RouterLink>

        <RouterLink to="/app/knowledge-base" class="relative bg-white/80 backdrop-blur-sm rounded-2xl p-8 flex flex-col items-center justify-center gap-4 text-center transition-all duration-500 group h-48 overflow-hidden hover:shadow-2xl hover:shadow-teal-500/20 hover:-translate-y-2 border border-white/50 hover:border-teal-200/50 cursor-pointer">
          <div class="absolute inset-0 bg-gradient-to-br from-white/40 to-white/10 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
          <div class="absolute -inset-full top-0 block h-full w-1/2 -skew-x-12 bg-gradient-to-r from-transparent to-white opacity-40 group-hover:animate-shine" />
          <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-teal-400 to-teal-600 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left"></div>

          <div class="relative w-16 h-16 min-w-16 min-h-16 aspect-square shrink-0 rounded-2xl bg-teal-50 text-teal-600 flex items-center justify-center text-3xl group-hover:scale-110 group-hover:rotate-6 transition-all duration-500 shadow-sm group-hover:shadow-teal-500/30 group-hover:bg-teal-500 group-hover:text-white ring-1 ring-teal-100 group-hover:ring-teal-400">
            <AppIcon name="book-open" class="w-8 h-8" />
          </div>
          <div class="relative z-10">
            <h3 class="font-bold text-slate-800 text-lg group-hover:text-teal-700 transition-colors duration-300">Baza Wiedzy</h3>
            <p class="text-xs text-slate-400 mt-1 font-medium tracking-wide group-hover:text-slate-600 transition-colors">Dokumenty i info</p>
          </div>
        </RouterLink>

        <RouterLink to="/app/quick-calculator" class="relative bg-white/80 backdrop-blur-sm rounded-2xl p-8 flex flex-col items-center justify-center gap-4 text-center transition-all duration-500 group h-48 overflow-hidden hover:shadow-2xl hover:shadow-blue-500/20 hover:-translate-y-2 border border-white/50 hover:border-blue-200/50 cursor-pointer">
          <div class="absolute inset-0 bg-gradient-to-br from-white/40 to-white/10 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
          <div class="absolute -inset-full top-0 block h-full w-1/2 -skew-x-12 bg-gradient-to-r from-transparent to-white opacity-40 group-hover:animate-shine" />
          <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-400 to-blue-600 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left"></div>

          <div class="relative w-16 h-16 min-w-16 min-h-16 aspect-square shrink-0 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-3xl group-hover:scale-110 group-hover:rotate-6 transition-all duration-500 shadow-sm group-hover:shadow-blue-500/30 group-hover:bg-blue-500 group-hover:text-white ring-1 ring-blue-100 group-hover:ring-blue-400">
            <AppIcon name="calculator" class="w-8 h-8" />
          </div>
          <div class="relative z-10">
            <h3 class="font-bold text-slate-800 text-lg group-hover:text-blue-700 transition-colors duration-300">Szybka Kalkulacja</h3>
            <p class="text-xs text-slate-400 mt-1 font-medium tracking-wide group-hover:text-slate-600 transition-colors">Uproszczona</p>
          </div>
        </RouterLink>

        <RouterLink to="/app/calculator" class="relative bg-white/80 backdrop-blur-sm rounded-2xl p-8 flex flex-col items-center justify-center gap-4 text-center transition-all duration-500 group h-48 overflow-hidden hover:shadow-2xl hover:shadow-purple-500/20 hover:-translate-y-2 border border-white/50 hover:border-purple-200/50 cursor-pointer">
          <div class="absolute inset-0 bg-gradient-to-br from-white/40 to-white/10 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
          <div class="absolute -inset-full top-0 block h-full w-1/2 -skew-x-12 bg-gradient-to-r from-transparent to-white opacity-40 group-hover:animate-shine" />
          <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-purple-400 to-purple-600 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left"></div>

          <div class="relative w-16 h-16 min-w-16 min-h-16 aspect-square shrink-0 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center text-3xl group-hover:scale-110 group-hover:rotate-6 transition-all duration-500 shadow-sm group-hover:shadow-purple-500/30 group-hover:bg-purple-500 group-hover:text-white ring-1 ring-purple-100 group-hover:ring-purple-400">
            <AppIcon name="chart-line" class="w-8 h-8" />
          </div>
          <div class="relative z-10">
            <h3 class="font-bold text-slate-800 text-lg group-hover:text-purple-700 transition-colors duration-300">Szczegółowa Kalkulacja</h3>
            <p class="text-xs text-slate-400 mt-1 font-medium tracking-wide group-hover:text-slate-600 transition-colors">Pełny raport</p>
          </div>
        </RouterLink>
      </div>

      <div class="mt-20 text-center">
        <p class="text-[10px] text-slate-300 font-medium uppercase tracking-widest">Stratton Prime - Doradztwo Biznesowe 2026</p>
      </div>
    </div>
  </div>


  <div v-else class="view-transition pb-20 space-y-8">
    <div class="w-full pt-6">
      <div class="bg-slate-900 rounded-3xl shadow-xl border border-slate-800 p-8 mb-8 flex flex-col md:flex-row justify-between items-center gap-6">
        <div class="flex-1">
          <div class="flex items-center gap-4 mb-3">
             <button @click="goBack" class="w-10 h-10 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-700 transition shadow-sm">
                <AppIcon name="arrow-left" class="w-5 h-5" />
             </button>
             <h1 class="font-serif font-bold text-4xl text-white tracking-tight">Nowe Spotkanie Sprzedażowe</h1>
          </div>
          <div class="ml-14 flex items-center gap-3">
              <span class="text-slate-400 font-medium">Panel Procesu Sprzedażowego</span>
              <span class="text-xs bg-emerald-500/20 text-emerald-400 font-bold px-2 py-0.5 rounded border border-emerald-500/30 uppercase tracking-wider">Sesja Aktywna</span>
              <span class="text-xs text-slate-500 font-mono">ID: {{ sessionId }}</span>
          </div>
        </div>
        <div class="hidden md:flex items-center gap-2">
          <div v-for="i in [1, 2, 3, 4]" :key="i" class="h-1.5 w-12 rounded-full transition-colors" :class="step >= i ? 'bg-stratton-gold' : 'bg-slate-800'"></div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
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
              <button @click="showFetchMeetingModal = true" class="text-[10px] font-bold text-emerald-600 border border-emerald-200 bg-emerald-50 px-3 py-1.5 rounded-lg hover:bg-emerald-100 hover:border-emerald-300 transition uppercase tracking-wide flex items-center gap-2">
                <AppIcon name="refresh" class="w-3.5 h-3.5" />
                POBIERZ DANE KLIENTA ZE SPOTKANIA
              </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
              <div class="space-y-6">
                <div>
                  <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide mb-2">Numer NIP</label>
                  <div class="flex">
                    <input v-model="companyData.nip" type="text" class="flex-1 bg-slate-50 border border-slate-200 rounded-l-lg px-4 py-3 text-slate-900 font-mono font-bold focus:ring-1 focus:ring-stratton-gold focus:border-stratton-gold outline-none transition-all" />
                    <button type="button" class="bg-slate-800 text-white px-4 rounded-r-lg hover:bg-slate-700 transition" :disabled="isFetchingGus" @click="fetchCompanyByNip">
                      <span v-if="isFetchingGus">...</span>
                      <span v-else>Pobierz</span>
                    </button>
                  </div>
                  <p v-if="gusError" class="text-xs text-red-500 mt-1">{{ gusError }}</p>
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
                class="flex items-start gap-4 p-4 rounded-xl border transition-all hover:shadow-sm"
                :class="consentAccepted[consent.id] ? 'border-emerald-500 bg-emerald-50/50' : 'border-slate-200 bg-white'"
              >
                <div class="pt-1">
                  <input v-model="consentAccepted[consent.id]" type="checkbox" class="h-5 w-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600 cursor-pointer" />
                </div>
                <div class="flex-1">
                  <div class="flex justify-between items-start">
                    <span class="font-bold text-slate-800 text-base mb-1 block">{{ consent.title }}<span v-if="consent.required" class="text-red-500"> *</span></span>
                    <button
                      v-if="consentTexts[consent.code] || consentTexts['marketing']"
                      @click.prevent="openConsentText(consent.code, consent.title)"
                      class="text-xs font-bold text-stratton-gold hover:text-stratton-gold/80 px-2 py-1 rounded hover:bg-amber-50 transition"
                    >
                      Pokaż treść
                    </button>
                  </div>
                  <p class="text-slate-500 text-sm leading-relaxed">{{ consent.description }}</p>
                  <div v-if="consent.file_url" class="mt-2 flex items-center gap-3 text-xs">
                    <button type="button" class="text-sky-600 hover:underline" @click.stop="openConsentFile(consent, false)">Podgląd</button>
                    <button type="button" class="text-sky-600 hover:underline" @click.stop="openConsentFile(consent, true)">Pobierz</button>
                  </div>
                </div>
              </label>
            </div>
            <ConsentTextModal 
              :show="showConsentModal"
              :title="selectedConsentTitle"
              :content="getConsentContent(selectedConsentCode)"
              @close="showConsentModal = false"
            />
          </div>

          <div v-else-if="step === 3" class="space-y-8 animate-fade-in-up">
            
            <!-- Section 1: Dane i Potencjał -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-6">
               <div class="pb-4 border-b border-slate-100 flex justify-between items-center">
                  <div>
                    <h3 class="text-xl font-serif font-bold text-slate-800">Profil Firmy</h3>
                    <p class="text-slate-500 text-sm mt-1">Podstawowe parametry do analizy.</p>
                  </div>
                  <div class="h-10 w-10 bg-slate-50 rounded-full flex items-center justify-center text-slate-400">
                    <span class="font-bold">1</span>
                  </div>
               </div>

               <!-- Industry -->
               <div class="space-y-2">
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide">Branża</label>
                <div class="relative">
                  <AppIcon name="magnifying-glass" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                  <input
                    v-model="industrySearch"
                    list="industry-options"
                    type="text"
                    placeholder="Wyszukaj branżę..."
                    class="w-full bg-white border-2 border-slate-200 rounded-lg pl-9 pr-4 py-2.5 text-sm text-slate-800 font-semibold focus:border-stratton-gold focus:ring-2 focus:ring-amber-100 outline-none transition-all"
                    @input="analysis.industry = industrySearch"
                  />
                </div>
                <datalist id="industry-options">
                  <option value="Uprawy rolne, chów i hodowla zwierząt, łowiectwo, włączając działalność usługową"></option>
                  <option value="Leśnictwo i pozyskiwanie drewna"></option>
                  <option value="Rybactwo"></option>
                  <option value="Wydobywanie węgla kamiennego i węgla brunatnego (lignitu)"></option>
                  <option value="Górnictwo ropy naftowej i gazu ziemnego"></option>
                  <option value="Górnictwo rud metali"></option>
                  <option value="Pozostałe górnictwo i wydobywanie"></option>
                  <option value="Usługi wspomagające górnictwo i wydobywanie"></option>
                  <option value="Produkcja art. spożywczych"></option>
                  <option value="Produkcja napojów"></option>
                  <option value="Produkcja wyrobów tytoniowych"></option>
                  <option value="Produkcja wyrobów tekstylnych"></option>
                  <option value="Produkcja odzieży"></option>
                  <option value="Produkcja skór i wyrobów ze skór wyprawionych"></option>
                  <option value="Produkcja wyrobów z drewna oraz korka, z wyłączeniem mebli; Produkcja wyrobów ze słomy i materiałów używanych do wyplatania"></option>
                  <option value="Produkcja papieru i wyrobów z papieru"></option>
                  <option value="Poligrafia i reprodukcja zapisanych nośników informacji"></option>
                  <option value="Wytwarzanie i przetwarzanie koksu i produktów rafinacji ropy naftowej"></option>
                  <option value="Produkcja chemikaliów i wyrobów chemicznych"></option>
                  <option value="Produkcja podstawowych substancji farmaceutycznych oraz leków i pozostałych wyrobów farmaceutycznych"></option>
                  <option value="Produkcja wyrobów z gumy i tworzyw sztucznych"></option>
                  <option value="Produkcja wyrobów z pozostałych mineralnych surowców niemetalicznych"></option>
                  <option value="Produkcja metali"></option>
                  <option value="Produkcja metalowych wyrobów gotowych, z wyłączeniem maszyn i urządzeń"></option>
                  <option value="Produkcja komputerów, wyrobów elektronicznych i optycznych"></option>
                  <option value="Produkcja urządzeń elektrycznych"></option>
                  <option value="Produkcja maszyn i urządzeń, gdzie indziej niesklasyfikowana"></option>
                  <option value="Produkcja pojazdów samochodowych, przyczep i naczep, z wyłączeniem motocykli"></option>
                  <option value="Produkcja pozostałego sprzętu transportowego"></option>
                  <option value="Produkcja mebli"></option>
                  <option value="Pozostała produkcja wyrobów"></option>
                  <option value="Naprawa, konserwacja i instalowanie maszyn i urządzeń"></option>
                  <option value="Wytwarzanie i zaopatrywanie w energię elektryczną, gaz, parę wodną, gorącą wodę i powietrze do układów klimatyzacyjnych"></option>
                  <option value="Pobór, uzdatnianie i dostarczanie wody"></option>
                  <option value="Odprowadzanie i oczyszczanie ścieków"></option>
                  <option value="Zbieranie, przetwarzanie i unieszkodliwianie odpadów oraz odzysk surowców"></option>
                  <option value="Rekultywacją i pozostałe usługi związane z gospodarką odpadami"></option>
                  <option value="Roboty budowlane związane ze wznoszeniem budynków"></option>
                  <option value="Roboty związane z budową obiektów inżynierii lądowej i wodnej"></option>
                  <option value="Roboty budowlane specjalistyczne"></option>
                  <option value="Handel hurtowy i detaliczny pojazdami samochodowymi; Naprawa pojazdów samochodowych"></option>
                  <option value="Handel hurtowy (bez pojazdów samochodowych)"></option>
                  <option value="Handel detaliczny (bez pojazdów samochodowych)"></option>
                  <option value="Transport lądowy oraz rurociągowy"></option>
                  <option value="Transport wodny"></option>
                  <option value="Transport lotniczy"></option>
                  <option value="Magazynowanie i usługi wspomagające transport"></option>
                  <option value="Działalność pocztowa i kurierska"></option>
                  <option value="Zakwaterowanie"></option>
                  <option value="Wyżywienie"></option>
                  <option value="Działalność wydawnicza"></option>
                  <option value="Działalność filmowa, telewizyjna, dźwiękowa i muzyczna"></option>
                  <option value="Nadawanie programów telewizyjnych i radiowych"></option>
                  <option value="Telekomunikacja"></option>
                  <option value="Oprogramowanie i doradztwo w zakresie informatyki"></option>
                  <option value="Zarządzanie stronami WWW, przetwarzanie danych i hosting"></option>
                  <option value="Usługi finansowe z wyłączeniem ubezpieczeń i funduszów emerytalnych"></option>
                  <option value="Ubezpieczenia, reasekuracja i fundusze emerytalne, z wyłączeniem obowiązkowego ubezpieczenia społecznego"></option>
                  <option value="Usługi objęte pośrednictwem finansowym"></option>
                  <option value="Obsługa rynku nieruchomości"></option>
                  <option value="Usługi prawnicze, rachunkowo-księgowe i doradztwo podatkowe"></option>
                  <option value="Działalność firm centralnych i doradztwo związane z zarządzaniem"></option>
                  <option value="Architektura, inżynieria, badania i analizy techniczne"></option>
                  <option value="Badania naukowe i prace rozwojowe"></option>
                  <option value="Reklama, badanie rynku i opinii publicznej"></option>
                  <option value="Projektowanie, fotografia, tłumaczenia, działalność profesjonalna"></option>
                  <option value="Weterynaria"></option>
                  <option value="Wynajem i dzierżawa"></option>
                  <option value="Zatrudnienie"></option>
                  <option value="Turystyka"></option>
                  <option value="Usługi detektywistyczne i ochroniarskie"></option>
                  <option value="Sprzątanie budynków i gospodarowanie terenami zieleni"></option>
                  <option value="Administracja biurowa i wspomaganie prowadzenia działalności gospodarczej"></option>
                  <option value="Administracja publiczna, obrona narodowa i obowiązkowe zabezpieczenia społeczne"></option>
                  <option value="Baza Wiedzy"></option>
                  <option value="Opieka zdrowotna"></option>
                  <option value="Pomoc społeczna (z zakwaterowaniem)"></option>
                  <option value="Pomoc społeczna (bez zakwaterowania)"></option>
                  <option value="Kultura i rozrywka"></option>
                  <option value="Biblioteki, archiwa, muzea, zoo oraz inne obiekty kulturalne"></option>
                  <option value="Gry losowe i zakłady wzajemne"></option>
                  <option value="Sport, rozrywka i rekreacja"></option>
                  <option value="Działalność organizacji członkowskich"></option>
                  <option value="Naprawa komputerów i artykułów osobistych oraz domowych"></option>
                  <option value="Pozostała indywidualna działalność usługowa"></option>
                </datalist>
               </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Employees -->
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide">Liczba pracowników</label>
                        <div class="relative">
                            <input v-model.number="analysis.uopCount" type="number" min="0" placeholder="0" class="w-full bg-slate-50 border-2 border-slate-200 rounded-lg px-4 py-2.5 pl-4 text-slate-800 font-bold focus:border-stratton-gold focus:bg-white outline-none transition-all" />
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-medium">os.</span>
                        </div>
                    </div>
                    
                    <!-- Savings -->
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide">Oczekiwana oszczędność</label>
                        <div class="relative">
                            <input v-model.number="analysis.desiredSavingsAmount" type="number" min="0" placeholder="0" class="w-full bg-slate-50 border-2 border-slate-200 rounded-lg px-4 py-2.5 pl-4 text-slate-800 font-bold focus:border-stratton-gold focus:bg-white outline-none transition-all" />
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-medium">PLN/msc</span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-6 border-t border-slate-100">
                    <!-- Tax Form -->
                    <div class="space-y-3">
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide">Forma opodatkowania</label>
                        <div class="flex bg-slate-100 p-1.5 rounded-xl">
                            <button 
                                @click="analysis.taxationType = 'ryczalt'"
                                :class="analysis.taxationType === 'ryczalt' ? 'bg-white text-stratton-gold shadow-sm ring-1 ring-slate-200' : 'text-slate-500 hover:text-slate-700'"
                                class="flex-1 py-2.5 text-sm font-bold rounded-lg transition-all duration-200"
                            >Ryczałt</button>
                            <button 
                                @click="analysis.taxationType = 'vat'"
                                :class="analysis.taxationType === 'vat' ? 'bg-white text-stratton-gold shadow-sm ring-1 ring-slate-200' : 'text-slate-500 hover:text-slate-700'"
                                class="flex-1 py-2.5 text-sm font-bold rounded-lg transition-all duration-200"
                            >Rozliczenie VAT</button>
                        </div>
                    </div>

                    <!-- Benefits -->
                    <div class="space-y-3">
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide">Benefity pracownicze</label>
                        <div class="flex bg-slate-100 p-1.5 rounded-xl">
                            <button 
                                @click="analysis.hasBenefits = true"
                                :class="analysis.hasBenefits === true ? 'bg-white text-stratton-gold shadow-sm ring-1 ring-slate-200' : 'text-slate-500 hover:text-slate-700'"
                                class="flex-1 py-2.5 text-sm font-bold rounded-lg transition-all duration-200"
                            >Tak</button>
                            <button 
                                @click="analysis.hasBenefits = false"
                                :class="analysis.hasBenefits === false ? 'bg-white text-stratton-gold shadow-sm ring-1 ring-slate-200' : 'text-slate-500 hover:text-slate-700'"
                                class="flex-1 py-2.5 text-sm font-bold rounded-lg transition-all duration-200"
                            >Nie</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2: Wywiad -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 bg-slate-50/50 border-b border-slate-200 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-serif font-bold text-slate-800">Wywiad Kwalifikacyjny</h3>
                        <p class="text-slate-500 text-xs mt-0.5">Dodatkowe informacje o kondycji firmy</p>
                    </div>
                    <div class="h-10 w-10 bg-white border border-slate-100 rounded-full flex items-center justify-center text-slate-400 shadow-sm">
                         <span class="font-bold">2</span>
                    </div>
                </div>

                <div class="divide-y divide-slate-100">
                    <!-- Q1 -->
                    <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-slate-50/50 transition-colors group">
                        <span class="text-sm font-bold text-slate-700 group-hover:text-slate-900 transition-colors">Czy planujecie Państwo inwestycje?</span>
                        <div class="flex bg-slate-100 p-1 rounded-lg shrink-0 w-full sm:w-auto">
                            <button @click="analysis.planningInvestments = true" :class="analysis.planningInvestments === true ? 'bg-white shadow text-stratton-gold font-bold' : 'text-slate-500 font-medium'" class="px-8 py-1.5 text-sm rounded-md transition-all flex-1 sm:flex-none">Tak</button>
                            <button @click="analysis.planningInvestments = false" :class="analysis.planningInvestments === false ? 'bg-white shadow text-slate-800 font-bold' : 'text-slate-500 font-medium'" class="px-8 py-1.5 text-sm rounded-md transition-all flex-1 sm:flex-none">Nie</button>
                        </div>
                    </div>
                    <!-- Q2 ZUS -->
                    <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-slate-50/50 transition-colors group">
                        <span class="text-sm font-bold text-slate-700 group-hover:text-slate-900 transition-colors">Czy płacicie wysokie składki ZUS?</span>
                        <div class="flex bg-slate-100 p-1 rounded-lg shrink-0 w-full sm:w-auto">
                            <button @click="analysis.highZUSPayments = true" :class="analysis.highZUSPayments === true ? 'bg-white shadow text-stratton-gold font-bold' : 'text-slate-500 font-medium'" class="px-8 py-1.5 text-sm rounded-md transition-all flex-1 sm:flex-none">Tak</button>
                            <button @click="analysis.highZUSPayments = false" :class="analysis.highZUSPayments === false ? 'bg-white shadow text-slate-800 font-bold' : 'text-slate-500 font-medium'" class="px-8 py-1.5 text-sm rounded-md transition-all flex-1 sm:flex-none">Nie</button>
                        </div>
                    </div>
                    <!-- Q3 Savings -->
                    <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-slate-50/50 transition-colors group">
                        <span class="text-sm font-bold text-slate-700 group-hover:text-slate-900 transition-colors">Czy wprowadzacie oszczędności?</span>
                        <div class="flex bg-slate-100 p-1 rounded-lg shrink-0 w-full sm:w-auto">
                            <button @click="analysis.implementingSavings = true" :class="analysis.implementingSavings === true ? 'bg-white shadow text-stratton-gold font-bold' : 'text-slate-500 font-medium'" class="px-8 py-1.5 text-sm rounded-md transition-all flex-1 sm:flex-none">Tak</button>
                            <button @click="analysis.implementingSavings = false" :class="analysis.implementingSavings === false ? 'bg-white shadow text-slate-800 font-bold' : 'text-slate-500 font-medium'" class="px-8 py-1.5 text-sm rounded-md transition-all flex-1 sm:flex-none">Nie</button>
                        </div>
                    </div>
                    <!-- Q4 Debts (Optional) -->
                    <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-slate-50/50 transition-colors group">
                        <div>
                            <span class="text-sm font-bold text-slate-700 block group-hover:text-slate-900 transition-colors">Czy firma ma zadłużenia?</span>
                            <span class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Opcjonalne</span>
                        </div>
                        <div class="flex bg-slate-100 p-1 rounded-lg shrink-0 w-full sm:w-auto overflow-hidden">
                            <button @click="analysis.hasDebts = true" :class="analysis.hasDebts === true ? 'bg-white shadow text-stratton-gold font-bold' : 'text-slate-500 font-medium'" class="px-5 py-1.5 text-xs sm:text-sm rounded-md transition-all flex-1 sm:flex-none">Tak</button>
                            <button @click="analysis.hasDebts = false" :class="analysis.hasDebts === false ? 'bg-white shadow text-slate-800 font-bold' : 'text-slate-500 font-medium'" class="px-5 py-1.5 text-xs sm:text-sm rounded-md transition-all flex-1 sm:flex-none">Nie</button>
                            <button @click="analysis.hasDebts = null" :class="analysis.hasDebts === null ? 'bg-white shadow text-slate-400 font-bold' : 'text-slate-400 font-normal'" class="px-5 py-1.5 text-xs sm:text-sm rounded-md transition-all flex-1 sm:flex-none truncate">Pomiń</button>
                        </div>
                    </div>
                    
                    <!-- Q5 Financing (Optional) -->
                    <div class="px-6 py-4 hover:bg-slate-50/50 transition-colors group">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-2">
                            <div>
                                <span class="text-sm font-bold text-slate-700 block group-hover:text-slate-900 transition-colors">Poszukujecie finansowania?</span>
                                <span class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Opcjonalne</span>
                            </div>
                            <div class="flex bg-slate-100 p-1 rounded-lg shrink-0 w-full sm:w-auto overflow-hidden">
                                <button @click="analysis.needsFinancing = true" :class="analysis.needsFinancing === true ? 'bg-white shadow text-stratton-gold font-bold' : 'text-slate-500 font-medium'" class="px-5 py-1.5 text-xs sm:text-sm rounded-md transition-all flex-1 sm:flex-none">Tak</button>
                                <button @click="analysis.needsFinancing = false" :class="analysis.needsFinancing === false ? 'bg-white shadow text-slate-800 font-bold' : 'text-slate-500 font-medium'" class="px-5 py-1.5 text-xs sm:text-sm rounded-md transition-all flex-1 sm:flex-none">Nie</button>
                                <button @click="analysis.needsFinancing = null" :class="analysis.needsFinancing === null ? 'bg-white shadow text-slate-400 font-bold' : 'text-slate-400 font-normal'" class="px-5 py-1.5 text-xs sm:text-sm rounded-md transition-all flex-1 sm:flex-none truncate">Pomiń</button>
                            </div>
                        </div>
                        <div v-if="analysis.needsFinancing" class="mt-3 pl-0 sm:pl-4 animate-fade-in-down">
                            <input v-model="analysis.financingPurpose" type="text" placeholder="Na co potrzebujecie finansowania?..." class="w-full bg-slate-50 border-b-2 border-slate-200 px-2 py-2 text-slate-800 text-sm font-bold focus:border-stratton-gold focus:bg-transparent outline-none transition-all placeholder:font-normal" />
                        </div>
                    </div>
                </div>
            </div>

          </div>

          <div v-else class="space-y-6 animate-fade-in-up">
            <div class="text-center mb-8">
               <h3 class="text-2xl font-serif font-bold text-slate-800 mb-2">Prezentacja Rozwiązania</h3>
               <p class="text-slate-500 max-w-lg mx-auto">Zapoznaj się z kluczowymi aspektami naszego modelu współpracy.</p>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
              
              <!-- Dynamic Files -->
              <button 
                  v-for="file in knowledgeFiles" 
                  :key="file.id" 
                  @click="openPresentation(file.category || 'FILE', file.name, file)" 
                  class="group relative bg-white border border-slate-200 rounded-2xl p-6 h-64 text-left shadow-sm hover:shadow-xl hover:border-stratton-gold/30 hover:-translate-y-1 transition-all duration-300 overflow-hidden flex flex-col justify-between"
              >
                 <div class="absolute top-0 right-0 w-32 h-32 bg-slate-50 rounded-bl-[100px] -mr-8 -mt-8 transition-transform group-hover:scale-150 duration-700"></div>
                 <div class="relative z-10">
                    <div class="w-14 h-14 rounded-2xl bg-slate-100 flex items-center justify-center text-slate-600 mb-6 group-hover:bg-stratton-gold group-hover:text-white transition-colors">
                      <AppIcon v-if="file.fileType?.includes('pdf')" name="document-text" class="w-7 h-7" />
                      <AppIcon v-else-if="file.fileType?.includes('video')" name="video-camera" class="w-7 h-7" />
                      <AppIcon v-else-if="file.fileType?.includes('image')" name="photo" class="w-7 h-7" />
                      <AppIcon v-else name="document" class="w-7 h-7" />
                    </div>
                    <h4 class="font-bold text-xl text-slate-900 group-hover:text-stratton-gold transition-colors line-clamp-2">{{ file.name }}</h4>
                 </div>
                 <div class="relative z-10 flex items-center justify-between mt-auto">
                    <p class="text-sm text-slate-500 font-medium truncate pr-4">{{ categoryNames[file.category as FileCategory] || file.category || 'Prezentacja' }}</p>
                    <div class="w-8 h-8 rounded-full bg-slate-50 flex items-center justify-center group-hover:bg-stratton-gold group-hover:text-white transition-colors shrink-0">
                       <AppIcon name="arrow-right" class="w-4 h-4" />
                    </div>
                 </div>
              </button>

              <!-- Fallback Hardcoded Tiles (only if no dynamic files) -->
              <template v-if="knowledgeFiles.length === 0">
                  <!-- Cash Flow Tile -->
                  <button @click="openPresentation('CASH_FLOW', 'Dokumenty do pobrania')" class="group relative bg-white border border-slate-200 rounded-2xl p-6 h-64 text-left shadow-sm hover:shadow-xl hover:border-stratton-gold/30 hover:-translate-y-1 transition-all duration-300 overflow-hidden flex flex-col justify-between">
                     <div class="absolute top-0 right-0 w-32 h-32 bg-emerald-50 rounded-bl-[100px] -mr-8 -mt-8 transition-transform group-hover:scale-150 duration-700"></div>
                     <div class="relative z-10">
                        <div class="w-14 h-14 rounded-2xl bg-emerald-100 flex items-center justify-center text-emerald-600 mb-6 group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                          <AppIcon name="chart-pie" class="w-7 h-7" />
                        </div>
                        <h4 class="font-bold text-xl text-slate-900 group-hover:text-emerald-700 transition-colors">Dokumenty do pobrania</h4>
                     </div>
                     <div class="relative z-10 flex items-center justify-between mt-auto">
                        <p class="text-sm text-slate-500 font-medium">Analiza finansowa</p>
                        <div class="w-8 h-8 rounded-full bg-slate-50 flex items-center justify-center group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                           <AppIcon name="arrow-right" class="w-4 h-4" />
                        </div>
                     </div>
                  </button>
                  
                  <!-- Legal Tile -->
                  <button @click="openPresentation('LEGAL', 'Podstawa prawna')" class="group relative bg-white border border-slate-200 rounded-2xl p-6 h-64 text-left shadow-sm hover:shadow-xl hover:border-stratton-gold/30 hover:-translate-y-1 transition-all duration-300 overflow-hidden flex flex-col justify-between">
                     <div class="absolute top-0 right-0 w-32 h-32 bg-blue-50 rounded-bl-[100px] -mr-8 -mt-8 transition-transform group-hover:scale-150 duration-700"></div>
                     <div class="relative z-10">
                        <div class="w-14 h-14 rounded-2xl bg-blue-100 flex items-center justify-center text-blue-600 mb-6 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                          <AppIcon name="scale" class="w-7 h-7" />
                        </div>
                        <h4 class="font-bold text-xl text-slate-900 group-hover:text-blue-700 transition-colors">Podstawa prawna</h4>
                     </div>
                     <div class="relative z-10 flex items-center justify-between mt-auto">
                        <p class="text-sm text-slate-500 font-medium">Bezpieczeństwo i przepisy</p>
                         <div class="w-8 h-8 rounded-full bg-slate-50 flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition-colors">
                           <AppIcon name="arrow-right" class="w-4 h-4" />
                        </div>
                     </div>
                  </button>

                  <!-- Graphic Presentation Tile -->
                  <button @click="openPresentation('GRAPHIC', 'Schemat działania')" class="group relative bg-white border border-slate-200 rounded-2xl p-6 h-64 text-left shadow-sm hover:shadow-xl hover:border-stratton-gold/30 hover:-translate-y-1 transition-all duration-300 overflow-hidden flex flex-col justify-between">
                     <div class="absolute top-0 right-0 w-32 h-32 bg-indigo-50 rounded-bl-[100px] -mr-8 -mt-8 transition-transform group-hover:scale-150 duration-700"></div>
                     <div class="relative z-10">
                        <div class="w-14 h-14 rounded-2xl bg-indigo-100 flex items-center justify-center text-indigo-600 mb-6 group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                          <AppIcon name="presentation-chart-line" class="w-7 h-7" />
                        </div>
                        <h4 class="font-bold text-xl text-slate-900 group-hover:text-indigo-700 transition-colors">Schemat działania</h4>
                     </div>
                     <div class="relative z-10 flex items-center justify-between mt-auto">
                        <p class="text-sm text-slate-500 font-medium">Wizualizacja modelu</p>
                         <div class="w-8 h-8 rounded-full bg-slate-50 flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                           <AppIcon name="arrow-right" class="w-4 h-4" />
                        </div>
                     </div>
                  </button>

                   <!-- Video Tile -->
                  <button @click="openPresentation('VIDEO', 'Materiały wideo')" class="group relative bg-white border border-slate-200 rounded-2xl p-6 h-64 text-left shadow-sm hover:shadow-xl hover:border-stratton-gold/30 hover:-translate-y-1 transition-all duration-300 overflow-hidden flex flex-col justify-between">
                     <div class="absolute top-0 right-0 w-32 h-32 bg-red-50 rounded-bl-[100px] -mr-8 -mt-8 transition-transform group-hover:scale-150 duration-700"></div>
                     <div class="relative z-10">
                        <div class="w-14 h-14 rounded-2xl bg-red-100 flex items-center justify-center text-red-600 mb-6 group-hover:bg-red-600 group-hover:text-white transition-colors">
                          <AppIcon name="video-camera" class="w-7 h-7" />
                        </div>
                        <h4 class="font-bold text-xl text-slate-900 group-hover:text-red-700 transition-colors">Materiały wideo</h4>
                     </div>
                     <div class="relative z-10 flex items-center justify-between mt-auto">
                        <p class="text-sm text-slate-500 font-medium">Materiał multimedialny</p>
                         <div class="w-8 h-8 rounded-full bg-slate-50 flex items-center justify-center group-hover:bg-red-600 group-hover:text-white transition-colors">
                           <AppIcon name="arrow-right" class="w-4 h-4" />
                        </div>
                     </div>
                  </button>
              </template>
            </div>
            
            <PresentationModal 
                :show="showPresentationModal"
                :title="selectedPresentationTitle"
                :type="selectedPresentationType"
                :file-url="selectedPresentationFile?.file_url"
                :file-type="selectedPresentationFile?.fileType"
                :description="selectedPresentationFile?.description"
                @close="showPresentationModal = false"
            />
          </div>

          <div class="mt-10 pt-6 border-t border-slate-100 flex items-center justify-between">
            <button
              type="button"
              class="px-6 py-3 rounded-xl font-bold text-xs uppercase tracking-wide border border-slate-200 text-slate-600 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="step <= 1 || isSavingStep"
              @click="prevStep"
            >
              Wstecz
            </button>
            <button
              type="button"
              class="bg-indigo-600 text-white px-8 py-4 rounded-xl font-bold uppercase tracking-wide hover:bg-indigo-700 transition flex items-center gap-3 shadow-lg shadow-indigo-200 hover:shadow-xl hover:-translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="isSavingStep || isCheckingNip || isFetchingGus || !isStepValid"
              @click="nextStep"
            >
              {{ isSavingStep ? 'Przetwarzanie...' : getButtonLabel }}
              <AppIcon :name="isStepValid ? 'arrow-right' : 'lock-closed'" class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
      </div>
    </div>

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
          <VueFilesPreview :key="previewKey" :file="previewFile || undefined" :url="previewUrl" class="w-full h-full" />
        </div>
      </div>
    </div>

    <div v-if="showFetchMeetingModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showFetchMeetingModal = false"></div>
      <div class="relative bg-white w-full max-w-2xl rounded-2xl shadow-2xl border border-slate-100 flex flex-col max-h-[80vh] animate-scale-in">
        <div class="flex items-center justify-between p-6 border-b border-slate-100 bg-slate-50/50 rounded-t-2xl">
          <h3 class="text-lg font-serif font-bold text-slate-800">Wybierz ze spotkań (Prospekci)</h3>
          <button @click="showFetchMeetingModal = false" class="text-slate-400 hover:text-slate-600 transition">
            <AppIcon name="xmark" class="w-6 h-6" />
          </button>
        </div>
        
        <div class="p-4 border-b border-slate-100">
           <div class="relative">
              <AppIcon name="search" class="absolute left-3 top-3 w-5 h-5 text-slate-400" />
              <input 
                v-model="selectMeetingSearch" 
                type="text" 
                placeholder="Szukaj po nazwie firmy lub NIP..." 
                class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all placeholder:text-slate-400"
                autofocus
              />
           </div>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-2">
            <div 
              v-for="prospect in filteredProspects" 
              :key="prospect.id"
              @click="handleSelectMeeting(prospect)"
              class="group p-4 rounded-xl border border-slate-200 hover:border-emerald-300 hover:bg-emerald-50/30 cursor-pointer transition-all flex items-center justify-between"
            >
               <div>
                  <div class="font-bold text-slate-800 group-hover:text-emerald-700 transition-colors">{{ prospect.name || 'Bez nazwy' }}</div>
                  <div class="text-xs text-slate-500 font-mono mt-1">NIP: {{ prospect.nip || 'brak' }}</div>
                  <div class="text-xs text-slate-500 mt-1 flex items-center gap-2">
                     <span class="inline-flex items-center gap-1">
                        <AppIcon name="user" class="w-3 h-3" /> {{ prospect.contactName || 'Brak os. kontaktowej' }}
                     </span>
                  </div>
               </div>
               <div class="opacity-0 group-hover:opacity-100 transition-opacity">
                  <button class="bg-emerald-600 text-white text-xs font-bold px-3 py-1.5 rounded-lg shadow-sm hover:bg-emerald-700">
                    Wybierz
                  </button>
               </div>
            </div>
            
            <div v-if="filteredProspects.length === 0" class="text-center py-8 text-slate-500 text-sm">
                Brak wyników wyszukiwania.
            </div>
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
