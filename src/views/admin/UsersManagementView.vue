<script setup lang="ts">
import { computed, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useStructureStore } from '@/stores/structure'
import { useSessionStore } from '@/stores/session'
import { useToastStore } from '@/stores/toast'
import TabHeader from '@/components/ui/TabHeader.vue'
import AppIcon from '@/components/AppIcon.vue'
import { RouterLink } from 'vue-router'
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
const session = useSessionStore()
const toast = useToastStore()

const { currentUser } = storeToRefs(session)
const { users } = storeToRefs(structure)

const userSearchQuery = ref('')
const roleFilter = ref<string>('ALL')
const selectedUserForEdit = ref<User | null>(null)
const editPasswordOverride = ref<string>('')
const editPasswordOriginal = ref<string>('')
// Opcja A — pozycja w strukturze sterowana rolą. Trzymamy wybranego przełożonego oraz
// rolę/rodzica sprzed edycji, by wiedzieć kiedy faktycznie przepiąć węzeł.
const editParentSupabaseId = ref<string | null>(null)
const editOriginalRole = ref<string>('')
const editOriginalParent = ref<string | null>(null)

// Jaki przełożony jest dozwolony pod daną (nową) rolą.
const PARENT_ROLES: Record<string, string[]> = {
  MANAGER: ['DIRECTOR'],
  SALES: ['MANAGER'],
}
const isCreating = ref(false)
const isSaving = ref(false)
const isDeleting = ref(false)

interface NewUserDraft {
  firstName: string
  lastName: string
  email: string
  phone: string
  role: UserRole
  parentSupabaseId: string | null
  password: string
  sendPasswordReset: boolean
}

const blankDraft = (): NewUserDraft => ({
  firstName: '',
  lastName: '',
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

// Rola wybrana w panelu edycji + reguły pozycji w strukturze.
const editRole = computed(() => selectedUserForEdit.value?.role ?? '')
const isRootRole = computed(() => editRole.value === 'DIRECTOR')
const requiresParent = computed(() => editRole.value in PARENT_ROLES)
const parentOptions = computed(() => {
  const allow = PARENT_ROLES[editRole.value]
  if (!allow) return []
  const selfId = selectedUserForEdit.value?.id
  const userList = Array.isArray(users.value) ? users.value : []
  return userList.filter(
    (u) => allow.includes(u.role ?? '') && !u.isRemovedFromStructure && u.id !== selfId,
  )
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
  const firstName = draft.firstName.trim()
  const lastName = draft.lastName.trim()
  const email = draft.email.trim().toLowerCase()
  const phone = draft.phone.trim()
  if (!firstName || !lastName) {
    toast.error('Imię i nazwisko są wymagane.')
    return
  }
  if (!email) {
    toast.error('Email jest wymagany (jest jednocześnie loginem).')
    return
  }
  if (!phone) {
    toast.error('Numer telefonu jest wymagany.')
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
        firstName,
        lastName,
        name: `${firstName} ${lastName}`,
        email,
        phone,
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
      toast.success(`Dodano ${firstName} ${lastName}.`)
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
  // Hydrate firstName/lastName from existing name when individual fields are missing
  let firstName = user.firstName ?? ''
  let lastName = user.lastName ?? ''
  if (!firstName && !lastName && user.name) {
    const parts = user.name.trim().split(/\s+/)
    firstName = parts[0] || ''
    lastName = parts.slice(1).join(' ') || ''
  }
  selectedUserForEdit.value = { ...user, firstName, lastName }
  // Pole "Hasło" pokazuje aktualne (jawne) hasło z bazy; edycja = ustawienie nowego.
  editPasswordOverride.value = (user as any).plainPassword || ''
  editPasswordOriginal.value = editPasswordOverride.value
  editParentSupabaseId.value = user.parentSupabaseId ?? null
  editOriginalRole.value = user.role ?? ''
  editOriginalParent.value = user.parentSupabaseId ?? null
}

const closeEditPanel = () => {
  selectedUserForEdit.value = null
  editPasswordOverride.value = ''
  editPasswordOriginal.value = ''
  editParentSupabaseId.value = null
  editOriginalRole.value = ''
  editOriginalParent.value = null
}

const copyPassword = async () => {
  if (!editPasswordOverride.value) return
  try {
    await navigator.clipboard.writeText(editPasswordOverride.value)
    toast.success('Skopiowano hasło.')
  } catch {
    toast.error('Nie udało się skopiować.')
  }
}

const generatePassword = () => {
  const words = ['Sokol', 'Orzel', 'Lampart', 'Tygrys', 'Delfin', 'Kondor', 'Pantera', 'Rekin', 'Jaguar', 'Bizon', 'Gepard', 'Wilk', 'Feniks', 'Diament', 'Granit', 'Bursztyn', 'Szafir', 'Kobalt', 'Tytan']
  const w = words[Math.floor(Math.random() * words.length)]
  const d = String(Math.floor(Math.random() * 10000)).padStart(4, '0')
  editPasswordOverride.value = `${w}-${d}`
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
    const firstName = (userToSave.firstName ?? '').trim()
    const lastName = (userToSave.lastName ?? '').trim()
    const email = (userToSave.email ?? '').trim().toLowerCase()
    const phone = (userToSave.phone ?? '').trim()
    if (!firstName || !lastName) {
      toast.error('Imię i nazwisko są wymagane.')
      return
    }
    if (!email) {
      toast.error('Email jest wymagany (login).')
      return
    }
    if (!phone) {
      toast.error('Numer telefonu jest wymagany.')
      return
    }
    const passwordChanged = editPasswordOverride.value !== editPasswordOriginal.value
    if (passwordChanged && editPasswordOverride.value && editPasswordOverride.value.length < 8) {
      toast.error('Hasło musi mieć min. 8 znaków.')
      return
    }
    // Opcja A — wyznacz nową pozycję w strukturze na podstawie (nowej) roli.
    // Przepinamy węzeł tylko gdy zmieniła się rola LUB ręcznie zmieniono przełożonego,
    // żeby zwykła edycja nazwiska/telefonu nie ruszała struktury.
    const role = userToSave.role ?? ''
    const roleChanged = role !== editOriginalRole.value
    const parentChanged = (editParentSupabaseId.value ?? null) !== (editOriginalParent.value ?? null)
    let newParent: string | null | undefined = undefined
    if (role === 'DIRECTOR' || role === 'ADMIN' || role === 'CLIENT_HR') {
      // Szczyt struktury / poza drzewem — bez przełożonego.
      if (roleChanged) newParent = null
    } else if (role === 'MANAGER' || role === 'SALES') {
      if (roleChanged || parentChanged) {
        if (!editParentSupabaseId.value) {
          toast.error(role === 'MANAGER' ? 'Wybierz dyrektora jako przełożonego.' : 'Wybierz menadżera jako przełożonego.')
          return
        }
        newParent = editParentSupabaseId.value
      }
    }
    // LEADOWIEC i brak zmian → pozycji nie ruszamy (newParent = undefined).

    isSaving.value = true
    try {
      const partial: any = {
        ...userToSave,
        firstName,
        lastName,
        name: `${firstName} ${lastName}`,
        email,
        phone,
      }
      // Wyślij hasło tylko gdy zmienione względem aktualnego (pole jest prefillowane).
      if (passwordChanged && editPasswordOverride.value) partial.password = editPasswordOverride.value
      // Pozycję w strukturze wysyłamy TYLKO gdy faktycznie ma się zmienić.
      delete partial.parentSupabaseId
      if (newParent !== undefined) partial.parentSupabaseId = newParent
      // Self-rate (override_commission_rate) and per-relacja override-y
      // ustawiane teraz w widoku Struktura (CommissionChain section).
      // Tu nie wysyłamy overrideCommissionRate żeby przypadkiem nie nadpisać.
      delete partial.overrideCommissionRate
      await structure.updateUserAdmin(userToSave.id, partial, me.id)
      const msg = editPasswordOverride.value
        ? 'Zaktualizowano dane użytkownika + hasło ustawione w Supabase.'
        : 'Zaktualizowano dane użytkownika.'
      toast.success(msg)
      closeEditPanel()
    } catch (e: any) {
      toast.error(e?.response?.data?.message || e?.message || 'Błąd zapisu.')
    } finally {
      isSaving.value = false
    }
  }
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
      <div>

        <!-- USERS TABLE -->
        <div class="bg-white rounded-card shadow-card border border-slate-100 overflow-hidden">
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
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Imię *</label>
                <input v-model="newUser.firstName" placeholder="Jan" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:border-stratton-gold" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Nazwisko *</label>
                <input v-model="newUser.lastName" placeholder="Kowalski" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:border-stratton-gold" />
              </div>
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Email * <span class="normal-case font-medium text-slate-400">(login do CRM)</span></label>
              <input v-model="newUser.email" type="email" placeholder="user@stratton-prime.pl" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:border-stratton-gold" />
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Telefon *</label>
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
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Imię *</label>
                <input v-model="selectedUserForEdit.firstName" placeholder="Jan" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:border-stratton-gold" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Nazwisko *</label>
                <input v-model="selectedUserForEdit.lastName" placeholder="Kowalski" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:border-stratton-gold" />
              </div>
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Email * <span class="normal-case font-medium text-slate-400">(login do CRM)</span></label>
              <input v-model="selectedUserForEdit.email" type="email" placeholder="user@stratton-prime.pl" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:border-stratton-gold" />
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Rola</label>
              <select v-model="selectedUserForEdit.role" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:border-stratton-gold cursor-pointer bg-white">
                <option v-for="r in ALL_ROLES" :key="r.value" :value="r.value">{{ r.label }}</option>
              </select>
              <p v-if="selectedUserForEdit.role !== editOriginalRole" class="text-xs text-stratton-gold font-semibold mt-1">
                Zmiana roli przestawi też pozycję w strukturze.
              </p>
            </div>

            <!-- Opcja A: pozycja w strukturze sterowana rolą -->
            <div v-if="isRootRole" class="bg-indigo-50 border border-indigo-200 rounded-lg p-3 text-xs text-indigo-700 flex items-start gap-2">
              <AppIcon name="info" class="w-4 h-4 mt-0.5 shrink-0" />
              <span>Dyrektor staje na szczycie struktury — bez przełożonego.</span>
            </div>
            <div v-else-if="requiresParent">
              <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">
                Przełożony * <span class="normal-case font-medium text-slate-400">({{ selectedUserForEdit.role === 'MANAGER' ? 'dyrektor' : 'menadżer' }})</span>
              </label>
              <select v-model="editParentSupabaseId" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:border-stratton-gold cursor-pointer bg-white">
                <option :value="null">— wybierz przełożonego —</option>
                <option v-for="p in parentOptions" :key="p.id" :value="p.id">{{ p.name }} ({{ p.role }})</option>
              </select>
              <p v-if="parentOptions.length === 0" class="text-xs text-amber-600 mt-1">
                Brak dostępnych {{ selectedUserForEdit.role === 'MANAGER' ? 'dyrektorów' : 'menadżerów' }} w strukturze.
              </p>
            </div>

            <template v-if="selectedUserForEdit.role === 'LEADOWIEC'">
              <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Przypisany Opiekun</label>
                <select
                  v-model="selectedUserForEdit.leadowiecOpiekunId"
                  class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:border-stratton-gold cursor-pointer bg-white"
                >
                  <option :value="null">— brak opiekuna —</option>
                  <option v-for="op in opiekunList" :key="op.id" :value="(op as any).dbId">
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
              <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Telefon *</label>
              <input v-model="selectedUserForEdit.phone" placeholder="+48 ..." class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:border-stratton-gold" />
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Hasło</label>
              <div class="flex gap-2">
                <input
                  v-model="editPasswordOverride"
                  type="text"
                  placeholder="hasło logowania do CRM"
                  class="flex-1 border border-slate-300 rounded-lg p-2.5 text-sm font-mono focus:outline-none focus:border-stratton-gold"
                />
                <button type="button" @click="copyPassword" class="px-3 text-xs font-bold border border-slate-300 rounded-lg text-slate-600 hover:bg-slate-50 transition">Kopiuj</button>
                <button type="button" @click="generatePassword" class="px-3 text-xs font-bold border border-slate-300 rounded-lg text-slate-600 hover:bg-slate-50 transition">Generuj</button>
              </div>
              <p class="text-xs text-slate-400 mt-1">Aktualne hasło logowania do CRM (czytane z bazy). Edycja zapisuje nowe hasło w Supabase Auth. Min. 8 znaków.</p>
            </div>

            <div class="bg-slate-50 border border-slate-200 rounded-lg p-4">
              <p class="text-xs text-slate-600 leading-relaxed flex items-start gap-2">
                <AppIcon name="info" class="w-4 h-4 mt-0.5 shrink-0 text-slate-400" />
                <span>
                  <strong class="text-slate-700">Prowizje override</strong> ustawiasz teraz w widoku
                  <RouterLink to="/app/structure" class="text-stratton-gold font-bold hover:underline">Struktura</RouterLink> —
                  rozwiń tego użytkownika i wpisz indywidualne stawki dla każdego z jego przełożonych.
                </span>
              </p>
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
