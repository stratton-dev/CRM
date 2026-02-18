<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useClientStore } from '@/stores/client'
import { useToastStore } from '@/stores/toast'
import { api } from '@/api/client'
import AppIcon from '@/components/AppIcon.vue'
import type { Client } from '@/types/models'

defineProps<{
  embedded?: boolean
}>()

const router = useRouter()
const clientStore = useClientStore()
const toast = useToastStore()
const { prospects: clients } = storeToRefs(clientStore)

const promoteToClient = (client: any) => {
  if (!client.nip || !client.name) {
    toast.error('Uzupełnij NIP i nazwę firmy przed utworzeniem kalkulacji.')
    openEditClient(client)
    return
  }
  // Navigate to Calculator with this client pre-selected
  router.push({ name: 'calculator', query: { clientId: client.id } })
}


const showAddModal = ref(false)
const showEditModal = ref(false)
const isSubmitting = ref(false)
const isFetchingGus = ref(false)
const dateInput = ref<HTMLInputElement | null>(null)
const wasValidated = ref(false)

const contactSources = [
  'Kontakt własny',
  'Cold calling',
  'Polecenie od innego klienta',
  'Przypadkowa rozmowa'
]

const industries = [
  "Uprawy rolne, chów i hodowla zwierząt, łowiectwo, włączając działalność usługową",
  "Leśnictwo i pozyskiwanie drewna",
  "Rybactwo",
  "Wydobywanie węgla kamiennego i węgla brunatnego (lignitu)",
  "Górnictwo ropy naftowej i gazu ziemnego",
  "Górnictwo rud metali",
  "Pozostałe górnictwo i wydobywanie",
  "Usługi wspomagające górnictwo i wydobywanie",
  "Produkcja art. spożywczych",
  "Produkcja napojów",
  "Produkcja wyrobów tytoniowych",
  "Produkcja wyrobów tekstylnych",
  "Produkcja odzieży",
  "Produkcja skór i wyrobów ze skór wyprawionych",
  "Produkcja wyrobów z drewna oraz korka, z wyłączeniem mebli; Produkcja wyrobów ze słomy i materiałów używanych do wyplatania",
  "Produkcja papieru i wyrobów z papieru",
  "Poligrafia i reprodukcja zapisanych nośników informacji",
  "Wytwarzanie i przetwarzanie koksu i produktów rafinacji ropy naftowej",
  "Produkcja chemikaliów i wyrobów chemicznych",
  "Produkcja podstawowych substancji farmaceutycznych oraz leków i pozostałych wyrobów farmaceutycznych",
  "Produkcja wyrobów z gumy i tworzyw sztucznych",
  "Produkcja wyrobów z pozostałych mineralnych surowców niemetalicznych",
  "Produkcja metali",
  "Produkcja metalowych wyrobów gotowych, z wyłączeniem maszyn i urządzeń",
  "Produkcja komputerów, wyrobów elektronicznych i optycznych",
  "Produkcja urządzeń elektrycznych",
  "Produkcja maszyn i urządzeń, gdzie indziej niesklasyfikowana",
  "Produkcja pojazdów samochodowych, przyczep i naczep, z wyłączeniem motocykli",
  "Produkcja pozostałego sprzętu transportowego",
  "Produkcja mebli",
  "Pozostała produkcja wyrobów",
  "Naprawa, konserwacja i instalowanie maszyn i urządzeń",
  "Wytwarzanie i zaopatrywanie w energię elektryczną, gaz, parę wodną, gorącą wodę i powietrze do układów klimatyzacyjnych",
  "Pobór, uzdatnianie i dostarczanie wody",
  "Odprowadzanie i oczyszczanie ścieków",
  "Zbieranie, przetwarzanie i unieszkodliwianie odpadów oraz odzysk surowców",
  "Rekultywacją i pozostałe usługi związane z gospodarką odpadami",
  "Roboty budowlane związane ze wznoszeniem budynków",
  "Roboty związane z budową obiektów inżynierii lądowej i wodnej",
  "Roboty budowlane specjalistyczne",
  "Handel hurtowy i detaliczny pojazdami samochodowymi; Naprawa pojazdów samochodowych",
  "Handel hurtowy (bez pojazdów samochodowych)",
  "Handel detaliczny (bez pojazdów samochodowych)",
  "Transport lądowy oraz rurociągowy",
  "Transport wodny",
  "Transport lotniczy",
  "Magazynowanie i usługi wspomagające transport",
  "Działalność pocztowa i kurierska",
  "Zakwaterowanie",
  "Wyżywienie",
  "Działalność wydawnicza",
  "Działalność filmowa, telewizyjna, dźwiękowa i muzyczna",
  "Nadawanie programów telewizyjnych i radiowych",
  "Telekomunikacja",
  "Oprogramowanie i doradztwo w zakresie informatyki",
  "Zarządzanie stronami WWW, przetwarzanie danych i hosting",
  "Usługi finansowe z wyłączeniem ubezpieczeń i funduszów emerytalnych",
  "Ubezpieczenia, reasekuracja i fundusze emerytalne, z wyłączeniem obowiązkowego ubezpieczenia społecznego",
  "Usługi objęrem pośrednictwem finansowym",
  "Obsługa rynku nieruchomości",
  "Usługi prawnicze, rachunkowo-księgowe i doradztwo podatkowe",
  "Działalność firm centralnych i doradztwo związane z zarządzaniem",
  "Architektura, inżynieria, badania i analizy techniczne",
  "Badania naukowe i prace rozwojowe",
  "Reklama, badanie rynku i opinii publicznej",
  "Projektowanie, fotografia, tłumaczenia, działalność profesjonalna",
  "Weterynaria",
  "Wynajem i dzierżawa",
  "Zatrudnienie",
  "Turystyka",
  "Usługi detektywistyczne i ochroniarskie",
  "Sprzątanie budynków i gospodarowanie terenami zieleni",
  "Administracja biurowa i wspomaganie prowadzenia działalności gospodarczej",
  "Administracja publiczna, obrona narodowa i obowiązkowe zabezpieczenia społeczne",
  "Edukacja",
  "Opieka zdrowotna",
  "Pomoc społeczna (z zakwaterowaniem)",
  "Pomoc społeczna (bez zakwaterowania)",
  "Kultura i rozrywka",
  "Biblioteki, archiwa, muzea, zoo oraz inne obiekty kulturalne",
  "Gry losowe i zakłady wzajemne",
  "Sport, rozrywka i rekreacja",
  "Działalność organizacji członkowskich",
  "Naprawa komputerów i artykułów osobistych oraz domowych",
  "Pozostała indywidualna działalność usługowa"
]

const initialForm = {
  contactName: '',
  contactPosition: '',
  contactPhone: '',
  contactEmail: '',
  isDecisionMaker: false,
  companyName: '',
  nip: '',
  address: '',
  industry: '',
  companySize: '',
  source: 'Kontakt własny',
  meetingDate: '',
  meetingNotes: '',
}

const form = ref({ ...initialForm })
const selectedClient = ref<Client | null>(null)

const searchQuery = ref('')
const sortKey = ref('name')
const sortOrder = ref<'asc' | 'desc'>('asc')
const meetingCurrentPage = ref(1)
const meetingItemsPerPage = 10

const filteredClients = computed(() => {
  let list = Array.isArray(clients.value) ? [...clients.value] : []
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter(c => 
      (c.name || '').toLowerCase().includes(q) || 
      (c.contactName || '').toLowerCase().includes(q) ||
      (c.nip || '').includes(q)
    )
  }
  
  list.sort((a, b) => {
    let valA = (a as any)[sortKey.value] || ''
    let valB = (b as any)[sortKey.value] || ''
    if (sortKey.value === 'lastMeeting') {
      const actA = (a.activityHistory || []).find(ah => ah.type === 'MEETING')
      const actB = (b.activityHistory || []).find(ah => ah.type === 'MEETING')
      valA = actA ? new Date(actA.date).getTime() : 0
      valB = actB ? new Date(actB.date).getTime() : 0
    }
    
    if (valA < valB) return sortOrder.value === 'asc' ? -1 : 1
    if (valA > valB) return sortOrder.value === 'asc' ? 1 : -1
    return 0
  })
  
  return list
})

const slicedMeetings = computed(() => {
  const start = (meetingCurrentPage.value - 1) * meetingItemsPerPage
  const end = start + meetingItemsPerPage
  return filteredClients.value.slice(start, end)
})

const totalMeetingPages = computed(() => Math.ceil(filteredClients.value.length / meetingItemsPerPage))

const nextMeetingPage = () => {
    if (meetingCurrentPage.value < totalMeetingPages.value) meetingCurrentPage.value++
}

const prevMeetingPage = () => {
    if (meetingCurrentPage.value > 1) meetingCurrentPage.value--
}

const toggleSort = (key: string) => {
  if (sortKey.value === key) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = key
    sortOrder.value = 'asc'
  }
}

const fetchGusData = async () => {
  if (!form.value.nip || form.value.nip.length !== 10) {
    toast.warning('Podaj poprawny 10-cyfrowy NIP')
    return
  }
  
  isFetchingGus.value = true
  try {
    const { data } = await api.get('/v1/gus', { params: { nip: form.value.nip } })
    if (data) {
      form.value.companyName = data.name || ''
      form.value.address = `${data.street || ''} ${data.houseNr || ''}${data.aptNr ? '/' + data.aptNr : ''}, ${data.zipCode || ''} ${data.city || ''}`.trim()
      toast.success('Dane pobrane pomyślnie')
    }
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Nie znaleziono danych dla podanego NIP')
  } finally {
    isFetchingGus.value = false
  }
}

const handleAddMeeting = async () => {
  wasValidated.value = true
  if (
    !form.value.contactName || 
    !form.value.source || 
    !form.value.meetingDate || 
    !form.value.companySize || 
    !form.value.industry
  ) {
    toast.warning('Wypełnij wymagane pola zaznaczone na czerwono')
    return
  }
  
  isSubmitting.value = true
  try {
    // 1. Create client profile if company/nip provided or just use contact data
    const payload = {
      name: form.value.companyName || form.value.contactName,
      nip: form.value.nip,
      contact_name: form.value.contactName,
      contact_phone: form.value.contactPhone,
      contact_email: form.value.contactEmail,
      contact_position: form.value.contactPosition,
      is_decision_maker: form.value.isDecisionMaker,
      address: form.value.address,
      industry: form.value.industry,
      company_size: form.value.companySize,
      source: form.value.source,
      status: 'IN_TALKS',
      initial_meeting: {
        date: new Date(form.value.meetingDate).toISOString(),
        notes: form.value.meetingNotes
      }
    }
    
    await api.post('/v1/meetings/prospect', payload)
    await clientStore.refreshApiData()
    toast.success('Spotkanie i klient dodani pomyślnie')
    showAddModal.value = false
    form.value = { ...initialForm }
  } catch (error: any) {
    toast.error('Błąd podczas zapisywania: ' + (error.response?.data?.message || error.message))
  } finally {
    isSubmitting.value = false
  }
}

const openAddModal = () => {
    // Reset form
    form.value = { ...initialForm }
    wasValidated.value = false
    isEditing.value = false
    showAddModal.value = true
}

const isEditing = ref(false)

const openEditClient = (client: Client) => {
  selectedClient.value = client
  isEditing.value = true
  form.value = {
    contactName: client.contactName || '',
    contactPosition: client.contactPosition || '',
    contactPhone: client.contactPhone || '',
    contactEmail: client.contactEmail || '',
    isDecisionMaker: client.isDecisionMaker || false,
    companyName: client.name || '',
    nip: client.nip || '',
    address: `${client.street || ''} ${client.buildingNr || ''}, ${client.zip || ''} ${client.city || ''}`.trim(),
    industry: client.industry || '',
    companySize: client.companySize || '',
    source: client.source || 'Kontakt własny',
    meetingDate: new Date().toISOString().slice(0, 16),
    meetingNotes: '',
  }
  showAddModal.value = true
}

const handleUpdateClient = async () => {
    if (!selectedClient.value) return
    isSubmitting.value = true
    try {
        await api.patch(`/v1/clients/${selectedClient.value.id}`, {
            name: form.value.companyName,
            nip: form.value.nip,
            contact_name: form.value.contactName,
            contact_phone: form.value.contactPhone,
            contact_email: form.value.contactEmail,
            contact_position: form.value.contactPosition,
            is_decision_maker: form.value.isDecisionMaker,
            address: form.value.address,
            industry: form.value.industry,
            company_size: form.value.companySize,
        })
        await clientStore.refreshApiData()
        toast.success('Dane klienta zaktualizowane')
        showAddModal.value = false
        isEditing.value = false
    } catch (error: any) {
        toast.error('Błąd aktualizacji: ' + (error.response?.data?.message || error.message))
    } finally {
        isSubmitting.value = false
    }
}

const handleDeleteClient = async (client: Client) => {
    if (!confirm(`Czy na pewno chcesz usunąć klienta ${client.contactName} oraz powiązane z nim dane?`)) {
        return
    }

    try {
        await api.delete(`/v1/clients/${client.id}`)
        await clientStore.refreshApiData()
        toast.success('Klient został usunięty')
    } catch (error: any) {
        toast.error('Błąd podczas usuwania: ' + (error.response?.data?.message || error.message))
    }
}

const getLastActivityDate = (client: Client) => {
  if (!client.activityHistory || !Array.isArray(client.activityHistory) || client.activityHistory.length === 0) return 'Brak'
  const activity = client.activityHistory[0] // history is sorted desc
  return activity ? new Date(activity.date).toLocaleString('pl-PL') : 'Brak'
}

const toggleActivityCompletion = async (client: Client, event: Event) => {
    const activity = client.activityHistory?.[0]
    if (!activity) return

    const checkbox = event.target as HTMLInputElement
    const newCompleted = checkbox.checked
    // Optimistic update
    activity.isCompleted = newCompleted

    try {
        // @ts-ignore
        await clientStore.updateActivity(client.id, {
            ...activity,
            isCompleted: newCompleted
        })
    } catch (e) {
        activity.isCompleted = !newCompleted
        toast.error('Błąd aktualizacji statusu')
    }
}

onMounted(() => {
    if (clients.value && clients.value.length === 0) {
        clientStore.fetchClients()
    }
})

const exportToCsv = () => {
  const rows = filteredClients.value
  if (rows.length === 0) {
    toast.warning('Brak danych do eksportu.')
    return
  }

  const header = ['Nazwa Firmy', 'NIP', 'Osoba Kontaktowa', 'Telefon', 'Email', 'Stanowisko', 'Branża', 'Źródło', 'Opiekun', 'Data Ost. Aktywności']
  const csvRows = rows.map((r) => [
    `"${(r.name || '').replace(/"/g, '""')}"`,
    r.nip || '',
    `"${(r.contactName || '').replace(/"/g, '""')}"`,
    r.contactPhone || '',
    r.contactEmail || '',
    `"${(r.contactPosition || '').replace(/"/g, '""')}"`,
    `"${(r.industry || '').replace(/"/g, '""')}"`,
    `"${(r.source || '').replace(/"/g, '""')}"`,
    `"${(r.ownerId || '').replace(/"/g, '""')}"`,
    getLastActivityDate(r),
  ].join(','))

  const csvContent = '\uFEFF' + [header.join(','), ...csvRows].join('\n')
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.setAttribute('href', url)
  link.setAttribute('download', 'spotkania_stratton.csv')
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  toast.success('Rozpoczęto pobieranie pliku CSV.')
}
</script>

<template>
  <div class="p-6 max-w-[1600px] mx-auto space-y-8" :class="{ '!p-0 !max-w-none !space-y-0': embedded }">
    <!-- Header -->
    <div v-if="!embedded" class="relative bg-slate-900 rounded-3xl p-8 md:p-10 overflow-hidden shadow-2xl shadow-slate-900/20 animate-fade-in">
      <div class="absolute inset-0 bg-gradient-to-br from-slate-800/50 to-transparent"></div>
      
      <div class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
        <div class="flex items-center gap-6">
          <button 
            @click="router.push('/app/dashboard')"
            class="group flex items-center justify-center w-12 h-12 rounded-2xl bg-white/10 text-white hover:bg-white hover:text-stratton-500 transition-all duration-300 ring-1 ring-white/20"
            title="Powrót do Dashboardu"
          >
            <AppIcon name="arrow-left" class="w-6 h-6 group-hover:-translate-x-1 transition-transform" />
          </button>
          
          <div>
            <h1 class="text-3xl md:text-4xl font-serif font-bold text-white tracking-tight">Zarządzanie Spotkaniami</h1>
            <p class="text-stratton-100 mt-1 font-medium opacity-90">Planuj kontakty i zarządzaj bazą klientów</p>
          </div>
        </div>

        <button 
          type="button"
          @click="openAddModal"
          class="bg-stratton-gold hover:bg-stratton-gold/90 text-slate-900 px-8 py-4 rounded-2xl font-bold transition-all flex items-center gap-3 shadow-xl shadow-stratton-gold/20 group hover:-translate-y-1 relative z-20"
        >
          <div class="w-6 h-6 rounded-lg bg-white/20 flex items-center justify-center group-hover:bg-white/40 transition-colors">
            <AppIcon name="plus" class="w-4 h-4" />
          </div>
          Dodaj Spotkanie
        </button>
      </div>
    </div>

    <!-- Filters & Table -->
    <div 
      class="bg-white flex flex-col min-h-0"
      :class="embedded ? 'h-auto overflow-visible' : 'rounded-3xl shadow-sm border border-slate-100 overflow-hidden'"
    >
      <div 
        class="bg-gray-50 border-b border-gray-200 flex flex-col md:flex-row items-center justify-between gap-4 shrink-0"
        :class="embedded ? 'p-4' : 'p-6'"
      >
        <div class="flex items-center gap-3">
          <AppIcon name="calendar" class="w-5 h-5 text-brand-main" />
          <h3 class="font-black text-slate-900 text-xl tracking-tight">Spotkania w obsłudze</h3>
        </div>

        <div class="flex flex-1 items-center justify-end gap-4 w-full md:w-auto">
          <div class="flex items-center space-x-2">
            <button type="button" class="flex items-center px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-200 rounded border border-gray-300 bg-white" @click="openAddModal">
              <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
              <span>Nowy</span>
            </button>
            <button type="button" class="flex items-center px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-200 rounded border border-gray-300 bg-white" @click="exportToCsv">
              <svg class="w-4 h-4 mr-1.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
              <span>Eksportuj</span>
            </button>
          </div>

          <div class="relative max-w-md w-full md:w-96">
            <AppIcon name="search" class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 w-5 h-5" />
            <input 
              v-model="searchQuery"
              type="text" 
              placeholder="Szukaj klienta, firmy lub NIP..."
              class="w-full pl-12 pr-4 py-3 rounded-xl border-slate-200 focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all font-medium"
            />
          </div>
        </div>
      </div>

      <div class="flex-1 relative bg-white" :class="embedded ? 'overflow-visible' : 'overflow-hidden'">
        <div :class="embedded ? 'h-auto overflow-auto' : 'h-full overflow-auto'">
          <table class="min-w-full divide-y divide-gray-200" style="min-width: 1200px;">
            <thead class="bg-gray-50 sticky top-0 z-10">
              <tr>
                <th
                  @click="toggleSort('contactName')"
                  class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer hover:text-stratton-gold"
                >
                  Imię i nazwisko
                  <span v-if="sortKey === 'contactName'" class="ml-1 text-[10px]">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                </th>
                <th
                  @click="toggleSort('name')"
                  class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer hover:text-stratton-gold"
                >
                  Firma
                  <span v-if="sortKey === 'name'" class="ml-1 text-[10px]">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                </th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Opiekun</th>
                <th
                  @click="toggleSort('lastMeeting')"
                  class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer hover:text-stratton-gold"
                >
                  Ostatnia Aktywność
                  <span v-if="sortKey === 'lastMeeting'" class="ml-1 text-[10px]">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                </th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Źródło</th>
                <th class="px-4 py-2 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Os.</th>
                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider pr-6">Akcje</th>
                <th class="px-4 py-2 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
              <tr
                v-for="client in slicedMeetings"
                :key="client.id"
                class="hover:bg-sky-50 cursor-pointer transition-colors"
              >
                <td class="px-4 py-2 whitespace-nowrap" @click="openEditClient(client)">
                  <div class="space-y-1 max-w-[260px]">
                    <div class="flex items-center gap-2">
                      <span class="text-sm font-semibold text-brand-main truncate" :title="client.contactName || 'Brak danych'">
                        {{ client.contactName || 'Brak danych' }}
                      </span>
                      <span
                        v-if="client.isDecisionMaker"
                        class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded-full border border-emerald-200"
                      >
                        Decyzyjna
                      </span>
                    </div>
                    <div class="text-xs text-gray-500 truncate" :title="client.contactEmail || client.contactPosition || '—'">
                      {{ client.contactPosition || client.contactEmail || '—' }}
                    </div>
                  </div>
                </td>
                <td class="px-4 py-2 whitespace-nowrap">
                  <div class="w-fit rounded-lg border border-dashed border-gray-200 px-3 py-1.5 bg-gray-50">
                    <div class="text-sm font-semibold text-gray-800 leading-tight truncate max-w-[220px]" :title="client.name">
                      {{ client.name || 'Nieznana firma' }}
                    </div>
                    <div class="text-xs text-gray-500 font-mono tracking-wide">NIP: {{ client.nip || 'brak' }}</div>
                  </div>
                </td>
                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600">
                  <div class="w-fit rounded-lg border border-dashed border-gray-200 px-3 py-1.5 bg-white/60">
                    <div class="text-xs font-semibold text-gray-800">{{ client.ownerId || 'Nieprzypisany' }}</div>
                    <div class="text-[11px] text-gray-500 font-mono">ID: {{ client.ownerId || 'Brak' }}</div>
                  </div>
                </td>
                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600">
                  <div class="flex items-center text-xs text-gray-500 gap-2">
                    <span class="w-2 h-2 rounded-full" :class="getLastActivityDate(client) === 'Brak' ? 'bg-gray-300' : 'bg-emerald-400'"></span>
                    <span>{{ getLastActivityDate(client) }}</span>
                  </div>
                </td>
                <td class="px-4 py-2 whitespace-nowrap text-xs">
                  <div class="inline-flex flex-col items-center justify-center px-3 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wide leading-tight"
                    :class="client.source ? 'bg-slate-100 text-slate-600 border border-slate-200' : 'bg-slate-50 text-slate-400 border border-dashed border-slate-200'"
                    :title="client.source || 'Brak'"
                  >
                    <span v-for="(word, i) in (client.source || 'Brak').split(' ')" :key="i">{{ word }}</span>
                  </div>
                </td>
                <td class="px-4 py-2 text-center text-sm font-bold text-gray-700">{{ client.companySize || '?' }}</td>
                <td class="px-4 py-2">
                  <div class="flex items-center justify-end gap-2">
                    <button
                      @click.stop="promoteToClient(client)"
                      class="p-2 rounded-lg border border-transparent text-slate-400 hover:text-emerald-600 hover:border-emerald-100 hover:bg-emerald-50 transition"
                      title="Utwórz klienta i kalkulację"
                    >
                      <AppIcon name="calculator" class="w-4 h-4" />
                    </button>
                    <button
                      @click.stop="openEditClient(client)"
                      class="p-2 rounded-lg border border-transparent text-slate-400 hover:text-blue-600 hover:border-blue-100 hover:bg-blue-50 transition"
                      title="Edytuj"
                    >
                      <AppIcon name="pencil-square" class="w-4 h-4" />
                    </button>
                    <button
                      @click.stop="handleDeleteClient(client)"
                      class="p-2 rounded-lg border border-transparent text-slate-400 hover:text-red-600 hover:border-red-100 hover:bg-red-50 transition"
                      title="Usuń"
                    >
                      <AppIcon name="trash" class="w-4 h-4" />
                    </button>
                  </div>
                </td>
                <td class="px-4 py-2 text-center" @click.stop>
                   <input 
                      type="checkbox" 
                      :checked="client.activityHistory && client.activityHistory.length > 0 && !!client.activityHistory[0].isCompleted"
                      @change="(e) => toggleActivityCompletion(client, e)"
                      class="w-6 h-6 rounded border-2 border-slate-300 text-green-500 focus:ring-green-500 cursor-pointer transition-all hover:scale-110"
                   />
                </td>
              </tr>
              <tr v-if="filteredClients.length === 0">
                <td colspan="8" class="p-8 text-center text-gray-500 text-sm">Nie znaleziono rekordów spełniających kryteria.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination Meetings -->
      <div 
        class="bg-slate-50 border-t border-slate-100 shrink-0 flex justify-between items-center"
        :class="embedded ? 'px-4 py-2' : 'px-6 py-4'"
      >
        <div class="text-[10px] text-slate-400 font-medium uppercase tracking-widest">
          Strona {{ meetingCurrentPage }} z {{ totalMeetingPages || 1 }} ({{ filteredClients.length }} rekordów)
        </div>
        <div class="flex gap-2">
            <button 
                type="button" 
                @click="prevMeetingPage"
                :disabled="meetingCurrentPage === 1"
                class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 transition shadow-sm disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
                <AppIcon name="chevron-left" class="w-3.5 h-3.5" />
                Poprzednia
            </button>
            <button 
                type="button" 
                @click="nextMeetingPage"
                :disabled="meetingCurrentPage >= totalMeetingPages"
                class="px-4 py-2 bg-stratton-gold text-slate-800 rounded-xl text-xs font-bold hover:bg-stratton-gold/90 transition shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
                Następna
                <AppIcon name="chevron-right" class="w-3.5 h-3.5" />
            </button>
        </div>
      </div>
    </div>

    <!-- Add Meeting Modal -->
    <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showAddModal = false"></div>
      <div class="relative bg-white w-full max-w-4xl max-h-[90vh] overflow-y-auto rounded-3xl shadow-2xl flex flex-col animate-in fade-in zoom-in duration-300">
        <div class="p-8 border-b border-slate-100 flex justify-between items-center sticky top-0 bg-white z-10">
          <div>
            <h2 class="text-2xl font-serif font-bold text-slate-900">{{ isEditing ? 'Edycja Spotkania' : 'Nowe Spotkanie' }}</h2>
            <p class="text-slate-500 mt-1">Uzupełnij dane spotkania i klienta</p>
          </div>
          <button @click="showAddModal = false" class="text-slate-400 hover:text-slate-600 transition-colors">
            <AppIcon name="xmark" class="w-8 h-8" />
          </button>
        </div>

        <div class="p-8 space-y-8">
          <!-- Section: Contact Person -->
          <div class="space-y-4">
            <h3 class="flex items-center gap-2 text-lg font-serif font-bold text-slate-800">
              <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                <AppIcon name="user" class="w-5 h-5" />
              </div>
              Osoba Kontaktowa
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="space-y-1">
                <label class="text-xs font-bold text-slate-500 uppercase ml-1">Imię i Nazwisko *</label>
                <input 
                  v-model="form.contactName" 
                  type="text" 
                  placeholder="Jan Kowalski" 
                  class="form-input"
                  :class="{ 'border-red-500 ring-1 ring-red-500': wasValidated && !form.contactName }" 
                />
              </div>
              <div class="space-y-1">
                <label class="text-xs font-bold text-slate-500 uppercase ml-1">Stanowisko</label>
                <input v-model="form.contactPosition" type="text" placeholder="Dyrektor HR" class="form-input" />
              </div>
              <div class="space-y-1">
                <label class="text-xs font-bold text-slate-500 uppercase ml-1">Telefon</label>
                <input 
                  v-model="form.contactPhone" 
                  type="text" 
                  placeholder="+48 000 000 000" 
                  class="form-input"
                  :class="{ 'border-red-500 ring-1 ring-red-500': wasValidated && !form.contactPhone }"
                />
              </div>
              <div class="space-y-1">
                <label class="text-xs font-bold text-slate-500 uppercase ml-1">Email</label>
                <input 
                  v-model="form.contactEmail" 
                  type="email" 
                  placeholder="email@firma.pl" 
                  class="form-input"
                  :class="{ 'border-red-500 ring-1 ring-red-500': wasValidated && !form.contactEmail }"
                />
              </div>
            </div>
            <label class="flex items-center gap-3 cursor-pointer group mt-2">
              <input v-model="form.isDecisionMaker" type="checkbox" class="w-5 h-5 rounded border-slate-300 text-green-600 focus:ring-green-500 transition-all" />
              <span class="text-slate-700 font-medium group-hover:text-green-600 transition-colors">Osoba decyzyjna</span>
            </label>
          </div>

          <!-- Section: Company Details -->
          <div class="space-y-4 pt-8 border-t border-slate-50">
            <h3 class="flex items-center gap-2 text-lg font-serif font-bold text-slate-800">
              <div class="w-8 h-8 rounded-lg bg-green-50 text-green-600 flex items-center justify-center">
                <AppIcon name="building" class="w-5 h-5" />
              </div>
              Dane Firmy (Opcjonalne)
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div class="space-y-1 md:col-span-2">
                <label class="text-xs font-bold text-slate-500 uppercase ml-1">NIP (GUS Autofill)</label>
                <div class="flex gap-2">
                  <input v-model="form.nip" type="text" placeholder="10 cyfr" class="form-input" />
                  <button 
                    @click="fetchGusData" 
                    :disabled="isFetchingGus"
                    class="bg-green-100 text-green-700 px-4 rounded-xl font-bold hover:bg-green-200 transition-all disabled:opacity-50 flex items-center gap-2 whitespace-nowrap"
                  >
                   <AppIcon v-if="!isFetchingGus" name="refresh" class="w-4 h-4" />
                   <div v-else class="w-4 h-4 border-2 border-green-700 border-t-transparent rounded-full animate-spin"></div>
                   Pobierz
                  </button>
                </div>
              </div>
              <div class="space-y-1">
                <label class="text-xs font-bold text-slate-500 uppercase ml-1">Ilość pracowników *</label>
                <input 
                  v-model="form.companySize" 
                  type="text" 
                  placeholder="Np. 25" 
                  class="form-input" 
                  :class="{ 'border-red-500 ring-1 ring-red-500': wasValidated && !form.companySize }"
                />
              </div>
              <div class="space-y-1 md:col-span-3">
                <label class="text-xs font-bold text-slate-500 uppercase ml-1">Nazwa firmy *</label>
                <input 
                  v-model="form.companyName" 
                  type="text" 
                  placeholder="Firma Sp. z o.o." 
                  class="form-input" 
                  :class="{ 'border-red-500 ring-1 ring-red-500': wasValidated && !form.companyName }"
                />
              </div>
              <div class="space-y-1 md:col-span-2">
                <label class="text-xs font-bold text-slate-500 uppercase ml-1">Adres *</label>
                <input 
                  v-model="form.address" 
                  type="text" 
                  placeholder="ul. Sezamkowa 1, 00-000 Warszawa" 
                  class="form-input" 
                  :class="{ 'border-red-500 ring-1 ring-red-500': wasValidated && !form.address }"
                />
              </div>
              <div class="space-y-1">
                <label class="text-xs font-bold text-slate-500 uppercase ml-1">Branża *</label>
                <input 
                  v-model="form.industry" 
                  list="industry-options"
                  type="text" 
                  placeholder="Wyszukaj branżę..." 
                  class="form-input" 
                  :class="{ 'border-red-500 ring-1 ring-red-500': wasValidated && !form.industry }"
                />
                <datalist id="industry-options">
                  <option v-for="ind in industries" :key="ind" :value="ind"></option>
                </datalist>
              </div>
            </div>
          </div>

          <!-- Section: Meeting Info -->
          <div class="space-y-4 pt-8 border-t border-slate-50">
             <h3 class="flex items-center gap-2 text-lg font-bold text-slate-800">
              <div class="w-8 h-8 rounded-lg bg-green-50 text-green-600 flex items-center justify-center">
                <AppIcon name="calendar" class="w-5 h-5" />
              </div>
              {{ isEditing ? 'Ostatnia Aktywność / Aktualizacja' : 'Informacje o Spotkaniu' }}
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="space-y-1">
                <label class="text-xs font-bold text-slate-500 uppercase ml-1">Źródło Kontaktu *</label>
                <select v-model="form.source" class="form-input font-bold text-green-700 bg-green-50/50">
                  <option v-for="src in contactSources" :key="src" :value="src">{{ src }}</option>
                </select>
              </div>
              <div class="space-y-1">
                <label class="text-xs font-bold text-slate-500 uppercase ml-1" :class="{'flex justify-between items-center': isEditing}">
                  {{ isEditing ? 'Data aktualizacji' : 'Data i Godzina *' }}
                </label>
                <div class="flex gap-2">
                  <input 
                    ref="dateInput" 
                    v-model="form.meetingDate" 
                    type="datetime-local" 
                    class="form-input flex-1" 
                    :class="{ 'border-red-500 ring-1 ring-red-500': wasValidated && !form.meetingDate }"
                  />
                  <button 
                    type="button"
                    @click="dateInput?.blur()"
                    class="bg-green-600 text-white px-6 rounded-xl font-bold hover:bg-green-700 transition-all shadow-md active:scale-95 flex items-center justify-center shrink-0"
                  >
                    OK
                  </button>
                </div>
              </div>
              <div class="space-y-1 md:col-span-2">
                <label class="text-xs font-bold text-slate-500 uppercase ml-1">Cel / Notatki</label>
                <textarea 
                  v-model="form.meetingNotes"
                  rows="3" 
                  :placeholder="isEditing ? 'Wprowadź notatkę z ostatniego kontaktu lub aktualizację...' : 'Opisz cel spotkania lub dodaj ważne uwagi...'"
                  class="form-input resize-none"
                ></textarea>
              </div>
            </div>
          </div>
        </div>

        <div class="p-8 bg-slate-50 border-t border-slate-100 flex justify-end gap-4 sticky bottom-0 z-10">
          <button 
            @click="showAddModal = false"
            class="px-6 py-3 rounded-xl font-bold text-slate-600 hover:bg-slate-200 transition-all"
          >
            Anuluj
          </button>
          <button 
            @click="isEditing ? handleUpdateClient() : handleAddMeeting()"
            :disabled="isSubmitting"
            class="bg-green-600 hover:bg-green-700 text-white px-10 py-3 rounded-xl font-bold transition-all shadow-lg shadow-green-500/30 disabled:opacity-50 flex items-center gap-2"
          >
            <AppIcon v-if="!isSubmitting" name="check" class="w-5 h-5" />
            <div v-else class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
            {{ isEditing ? 'Zapisz Zmiany' : 'Zapisz Spotkanie' }}
          </button>
        </div>
      </div>
    </div>


    <!-- Edit Client Modal - DEPRECATED / REMOVED from UI trigger but kept for safety if any other calls remain, though we will disable it -->
    <!-- We will remove the v-if from here or comment out the block to ensure it's not used. 
         Actually, let's keep it but since we changed openEditClient to use showAddModal, this block will never be shown.
    -->
    <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showEditModal = false"></div>
      <div class="relative bg-white w-full max-w-4xl max-h-[90vh] overflow-y-auto rounded-3xl shadow-2xl flex flex-col animate-in fade-in zoom-in duration-300">
        <div class="p-8 border-b border-slate-100 flex justify-between items-center sticky top-0 bg-white z-10">
          <div>
            <h2 class="text-2xl font-bold text-slate-900">Edycja Klienta</h2>
            <p class="text-slate-500 mt-1">Popraw dane w bazie CRM</p>
          </div>
          <button @click="showEditModal = false" class="text-slate-400 hover:text-slate-600 transition-colors">
            <AppIcon name="xmark" class="w-8 h-8" />
          </button>
        </div>

        <div class="p-8 space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <h3 class="text-slate-800 font-bold uppercase text-xs tracking-widest border-l-4 border-blue-500 pl-3">Dane Osobowe</h3>
                    <div class="space-y-4">
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-slate-500 uppercase">Imię i Nazwisko</label>
                            <input v-model="form.contactName" type="text" class="form-input" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-slate-500 uppercase">Stanowisko</label>
                            <input v-model="form.contactPosition" type="text" class="form-input" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-slate-500 uppercase">Telefon</label>
                            <input v-model="form.contactPhone" type="text" class="form-input" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-slate-500 uppercase">Email</label>
                            <input v-model="form.contactEmail" type="email" class="form-input" />
                        </div>
                        <label class="flex items-center gap-3 cursor-pointer mt-2">
                            <input v-model="form.isDecisionMaker" type="checkbox" class="w-5 h-5 rounded border-slate-300 text-green-600" />
                            <span class="text-slate-700 font-medium">Osoba decyzyjna</span>
                        </label>
                    </div>
                </div>
                <div class="space-y-4">
                    <h3 class="text-slate-800 font-bold uppercase text-xs tracking-widest border-l-4 border-green-500 pl-3">Dane Firmowe</h3>
                    <div class="space-y-4">
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-slate-500 uppercase">Nazwa Firmy</label>
                            <input v-model="form.companyName" type="text" class="form-input" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-slate-500 uppercase">NIP</label>
                            <input v-model="form.nip" type="text" class="form-input" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-slate-500 uppercase">Adres</label>
                            <input v-model="form.address" type="text" class="form-input" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-slate-500 uppercase">Branża</label>
                            <input 
                              v-model="form.industry" 
                              list="industry-options-edit"
                              type="text" 
                              class="form-input" 
                            />
                            <datalist id="industry-options-edit">
                              <option v-for="ind in industries" :key="ind" :value="ind"></option>
                            </datalist>
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-slate-500 uppercase">Ilość pracowników</label>
                            <input v-model="form.companySize" type="text" class="form-input" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="p-8 bg-slate-50 border-t border-slate-100 flex justify-end gap-4 sticky bottom-0 z-10">
          <button @click="showEditModal = false" class="px-6 py-3 rounded-xl font-bold text-slate-600 hover:bg-slate-200 transition-all">Anuluj</button>
          <button 
            @click="handleUpdateClient"
            :disabled="isSubmitting"
            class="bg-blue-600 hover:bg-blue-700 text-white px-10 py-3 rounded-xl font-bold transition-all shadow-lg shadow-blue-500/30 disabled:opacity-50 flex items-center gap-2"
          >
            <AppIcon v-if="!isSubmitting" name="check" class="w-5 h-5" />
            <div v-else class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
            Zapisz Zmiany
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@reference "../assets/tailwind.css";

.form-input {
  @apply w-full border-slate-200 rounded-xl py-3 px-4 focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all font-medium placeholder:text-slate-300;
}
</style>
