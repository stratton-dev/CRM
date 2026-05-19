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
  notes?: ClientNote[]
  crmProfile?: { status?: string; contact_name?: string; contact_phone?: string }
}

const clients = ref<LeadClient[]>([])
const loading = ref(false)
const search = ref('')
const page = ref(1)
const lastPage = ref(1)
const showAddModal = ref(false)
const editingClient = ref<LeadClient | null>(null)
const noteContent = ref<Record<number, string>>({})
const expandedClient = ref<number | null>(null)
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

const fetchClients = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/v1/clients', {
      params: { search: search.value || undefined, page: page.value, per_page: 20 },
    })
    clients.value = Array.isArray(data) ? data : data.data ?? []
    lastPage.value = data.last_page ?? 1
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
  if (expandedClient.value === clientId) {
    expandedClient.value = null
  } else {
    expandedClient.value = clientId
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
  } catch {
    // ignore
  }
}

const deleteNote = async (clientId: number, noteId: number) => {
  try {
    await api.delete(`/v1/clients/${clientId}/notes/${noteId}`)
    clientNotes.value[clientId] = (clientNotes.value[clientId] || []).filter((n) => n.id !== noteId)
  } catch {
    // ignore
  }
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
    contact_name: client.crmProfile?.contact_name ?? '',
    contact_phone: client.crmProfile?.contact_phone ?? '',
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

const formatDate = (d: string) => new Date(d).toLocaleDateString('pl-PL', { day: '2-digit', month: '2-digit', year: 'numeric' })

let searchTimer: ReturnType<typeof setTimeout> | null = null
const onSearch = () => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { page.value = 1; fetchClients() }, 400)
}

onMounted(fetchClients)
</script>

<template>
  <div class="min-h-screen bg-slate-50 p-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl font-bold text-slate-800">Moi Klienci</h1>
        <p class="text-sm text-slate-500 mt-0.5">Lista dodanych przez Ciebie klientów</p>
      </div>
      <button
        @click="openAddModal"
        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white"
        style="background: linear-gradient(135deg, #001f3d 0%, #003366 100%)"
      >
        <AppIcon name="plus" class="w-4 h-4" />
        Dodaj klienta
      </button>
    </div>

    <!-- Search -->
    <div class="relative mb-5 max-w-md">
      <AppIcon name="magnifying-glass" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
      <input
        v-model="search"
        @input="onSearch"
        placeholder="Szukaj po nazwie lub NIP..."
        class="w-full pl-9 pr-4 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-slate-300"
      />
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-16">
      <div class="w-8 h-8 border-2 border-slate-300 border-t-[#C5A059] rounded-full animate-spin" />
    </div>

    <!-- Empty -->
    <div v-else-if="!clients.length" class="text-center py-16 text-slate-400">
      <AppIcon name="users" class="w-12 h-12 mx-auto mb-3 opacity-30" />
      <p>Brak klientów. Dodaj pierwszego!</p>
    </div>

    <!-- Client cards -->
    <div v-else class="space-y-3">
      <div
        v-for="client in clients"
        :key="client.id"
        class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm"
      >
        <!-- Card header -->
        <div class="flex items-center justify-between px-4 py-3 cursor-pointer hover:bg-slate-50 transition-colors" @click="toggleExpand(client.id)">
          <div class="flex items-center gap-3 min-w-0">
            <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0" style="background: linear-gradient(135deg, #001f3d, #003366)">
              <AppIcon name="building-office" class="w-4 h-4 text-white" />
            </div>
            <div class="min-w-0">
              <p class="font-semibold text-slate-800 text-sm truncate">{{ client.name }}</p>
              <p class="text-xs text-slate-400">
                {{ [client.nip ? 'NIP: ' + client.nip : null, client.city].filter(Boolean).join(' · ') || 'Brak danych' }}
              </p>
            </div>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <button
              @click.stop="openEditModal(client)"
              class="p-1.5 rounded-md text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors"
            >
              <AppIcon name="pencil" class="w-3.5 h-3.5" />
            </button>
            <AppIcon
              name="chevron-down"
              class="w-4 h-4 text-slate-400 transition-transform duration-200"
              :class="expandedClient === client.id ? 'rotate-180' : ''"
            />
          </div>
        </div>

        <!-- Expanded: details + notes -->
        <div v-if="expandedClient === client.id" class="border-t border-slate-100 px-4 py-4 bg-slate-50">
          <!-- Contact info -->
          <div class="grid grid-cols-2 gap-3 mb-4 text-xs text-slate-600">
            <div v-if="client.phone" class="flex items-center gap-1.5">
              <AppIcon name="phone" class="w-3.5 h-3.5 text-slate-400" />
              {{ client.phone }}
            </div>
            <div v-if="client.email" class="flex items-center gap-1.5 truncate">
              <AppIcon name="envelope" class="w-3.5 h-3.5 text-slate-400" />
              {{ client.email }}
            </div>
            <div v-if="client.address_line1" class="col-span-2 flex items-center gap-1.5">
              <AppIcon name="map-pin" class="w-3.5 h-3.5 text-slate-400 shrink-0" />
              {{ [client.address_line1, client.postal_code, client.city].filter(Boolean).join(', ') }}
            </div>
          </div>

          <!-- Notes section -->
          <div>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Notatki</p>

            <!-- Add note input -->
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
              >
                Dodaj
              </button>
            </div>

            <!-- Notes list -->
            <div v-if="loadingNotes[client.id]" class="text-xs text-slate-400 py-2">Ładowanie...</div>
            <div v-else-if="!clientNotes[client.id]?.length" class="text-xs text-slate-400 py-1">Brak notatek.</div>
            <div v-else class="space-y-2">
              <div
                v-for="note in clientNotes[client.id]"
                :key="note.id"
                class="bg-white border border-slate-200 rounded-lg px-3 py-2 flex justify-between gap-2"
              >
                <div class="min-w-0">
                  <p class="text-sm text-slate-700 break-words">{{ note.content }}</p>
                  <p class="text-[10px] text-slate-400 mt-0.5">
                    {{ note.author?.name }} · {{ formatDate(note.created_at) }}
                  </p>
                </div>
                <button @click="deleteNote(client.id, note.id)" class="text-slate-300 hover:text-red-400 transition-colors shrink-0">
                  <AppIcon name="trash" class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="lastPage > 1" class="flex justify-center gap-2 mt-6">
      <button
        v-for="p in lastPage"
        :key="p"
        @click="page = p; fetchClients()"
        class="w-8 h-8 rounded-lg text-sm font-medium transition-colors"
        :class="page === p ? 'text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'"
        :style="page === p ? 'background: linear-gradient(135deg, #001f3d, #003366)' : ''"
      >{{ p }}</button>
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
