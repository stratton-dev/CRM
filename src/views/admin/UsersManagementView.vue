<script setup lang="ts">
import { computed, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useStructureStore } from '@/stores/structure'
import { useFinanceStore } from '@/stores/finance'
import { useSessionStore } from '@/stores/session'
import { useToastStore } from '@/stores/toast'
import TabHeader from '@/components/ui/TabHeader.vue'
import AppIcon from '@/components/AppIcon.vue'
import type { User, UserRole } from '@/types/models'

const opiekunRoles = ['SALES', 'MANAGER', 'DIRECTOR', 'ADMIN']
const ALL_ROLES: { value: UserRole; label: string }[] = [
  { value: 'SALES',     label: 'Handlowiec' },
  { value: 'MANAGER',   label: 'Menadżer' },
  { value: 'DIRECTOR',  label: 'Dyrektor' },
  { value: 'ADMIN',     label: 'Admin' },
  { value: 'LEADOWIEC', label: 'Leadowiec' },
  { value: 'CLIENT_HR', label: 'Klient HR' },
]

const ROLE_BADGE: Record<string, string> = {
  ADMIN:     'bg-stratton-gold/10 text-amber-700 border-stratton-gold/30',
  DIRECTOR:  'bg-indigo-50 text-indigo-700 border-indigo-200',
  MANAGER:   'bg-sky-50 text-sky-700 border-sky-200',
  SALES:     'bg-emerald-50 text-emerald-700 border-emerald-200',
  LEADOWIEC: 'bg-violet-50 text-violet-700 border-violet-200',
  CLIENT_HR: 'bg-slate-100 text-slate-600 border-slate-200',
}

const structure = useStructureStore()
const finance = useFinanceStore()
const session = useSessionStore()
const toast = useToastStore()

const { currentUser } = storeToRefs(session)
const { users } = storeToRefs(structure)
const { commissionConfig } = storeToRefs(finance)

const userSearchQuery = ref('')
const roleFilter = ref<string>('ALL')
const selectedUserForEdit = ref<User | null>(null)
const config = ref({ ...commissionConfig.value })
const isCreating = ref(false)
const isSaving = ref(false)
const isDeleting = ref(false)

interface NewUserDraft {
  name: string
  email: string
  phone: string
  role: UserRole
  parentSupabaseId: string | null
  password: string
  sendPasswordReset: boolean
}

const blankDraft = (): NewUserDraft => ({
  name: '',
  email: '',
  phone: '',
  role: 'SALES',
  parentSupabaseId: null,
  password: '',
  sendPasswordReset: true,
})

const newUser = ref<NewUserDraft>(blankDraft())

const isAdmin = computed(() => currentUser.value?.role === 'ADMIN')

const filteredUsers = computed(() => {
  const query = userSearchQuery.value.toLowerCase().trim()
  const userList = Array.isArray(users.value) ? users.value : []
  return userList.filter((user) => {
    if (roleFilter.value !== 'ALL' && (user.role || '') !== roleFilter.value) return false
    if (!query) return true
    return (
      user.name.toLowerCase().includes(query)
      || user.email.toLowerCase().includes(query)
      || (user.role || '').toLowerCase().includes(query)
    )
  })
})

const opiekunList = computed(() => {
  const userList = Array.isArray(users.value) ? users.value : []
  return userList.filter((u) => opiekunRoles.includes(u.role ?? ''))
})

const roleCounts = computed(() => {
  const userList = Array.isArray(users.value) ? users.value : []
  const counts: Record<string, number> = { ALL: userList.length }
  for (const u of userList) counts[u.role || 'UNKNOWN'] = (counts[u.role || 'UNKNOWN'] || 0) + 1
  return counts
})

const initialsOf = (name?: string) => {
  if (!name) return '??'
  const parts = name.trim().split(/\s+/)
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
}

const openAddUser = () => {
  newUser.value = blankDraft()
  isCreating.value = true
}

const closeAddUser = () => {
  isCreating.value = false
}

const submitNewUser = async () => {
  const me = currentUser.value
  if (!me) return
  const draft = newUser.value
  if (!draft.name.trim() || !draft.email.trim()) {
    toast.error('Imię i email są wymagane.')
    return
  }
  if (draft.password && draft.password.length < 8) {
    toast.error('Hasło musi mieć min. 8 znaków.')
    return
  }
  isSaving.value = true
  try {
    const created = await structure.addUser(
      {
        name: draft.name.trim(),
        email: draft.email.trim().toLowerCase(),
        phone: draft.phone.trim() || undefined,
        role: draft.role,
        parentSupabaseId: draft.parentSupabaseId,
        password: draft.password || undefined,
        sendPasswordReset: draft.sendPasswordReset,
      } as any,
      me.id,
    )
    if ((created as any)?.inviteSent === false && (created as any)?.inviteError) {
      toast.error(`Utworzono, ale reset hasła nie poszedł: ${(created as any).inviteError}`)
    } else if ((created as any)?.supabaseUserCreated === false) {
      toast.success('Użytkownik dodany (DB only — Supabase admin nieskonfigurowany).')
    } else {
      toast.success(`Dodano ${draft.name}.`)
    }
    closeAddUser()
  } catch (e: any) {
    const msg = e?.response?.data?.message || e?.message || 'Nie udało się dodać użytkownika.'
    toast.error(msg)
  } finally {
    isSaving.value = false
  }
}

const editUser = (user: User) => {
  selectedUserForEdit.value = { ...user }
}

const closeEditPanel = () => {
  selectedUserForEdit.value = null
}

const toggleBlock = async (user: User) => {
  const me = currentUser.value
  if (me) {
    await structure.toggleBlockUser(user, me.id)
    toast.success(user.isBlocked ? `Odblokowano ${user.name}.` : `Zablokowano ${user.name}.`)
  }
}

const deleteUserConfirm = async (user: User) => {
  if (!isAdmin.value) {
    toast.error('Tylko admin może usuwać użytkowników.')
    return
  }
  if (!confirm(`Czy na pewno usunąć użytkownika ${user.name} (${user.email})? Operacja nieodwracalna — konto Supabase + dane CRM zostaną usunięte.`)) return
  isDeleting.value = true
  try {
    const res = await structure.deleteUserAdmin(user)
    if (res?.supabaseError) {
      toast.error(`Usunięto z DB, ale Supabase odrzuciło: ${res.supabaseError}`)
    } else {
      toast.success(`Usunięto ${user.name}.`)
    }
  } catch (e: any) {
    const msg = e?.response?.data?.message || e?.message || 'Nie udało się usunąć.'
    toast.error(msg)
  } finally {
    isDeleting.value = false
  }
}

const sendResetFor = async (user: User) => {
  try {
    const res = await structure.sendPasswordReset(user)
    if (res?.sent && res.actionLink) {
      await navigator.clipboard.writeText(res.actionLink).catch(() => undefined)
      toast.success(`Link resetu hasła wygenerowany (skopiowany do schowka).`)
    } else {
      toast.info('Wysłano żądanie resetu hasła.')
    }
  } catch (e: any) {
    const msg = e?.response?.data?.message || e?.message || 'Reset hasła nie powiódł się.'
    toast.error(msg)
  }
}

const saveUser = async () => {
  const me = currentUser.value
  const userToSave = selectedUserForEdit.value
  if (userToSave && me) {
    isSaving.value = true
    try {
      await structure.updateUserAdmin(userToSave.id, userToSave, me.id)
      toast.success('Zaktualizowano dane użytkownika.')
      closeEditPanel()
    } catch (e: any) {
      toast.error(e?.response?.data?.message || e?.message || 'Błąd zapisu.')
    } finally {
      isSaving.value = false
    }
  }
}

const saveConfig = () => {
  finance.updateCommissionConfig(config.value)
  toast.success('Konfiguracja zapisana.')
}
</script>

<template>
  <div class="flex flex-col h-[calc(100vh-112px)]">

    <TabHeader icon="users" title="Zarządzanie Użytkownikami">
      <template #actions>
        <div class="flex items-center gap-2 flex-wrap">
          <select
            v-model="roleFilter"
            class="px-3 py-1.5 text-sm font-medium bg-white border border-slate-200 rounded-lg focus:outline-none focus:border-stratton-gold cursor-pointer"
          >
            <option value="ALL">Wszystkie role ({{ roleCounts.ALL || 0 }})</option>
            <option v-for="r in ALL_ROLES" :key="r.value" :value="r.value">
              {{ r.label }} ({{ roleCounts[r.value] || 0 }})
            </option>
          </select>
          <div class="relative">
            <input
              v-model="userSearchQuery"
              type="text"
              placeholder="Szukaj (imię, email, rola)..."
              class="pl-8 pr-3 py-1.5 w-64 text-sm bg-white border border-slate-200 rounded-lg focus:outline-none focus:border-stratton-gold"
            />
            <AppIcon name="search" class="w-4 h-4 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2" />
          </div>
          <button
            v-if="isAdmin"
            type="button"
            class="flex items-center gap-2 px-3 py-1.5 text-sm font-bold text-white bg-primary hover:bg-primary-dark rounded-lg shadow-sm transition-colors"
            @click="openAddUser"
          >
            <AppIcon name="plus" class="w-4 h-4" />
            <span>Dodaj użytkownika</span>
          </button>
        </div>
      </template>
    </TabHeader>

    <div class="flex-1 overflow-y-auto p-4 md:p-6">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6">

        <!-- USERS TABLE -->
        <div class="lg:col-span-2 bg-white rounded-card shadow-card border border-slate-100 overflow-hidden">
          <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
            <div>
              <h3 class="font-bold text-slate-800">Użytkownicy Systemu</h3>
              <p class="text-xs text-slate-500 mt-0.5">{{ filteredUsers.length }} z {{ roleCounts.ALL || 0 }} kont</p>
            </div>
          </div>
          <div class="max-h-[640px] overflow-y-auto">
            <table class="w-full divide-y divide-slate-100">
              <thead class="bg-slate-50 sticky top-0 z-10">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Użytkownik</th>
                  <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Rola</th>
                  <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Status</th>
                  <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Akcje</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="u in filteredUsers" :key="u.id" class="hover:bg-slate-50/60 transition">
                  <td class="px-6 py-3">
                    <div class="flex items-center gap-3">
                      <div class="w-9 h-9 rounded-full bg-linear-to-br from-slate-700 to-slate-900 text-white flex items-center justify-center text-xs font-bold shadow-sm">
                        {{ initialsOf(u.name) }}
                      </div>
                      <div>
                        <div class="text-sm font-bold text-slate-900">{{ u.name }}</div>
                        <div class="text-xs text-slate-500">{{ u.email }}</div>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-3">
                    <span
                      class="px-2.5 py-1 inline-flex text-xs leading-5 font-bold rounded-full border"
                      :class="ROLE_BADGE[u.role || ''] || 'bg-slate-100 text-slate-600 border-slate-200'"
                    >
                      {{ u.role || '—' }}
                    </span>
                  </td>
                  <td class="px-6 py-3">
                    <span v-if="u.isBlocked" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-bold bg-red-50 text-red-700 border border-red-200">
                      <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                      Zablokowany
                    </span>
                    <span v-else class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                      <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                      Aktywny
                    </span>
                  </td>
                  <td class="px-6 py-3 text-right text-sm font-medium whitespace-nowrap">
                    <button
                      type="button"
                      class="text-slate-500 hover:text-slate-900 mr-2 p-1.5 hover:bg-slate-100 rounded"
                      title="Wyślij reset hasła"
                      @click="sendResetFor(u)"
                    >
                      <AppIcon name="key" class="w-4 h-4" />
                    </button>
                    <button
                      type="button"
                      class="text-indigo-600 hover:text-indigo-900 mr-2 font-bold text-xs"
                      @click="editUser(u)"
                    >
                      Edytuj
                    </button>
                    <button
                      v-if="u.isBlocked"
                      type="button"
                      class="text-emerald-600 hover:text-emerald-900 font-bold text-xs"
                      @click="toggleBlock(u)"
                    >
                      Odblokuj
                    </button>
                    <button
                      v-else
                      type="button"
                      class="text-amber-600 hover:text-amber-900 font-bold text-xs"
                      @click="toggleBlock(u)"
                    >
                      Zablokuj
                    </button>
                    <button
                      v-if="isAdmin && u.id !== currentUser?.id"
                      type="button"
                      class="text-red-500 hover:text-red-700 font-bold text-xs ml-2"
                      :disabled="isDeleting"
                      @click="deleteUserConfirm(u)"
                    >
                      Usuń
                    </button>
                  </td>
                </tr>
                <tr v-if="filteredUsers.length === 0">
                  <td colspan="4" class="px-6 py-10 text-center text-sm text-slate-400">
                    Brak użytkowników pasujących do filtrów.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- COMMISSION CONFIG -->
        <div class="bg-white rounded-card shadow-card border border-slate-100 h-fit">
          <div class="px-6 py-4 border-b border-slate-100">
            <h3 class="font-bold text-slate-800">Konfiguracja Prowizji</h3>
            <p class="text-xs text-slate-500 mt-0.5">Globalne stawki dla handlowców</p>
          </div>
          <div class="p-6 space-y-4">
            <div>
              <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Prowizja Handlowca (Umowa &lt;= 14 dni)</label>
              <div class="flex items-center">
                <input v-model.number="config.salesCommissionFirstMonthLt14" type="number" step="0.01" class="flex-1 border border-slate-300 p-2.5 rounded-lg text-sm bg-white focus:outline-none focus:border-stratton-gold" />
                <span class="ml-2 text-xs font-bold text-slate-500 uppercase">% (dziesiętnie)</span>
              </div>
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Prowizja Handlowca (Umowa &gt; 14 dni)</label>
              <input v-model.number="config.salesCommissionFirstMonthGt14" type="number" step="0.01" class="w-full border border-slate-300 p-2.5 rounded-lg text-sm bg-white focus:outline-none focus:border-stratton-gold" />
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Prowizja Odnowieniowa (2 msc+)</label>
              <input v-model.number="config.salesCommissionRenewal" type="number" step="0.01" class="w-full border border-slate-300 p-2.5 rounded-lg text-sm bg-white focus:outline-none focus:border-stratton-gold" />
            </div>
            <button type="button" class="w-full bg-slate-900 text-white py-2.5 rounded-xl hover:bg-slate-800 text-sm font-bold mt-4 shadow-sm transition" @click="saveConfig">
              Zapisz Konfigurację
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ADD USER MODAL -->
    <div v-if="isCreating" class="fixed inset-0 z-50 flex items-start justify-end">
      <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="closeAddUser"></div>
      <div class="relative h-full w-full max-w-md bg-white shadow-2xl flex flex-col animate-slide-in-right border-l border-slate-200">
        <div class="p-6 bg-white border-b border-slate-100 shrink-0">
          <div class="flex justify-between items-start">
            <div>
              <h3 class="text-xl font-bold text-slate-900">Nowy Użytkownik</h3>
              <p class="text-sm text-slate-500 font-medium">Konto Supabase + rekord w CRM</p>
            </div>
            <button type="button" class="p-2 text-slate-400 hover:bg-slate-100 rounded-full transition" @click="closeAddUser">
              <AppIcon name="x" class="w-5 h-5" />
            </button>
          </div>
        </div>

        <div class="flex-1 overflow-y-auto p-6 space-y-5">
          <div class="space-y-4">
            <div>
              <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Imię i Nazwisko *</label>
              <input v-model="newUser.name" placeholder="Np. Jan Kowalski" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:border-stratton-gold" />
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Email *</label>
              <input v-model="newUser.email" type="email" placeholder="user@stratton-prime.pl" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:border-stratton-gold" />
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Telefon</label>
              <input v-model="newUser.phone" placeholder="+48 ..." class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:border-stratton-gold" />
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Rola *</label>
              <select v-model="newUser.role" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:border-stratton-gold cursor-pointer bg-white">
                <option v-for="r in ALL_ROLES" :key="r.value" :value="r.value">{{ r.label }}</option>
              </select>
            </div>
            <div v-if="newUser.role !== 'ADMIN' && newUser.role !== 'CLIENT_HR'">
              <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Przełożony (opcjonalnie)</label>
              <select v-model="newUser.parentSupabaseId" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:border-stratton-gold cursor-pointer bg-white">
                <option :value="null">— brak —</option>
                <option v-for="op in opiekunList" :key="op.id" :value="op.id">
                  {{ op.name }} ({{ op.role }})
                </option>
              </select>
            </div>
          </div>

          <div class="border-t border-slate-100 pt-5 space-y-4">
            <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider">Hasło</h4>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-2">Hasło tymczasowe (pozostaw puste = auto)</label>
              <input v-model="newUser.password" type="text" placeholder="min. 8 znaków" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm font-mono focus:outline-none focus:border-stratton-gold" />
            </div>
            <label class="flex items-start gap-2 cursor-pointer">
              <input v-model="newUser.sendPasswordReset" type="checkbox" class="mt-1 w-4 h-4 rounded border-slate-300 text-stratton-gold focus:ring-stratton-gold" />
              <span class="text-xs text-slate-600">Wygeneruj link resetu hasła (do przekazania użytkownikowi)</span>
            </label>
          </div>
        </div>

        <div class="p-6 bg-white border-t border-slate-100 shrink-0 flex justify-end gap-3">
          <button type="button" class="px-6 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-600 hover:bg-slate-50 transition" @click="closeAddUser">
            Anuluj
          </button>
          <button
            type="button"
            class="px-6 py-2.5 bg-stratton-gold text-white rounded-xl text-sm font-bold hover:bg-yellow-600 shadow-sm transition disabled:opacity-60"
            :disabled="isSaving"
            @click="submitNewUser"
          >
            {{ isSaving ? 'Tworzenie...' : 'Utwórz Użytkownika' }}
          </button>
        </div>
      </div>
    </div>

    <!-- EDIT USER MODAL -->
    <div v-if="selectedUserForEdit" class="fixed inset-0 z-50 flex items-start justify-end">
      <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="closeEditPanel"></div>
      <div class="relative h-full w-full max-w-md bg-white shadow-2xl flex flex-col animate-slide-in-right border-l border-slate-200">
        <div class="p-6 bg-white border-b border-slate-100 shrink-0">
          <div class="flex justify-between items-start">
            <div>
              <h3 class="text-xl font-bold text-slate-900">Edytuj Użytkownika</h3>
              <p class="text-sm text-slate-500 font-medium">{{ selectedUserForEdit.email }}</p>
            </div>
            <button type="button" class="p-2 text-slate-400 hover:bg-slate-100 rounded-full transition" @click="closeEditPanel">
              <AppIcon name="x" class="w-5 h-5" />
            </button>
          </div>
        </div>

        <div class="flex-1 overflow-y-auto p-6 space-y-5">
          <div class="space-y-4">
            <div>
              <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Imię i Nazwisko</label>
              <input v-model="selectedUserForEdit.name" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:border-stratton-gold" />
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Rola</label>
              <select v-model="selectedUserForEdit.role" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:border-stratton-gold cursor-pointer bg-white">
                <option v-for="r in ALL_ROLES" :key="r.value" :value="r.value">{{ r.label }}</option>
              </select>
            </div>

            <template v-if="selectedUserForEdit.role === 'LEADOWIEC'">
              <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Przypisany Opiekun</label>
                <select
                  v-model="selectedUserForEdit.leadowiecOpiekunId"
                  class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:border-stratton-gold cursor-pointer bg-white"
                >
                  <option :value="null">— brak opiekuna —</option>
                  <option v-for="op in opiekunList" :key="op.id" :value="Number(op.id)">
                    {{ op.name }} ({{ op.role }})
                  </option>
                </select>
                <p class="text-xs text-slate-400 mt-1">Handlowiec, menedżer lub dyrektor obsługujący leadowca.</p>
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Stawka prowizji (%)</label>
                <input
                  v-model.number="selectedUserForEdit.leadowiecCommissionRate"
                  type="number"
                  min="0"
                  max="1"
                  step="0.001"
                  placeholder="0.030 = 3%"
                  class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:border-stratton-gold"
                />
                <p class="text-xs text-slate-400 mt-1">Wartość dziesiętna: 0.030 = 3%, 0.05 = 5%.</p>
              </div>
            </template>

            <div>
              <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Telefon</label>
              <input v-model="selectedUserForEdit.phone" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:border-stratton-gold" />
            </div>
          </div>

          <div class="border-t border-slate-100 pt-4">
            <button
              type="button"
              class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-slate-50 border border-slate-200 hover:bg-slate-100 rounded-lg text-sm font-bold text-slate-700 transition"
              @click="sendResetFor(selectedUserForEdit)"
            >
              <AppIcon name="key" class="w-4 h-4" />
              Wyślij link resetu hasła
            </button>
          </div>
        </div>

        <div class="p-6 bg-white border-t border-slate-100 shrink-0 flex justify-end gap-3">
          <button type="button" class="px-6 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-600 hover:bg-slate-50 transition" @click="closeEditPanel">Anuluj</button>
          <button
            type="button"
            class="px-6 py-2.5 bg-stratton-gold text-white rounded-xl text-sm font-bold hover:bg-yellow-600 shadow-sm transition disabled:opacity-60"
            :disabled="isSaving"
            @click="saveUser"
          >
            {{ isSaving ? 'Zapisywanie...' : 'Zapisz Zmiany' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.animate-slide-in-right {
  animation: slideInRight 0.3s ease-out;
}

@keyframes slideInRight {
  from { transform: translateX(100%); opacity: 0; }
  to { transform: translateX(0); opacity: 1; }
}
</style>
