<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { api } from '@/api/client'
import AppIcon from '@/components/AppIcon.vue'

interface ClientNote {
  id: number
  content: string
  created_at: string
  author?: { id: number; name: string }
}

interface LeadClient {
  id: number
  name: string
  nip?: string
  city?: string
  phone?: string
  email?: string
  address_line1?: string
  postal_code?: string
  status?: string
  last_action_date?: string
  contact_name?: string
  contact_phone?: string
  crmProfile?: { status?: string; contact_name?: string; contact_phone?: string }
}

const clients = ref<LeadClient[]>([])
const loading = ref(false)
const search = ref('')
const page = ref(1)
const lastPage = ref(1)
const totalClients = ref(0)
const showAddModal = ref(false)
const editingClient = ref<LeadClient | null>(null)
const noteContent = ref<Record<number, string>>({})
const expandedClientId = ref<number | null>(null)
const clientNotes = ref<Record<number, ClientNote[]>>({})
const loadingNotes = ref<Record<number, boolean>>({})

const form = ref({
  name: '',
  nip: '',
  phone: '',
  email: '',
  city: '',
  postal_code: '',
  address_line1: '',
  website: '',
  notes: '',
  contact_name: '',
  contact_phone: '',
  contact_email: '',
})

const saving = ref(false)
const formError = ref('')

const statusLabel = (status?: string) => {
  const labels: Record<string, string> = {
    NEW: 'Nowy',
    IN_TALKS: 'W rozmowach',
    SIGNED: 'Podpisano',
    TERMINATED: 'Rozwiązano',
    RESIGNED: 'Rezygnacja',
  }
  return status ? (labels[status] || status) : '—'
}

const statusClass = (status?: string) => {
  const map: Record<string, string> = {
    NEW: 'bg-yellow-100 text-yellow-800',
    IN_TALKS: 'bg-indigo-100 text-indigo-800',
    SIGNED: 'bg-emerald-100 text-emerald-800',
    TERMINATED: 'bg-gray-200 text-gray-800',
    RESIGNED: 'bg-red-100 text-red-800',
  }
  return status ? (map[status] || 'bg-slate-100 text-slate-600') : 'bg-slate-100 text-slate-400'
}

const clientStatus = (client: LeadClient) =>
  client.status || client.crmProfile?.status

const fetchClients = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/v1/clients', {
      params: { search: search.value || undefined, page: page.value, per_page: 25 },
    })
    clients.value = Array.isArray(data) ? data : (data.data ?? [])
    lastPage.value = data.last_page ?? 1
    totalClients.value = data.total ?? clients.value.length
  } catch {
    clients.value = []
  } finally {
    loading.value = false
  }
}

const fetchNotes = async (clientId: number) => {
  if (clientNotes.value[clientId]) return
  loadingNotes.value[clientId] = true
  try {
    const { data } = await api.get(`/v1/clients/${clientId}/notes`)
    clientNotes.value[clientId] = Array.isArray(data) ? data : []
  } catch {
    clientNotes.value[clientId] = []
  } finally {
    loadingNotes.value[clientId] = false
  }
}

const toggleExpand = (clientId: number) => {
  if (expandedClientId.value === clientId) {
    expandedClientId.value = null
  } else {
    expandedClientId.value = clientId
    fetchNotes(clientId)
  }
}

const addNote = async (clientId: number) => {
  const content = noteContent.value[clientId]?.trim()
  if (!content) return
  try {
    const { data } = await api.post(`/v1/clients/${clientId}/notes`, { content })
    if (!clientNotes.value[clientId]) clientNotes.value[clientId] = []
    clientNotes.value[clientId].unshift(data)
    noteContent.value[clientId] = ''
  } catch { /* ignore */ }
}

const deleteNote = async (clientId: number, noteId: number) => {
  try {
    await api.delete(`/v1/clients/${clientId}/notes/${noteId}`)
    clientNotes.value[clientId] = (clientNotes.value[clientId] || []).filter((n) => n.id !== noteId)
  } catch { /* ignore */ }
}

const openAddModal = () => {
  editingClient.value = null
  Object.assign(form.value, {
    name: '', nip: '', phone: '', email: '', city: '', postal_code: '',
    address_line1: '', website: '', notes: '', contact_name: '', contact_phone: '', contact_email: '',
  })
  formError.value = ''
  showAddModal.value = true
}

const openEditModal = (client: LeadClient) => {
  editingClient.value = client
  Object.assign(form.value, {
    name: client.name ?? '',
    nip: client.nip ?? '',
    phone: client.phone ?? '',
    email: client.email ?? '',
    city: client.city ?? '',
    postal_code: client.postal_code ?? '',
    address_line1: client.address_line1 ?? '',
    website: '',
    notes: '',
    contact_name: client.contact_name ?? client.crmProfile?.contact_name ?? '',
    contact_phone: client.contact_phone ?? client.crmProfile?.contact_phone ?? '',
    contact_email: '',
  })
  formError.value = ''
  showAddModal.value = true
}

const saveClient = async () => {
  saving.value = true
  formError.value = ''
  try {
    if (editingClient.value) {
      const { data } = await api.put(`/v1/clients/${editingClient.value.id}`, form.value)
      const idx = clients.value.findIndex((c) => c.id === editingClient.value!.id)
      if (idx !== -1) clients.value[idx] = { ...clients.value[idx], ...data }
    } else {
      await api.post('/v1/clients', form.value)
      await fetchClients()
    }
    showAddModal.value = false
  } catch (err: any) {
    formError.value = err?.response?.data?.message || Object.values(err?.response?.data?.errors || {})[0]?.[0] || 'Wystąpił błąd.'
  } finally {
    saving.value = false
  }
}

const formatDate = (d?: string) => {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('pl-PL', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

let searchTimer: ReturnType<typeof setTimeout> | null = null
const onSearch = () => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { page.value = 1; fetchClients() }, 400)
}

onMounted(fetchClients)
</script>

<template>
  <div class="flex flex-col h-full bg-slate-50">
    <!-- Header -->
    <div class="bg-white border-b border-slate-200 px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shrink-0">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Moi Klienci</h1>
        <p class="text-xs text-slate-500 mt-0.5">Lista dodanych przez Ciebie klientów{{ totalClients ? ` · ${totalClients} rekordów` : '' }}</p>
      </div>
      <div class="flex items-center gap-3">
        <div class="relative">
          <AppIcon name="magnifying-glass" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            v-model="search"
            @input="onSearch"
            placeholder="Szukaj po nazwie lub NIP..."
            class="pl-9 pr-4 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-slate-300 w-64"
          />
        </div>
        <button
          @click="openAddModal"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white shrink-0"
          style="background: linear-gradient(135deg, #001f3d 0%, #003366 100%)"
        >
          <AppIcon name="plus" class="w-4 h-4" />
          Dodaj klienta
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-20">
      <div class="w-8 h-8 border-2 border-slate-300 border-t-[#C5A059] rounded-full animate-spin" />
    </div>

    <!-- Empty -->
    <div v-else-if="!clients.length" class="text-center py-20 text-slate-400">
      <AppIcon name="users" class="w-14 h-14 mx-auto mb-3 opacity-20" />
      <p class="text-sm font-medium">Brak klientów. Dodaj pierwszego!</p>
    </div>

    <!-- Table -->
    <div v-else class="flex-1 overflow-auto">
      <table class="w-full divide-y divide-slate-100">
        <thead class="bg-slate-50 sticky top-0 z-10 shadow-sm">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Nazwa Klienta</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Status</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Lokalizacja</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Kontakt</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Ostatnia Akt.</th>
            <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-wider pr-6">Akcje</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50 bg-white">
          <template v-for="client in clients" :key="client.id">
            <tr
              class="hover:bg-slate-50 cursor-pointer transition-colors group"
              :class="expandedClientId === client.id ? 'bg-indigo-50/20' : ''"
              @click="toggleExpand(client.id)"
            >
              <!-- Nazwa + NIP -->
              <td class="px-4 py-3 whitespace-nowrap">
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background: linear-gradient(135deg, #001f3d, #003366)">
                    <AppIcon name="building-office" class="w-3.5 h-3.5 text-white" />
                  </div>
                  <div class="min-w-0">
                    <div class="text-sm font-bold text-slate-700 group-hover:text-[#002a52] transition-colors truncate max-w-[220px]">{{ client.name }}</div>
                    <div class="text-xs text-slate-400 font-mono">{{ client.nip || '—' }}</div>
                  </div>
                </div>
              </td>
              <!-- Status -->
              <td class="px-4 py-3 whitespace-nowrap">
                <span
                  class="px-2 py-0.5 inline-flex text-xs leading-4 font-semibold rounded-full"
                  :class="statusClass(clientStatus(client))"
                >
                  {{ statusLabel(clientStatus(client)) }}
                </span>
              </td>
              <!-- Lokalizacja -->
              <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-600">
                {{ [client.postal_code, client.city].filter(Boolean).join(' ') || '—' }}
              </td>
              <!-- Kontakt -->
              <td class="px-4 py-3 whitespace-nowrap">
                <div class="text-sm text-slate-700">{{ client.phone || '—' }}</div>
                <div class="text-xs text-slate-400 truncate max-w-[180px]">{{ client.email || '' }}</div>
              </td>
              <!-- Ostatnia aktywność -->
              <td class="px-4 py-3 whitespace-nowrap text-xs text-slate-500">
                {{ formatDate(client.last_action_date) }}
              </td>
              <!-- Akcje -->
              <td class="px-4 py-3 whitespace-nowrap text-right" @click.stop>
                <div class="flex items-center justify-end gap-1">
                  <button
                    @click="openEditModal(client)"
                    class="p-1.5 rounded-lg border border-transparent text-slate-400 hover:text-blue-600 hover:border-blue-100 hover:bg-blue-50 transition"
                    title="Edytuj"
                  >
                    <AppIcon name="pencil-square" class="w-4 h-4" />
                  </button>
                  <button
                    @click="toggleExpand(client.id)"
                    class="p-1.5 rounded-lg border border-transparent text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition"
                    :title="expandedClientId === client.id ? 'Zwiń' : 'Notatki'"
                  >
                    <AppIcon name="chat-bubble-left-ellipsis" class="w-4 h-4" />
                  </button>
                  <AppIcon
                    name="chevron-down"
                    class="w-4 h-4 text-slate-300 transition-transform duration-200 ml-1"
                    :class="expandedClientId === client.id ? 'rotate-180 text-slate-500' : ''"
                  />
                </div>
              </td>
            </tr>

            <!-- Expanded row: contact details + notes -->
            <tr v-if="expandedClientId === client.id" class="bg-slate-50 border-y border-slate-200 animate-fade-in">
              <td colspan="6" class="px-6 py-4" @click.stop>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                  <!-- Dane kontaktowe -->
                  <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Dane szczegółowe</p>
                    <div class="grid grid-cols-2 gap-2 text-sm text-slate-700">
                      <div v-if="client.address_line1" class="col-span-2 flex items-start gap-2">
                        <AppIcon name="map-pin" class="w-4 h-4 text-slate-400 mt-0.5 shrink-0" />
                        <span>{{ [client.address_line1, client.postal_code, client.city].filter(Boolean).join(', ') }}</span>
                      </div>
                      <div v-if="client.phone" class="flex items-center gap-2">
                        <AppIcon name="phone" class="w-4 h-4 text-slate-400 shrink-0" />
                        <span>{{ client.phone }}</span>
                      </div>
                      <div v-if="client.email" class="flex items-center gap-2 truncate">
                        <AppIcon name="envelope" class="w-4 h-4 text-slate-400 shrink-0" />
                        <span class="truncate">{{ client.email }}</span>
                      </div>
                      <div v-if="client.contact_name || client.crmProfile?.contact_name" class="flex items-center gap-2">
                        <AppIcon name="user" class="w-4 h-4 text-slate-400 shrink-0" />
                        <span>{{ client.contact_name || client.crmProfile?.contact_name }}</span>
                      </div>
                      <div v-if="client.contact_phone || client.crmProfile?.contact_phone" class="flex items-center gap-2">
                        <AppIcon name="phone" class="w-4 h-4 text-slate-400 shrink-0" />
                        <span>{{ client.contact_phone || client.crmProfile?.contact_phone }}</span>
                      </div>
                    </div>
                  </div>

                  <!-- Notatki -->
                  <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Notatki</p>
                    <div class="flex gap-2 mb-3">
                      <input
                        v-model="noteContent[client.id]"
                        placeholder="Dodaj notatkę..."
                        class="flex-1 text-sm border border-slate-200 rounded-lg px-3 py-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-slate-300"
                        @keydown.enter="addNote(client.id)"
                      />
                      <button
                        @click="addNote(client.id)"
                        class="px-3 py-1.5 rounded-lg text-sm font-medium text-white shrink-0"
                        style="background: linear-gradient(135deg, #001f3d, #003366)"
                      >Dodaj</button>
                    </div>
                    <div v-if="loadingNotes[client.id]" class="text-xs text-slate-400 py-1">Ładowanie...</div>
                    <div v-else-if="!clientNotes[client.id]?.length" class="text-xs text-slate-400 py-1 italic">Brak notatek.</div>
                    <div v-else class="space-y-2 max-h-40 overflow-y-auto">
                      <div
                        v-for="note in clientNotes[client.id]"
                        :key="note.id"
                        class="bg-white border border-slate-200 rounded-lg px-3 py-2 flex justify-between gap-2"
                      >
                        <div class="min-w-0">
                          <p class="text-sm text-slate-700 wrap-break-word">{{ note.content }}</p>
                          <p class="text-[10px] text-slate-400 mt-0.5">{{ note.author?.name }} · {{ formatDate(note.created_at) }}</p>
                        </div>
                        <button @click="deleteNote(client.id, note.id)" class="text-slate-300 hover:text-red-400 transition-colors shrink-0 mt-0.5">
                          <AppIcon name="trash" class="w-3.5 h-3.5" />
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="lastPage > 1" class="bg-white border-t border-slate-200 px-6 py-3 flex justify-between items-center shrink-0">
      <div class="text-xs text-slate-400 uppercase tracking-wider font-medium">
        Strona {{ page }} z {{ lastPage }} · {{ totalClients }} rekordów
      </div>
      <div class="flex gap-2">
        <button
          @click="page--; fetchClients()"
          :disabled="page === 1"
          class="px-4 py-2 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-1.5"
        >
          <AppIcon name="chevron-left" class="w-3.5 h-3.5" />
          Poprzednia
        </button>
        <button
          @click="page++; fetchClients()"
          :disabled="page >= lastPage"
          class="px-4 py-2 rounded-lg text-xs font-bold text-white disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-1.5"
          style="background: linear-gradient(135deg, #001f3d, #003366)"
        >
          Następna
          <AppIcon name="chevron-right" class="w-3.5 h-3.5" />
        </button>
      </div>
    </div>

    <!-- Add/Edit modal -->
    <Teleport to="body">
      <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
            <h2 class="text-lg font-bold text-slate-800">{{ editingClient ? 'Edytuj klienta' : 'Nowy klient' }}</h2>
            <button @click="showAddModal = false" class="text-slate-400 hover:text-slate-700">
              <AppIcon name="x-mark" class="w-5 h-5" />
            </button>
          </div>
          <form @submit.prevent="saveClient" class="px-6 py-5 space-y-4">
            <div>
              <label class="block text-xs font-semibold text-slate-600 mb-1">Nazwa firmy *</label>
              <input v-model="form.name" required class="input-field w-full" placeholder="Nazwa firmy" />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">NIP</label>
                <input v-model="form.nip" class="input-field w-full" placeholder="000-000-00-00" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Telefon</label>
                <input v-model="form.phone" class="input-field w-full" placeholder="+48 000 000 000" />
              </div>
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-600 mb-1">Email</label>
              <input v-model="form.email" type="email" class="input-field w-full" placeholder="firma@example.com" />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Miasto</label>
                <input v-model="form.city" class="input-field w-full" placeholder="Warszawa" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Kod pocztowy</label>
                <input v-model="form.postal_code" class="input-field w-full" placeholder="00-000" />
              </div>
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-600 mb-1">Adres</label>
              <input v-model="form.address_line1" class="input-field w-full" placeholder="ul. Przykładowa 1" />
            </div>
            <hr class="border-slate-100" />
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Osoba kontaktowa</p>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Imię i nazwisko</label>
                <input v-model="form.contact_name" class="input-field w-full" placeholder="Jan Kowalski" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Telefon</label>
                <input v-model="form.contact_phone" class="input-field w-full" placeholder="+48 000 000 000" />
              </div>
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-600 mb-1">Email kontaktowy</label>
              <input v-model="form.contact_email" type="email" class="input-field w-full" placeholder="kontakt@firma.pl" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-600 mb-1">Notatka</label>
              <textarea v-model="form.notes" class="input-field w-full h-20 resize-none" placeholder="Dodatkowe informacje..." />
            </div>
            <p v-if="formError" class="text-sm text-red-500">{{ formError }}</p>
            <div class="flex gap-3 pt-2">
              <button
                type="submit"
                :disabled="saving"
                class="flex-1 py-2.5 rounded-xl text-sm font-semibold text-white disabled:opacity-60"
                style="background: linear-gradient(135deg, #001f3d, #003366)"
              >
                {{ saving ? 'Zapisywanie...' : (editingClient ? 'Zapisz zmiany' : 'Dodaj klienta') }}
              </button>
              <button type="button" @click="showAddModal = false" class="px-4 py-2.5 rounded-xl text-sm font-medium text-slate-600 border border-slate-200 hover:bg-slate-50">
                Anuluj
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
@reference "tailwindcss";
.input-field {
  @apply border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-slate-300;
}
</style>
