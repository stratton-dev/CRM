<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useDataStore } from '@/stores/data'
import { useToastStore } from '@/stores/toast'
import { useNotificationStore } from '@/stores/notification'
import { useStructureStore } from '@/stores/structure'
import { useAuthStore } from '@/stores/auth'
import { useSessionStore } from '@/stores/session'
import { useClientStore } from '@/stores/client'
import { api } from '@/api/client'
import AppIcon from '@/components/AppIcon.vue'
import { getAutentiStatusTone } from '@/utils/uiColors'
import type { AutentiDocument, DocumentTemplate } from '@/types/models'

const data = useDataStore()
const toast = useToastStore()
const notify = useNotificationStore()
const structure = useStructureStore()
const auth = useAuthStore()
const session = useSessionStore()
const clientStore = useClientStore()

const { autentiDocuments } = storeToRefs(data)
const { clients } = storeToRefs(clientStore)

const apiDocuments = ref<AutentiDocument[]>([])
const templates = ref<DocumentTemplate[]>([])
const isLoading = ref(false)
const isUploading = ref(false)
const newTemplateName = ref('')
const newTemplateSlug = ref('')
const newTemplateType = ref<'pdf' | 'html'>('pdf')
const newTemplateFile = ref<File | null>(null)
const newTemplateHtml = ref('')
const newEditorMode = ref<'code' | 'preview'>('code')
const newHtmlTextarea = ref<HTMLTextAreaElement | null>(null)
const newTagGroup = ref<'user' | 'client'>('user')
const templateSuggestions = ref<Array<{ key: string; label: string; slug: string }>>([])
const editingTemplate = ref<DocumentTemplate | null>(null)
const editName = ref('')
const editSlug = ref('')
const editHtml = ref('')
const editActive = ref(true)
const editFile = ref<File | null>(null)
const editEditorMode = ref<'code' | 'preview'>('code')
const editHtmlTextarea = ref<HTMLTextAreaElement | null>(null)
const editTagGroup = ref<'user' | 'client'>('user')
const fieldSearch = ref('')
const connectionState = ref<'idle' | 'loading' | 'ok' | 'error' | 'disabled'>('idle')
const connectionDetail = ref<string | null>(null)
const autentiAppUrl = ref<string>('')
const testTemplateId = ref<string>('')
const testClientId = ref<string>('')
const testSignerName = ref('')
const testSignerEmail = ref('')
const testClientSearch = ref('')
const isCreatingTestDoc = ref(false)
const filterQuery = ref('')
const filterStatus = ref('')
const page = ref(1)
const perPage = ref(25)
const total = ref(0)
const pollIntervalMs = 15000
let pollId: number | undefined

const editTemplateType = computed(() => editingTemplate.value?.type || 'pdf')
const connectionTone = computed(() => {
  switch (connectionState.value) {
    case 'ok':
      return { label: 'Połączono', className: 'bg-emerald-100 text-emerald-800 border-emerald-200' }
    case 'error':
      return { label: 'Błąd', className: 'bg-rose-100 text-rose-800 border-rose-200' }
    case 'disabled':
      return { label: 'Wyłączone', className: 'bg-gray-100 text-gray-700 border-gray-200' }
    case 'loading':
      return { label: 'Sprawdzanie...', className: 'bg-sky-100 text-sky-800 border-sky-200' }
    default:
      return { label: 'Nieznany', className: 'bg-gray-100 text-gray-700 border-gray-200' }
  }
})

const templateFields = [
  { key: 'user.id', label: 'ID uzytkownika', description: 'ID rekordu uzytkownika', group: 'user' },
  { key: 'user.supabase_id', label: 'ID Supabase', description: 'Identyfikator w Supabase', group: 'user' },
  { key: 'user.name', label: 'Imie i nazwisko', description: 'Pelne imie i nazwisko', group: 'user' },
  { key: 'user.first_name', label: 'Imie', description: 'Pierwsze imie', group: 'user' },
  { key: 'user.last_name', label: 'Nazwisko', description: 'Nazwisko', group: 'user' },
  { key: 'user.email', label: 'Email', description: 'Adres email', group: 'user' },
  { key: 'user.phone', label: 'Telefon', description: 'Numer telefonu', group: 'user' },
  { key: 'user.role', label: 'Rola', description: 'Rola w strukturze', group: 'user' },
  { key: 'user.contract_status', label: 'Status umowy', description: 'Status umowy uzytkownika', group: 'user' },
  { key: 'user.crm_number', label: 'Numer CRM', description: 'Numer CRM', group: 'user' },
  { key: 'user.rank', label: 'Ranga', description: 'Ranga', group: 'user' },
  { key: 'user.type', label: 'Typ uzytkownika', description: 'Typ (np. PRIVATE)', group: 'user' },
  { key: 'user.team_group_path', label: 'Sciezka zespolu', description: 'Sciezka zespolu', group: 'user' },
  { key: 'user.hierarchical_code', label: 'Kod hierarchiczny', description: 'Kod hierarchiczny', group: 'user' },
  { key: 'user.hierarchical_id', label: 'ID hierarchiczne', description: 'ID hierarchiczne', group: 'user' },
  { key: 'address.street', label: 'Ulica', description: 'Ulica', group: 'user' },
  { key: 'address.houseNr', label: 'Numer domu', description: 'Numer domu', group: 'user' },
  { key: 'address.aptNr', label: 'Numer lokalu', description: 'Numer lokalu', group: 'user' },
  { key: 'address.zipCode', label: 'Kod pocztowy', description: 'Kod pocztowy', group: 'user' },
  { key: 'address.city', label: 'Miasto', description: 'Miasto', group: 'user' },
  { key: 'documents.nda', label: 'NDA (1/0)', description: '1 jesli wybrano NDA', group: 'user' },
  { key: 'documents.cooperationAgreement', label: 'Umowa wspolpracy (1/0)', description: '1 jesli wybrano umowe wspolpracy', group: 'user' },
  { key: 'documents.careerPath', label: 'Sciezka kariery (1/0)', description: '1 jesli wybrano sciezke kariery', group: 'user' },
  { key: 'documents.otherFileName', label: 'Inny plik', description: 'Nazwa dodatkowego pliku', group: 'user' },
  { key: 'date', label: 'Data (YYYY-MM-DD)', description: 'Aktualna data', group: 'user' },
  { key: 'datetime', label: 'Data i czas', description: 'Aktualna data i czas', group: 'user' },
  { key: 'client.id', label: 'ID klienta', description: 'ID klienta', group: 'client' },
  { key: 'client.name', label: 'Nazwa firmy', description: 'Nazwa firmy', group: 'client' },
  { key: 'client.nip', label: 'NIP', description: 'NIP klienta', group: 'client' },
  { key: 'client.regon', label: 'REGON', description: 'REGON klienta', group: 'client' },
  { key: 'client.krs', label: 'KRS', description: 'KRS klienta', group: 'client' },
  { key: 'client.email', label: 'Email klienta', description: 'Email klienta', group: 'client' },
  { key: 'client.phone', label: 'Telefon klienta', description: 'Telefon klienta', group: 'client' },
  { key: 'client.website', label: 'Strona WWW', description: 'Strona WWW', group: 'client' },
  { key: 'client.address_line1', label: 'Adres linia 1', description: 'Adres linia 1', group: 'client' },
  { key: 'client.address_line2', label: 'Adres linia 2', description: 'Adres linia 2', group: 'client' },
  { key: 'client.postal_code', label: 'Kod pocztowy', description: 'Kod pocztowy', group: 'client' },
  { key: 'client.city', label: 'Miasto', description: 'Miasto', group: 'client' },
  { key: 'client.country', label: 'Kraj', description: 'Kraj', group: 'client' },
  { key: 'client.industry', label: 'Branza', description: 'Branza', group: 'client' },
  { key: 'client.vat_type', label: 'Typ VAT', description: 'Typ VAT', group: 'client' },
  { key: 'client.employee_count', label: 'Liczba pracownikow', description: 'Liczba pracownikow', group: 'client' },
  { key: 'client.benefits_enabled', label: 'Benefity (1/0)', description: '1 jesli benefity wlaczone', group: 'client' },
  { key: 'client.address.street', label: 'Ulica (address_json)', description: 'Ulica (address_json)', group: 'client' },
  { key: 'client.address.houseNr', label: 'Numer domu (address_json)', description: 'Numer domu (address_json)', group: 'client' },
  { key: 'client.address.aptNr', label: 'Numer lokalu (address_json)', description: 'Numer lokalu (address_json)', group: 'client' },
  { key: 'client.address.zipCode', label: 'Kod pocztowy (address_json)', description: 'Kod pocztowy (address_json)', group: 'client' },
  { key: 'client.address.city', label: 'Miasto (address_json)', description: 'Miasto (address_json)', group: 'client' },
]

const sectionSnippets = [
  { label: 'Naglowek H1', html: '<h1>Tytul dokumentu</h1>' },
  { label: 'Tytul H2', html: '<h2>Sekcja</h2>' },
  { label: 'Paragraf', html: '<p>Wpisz tresc paragrafu.</p>' },
  { label: 'Akapit z lista', html: '<p>Opis:</p><ul><li>Punkt 1</li><li>Punkt 2</li></ul>' },
]

const filterFields = (group: 'user' | 'client') => {
  const query = fieldSearch.value.trim().toLowerCase()
  return templateFields.filter((field) => {
    if (field.group !== group) return false
    if (!query) return true
    return field.key.toLowerCase().includes(query) ||
      field.label.toLowerCase().includes(query) ||
      field.description.toLowerCase().includes(query)
  })
}

const documents = computed(() => {
  const list = auth.enabled ? apiDocuments.value : Array.isArray(autentiDocuments.value) ? autentiDocuments.value : []
  return [...list].sort((a, b) => new Date(b.sentDate).getTime() - new Date(a.sentDate).getTime())
})

const filteredClients = computed(() => {
  const list = Array.isArray(clients.value) ? clients.value : []
  const query = testClientSearch.value.trim().toLowerCase()
  if (!query) return list
  return list.filter((client) => client.name.toLowerCase().includes(query) || String(client.nip || '').includes(query))
})

const selectedTemplate = computed(() => templates.value.find((item) => String(item.id) === testTemplateId.value) || null)

const markAsViewed = (doc: AutentiDocument) => {
  if (auth.enabled) return
  if (doc.status !== 'SENT') return
  data.rawUpdateAutentiDocument(doc.id, { status: 'VIEWED', viewedDate: new Date().toISOString() })
  toast.info(`Dokument dla ${doc.recipientName} został oznaczony jako obejrzany.`)
}

const markAsSigned = (doc: AutentiDocument) => {
  if (auth.enabled) return
  if (doc.status !== 'SENT' && doc.status !== 'VIEWED') return

  data.rawUpdateAutentiDocument(doc.id, { status: 'SIGNED', signedDate: new Date().toISOString() })
  structure.updateUserContractStatus(doc.userId, 'SIGNED')

  notify.add({
    userId: doc.initiatorId,
    type: 'INFO',
    message: `Autenti: Kandydat ${doc.recipientName} podpisał dokumenty. Można rozpocząć szkolenie.`,
  })
  notify.add({
    userId: doc.userId,
    type: 'INFO',
    message: 'Witamy w zespole! Twoje konto jest aktywne. Dokumenty zostały zarchiwizowane.',
  })
  toast.success(`Proces dla ${doc.recipientName} został zakończony pomyślnie.`)
}

const syncAutentiDoc = async (doc: AutentiDocument) => {
  if (!auth.enabled) return
  try {
    const { data: updated } = await api.post(`/v1/autenti-documents/${doc.id}/sync`)
    replaceApiDoc(updated)
    toast.info('Odświeżono status dokumentu.')
  } catch {
    toast.error('Nie udało się odświeżyć statusu.')
  }
}

const sendAutentiDoc = async (doc: AutentiDocument) => {
  if (!auth.enabled) return
  try {
    const { data: updated } = await api.post(`/v1/autenti-documents/${doc.id}/send`)
    replaceApiDoc(updated)
    toast.success('Dokument wysłany do Autenti.')
  } catch {
    toast.error('Nie udało się wysłać dokumentu.')
  }
}

const deleteAutentiDoc = async (doc: AutentiDocument) => {
  if (!auth.enabled) return
  if (doc.status === 'SIGNED') return
  if (!window.confirm('Usunąć dokument i wycofać proces w Autenti?')) return
  try {
    await api.delete(`/v1/autenti-documents/${doc.id}`)
    apiDocuments.value = apiDocuments.value.filter((item) => item.id !== doc.id)
    toast.success('Dokument usunięty.')
  } catch {
    toast.error('Nie udało się usunąć dokumentu.')
  }
}

const fetchAutentiDocs = async () => {
  if (!auth.enabled) return
  isLoading.value = true
  try {
    const { data: response } = await api.get('/v1/autenti-documents', {
      params: {
        page: page.value,
        per_page: perPage.value,
        status: filterStatus.value || undefined,
        q: filterQuery.value || undefined,
      },
    })
    const list = Array.isArray(response?.data) ? response.data : Array.isArray(response) ? response : []
    apiDocuments.value = list.map(mapAutentiDoc)
    total.value = Number(response?.total ?? list.length)
  } finally {
    isLoading.value = false
  }
}

const totalPages = computed(() => Math.max(1, Math.ceil(total.value / perPage.value)))

const goToPage = async (nextPage: number) => {
  if (nextPage < 1 || nextPage > totalPages.value || nextPage === page.value) return
  page.value = nextPage
  await fetchAutentiDocs()
}

watch([filterQuery, filterStatus, perPage], async () => {
  page.value = 1
  await fetchAutentiDocs()
})

const fetchTemplates = async () => {
  if (!auth.enabled) return
  const { data: list } = await api.get('/v1/document-templates')
  templates.value = Array.isArray(list) ? list : []
}

const fetchTemplateSuggestions = async () => {
  if (!auth.enabled) return
  try {
    const { data } = await api.get('/v1/document-templates/suggestions')
    templateSuggestions.value = Array.isArray(data) ? data : []
  } catch {
    templateSuggestions.value = []
  }
}

const fetchAutentiStatus = async () => {
  if (!auth.enabled) {
    connectionState.value = 'disabled'
    connectionDetail.value = 'Autoryzacja wyłączona'
    return
  }
  connectionState.value = 'loading'
  connectionDetail.value = null
  try {
    const { data } = await api.get('/v1/autenti/status')
    if (!data?.enabled) {
      connectionState.value = 'disabled'
      connectionDetail.value = data?.message || 'Autenti wyłączone po stronie API'
      autentiAppUrl.value = data?.app_url || ''
      return
    }
    if (data?.ok) {
      connectionState.value = 'ok'
      connectionDetail.value = null
      autentiAppUrl.value = data?.app_url || ''
      return
    }
    connectionState.value = 'error'
    connectionDetail.value = data?.message || data?.error || 'Brak połączenia z Autenti'
    autentiAppUrl.value = data?.app_url || ''
  } catch {
    connectionState.value = 'error'
    connectionDetail.value = 'Nie udało się sprawdzić połączenia'
  }
}

const buildAutentiProcessLink = (doc: AutentiDocument) => {
  if (!autentiAppUrl.value || !doc.autentiProcessId) return ''
  const base = autentiAppUrl.value.replace(/\/+$/, '')
  const processId = String(doc.autentiProcessId || '').replace('DOCUMENT_PROCESS:', '')
  return `${base}/?id=${processId}`
}

const startAutentiOAuth = async () => {
  if (!auth.enabled) return
  try {
    const { data } = await api.get('/v1/autenti/oauth/start')
    if (data?.url) {
      window.open(data.url, '_blank', 'noopener,noreferrer')
      return
    }
    toast.error('Nie udało się rozpocząć autoryzacji.')
  } catch {
    toast.error('Nie udało się rozpocząć autoryzacji.')
  }
}

const uploadTemplate = async () => {
  if (!auth.enabled || !newTemplateName.value) {
    toast.warning('Podaj nazwę szablonu.')
    return
  }
  if (newTemplateType.value === 'pdf' && !newTemplateFile.value) {
    toast.warning('Wybierz plik PDF.')
    return
  }
  if (newTemplateType.value === 'html' && !newTemplateHtml.value.trim()) {
    toast.warning('Uzupełnij treść HTML.')
    return
  }
  isUploading.value = true
  try {
    const templateType = newTemplateType.value
    let created
    if (templateType === 'pdf') {
      const form = new FormData()
      form.append('name', newTemplateName.value)
      if (newTemplateSlug.value.trim()) {
        form.append('slug', newTemplateSlug.value.trim())
      }
      form.append('file', newTemplateFile.value as File)
      const { data } = await api.post('/v1/document-templates', form, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      created = data
    } else {
      const { data } = await api.post('/v1/document-templates', {
        name: newTemplateName.value,
        slug: newTemplateSlug.value.trim() || undefined,
        type: 'html',
        html_content: newTemplateHtml.value,
      })
      created = data
    }
    templates.value = [...templates.value, created]
    newTemplateName.value = ''
    newTemplateSlug.value = ''
    newTemplateFile.value = null
    newTemplateHtml.value = ''
    newTemplateType.value = 'pdf'
    toast.success(templateType === 'pdf' ? 'Dodano szablon PDF.' : 'Dodano szablon HTML.')
  } catch {
    toast.error('Nie udało się dodać szablonu.')
  } finally {
    isUploading.value = false
  }
}

const removeTemplate = async (template: DocumentTemplate) => {
  if (!auth.enabled) return
  if (!window.confirm(`Usunąć szablon ${template.name}?`)) return
  await api.delete(`/v1/document-templates/${template.id}`)
  templates.value = templates.value.filter((item) => item.id !== template.id)
}

const previewTemplate = async (template: DocumentTemplate) => {
  if (!auth.enabled) return
  try {
    const currentUserId = session.currentUser?.id || ''
    const url = `/v1/document-templates/${template.id}/preview${currentUserId ? `?user_supabase_id=${currentUserId}` : ''}`
    const response = await api.get(url, { responseType: 'blob' })
    const blob = new Blob([response.data], { type: 'application/pdf' })
    const blobUrl = window.URL.createObjectURL(blob)
    window.open(blobUrl, '_blank')
    setTimeout(() => window.URL.revokeObjectURL(blobUrl), 5000)
  } catch {
    toast.error('Nie udało się wygenerować podglądu PDF.')
  }
}

const downloadTemplate = async (template: DocumentTemplate) => {
  if (!auth.enabled) return
  try {
    const response = await api.get(`/v1/document-templates/${template.id}/download`, { responseType: 'blob' })
    const blob = new Blob([response.data], { type: 'application/pdf' })
    const blobUrl = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = blobUrl
    link.download = `${template.slug || template.name || 'template'}.pdf`
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => window.URL.revokeObjectURL(blobUrl), 5000)
  } catch {
    toast.error('Nie udało się pobrać pliku PDF.')
  }
}

const handleTemplateFile = (event: Event) => {
  const input = event.target as HTMLInputElement
  newTemplateFile.value = input.files?.[0] || null
}

const insertToken = (token: string, target: 'new' | 'edit') => {
  const insertValue = `{{${token}}}`
  const isNew = target === 'new'
  const htmlValue = isNew ? newTemplateHtml.value : editHtml.value
  const textarea = isNew ? newHtmlTextarea.value : editHtmlTextarea.value
  const start = textarea?.selectionStart ?? htmlValue.length
  const end = textarea?.selectionEnd ?? htmlValue.length
  const updated = htmlValue.slice(0, start) + insertValue + htmlValue.slice(end)
  if (isNew) {
    newTemplateHtml.value = updated
  } else {
    editHtml.value = updated
  }
  nextTick(() => {
    if (!textarea) return
    textarea.focus()
    const cursor = start + insertValue.length
    textarea.selectionStart = cursor
    textarea.selectionEnd = cursor
  })
}

const insertSection = (html: string, target: 'new' | 'edit') => {
  const isNew = target === 'new'
  const htmlValue = isNew ? newTemplateHtml.value : editHtml.value
  const textarea = isNew ? newHtmlTextarea.value : editHtmlTextarea.value
  const start = textarea?.selectionStart ?? htmlValue.length
  const end = textarea?.selectionEnd ?? htmlValue.length
  const updated = htmlValue.slice(0, start) + html + htmlValue.slice(end)
  if (isNew) {
    newTemplateHtml.value = updated
  } else {
    editHtml.value = updated
  }
  nextTick(() => {
    if (!textarea) return
    textarea.focus()
    const cursor = start + html.length
    textarea.selectionStart = cursor
    textarea.selectionEnd = cursor
  })
}

const renderPreview = (html: string) => {
  const tokenRegex = /\{\{\s*([^}]+?)\s*\}\}/g
  return html.replace(tokenRegex, (_match, token) => {
    const label = `{{${String(token).trim()}}}`
    return `<span class="inline-flex items-center rounded bg-slate-100 text-slate-700 text-xs px-2 py-0.5 border border-slate-200">${label}</span>`
  })
}

const startEditTemplate = (template: DocumentTemplate) => {
  editingTemplate.value = template
  editName.value = template.name
  editSlug.value = template.slug || ''
  editHtml.value = template.html_content || ''
  editActive.value = template.is_active
  editFile.value = null
  editTagGroup.value = editHtml.value.includes('{{client.') ? 'client' : 'user'
}

const cancelEditTemplate = () => {
  editingTemplate.value = null
  editName.value = ''
  editSlug.value = ''
  editHtml.value = ''
  editActive.value = true
  editFile.value = null
}

const handleEditFile = (event: Event) => {
  const input = event.target as HTMLInputElement
  editFile.value = input.files?.[0] || null
}

const saveEditTemplate = async () => {
  if (!auth.enabled || !editingTemplate.value) return
  const template = editingTemplate.value
  try {
    const templateType = template.type || 'pdf'
    if (templateType === 'pdf' && editFile.value) {
      const form = new FormData()
      form.append('name', editName.value)
      if (editSlug.value.trim()) {
        form.append('slug', editSlug.value.trim())
      }
      form.append('file', editFile.value)
      form.append('is_active', String(editActive.value))
      const { data } = await api.patch(`/v1/document-templates/${template.id}`, form, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      templates.value = templates.value.map((item) => (item.id === data.id ? data : item))
    } else {
      const { data } = await api.patch(`/v1/document-templates/${template.id}`, {
        name: editName.value,
        slug: editSlug.value.trim() || undefined,
        is_active: editActive.value,
        html_content: templateType === 'html' ? editHtml.value : undefined,
      })
      templates.value = templates.value.map((item) => (item.id === data.id ? data : item))
    }
    toast.success('Zapisano zmiany w szablonie.')
    cancelEditTemplate()
  } catch {
    toast.error('Nie udało się zapisać zmian.')
  }
}

const mapAutentiDoc = (doc: any): AutentiDocument => ({
  id: String(doc.id),
  recipientName: doc.recipient_name,
  recipientEmail: doc.recipient_email,
  documentList: doc.document_list,
  status: doc.status,
  sentDate: doc.sent_at,
  viewedDate: doc.viewed_at || undefined,
  signedDate: doc.signed_at || undefined,
  userId: doc.user_supabase_id || String(doc.user_id || ''),
  initiatorId: doc.initiator_supabase_id || '',
  autentiProcessId: doc.autenti_process_id || undefined,
  autentiStatus: doc.autenti_status || undefined,
})

const replaceApiDoc = (doc: any) => {
  const mapped = mapAutentiDoc(doc)
  apiDocuments.value = apiDocuments.value.map((item) => (item.id === mapped.id ? mapped : item))
}

const createTestDocument = async () => {
  if (!auth.enabled) return
  if (!testTemplateId.value) {
    toast.warning('Wybierz szablon.')
    return
  }
  if (!testClientId.value) {
    toast.warning('Wybierz klienta.')
    return
  }
  if (testSignerEmail.value && !testSignerName.value) {
    toast.warning('Podaj imię i nazwisko osoby podpisującej.')
    return
  }
  isCreatingTestDoc.value = true
  try {
    const payload: any = {
      client_id: Number(testClientId.value),
      user_id: session.currentUser?.id || undefined,
      autenti: {
        title: selectedTemplate.value?.name || 'Testowy dokument',
        file_name: selectedTemplate.value?.slug ? `${selectedTemplate.value.slug}.pdf` : undefined,
        file_description: selectedTemplate.value?.name || undefined,
      },
    }
    if (testSignerName.value || testSignerEmail.value) {
      payload.autenti.signer = {
        name: testSignerName.value || undefined,
        email: testSignerEmail.value || undefined,
      }
    }
    await api.post(`/v1/document-templates/${testTemplateId.value}/documents`, payload)
    toast.success('Dokument wysłany do Autenti.')
    testSignerName.value = ''
    testSignerEmail.value = ''
  } catch {
    toast.error('Nie udało się utworzyć dokumentu.')
  } finally {
    isCreatingTestDoc.value = false
  }
}

onMounted(async () => {
  if (auth.enabled) {
    await Promise.all([
      fetchAutentiDocs(),
      fetchTemplates(),
      fetchTemplateSuggestions(),
      fetchAutentiStatus(),
      clientStore.fetchClients({ perPage: 200 }),
    ])
    pollId = window.setInterval(() => {
      fetchAutentiDocs()
    }, pollIntervalMs)
    return
  }
  connectionState.value = 'disabled'
})

onBeforeUnmount(() => {
  if (pollId) {
    window.clearInterval(pollId)
  }
})
</script>

<template>
  <div class="space-y-3 md:space-y-6">
    <header>
      <div class="flex flex-wrap items-start justify-between gap-3 md:gap-4">
        <div>
          <h1 class="text-xl md:text-2xl font-bold text-gray-900">Panel Integracji Autenti</h1>
          <p class="text-xs md:text-sm text-gray-500">{{ auth.enabled ? 'Zarządzaj realnymi procesami podpisu z Autenti.' : 'Symuluj i zarządzaj procesem podpisywania dokumentów dla nowych członków zespołu.' }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <span class="text-xs text-gray-500">Status:</span>
          <span class="px-2 py-1 inline-flex items-center text-xs font-semibold rounded-full border" :class="connectionTone.className" :title="connectionDetail || ''">
            {{ connectionTone.label }}
          </span>
          <button
            v-if="auth.enabled && connectionState !== 'ok'"
            type="button"
            class="px-3 py-1.5 text-xs font-semibold bg-slate-900 text-white rounded hover:bg-slate-800"
            @click="startAutentiOAuth"
          >
            Połącz Autenti
          </button>
        </div>
      </div>
      <p v-if="auth.enabled && connectionState !== 'ok' && connectionDetail" class="mt-2 text-xs text-rose-600">
        {{ connectionDetail }}
      </p>
    </header>

    <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-200">
      <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
        <h3 class="font-bold text-gray-700">Dokumenty w Procesie</h3>
      </div>

      <div v-if="auth.enabled" class="px-6 py-4 border-b border-gray-200 bg-white">
        <div class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Szablon</label>
            <select v-model="testTemplateId" class="w-full border rounded px-3 py-2 text-sm">
              <option value="">Wybierz szablon</option>
              <option v-for="template in templates" :key="template.id" :value="template.id">
                {{ template.name }} ({{ template.slug }})
              </option>
            </select>
          </div>
          <div class="md:col-span-2 space-y-2">
            <label class="block text-xs font-semibold text-gray-600">Klient</label>
            <div class="relative">
              <AppIcon name="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
              <input v-model="testClientSearch" type="text" class="w-full border rounded-lg pl-10 pr-4 py-2 text-sm focus:ring-2 focus:ring-indigo-100 outline-none text-right font-bold transition-all" placeholder="Szukaj klienta po nazwie lub NIP..." />
            </div>
            <select v-model="testClientId" class="w-full border rounded px-3 py-2 text-sm">
              <option value="">Wybierz klienta</option>
              <option v-for="client in filteredClients" :key="client.id" :value="client.id">
                {{ client.name }} ({{ client.nip || 'brak NIP' }})
              </option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Podpisujący (opcjonalnie)</label>
            <input v-model="testSignerName" type="text" class="w-full border rounded px-3 py-2 text-sm" placeholder="Imię i nazwisko" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Email podpisującego</label>
            <input v-model="testSignerEmail" type="email" class="w-full border rounded px-3 py-2 text-sm" placeholder="email@firma.pl" />
          </div>
          <div>
            <button type="button" class="px-4 py-2 text-sm font-semibold bg-emerald-600 text-white rounded hover:bg-emerald-700 disabled:opacity-50" :disabled="isCreatingTestDoc" @click="createTestDocument">
              {{ isCreatingTestDoc ? 'Wysyłanie...' : 'Utwórz dokument' }}
            </button>
          </div>
        </div>
      </div>

      <div v-if="auth.enabled" class="px-6 py-4 border-b border-gray-200 bg-gray-50">
        <div class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
          <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Szukaj</label>
            <input v-model="filterQuery" type="text" class="crm-input" placeholder="Odbiorca, email, proces..." />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Status</label>
            <select v-model="filterStatus" class="crm-select">
              <option value="">Wszystkie</option>
              <option value="DRAFT">Wersja robocza</option>
              <option value="SENT">Wysłano</option>
              <option value="VIEWED">Obejrzano</option>
              <option value="SIGNED">Podpisano</option>
              <option value="REJECTED">Odrzucono</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Na stronę</label>
            <select v-model.number="perPage" class="crm-select">
              <option :value="10">10</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
            </select>
          </div>
          <div class="md:col-span-2 flex items-center justify-end gap-2 text-xs text-gray-600">
            <span>Strona {{ page }} z {{ totalPages }}</span>
            <button type="button" class="px-2 py-1 border rounded disabled:opacity-50" :disabled="page <= 1 || isLoading" @click="goToPage(page - 1)">
              Prev
            </button>
            <button type="button" class="px-2 py-1 border rounded disabled:opacity-50" :disabled="page >= totalPages || isLoading" @click="goToPage(page + 1)">
              Next
            </button>
          </div>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="crm-table divide-y divide-gray-200">
          <thead class="crm-table-head">
            <tr>
              <th class="crm-table-th crm-table-th-xs">Odbiorca</th>
              <th class="crm-table-th crm-table-th-xs">Dokumenty</th>
              <th class="crm-table-th crm-table-th-xs">Proces</th>
              <th class="crm-table-th crm-table-th-xs text-center">Status</th>
              <th class="crm-table-th crm-table-th-xs text-right">{{ auth.enabled ? 'Akcje' : 'Akcje (Symulacja)' }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="doc in documents" :key="doc.id" class="hover:bg-gray-50">
              <td class="crm-table-td whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">{{ doc.recipientName }}</div>
                <div class="text-xs text-gray-500">{{ doc.recipientEmail }}</div>
              </td>
              <td class="crm-table-td whitespace-nowrap text-xs text-gray-600 max-w-xs truncate" :title="doc.documentList">{{ doc.documentList }}</td>
              <td class="crm-table-td whitespace-nowrap text-xs text-gray-600">
                <div v-if="doc.autentiProcessId" class="flex flex-col gap-1">
                  <span class="font-mono text-[11px]" :title="doc.autentiProcessId">{{ doc.autentiProcessId }}</span>
                  <a v-if="doc.status !== 'SIGNED' && buildAutentiProcessLink(doc)" :href="buildAutentiProcessLink(doc)" target="_blank" rel="noreferrer" class="text-indigo-600 hover:underline">
                    Otwórz w Autenti
                  </a>
                </div>
                <span v-else class="text-gray-400">Brak</span>
              </td>
              <td class="crm-table-td whitespace-nowrap text-center">
                <span class="crm-badge inline-flex items-center gap-1" :class="getAutentiStatusTone(doc.status).className">
                  <AppIcon :name="getAutentiStatusTone(doc.status).icon" class="w-3.5 h-3.5" />
                  {{ getAutentiStatusTone(doc.status).label }}
                </span>
              </td>
              <td class="crm-table-td whitespace-nowrap text-right text-sm font-medium space-x-2">
                <button v-if="!auth.enabled && doc.status === 'SENT'" type="button" class="px-3 py-1.5 text-xs font-medium text-yellow-800 bg-yellow-100 border border-yellow-200 rounded hover:bg-yellow-200" @click="markAsViewed(doc)">
                  Oznacz jako Obejrzany
                </button>
                <button v-if="!auth.enabled && (doc.status === 'SENT' || doc.status === 'VIEWED')" type="button" class="px-3 py-1.5 text-xs font-medium text-green-800 bg-green-100 border border-green-200 rounded hover:bg-green-200" @click="markAsSigned(doc)">
                  Oznacz jako Podpisany
                </button>
                <button v-if="auth.enabled" type="button" class="px-3 py-1.5 text-xs font-medium text-sky-800 bg-sky-100 border border-sky-200 rounded hover:bg-sky-200" @click="syncAutentiDoc(doc)">
                  Odśwież
                </button>
                <button v-if="auth.enabled && doc.status === 'DRAFT'" type="button" class="px-3 py-1.5 text-xs font-medium text-emerald-800 bg-emerald-100 border border-emerald-200 rounded hover:bg-emerald-200" @click="sendAutentiDoc(doc)">
                  Wyślij
                </button>
                <button
                  v-if="auth.enabled && doc.status !== 'SIGNED'"
                  type="button"
                  class="px-3 py-1.5 text-xs font-medium text-rose-800 bg-rose-100 border border-rose-200 rounded hover:bg-rose-200"
                  @click="deleteAutentiDoc(doc)"
                >
                  Usuń
                </button>
                <span v-if="!auth.enabled && doc.status === 'SIGNED'" class="text-xs text-gray-400">Proces zakończony</span>
              </td>
            </tr>
            <tr v-if="documents.length === 0">
              <td colspan="5" class="crm-table-empty text-center">Brak dokumentów w procesie Autenti.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="auth.enabled" class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-200">
      <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
        <h3 class="font-bold text-gray-700">Szablony dokumentów</h3>
      </div>
      <div class="p-6 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Nazwa szablonu</label>
            <input v-model="newTemplateName" type="text" class="w-full border rounded px-3 py-2 text-sm" placeholder="np. NDA" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Slug</label>
            <input v-model="newTemplateSlug" list="template-slug-suggestions" type="text" class="w-full border rounded px-3 py-2 text-sm" placeholder="np. nda-2025" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Typ</label>
            <select v-model="newTemplateType" class="w-full border rounded px-3 py-2 text-sm">
              <option value="pdf">PDF</option>
              <option value="html">HTML</option>
            </select>
          </div>
          <div v-if="newTemplateType === 'pdf'">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Plik PDF</label>
            <input type="file" accept="application/pdf" @change="handleTemplateFile" class="w-full text-sm" :disabled="newTemplateType !== 'pdf'" />
          </div>
          <div>
            <button type="button" class="px-4 py-2 text-sm font-semibold bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50" :disabled="isUploading" @click="uploadTemplate">
              {{ isUploading ? 'Wysyłanie...' : 'Dodaj szablon' }}
            </button>
          </div>
        </div>
        <datalist id="template-slug-suggestions">
          <option v-for="item in templateSuggestions" :key="item.slug" :value="item.slug">
            {{ item.label }}
          </option>
        </datalist>

        <div v-if="newTemplateType === 'html'" class="space-y-3">
          <div class="flex items-center gap-2">
            <button type="button" class="px-3 py-1.5 text-xs font-semibold rounded border" :class="newEditorMode === 'code' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-200'" @click="newEditorMode = 'code'">Kod</button>
            <button type="button" class="px-3 py-1.5 text-xs font-semibold rounded border" :class="newEditorMode === 'preview' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-200'" @click="newEditorMode = 'preview'">Podglad</button>
          </div>
          <div class="flex items-center gap-2">
            <button type="button" class="px-3 py-1.5 text-xs font-semibold rounded border" :class="newTagGroup === 'user' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-200'" @click="newTagGroup = 'user'">Tagi uzytkownika</button>
            <button type="button" class="px-3 py-1.5 text-xs font-semibold rounded border" :class="newTagGroup === 'client' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-200'" @click="newTagGroup = 'client'">Tagi klienta</button>
          </div>
          <label class="block text-xs font-semibold text-gray-600">Treść HTML</label>
          <textarea v-if="newEditorMode === 'code'" ref="newHtmlTextarea" v-model="newTemplateHtml" rows="8" class="w-full border rounded px-3 py-2 text-sm font-mono" placeholder="<h1>Umowa</h1>"></textarea>
          <div v-else class="w-full border rounded px-3 py-2 text-sm bg-white min-h-[160px]" v-html="renderPreview(newTemplateHtml)"></div>
          <div class="space-y-2">
            <div class="text-xs font-semibold text-gray-600">Sekcje HTML</div>
            <div class="flex flex-wrap gap-2">
              <button v-for="section in sectionSnippets" :key="section.label" type="button" class="text-xs px-2 py-1 rounded border border-slate-200 text-slate-700 hover:bg-slate-50" @click="insertSection(section.html, 'new')">
                {{ section.label }}
              </button>
            </div>
          </div>
          <div class="space-y-2">
            <div class="flex items-center gap-3">
              <div class="relative flex-1">
                <AppIcon name="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                <input v-model="fieldSearch" type="text" class="w-full border rounded-lg pl-10 pr-4 py-2 text-sm text-right font-bold outline-none focus:ring-2 focus:ring-indigo-50" placeholder="Szukaj pola..." />
              </div>
              <span class="text-xs text-gray-500">{{ filterFields(newTagGroup).length }}</span>
            </div>
            <div class="flex flex-wrap gap-2">
              <button v-for="field in filterFields(newTagGroup)" :key="field.key" type="button" class="text-xs px-2 py-1 rounded border border-slate-200 text-slate-700 hover:bg-slate-50" :title="field.description" @click="insertToken(field.key, 'new')">
                {{ field.label }}
              </button>
            </div>
          </div>
        </div>

        <div class="border rounded-lg overflow-hidden">
          <table class="crm-table divide-y divide-gray-200">
            <thead class="crm-table-head">
              <tr>
                <th class="crm-table-th crm-table-th-xs">Nazwa</th>
                <th class="crm-table-th crm-table-th-xs">Slug</th>
                <th class="crm-table-th crm-table-th-xs">Typ</th>
                <th class="crm-table-th crm-table-th-xs">Sync</th>
                <th class="crm-table-th crm-table-th-xs text-right">Akcje</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="template in templates" :key="template.id">
                <td class="crm-table-td text-sm text-gray-700">{{ template.name }}</td>
                <td class="crm-table-td text-xs text-gray-500">{{ template.slug }}</td>
                <td class="crm-table-td text-xs text-gray-500">{{ (template.type || 'pdf').toUpperCase() }}</td>
                <td class="crm-table-td text-xs text-gray-600">
                  <span v-if="template.autenti_mapped" class="px-2 py-1 rounded-full bg-emerald-100 text-emerald-700">Autenti</span>
                  <span v-else class="px-2 py-1 rounded-full bg-gray-100 text-gray-600">Lokalny</span>
                </td>
                <td class="crm-table-td text-right text-sm space-x-3">
                  <button type="button" class="text-slate-700 hover:underline" @click="previewTemplate(template)">Podgląd PDF</button>
                  <button type="button" class="text-slate-700 hover:underline" @click="downloadTemplate(template)">Pobierz</button>
                  <button type="button" class="text-indigo-600 hover:underline" @click="startEditTemplate(template)">Edytuj</button>
                  <button type="button" class="text-red-600 hover:underline" @click="removeTemplate(template)">Usuń</button>
                </td>
              </tr>
              <tr v-if="templates.length === 0">
                <td colspan="5" class="crm-table-empty text-center">Brak szablonów.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div v-if="auth.enabled && editingTemplate" class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-200">
      <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex items-center justify-between">
        <h3 class="font-bold text-gray-700">Edycja szablonu</h3>
        <button type="button" class="text-sm text-gray-500 hover:underline" @click="cancelEditTemplate">Zamknij</button>
      </div>
      <div class="p-6 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Nazwa</label>
            <input v-model="editName" type="text" class="w-full border rounded px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Slug</label>
            <input v-model="editSlug" list="template-slug-suggestions" type="text" class="w-full border rounded px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Status</label>
            <select v-model="editActive" class="w-full border rounded px-3 py-2 text-sm">
              <option :value="true">Aktywny</option>
              <option :value="false">Nieaktywny</option>
            </select>
          </div>
          <div v-if="editTemplateType === 'pdf'">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Nowy plik PDF (opcjonalnie)</label>
            <input type="file" accept="application/pdf" @change="handleEditFile" class="w-full text-sm" />
          </div>
        </div>
        <div v-if="editTemplateType === 'html'" class="space-y-3">
          <div class="flex items-center gap-2">
            <button type="button" class="px-3 py-1.5 text-xs font-semibold rounded border" :class="editEditorMode === 'code' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-200'" @click="editEditorMode = 'code'">Kod</button>
            <button type="button" class="px-3 py-1.5 text-xs font-semibold rounded border" :class="editEditorMode === 'preview' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-200'" @click="editEditorMode = 'preview'">Podglad</button>
          </div>
          <div class="flex items-center gap-2">
            <button type="button" class="px-3 py-1.5 text-xs font-semibold rounded border" :class="editTagGroup === 'user' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-200'" @click="editTagGroup = 'user'">Tagi uzytkownika</button>
            <button type="button" class="px-3 py-1.5 text-xs font-semibold rounded border" :class="editTagGroup === 'client' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-200'" @click="editTagGroup = 'client'">Tagi klienta</button>
          </div>
          <label class="block text-xs font-semibold text-gray-600">Treść HTML</label>
          <textarea v-if="editEditorMode === 'code'" ref="editHtmlTextarea" v-model="editHtml" rows="8" class="w-full border rounded px-3 py-2 text-sm font-mono"></textarea>
          <div v-else class="w-full border rounded px-3 py-2 text-sm bg-white min-h-[160px]" v-html="renderPreview(editHtml)"></div>
          <div class="space-y-2">
            <div class="text-xs font-semibold text-gray-600">Sekcje HTML</div>
            <div class="flex flex-wrap gap-2">
              <button v-for="section in sectionSnippets" :key="section.label" type="button" class="text-xs px-2 py-1 rounded border border-slate-200 text-slate-700 hover:bg-slate-50" @click="insertSection(section.html, 'edit')">
                {{ section.label }}
              </button>
            </div>
          </div>
          <div class="space-y-2">
            <div class="flex items-center gap-3">
                <div class="relative flex-1">
                  <AppIcon name="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                  <input v-model="fieldSearch" type="text" class="w-full border rounded-lg pl-10 pr-4 py-2 text-sm text-right font-bold outline-none focus:ring-2 focus:ring-indigo-50" placeholder="Szukaj pola..." />
                </div>
              <span class="text-xs text-gray-500">{{ filterFields(editTagGroup).length }}</span>
            </div>
            <div class="flex flex-wrap gap-2">
              <button v-for="field in filterFields(editTagGroup)" :key="field.key" type="button" class="text-xs px-2 py-1 rounded border border-slate-200 text-slate-700 hover:bg-slate-50" :title="field.description" @click="insertToken(field.key, 'edit')">
                {{ field.label }}
              </button>
            </div>
          </div>
        </div>
        <div class="flex justify-end space-x-3">
          <button type="button" class="px-4 py-2 text-sm font-semibold text-gray-600 hover:underline" @click="cancelEditTemplate">Anuluj</button>
          <button type="button" class="px-4 py-2 text-sm font-semibold bg-emerald-600 text-white rounded hover:bg-emerald-700" @click="saveEditTemplate">Zapisz</button>
        </div>
      </div>
    </div>
  </div>
</template>
