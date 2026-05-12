<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRecruitmentStore, type Candidate, type CandidateDocument } from '@/stores/recruitment'
import AppIcon from '@/components/AppIcon.vue'
import { useToastStore } from '@/stores/toast'

const store = useRecruitmentStore()
const toast = useToastStore()

onMounted(() => {
    store.fetchCandidates()
})

// Wizard State
const showWizard = ref(false)
const step = ref(1)
const isSubmitting = ref(false)
const editingId = ref<number | null>(null)
// const editingId = ref<number | null>(null) // REMOVED: Using panel for edits
const errors = reactive<Record<string, boolean>>({})

// Panel State
const selectedCandidate = ref<Candidate | null>(null)
const activePanelTab = ref<'details' | 'documents'>('details')
const isSavingPanel = ref(false)

// Edit Form State (for panel)
const editForm = reactive({
    type: 'person' as 'person' | 'sole_proprietorship' | 'company',
    first_name: '',
    last_name: '',
    company_name: '',
    nip: '',
    pesel: '',
    email: '',
    phone: '',
    company_representative: '',
    address: {
        street: '',
        house_number: '',
        apartment_number: '',
        postal_code: '',
        city: ''
    }
})

const form = reactive({
    type: 'person' as 'person' | 'sole_proprietorship' | 'company',
    first_name: '',
    last_name: '',
    company_name: '',
    nip: '',
    regon: '',
    krs: '',
    pesel: '',
    email: '',
    phone: '',
    address: {
        street: '',
        house_number: '',
        apartment_number: '',
        postal_code: '',
        city: ''
    },
    documents: [] as string[],
    company_representative: ''
})

const viewMode = ref<'list' | 'kanban'>('list')
const searchQuery = ref('')
const statusStages = [
    { key: 'new', label: 'New' },
    { key: 'in_talks', label: 'NDA podpisane' },
    { key: 'offer_preparing', label: 'Umowa podpisana' },
    { key: 'signed', label: 'Certyfikacja' }
]
const labelByStage = new Map(statusStages.map(stage => [stage.key, stage.label]))
const normalizeStatus = (status?: string) => (status || 'new').toLowerCase().replace(/\s+/g, '_')
const formatStatusLabel = (status: string) =>
    labelByStage.get(status) ||
    status
        .split(/[_\s]+/)
        .filter(Boolean)
        .map((word) => `${word.charAt(0).toUpperCase()}${word.slice(1).toLowerCase()}`)
        .join(' ')

const filteredCandidates = computed(() => {
    const query = searchQuery.value.toLowerCase().trim()
    if (!query) return store.candidates
    
    return store.candidates.filter(c => {
        const fullName = `${c.first_name || ''} ${c.last_name || ''}`.toLowerCase()
        const companyName = (c.company_name || '').toLowerCase()
        const nip = (c.nip || '').toLowerCase()
        const email = (c.email || '').toLowerCase()
        const phone = (c.phone || '').toLowerCase()
        
        return fullName.includes(query) || 
               companyName.includes(query) || 
               nip.includes(query) || 
               email.includes(query) || 
               phone.includes(query)
    })
})

const kanbanColumns = computed(() => {
    const candidates = filteredCandidates.value
    const columns = statusStages.map((stage) => ({
        status: stage.key,
        label: stage.label,
        items: candidates.filter((candidate) => normalizeStatus(candidate.status) === stage.key)
    }))
    const seen = new Set(statusStages.map((stage) => stage.key))
    candidates.forEach((candidate) => {
        const candidateStatus = normalizeStatus(candidate.status)
        if (seen.has(candidateStatus)) return
        seen.add(candidateStatus)
        columns.push({
            status: candidateStatus,
            label: formatStatusLabel(candidateStatus),
            items: candidates.filter((candidate) => normalizeStatus(candidate.status) === candidateStatus)
        })
    })
    return columns
})
const setViewMode = (mode: 'list' | 'kanban') => {
    viewMode.value = mode
}

const openWizard = () => {
    // editingId.value = null // REMOVED
    step.value = 1
    // Reset form
    Object.keys(errors).forEach(k => delete errors[k])
    
    form.type = 'person'
    form.first_name = ''
    form.last_name = ''
    form.company_name = ''
    form.nip = ''
    form.regon = ''
    form.krs = ''
    form.pesel = ''
    form.email = ''
    form.phone = ''
    form.company_representative = ''
    form.address = { street: '', house_number: '', apartment_number: '', postal_code: '', city: '' }
    form.documents = []
    
    showWizard.value = true
}

const openCandidatePanel = (c: Candidate) => {
    selectedCandidate.value = c
    activePanelTab.value = 'details'
    
    // Init edit form
    editForm.type = c.type
    editForm.first_name = c.type === 'company' ? '' : (c.first_name || '')
    editForm.last_name = c.type === 'company' ? '' : (c.last_name || '')
    editForm.company_name = c.company_name || ''
    // If company, combine first/last name as representative
    editForm.company_representative = c.type === 'company' ? `${c.first_name || ''} ${c.last_name || ''}`.trim() : ''
    
    editForm.nip = c.nip || ''
    editForm.pesel = c.pesel || ''
    editForm.email = c.email
    editForm.phone = c.phone
    editForm.address = c.address_json
      ? {
          street: c.address_json.street || '',
          house_number: c.address_json.house_number || '',
          apartment_number: c.address_json.apartment_number || '',
          postal_code: c.address_json.postal_code || '',
          city: c.address_json.city || '',
        }
      : { street: '', house_number: '', apartment_number: '', postal_code: '', city: '' }
    panelDocumentSelection.value = (c.documents || []).map(d => d.type)
}

const closePanel = () => {
    selectedCandidate.value = null
}

const updateCandidateFromPanel = async () => {
    if (!selectedCandidate.value) return
    isSavingPanel.value = true
    try {
        let firstName = editForm.first_name
        let lastName = editForm.last_name
        
        if (editForm.type === 'company' && editForm.company_representative) {
             const parts = editForm.company_representative.trim().split(' ')
             if (parts.length > 0) firstName = parts[0]
             if (parts.length > 1) lastName = parts.slice(1).join(' ')
             if (parts.length === 1) lastName = ''
        }

         await store.updateCandidate(selectedCandidate.value.id, {
            type: editForm.type,
            first_name: firstName,
            last_name: lastName,
            company_name: editForm.company_name,
            nip: editForm.nip,
            pesel: editForm.pesel,
            email: editForm.email,
            phone: editForm.phone,
            address_json: { ...editForm.address }
        })
        toast.success('Zaktualizowano dane kandydata')
        
        // Update local object to reflect changes immediately in header
        if (selectedCandidate.value) {
            selectedCandidate.value.first_name = firstName
            selectedCandidate.value.last_name = lastName
            selectedCandidate.value.company_name = editForm.company_name
            selectedCandidate.value.nip = editForm.nip
        }

    } catch (e: any) {
        toast.error(e?.response?.data?.message || 'Błąd aktualizacji')
    } finally {
        isSavingPanel.value = false
    }
}


const closeWizard = () => {
    if (!isSubmitting.value) {
        showWizard.value = false
    }
}

const validateStep2 = () => {
    Object.keys(errors).forEach(k => delete errors[k])
    let isValid = true

    if (form.type !== 'person') {
        if (!form.nip) { errors.nip = true; isValid = false }
        if (!form.company_name) { errors.company_name = true; isValid = false }
    }
    if (form.type !== 'company') {
        if (!form.first_name) { errors.first_name = true; isValid = false }
        if (!form.last_name) { errors.last_name = true; isValid = false }
    } else {
        // Validation for company representative
        if(!form.company_representative) { errors.company_representative = true; isValid = false }
    }
    if (form.type === 'person') {
        if (!form.pesel) { errors.pesel = true; isValid = false }
    }

    if (!form.email) { errors.email = true; isValid = false }
    if (!form.phone) { errors.phone = true; isValid = false }
    
    if (!form.address.street) { errors['address.street'] = true; isValid = false }
    if (!form.address.house_number) { errors['address.house_number'] = true; isValid = false }
    if (!form.address.postal_code) { errors['address.postal_code'] = true; isValid = false }
    if (!form.address.city) { errors['address.city'] = true; isValid = false }

    if (!isValid) {
        toast.error('Uzupełnij wymagane pola')
    }
    return isValid
}

const nextStep = () => {
    if (step.value === 2) {
        if (!validateStep2()) return
    }
    step.value++
}

const prevStep = () => {
    step.value--
}

const fetchGus = async () => {
    if (!form.nip) return
    try {
        const data = await store.fetchGusData(form.nip)
        if (data) {
            form.company_name = data.name || ''
            form.address.street = data.street || ''
            form.address.house_number = data.house_number || ''
            form.address.postal_code = data.postal_code || ''
            form.address.city = data.city || ''
            form.address.apartment_number = data.apartment_number || ''
            toast.success('Pobrano dane z GUS')
        }
    } catch (e) {
        toast.error('Nie udało się pobrać danych z GUS')
    }
}

const submitForm = async () => {
    isSubmitting.value = true
    try {
        let firstName = form.first_name
        let lastName = form.last_name
        
        if (form.type === 'company' && form.company_representative) {
             const parts = form.company_representative.trim().split(' ')
             if (parts.length > 0) firstName = parts[0]
             if (parts.length > 1) lastName = parts.slice(1).join(' ')
             if (parts.length === 1) lastName = ''
        }
        
        const payload = {
            type: form.type,
            first_name: firstName,
            last_name: lastName,
            company_name: form.company_name,
            nip: form.nip,
            regon: form.regon,
            krs: form.krs,
            pesel: form.pesel,
            email: form.email,
            phone: form.phone,
            address_json: { ...form.address }
        }

        // Always add in wizard
        await store.addCandidate(payload, form.documents)
        toast.success('Dodano handlowca')
        
        showWizard.value = false
    } catch (e: any) {
        toast.error(e?.response?.data?.message || 'Wystąpił błąd')
    } finally {
        isSubmitting.value = false
    }
}

const deleteCandidate = async (id: number) => {
    if (confirm('Czy na pewno chcesz usunąć kandydata?')) {
        try {
            await store.deleteCandidate(id)
            toast.success('Kandydat usunięty')
        } catch (e) {
            toast.error('Błąd usuwania')
        }
    }
}

const getDisplayName = (c: Candidate) => {
    if (c.type === 'company' || c.type === 'sole_proprietorship') {
        const name = c.company_name || `${c.first_name || ''} ${c.last_name || ''}`
        return name || '-'
    }
    return `${c.first_name || ''} ${c.last_name || ''}`
}

const getDocumentName = (type: string) => {
    switch (type) {
        case 'contract': return 'Umowa Współpracy'
        case 'nda': return 'Umowa NDA'
        case 'career_path': return 'Ścieżka Kariery'
        default: return type.toUpperCase()
    }
}

const toggleDocument = (doc: string) => {
    const idx = form.documents.indexOf(doc)
    if (idx === -1) form.documents.push(doc)
    else form.documents.splice(idx, 1)
}

const panelDocumentSelection = ref<string[]>([])
const documentTypes = ['nda', 'contract', 'career_path']

const togglePanelDocument = (doc: string) => {
    const idx = panelDocumentSelection.value.indexOf(doc)
    if (idx === -1) panelDocumentSelection.value.push(doc)
    else panelDocumentSelection.value.splice(idx, 1)
}

const createPendingDocument = (type: string): CandidateDocument => ({
    id: Date.now() + Math.floor(Math.random() * 1000),
    type,
    status: 'pending'
})

const sendMissingDocuments = () => {
    if (!selectedCandidate.value) {
        toast.error('Wybierz kandydata')
        return
    }

    if (!panelDocumentSelection.value.length) {
        toast.info('Zaznacz dokumenty do wysłania')
        return
    }

    const existingDocs = selectedCandidate.value.documents ?? []
    const existingTypes = new Set(existingDocs.map(doc => doc.type))
    const newDocs = panelDocumentSelection.value
        .filter(type => !existingTypes.has(type))
        .map((type) => createPendingDocument(type))

    if (!newDocs.length) {
        toast.info('Zaznaczone dokumenty już wysłano')
        return
    }

    selectedCandidate.value.documents = [...existingDocs, ...newDocs]
    const storeIdx = store.candidates.findIndex(c => c.id === selectedCandidate.value?.id)
    if (storeIdx !== -1) {
        store.candidates[storeIdx] = { ...store.candidates[storeIdx], documents: selectedCandidate.value.documents }
    }

    toast.success('Zaznaczone dokumenty zostały przygotowane do wysłania')
}
</script>

<template>
  <div class="h-full flex flex-col p-3 md:p-6">
    <div class="bg-stratton-900 rounded-3xl p-4 md:p-8 mb-4 md:mb-8 shadow-2xl relative overflow-hidden group">
      <!-- Decor -->
      <div class="absolute top-0 right-0 w-64 h-64 bg-stratton-800 rounded-full mix-blend-overlay filter blur-3xl opacity-20 -translate-y-1/2 translate-x-1/2 pointer-events-none"></div>

      <div class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-3 md:gap-6">
        <div class="flex items-center gap-3 md:gap-6">
          <RouterLink to="/app/dashboard" class="hidden md:inline-flex items-center justify-center w-12 h-12 bg-slate-800 border border-slate-700 rounded-xl text-slate-400 hover:bg-slate-700 hover:text-white transition-all shadow-sm group">
             <AppIcon name="arrow-left" class="w-5 h-5 transition-transform group-hover:-translate-x-1" />
          </RouterLink>

          <div>
            <h1 class="text-xl md:text-4xl font-serif font-bold text-white tracking-wide leading-tight">Rekrutacja</h1>
            <p class="text-slate-400 max-w-xl text-sm md:text-lg mt-1">Zarządzaj procesem rekrutacji handlowców.</p>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 md:gap-4 bg-slate-800/50 p-1.5 rounded-2xl border border-slate-700/50 backdrop-blur-sm w-full md:w-auto overflow-x-auto">
            <div class="flex items-center bg-slate-900 rounded-xl p-1 border border-slate-800">
                <button
                    type="button"
                    class="flex items-center px-4 py-2 text-sm font-bold rounded-lg transition-all"
                    :class="viewMode === 'list' ? 'bg-stratton-gold text-slate-900 shadow-lg' : 'text-slate-400 hover:text-white hover:bg-slate-800'"
                    @click="setViewMode('list')"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                    Lista
                </button>
                <button
                    type="button"
                    class="flex items-center px-4 py-2 text-sm font-bold rounded-lg transition-all"
                    :class="viewMode === 'kanban' ? 'bg-stratton-gold text-slate-900 shadow-lg' : 'text-slate-400 hover:text-white hover:bg-slate-800'"
                    @click="setViewMode('kanban')"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"></path></svg>
                    Kanban
                </button>
            </div>
            
            <div class="w-px h-8 bg-slate-700 mx-1"></div>

            <div class="relative group/search">
                <input 
                    v-model="searchQuery" 
                    type="text" 
                    placeholder="Szukaj handlowca..." 
                    class="w-36 md:w-64 pl-10 pr-4 py-2 md:py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-stratton-gold/50 focus:border-stratton-gold/50 transition-all text-sm md:text-lg font-bold text-right"
                />
                <AppIcon name="search" class="absolute left-3 top-4 w-4 h-4 text-slate-500 group-focus-within/search:text-stratton-gold transition-colors" />
            </div>

            <button 
                @click="openWizard"
                class="flex items-center gap-2 px-4 md:px-6 py-2 md:py-2.5 bg-white text-slate-900 text-sm rounded-xl hover:bg-blue-50 transition-all font-bold shadow-lg shadow-white/5 hover:scale-105 active:scale-95 shrink-0"
            >
                <AppIcon name="user-plus" class="w-5 h-5 text-stratton-gold" />
                <span>Dodaj Handlowca</span>
            </button>
        </div>
      </div>
    </div>

    <!-- Content -->
    <div class="flex-1 bg-white rounded-xl shadow border border-gray-200 overflow-hidden flex flex-col">
        <div v-if="store.loading && store.candidates.length === 0" class="flex-1 flex items-center justify-center">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        </div>
        <div v-else class="flex-1 flex flex-col overflow-hidden">
            <div v-if="viewMode === 'list'" class="overflow-x-auto flex-1">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Nazwa / Imię Nazwisko</th>
                            <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Typ</th>
                            <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Kontakt</th>
                            <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Dokumenty</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Akcje</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="candidate in filteredCandidates" :key="candidate.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900">{{ getDisplayName(candidate) }}</div>
                                <div v-if="candidate.type !== 'person'" class="text-xs text-gray-500">NIP: {{ candidate.nip }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <span v-if="candidate.type === 'person'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">Osoba prywatna</span>
                                <span v-else-if="candidate.type === 'sole_proprietorship'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">JDG</span>
                                <span v-else class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">Spółka</span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900">{{ candidate.email }}</div>
                                <div class="text-sm text-gray-500">{{ candidate.phone }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800 font-medium">{{ candidate.status }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex gap-1">
                                    <span v-for="doc in candidate.documents" :key="doc.id" :class="doc.status === 'signed' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'" class="px-1.5 py-0.5 rounded text-[10px] uppercase font-bold">
                                        {{ getDocumentName(doc.type) }}
                                    </span>
                                    <span v-if="!candidate.documents?.length" class="text-gray-400 text-xs">-</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <button @click="openCandidatePanel(candidate)" class="text-blue-600 hover:text-blue-900 text-sm font-bold uppercase tracking-wider">Edytuj</button>
                                    <button @click="deleteCandidate(candidate.id)" class="text-red-600 hover:text-red-900 text-sm font-bold uppercase tracking-wider">Usuń</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="flex-1 overflow-x-auto p-4">
                <div v-if="!kanbanColumns.length" class="h-full flex items-center justify-center text-sm text-gray-500">Brak kandydatów</div>
                <div v-else class="flex gap-4 min-h-full">
                    <div v-for="column in kanbanColumns" :key="column.status" class="w-80 bg-gray-50 rounded-xl border border-gray-200 shadow-sm flex flex-col">
                        <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                            <span class="text-sm font-semibold text-gray-700">{{ column.label }}</span>
                            <span class="text-xs text-gray-500">{{ column.items.length }}</span>
                        </div>
                        <div class="p-4 flex-1 space-y-3">
                            <div v-for="candidate in column.items" :key="candidate.id" class="bg-white border border-gray-200 rounded-2xl p-4 space-y-2 shadow-sm">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <div class="font-semibold text-sm text-gray-900">{{ getDisplayName(candidate) }}</div>
                                        <div class="text-[10px] uppercase tracking-wide text-gray-500">
                                            {{ candidate.type === 'person' ? 'Osoba prywatna' : candidate.type === 'sole_proprietorship' ? 'JDG' : 'Spółka' }}
                                        </div>
                                    </div>
                                    <span class="px-2 py-0.5 text-[10px] rounded-full bg-gray-100 text-gray-600">{{ column.status }}</span>
                                </div>
                                <div class="text-xs text-gray-500 space-y-0.5">
                                    <div>Email: {{ candidate.email }}</div>
                                    <div>Tel: {{ candidate.phone }}</div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div class="flex gap-1">
                                        <span v-for="doc in candidate.documents" :key="doc.id" class="px-1.5 py-0.5 text-[10px] rounded-full bg-gray-100 text-gray-600 uppercase font-bold">{{ getDocumentName(doc.type) }}</span>
                                    </div>
                                    <button class="text-xs font-semibold text-blue-600 hover:text-blue-700" @click="openCandidatePanel(candidate)">Edytuj</button>
                                </div>
                            </div>
                            <div v-if="!column.items.length" class="text-xs text-center text-gray-400">Brak kandydatów</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Wizard Modal -->
    <Teleport to="body">
    <div v-if="showWizard" class="fixed inset-0 bg-black/50 z-[9999] flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full flex flex-col max-h-[90vh]">
        <!-- Modal Header -->
        <div class="p-6 border-b border-gray-100 flex justify-between items-center">
            <div>
                <h2 class="text-xl font-bold text-gray-900">{{ editingId ? 'Edytuj handlowca' : 'Dodaj nowego handlowca' }}</h2>
                <div class="flex gap-2 mt-2">
                    <div class="h-1 w-8 rounded-full transition-colors" :class="step >= 1 ? 'bg-blue-600' : 'bg-gray-200'"></div>
                    <div class="h-1 w-8 rounded-full transition-colors" :class="step >= 2 ? 'bg-blue-600' : 'bg-gray-200'"></div>
                    <div class="h-1 w-8 rounded-full transition-colors" :class="step >= 3 ? 'bg-blue-600' : 'bg-gray-200'"></div>
                </div>
            </div>
            <button @click="closeWizard" class="text-gray-400 hover:text-gray-600">
                <AppIcon name="xmark" class="w-6 h-6" />
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-6 overflow-y-auto flex-1">
            <!-- Step 1: Type Selection -->
            <div v-if="step === 1" class="space-y-4">
                <h3 class="text-lg font-medium mb-4">Wybierz typ podmiotu</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div @click="form.type = 'person'" :class="form.type === 'person' ? 'ring-2 ring-blue-500 bg-blue-50' : 'border-gray-200 hover:border-blue-300'" class="border rounded-xl p-4 cursor-pointer transition text-center">
                        <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-3">
                            <AppIcon name="user" class="w-6 h-6" />
                        </div>
                        <div class="font-bold text-gray-900">Osoba prywatna</div>
                    </div>
                    <div @click="form.type = 'sole_proprietorship'" :class="form.type === 'sole_proprietorship' ? 'ring-2 ring-green-500 bg-green-50' : 'border-gray-200 hover:border-green-300'" class="border rounded-xl p-4 cursor-pointer transition text-center">
                        <div class="w-12 h-12 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-3">
                            <AppIcon name="briefcase" class="w-6 h-6" />
                        </div>
                        <div class="font-bold text-gray-900">JDG</div>
                    </div>
                    <div @click="form.type = 'company'" :class="form.type === 'company' ? 'ring-2 ring-purple-500 bg-purple-50' : 'border-gray-200 hover:border-purple-300'" class="border rounded-xl p-4 cursor-pointer transition text-center">
                        <div class="w-12 h-12 bg-purple-100 text-purple-600 rounded-full flex items-center justify-center mx-auto mb-3">
                            <AppIcon name="building" class="w-6 h-6" />
                        </div>
                        <div class="font-bold text-gray-900">Spółka</div>
                    </div>
                </div>
            </div>

            <!-- Step 2: Details -->
            <div v-if="step === 2" class="space-y-4">
                <h3 class="text-lg font-medium mb-4">Dane szczegółowe</h3>
                
                <div v-if="form.type !== 'person'" class="bg-gray-50 p-4 rounded-lg border border-gray-200 mb-4">
                    <label class="block text-sm font-bold text-gray-700 mb-1">NIP <span class="text-red-500">*</span></label>
                    <div class="flex gap-2">
                        <div class="flex-1">
                            <input 
                                v-model="form.nip" 
                                type="text" 
                                placeholder="Wpisz NIP" 
                                class="w-full px-3 py-2 rounded-lg border focus:ring-blue-500 focus:border-blue-500"
                                :class="errors.nip ? 'border-red-500 focus:ring-red-200' : 'border-gray-300'"
                            />
                            <p v-if="errors.nip" class="text-xs text-red-500 mt-1">NIP jest wymagany</p>
                        </div>
                        <button @click="fetchGus" :disabled="!form.nip || store.gusLoading" class="px-4 py-2 bg-slate-800 text-white rounded-lg hover:bg-slate-700 disabled:opacity-50 h-[42px]">
                            {{ store.gusLoading ? 'Pobieranie...' : 'Pobierz GUS' }}
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div v-if="form.type !== 'company'">
                        <label class="block text-sm font-medium text-gray-700">Imię <span class="text-red-500">*</span></label>
                        <input 
                            v-model="form.first_name" 
                            type="text" 
                            class="mt-1 w-full px-3 py-2 rounded-lg border focus:ring-blue-500 focus:border-blue-500"
                            :class="errors.first_name ? 'border-red-500 focus:ring-red-200' : 'border-gray-300'"
                        />
                         <p v-if="errors.first_name" class="text-xs text-red-500 mt-1">Imię jest wymagane</p>
                    </div>
                    <div v-if="form.type !== 'company'">
                        <label class="block text-sm font-medium text-gray-700">Nazwisko <span class="text-red-500">*</span></label>
                        <input 
                            v-model="form.last_name" 
                            type="text" 
                            class="mt-1 w-full px-3 py-2 rounded-lg border focus:ring-blue-500 focus:border-blue-500"
                            :class="errors.last_name ? 'border-red-500 focus:ring-red-200' : 'border-gray-300'"
                        />
                         <p v-if="errors.last_name" class="text-xs text-red-500 mt-1">Nazwisko jest wymagane</p>
                    </div>
                    <div v-if="form.type !== 'person'" class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Nazwa Firmy <span class="text-red-500">*</span></label>
                        <input 
                            v-model="form.company_name" 
                            type="text" 
                            class="mt-1 w-full px-3 py-2 rounded-lg border focus:ring-blue-500 focus:border-blue-500"
                            :class="errors.company_name ? 'border-red-500 focus:ring-red-200' : 'border-gray-300'"
                        />
                        <p v-if="errors.company_name" class="text-xs text-red-500 mt-1">Nazwa firmy jest wymagana</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email <span class="text-red-500">*</span></label>
                        <input 
                            v-model="form.email" 
                            type="email" 
                            class="mt-1 w-full px-3 py-2 rounded-lg border focus:ring-blue-500 focus:border-blue-500"
                            :class="errors.email ? 'border-red-500 focus:ring-red-200' : 'border-gray-300'"
                        />
                        <p v-if="errors.email" class="text-xs text-red-500 mt-1">Email jest wymagany</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Telefon <span class="text-red-500">*</span></label>
                        <input 
                            v-model="form.phone" 
                            type="text" 
                            class="mt-1 w-full px-3 py-2 rounded-lg border focus:ring-blue-500 focus:border-blue-500"
                            :class="errors.phone ? 'border-red-500 focus:ring-red-200' : 'border-gray-300'"
                        />
                        <p v-if="errors.phone" class="text-xs text-red-500 mt-1">Telefon jest wymagany</p>
                    </div>
                </div>

                <div v-if="form.type === 'person'" class="mt-2">
                     <label class="block text-sm font-medium text-gray-700">PESEL <span class="text-red-500">*</span></label>
                     <input 
                        v-model="form.pesel" 
                        type="text" 
                        class="mt-1 w-full px-3 py-2 rounded-lg border focus:ring-blue-500 focus:border-blue-500"
                        :class="errors.pesel ? 'border-red-500 focus:ring-red-200' : 'border-gray-300'"
                     />
                     <p v-if="errors.pesel" class="text-xs text-red-500 mt-1">PESEL jest wymagany</p>
                </div>

                     <div v-if="form.type === 'company'" class="mt-4">
                            <label class="block text-sm font-medium text-gray-700">Osoba reprezentująca <span class="text-red-500">*</span></label>
                            <input 
                                v-model="form.company_representative" 
                                type="text" 
                                class="mt-1 w-full px-3 py-2 rounded-lg border focus:ring-blue-500 focus:border-blue-500"
                                :class="errors.company_representative ? 'border-red-500 focus:ring-red-200' : 'border-gray-300'"
                            />
                            <p v-if="errors.company_representative" class="text-xs text-red-500 mt-1">Osoba reprezentująca jest wymagana</p>
                     </div>
                
                <div class="pt-4 border-t border-gray-100 mt-4">
                    <h4 class="text-sm font-bold text-gray-900 mb-3">Adres</h4>
                    <div class="grid grid-cols-6 gap-3">
                         <div class="col-span-4">
                             <label class="block text-xs text-gray-500">Ulica <span class="text-red-500">*</span></label>
                             <input 
                                v-model="form.address.street" 
                                type="text" 
                                class="w-full px-3 py-2 rounded border focus:ring-blue-500 focus:border-blue-500"
                                :class="errors['address.street'] ? 'border-red-500 focus:ring-red-200' : 'border-gray-300'"
                            />
                         </div>
                         <div class="col-span-1">
                             <label class="block text-xs text-gray-500">Nr domu <span class="text-red-500">*</span></label>
                             <input 
                                v-model="form.address.house_number" 
                                type="text" 
                                class="w-full px-3 py-2 rounded border focus:ring-blue-500 focus:border-blue-500"
                                :class="errors['address.house_number'] ? 'border-red-500 focus:ring-red-200' : 'border-gray-300'"
                            />
                         </div>
                         <div class="col-span-1">
                             <label class="block text-xs text-gray-500">Lok.</label>
                             <input v-model="form.address.apartment_number" type="text" class="w-full px-3 py-2 rounded border border-gray-300" />
                         </div>
                         <div class="col-span-2">
                             <label class="block text-xs text-gray-500">Kod pocztowy <span class="text-red-500">*</span></label>
                             <input 
                                v-model="form.address.postal_code" 
                                type="text" 
                                class="w-full px-3 py-2 rounded border focus:ring-blue-500 focus:border-blue-500"
                                :class="errors['address.postal_code'] ? 'border-red-500 focus:ring-red-200' : 'border-gray-300'"
                            />
                         </div>
                         <div class="col-span-4">
                             <label class="block text-xs text-gray-500">Miasto <span class="text-red-500">*</span></label>
                             <input 
                                v-model="form.address.city" 
                                type="text" 
                                class="w-full px-3 py-2 rounded border focus:ring-blue-500 focus:border-blue-500"
                                :class="errors['address.city'] ? 'border-red-500 focus:ring-red-200' : 'border-gray-300'"
                            />
                         </div>
                    </div>
                </div>
            </div>

            <!-- Step 3: Documents -->
            <div v-if="step === 3" class="space-y-4">
                <h3 class="text-lg font-medium mb-4">Wybierz dokumenty do wysłania (Autenti)</h3>
                
                <div class="space-y-3">
                    <div 
                        @click="toggleDocument('nda')" 
                        class="flex items-center p-4 border rounded-xl cursor-pointer transition"
                        :class="form.documents.includes('nda') ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'"
                    >
                        <div class="flex-1">
                            <div class="font-bold text-gray-900">Umowa NDA</div>
                            <div class="text-xs text-gray-500">O zachowaniu poufności</div>
                        </div>
                        <div class="w-6 h-6 rounded-full border-2 flex items-center justify-center" :class="form.documents.includes('nda') ? 'border-blue-500 bg-blue-500 text-white' : 'border-gray-300'">
                           <AppIcon v-if="form.documents.includes('nda')" name="check-circle" class="w-4 h-4" />
                        </div>
                    </div>

                    <div 
                        @click="toggleDocument('contract')" 
                        class="flex items-center p-4 border rounded-xl cursor-pointer transition"
                        :class="form.documents.includes('contract') ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'"
                    >
                        <div class="flex-1">
                            <div class="font-bold text-gray-900">Umowa Współpracy</div>
                            <div class="text-xs text-gray-500">Standardowa umowa B2B/UoP</div>
                        </div>
                        <div class="w-6 h-6 rounded-full border-2 flex items-center justify-center" :class="form.documents.includes('contract') ? 'border-blue-500 bg-blue-500 text-white' : 'border-gray-300'">
                           <AppIcon v-if="form.documents.includes('contract')" name="check-circle" class="w-4 h-4" />
                        </div>
                    </div>

                    <div 
                        @click="toggleDocument('career_path')" 
                        class="flex items-center p-4 border rounded-xl cursor-pointer transition"
                        :class="form.documents.includes('career_path') ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'"
                    >
                        <div class="flex-1">
                            <div class="font-bold text-gray-900">Ścieżka Kariery</div>
                            <div class="text-xs text-gray-500">Załącznik z planem rozwoju</div>
                        </div>
                        <div class="w-6 h-6 rounded-full border-2 flex items-center justify-center" :class="form.documents.includes('career_path') ? 'border-blue-500 bg-blue-500 text-white' : 'border-gray-300'">
                           <AppIcon v-if="form.documents.includes('career_path')" name="check-circle" class="w-4 h-4" />
                        </div>
                    </div>
                </div>

                <div class="mt-6 p-4 border border-dashed border-gray-300 rounded-xl text-center bg-gray-50 cursor-pointer hover:bg-gray-100">
                    <AppIcon name="document-text" class="w-8 h-8 text-gray-400 mx-auto mb-2" />
                    <span class="text-sm font-medium text-gray-600">Kliknij, aby dodać inny plik</span>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="p-6 border-t border-gray-100 flex justify-between">
            <button 
                v-if="step > 1" 
                @click="prevStep" 
                class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50"
            >
                Wstecz
            </button>
            <div v-else></div> <!-- Spacer -->

            <button 
                v-if="step < 3" 
                @click="nextStep" 
                class="px-6 py-2 bg-blue-600 rounded-lg text-white font-medium hover:bg-blue-700"
            >
                Dalej
            </button>
            <button 
                v-else 
                @click="submitForm" 
                :disabled="isSubmitting"
                class="px-6 py-2 bg-green-600 rounded-lg text-white font-medium hover:bg-green-700 flex items-center gap-2 disabled:opacity-50"
            >
                <div v-if="isSubmitting" class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></div>
                Zapisz i Wyślij
            </button>
        </div>
      </div>
    </div>
    </Teleport>

    <!-- Side Panel -->
    <div v-if="selectedCandidate" class="fixed inset-0 z-[100]">
      <div class="absolute inset-0 bg-black/30" @click="closePanel"></div>
      <div class="absolute top-0 right-0 h-full w-full max-w-2xl bg-gray-50 z-50 shadow-2xl flex flex-col animate-slide-in-right">
        <!-- Header -->
        <div class="p-6 bg-white border-b border-gray-200 flex-shrink-0">
          <div class="flex justify-between items-start">
            <div>
              <h3 class="text-xl font-bold text-gray-900">{{ getDisplayName(selectedCandidate) }}</h3>
              <p class="text-sm text-gray-500 mt-1">NIP: {{ selectedCandidate.nip || '-' }}</p>
               <span 
                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium mt-2"
                :class="{
                    'bg-blue-100 text-blue-800': selectedCandidate.type === 'person',
                    'bg-green-100 text-green-800': selectedCandidate.type === 'sole_proprietorship',
                    'bg-purple-100 text-purple-800': selectedCandidate.type === 'company'
                }"
               >
                {{ selectedCandidate.type === 'person' ? 'Osoba prywatna' : (selectedCandidate.type === 'sole_proprietorship' ? 'JDG' : 'Spółka') }}
               </span>
            </div>
            <button type="button" class="p-2 text-gray-400 hover:bg-gray-100 rounded-full transition" @click="closePanel">
                <AppIcon name="xmark" class="w-6 h-6" />
            </button>
          </div>
        </div>

        <!-- Navigation -->
        <div class="border-b border-gray-200 bg-white flex-shrink-0">
          <nav class="flex space-x-6 px-6">
            <button type="button" class="py-4 text-sm font-medium border-b-2 transition-colors" :class="activePanelTab === 'details' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'" @click="activePanelTab = 'details'">Szczegóły</button>
            <button type="button" class="py-4 text-sm font-medium border-b-2 transition-colors" :class="activePanelTab === 'documents' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'" @click="activePanelTab = 'documents'">Dokumenty</button>
          </nav>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto p-6">
          
          <!-- Details Tab -->
          <div v-if="activePanelTab === 'details'" class="space-y-6">
              
              <!-- Basic Info -->
              <div class="bg-white p-5 border border-gray-200 rounded-xl shadow-sm">
                <h4 class="font-bold text-gray-900 text-sm uppercase tracking-wider mb-4">Dane Podstawowe</h4>
                <div class="grid grid-cols-1 gap-4">
                    <div v-if="editForm.type !== 'person'">
                        <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Nazwa Firmy</label>
                        <input v-model="editForm.company_name" type="text" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-sm" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div v-if="editForm.type !== 'company'">
                            <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Imię</label>
                            <input v-model="editForm.first_name" type="text" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-sm" />
                        </div>
                        <div v-if="editForm.type !== 'company'">
                            <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Nazwisko</label>
                            <input v-model="editForm.last_name" type="text" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-sm" />
                        </div>
                        <div v-if="editForm.type === 'company'" class="col-span-2">
                             <label class="block text-xs font-medium text-gray-500 uppercase mb-1">reprezentacja spółki</label>
                             <input v-model="editForm.company_representative" type="text" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-sm" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                         <div v-if="editForm.type !== 'person'">
                            <label class="block text-xs font-medium text-gray-500 uppercase mb-1">NIP</label>
                            <input v-model="editForm.nip" type="text" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-sm" />
                        </div>
                         <div v-if="editForm.type === 'person'">
                            <label class="block text-xs font-medium text-gray-500 uppercase mb-1">PESEL</label>
                            <input v-model="editForm.pesel" type="text" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-sm" />
                        </div>
                    </div>
                </div>
              </div>

               <!-- Contact Info -->
              <div class="bg-white p-5 border border-gray-200 rounded-xl shadow-sm">
                <h4 class="font-bold text-gray-900 text-sm uppercase tracking-wider mb-4">Kontakt</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Email</label>
                        <input v-model="editForm.email" type="email" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Telefon</label>
                        <input v-model="editForm.phone" type="text" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-sm" />
                    </div>
                </div>
              </div>

              <!-- Address Info -->
              <div class="bg-white p-5 border border-gray-200 rounded-xl shadow-sm">
                <h4 class="font-bold text-gray-900 text-sm uppercase tracking-wider mb-4">Adres</h4>
                 <div class="grid grid-cols-6 gap-3">
                         <div class="col-span-4">
                             <label class="block text-xs text-gray-500 mb-1">Ulica</label>
                             <input v-model="editForm.address.street" type="text" class="w-full rounded border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-sm" />
                         </div>
                         <div class="col-span-1">
                             <label class="block text-xs text-gray-500 mb-1">Nr</label>
                             <input v-model="editForm.address.house_number" type="text" class="w-full rounded border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-sm" />
                         </div>
                         <div class="col-span-1">
                             <label class="block text-xs text-gray-500 mb-1">Lok.</label>
                             <input v-model="editForm.address.apartment_number" type="text" class="w-full rounded border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-sm" />
                         </div>
                         <div class="col-span-2">
                             <label class="block text-xs text-gray-500 mb-1">Kod</label>
                             <input v-model="editForm.address.postal_code" type="text" class="w-full rounded border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-sm" />
                         </div>
                         <div class="col-span-4">
                             <label class="block text-xs text-gray-500 mb-1">Miasto</label>
                             <input v-model="editForm.address.city" type="text" class="w-full rounded border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-sm" />
                         </div>
                    </div>
              </div>

               <div class="flex justify-end pt-4">
                    <button 
                        @click="updateCandidateFromPanel" 
                        :disabled="isSavingPanel"
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium shadow-sm flex items-center gap-2 disabled:opacity-50"
                    >
                         <div v-if="isSavingPanel" class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></div>
                        Zapisz zmiany
                    </button>
               </div>
          </div>

          <!-- Documents Tab -->
          <div v-if="activePanelTab === 'documents'" class="space-y-6">
                <div class="bg-white p-5 border border-gray-200 rounded-xl shadow-sm space-y-5">
                     <div>
                        <h4 class="font-bold text-gray-900 text-sm uppercase tracking-wider mb-4">Wyślij brakujące dokumenty (Autenti)</h4>
                        <div class="space-y-3">
                             <div 
                                 v-for="doc in documentTypes" 
                                 :key="doc" 
                                 @click="togglePanelDocument(doc)" 
                                 class="flex items-center p-4 border rounded-xl cursor-pointer transition"
                                 :class="panelDocumentSelection.includes(doc) ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'"
                             >
                                 <div class="flex-1">
                                     <div class="font-bold text-gray-900">{{ getDocumentName(doc) }}</div>
                                     <div class="text-xs text-gray-500">{{ doc === 'nda' ? 'O zachowaniu poufności' : doc === 'contract' ? 'Standardowa umowa B2B/UoP' : 'Ścieżka rozwoju handlowca' }}</div>
                                 </div>
                                 <div class="w-6 h-6 rounded-full border-2 flex items-center justify-center" :class="panelDocumentSelection.includes(doc) ? 'border-blue-500 bg-blue-500 text-white' : 'border-gray-300'">
                                    <AppIcon v-if="panelDocumentSelection.includes(doc)" name="check-circle" class="w-4 h-4" />
                                 </div>
                             </div>
                        </div>
                        <div class="mt-4 p-4 border border-dashed border-gray-300 rounded-xl text-center bg-gray-50 cursor-pointer hover:bg-gray-100">
                            <AppIcon name="document-text" class="w-8 h-8 text-gray-400 mx-auto mb-2" />
                            <span class="text-sm font-medium text-gray-600">Kliknij, aby dodać inny plik</span>
                        </div>
                        <div class="flex justify-end">
                            <button 
                                @click="sendMissingDocuments" 
                                class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium shadow-sm flex items-center gap-2"
                            >
                                <AppIcon name="paper-airplane" class="w-4 h-4" />
                                Wyślij brakujące dokumenty
                            </button>
                        </div>
                     </div>
                     <div class="bg-white p-5 border border-gray-200 rounded-xl shadow-sm">
                         <h4 class="font-bold text-gray-900 text-sm uppercase tracking-wider mb-4">Dokumenty Kandydata</h4>
                         <div v-if="selectedCandidate.documents?.length" class="space-y-3">
                            <div v-for="doc in selectedCandidate.documents" :key="doc.id" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200">
                                 <div class="flex items-center gap-3">
                                     <div class="w-10 h-10 bg-white rounded-lg border border-gray-200 flex items-center justify-center text-gray-500">
                                         <AppIcon name="document-text" class="w-6 h-6" />
                                     </div>
                                     <div>
                                         <div class="font-bold text-sm text-gray-900 uppercase">{{ getDocumentName(doc.type) }}</div>
                                         <div class="text-xs text-gray-500">Status: <span class="font-medium" :class="doc.status === 'signed' ? 'text-green-600' : 'text-yellow-600'">{{ doc.status }}</span></div>
                                     </div>
                                 </div>
                                 <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">Podgląd</button>
                            </div>
                         </div>
                         <div v-else class="text-center py-8 text-gray-500 text-sm">
                             Brak dokumentów
                         </div>
                     </div>
                </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</template>
