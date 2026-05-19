<script setup lang="ts">
import { computed, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useStructureStore } from '@/stores/structure'
import { useFinanceStore } from '@/stores/finance'
import { useSessionStore } from '@/stores/session'
import { useToastStore } from '@/stores/toast'
import type { User } from '@/types/models'

const opiekunRoles = ['SALES', 'MANAGER', 'DIRECTOR', 'ADMIN']

const structure = useStructureStore()
const finance = useFinanceStore()
const session = useSessionStore()
const toast = useToastStore()

const { currentUser } = storeToRefs(session)
const { users } = storeToRefs(structure)
const { commissionConfig } = storeToRefs(finance)

const userSearchQuery = ref('')
const selectedUserForEdit = ref<User | null>(null)
const config = ref({ ...commissionConfig.value })

const filteredUsers = computed(() => {
  const query = userSearchQuery.value.toLowerCase()
  const userList = Array.isArray(users.value) ? users.value : []
  if (!query) return userList
  return userList.filter((user) => user.name.toLowerCase().includes(query) || user.email.toLowerCase().includes(query))
})

const opiekunList = computed(() => {
  const userList = Array.isArray(users.value) ? users.value : []
  return userList.filter((u) => opiekunRoles.includes(u.role ?? ''))
})

const editUser = (user: User) => {
  selectedUserForEdit.value = { ...user }
}

const closeEditPanel = () => {
  selectedUserForEdit.value = null
}

const toggleBlock = async (user: User) => {
  const me = currentUser.value
  if (me) await structure.toggleBlockUser(user, me.id)
}

const saveUser = async () => {
  const me = currentUser.value
  const userToSave = selectedUserForEdit.value
  if (userToSave && me) {
    await structure.updateUserAdmin(userToSave.id, userToSave, me.id)
    closeEditPanel()
  }
}

const saveConfig = () => {
  finance.updateCommissionConfig(config.value)
  toast.success('Konfiguracja zapisana.')
}
</script>

<template>
  <div class="space-y-3 md:space-y-6 animate-fade-in view-transition">
    <div class="flex items-center justify-between mb-2 md:mb-4">
       <h1 class="text-xl md:text-2xl font-bold text-slate-900">Zarządzanie Użytkownikami</h1>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6">
      <div class="lg:col-span-2 crm-card">
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
          <h3 class="font-bold text-slate-700">Użytkownicy Systemu</h3>
          <input v-model="userSearchQuery" type="text" placeholder="Filtruj użytkowników..." class="w-full md:w-64 crm-input" />
        </div>
        <div class="max-h-[600px] overflow-y-auto">
          <table class="crm-table divide-y divide-slate-200">
            <thead class="crm-table-head">
              <tr>
                <th class="crm-table-th crm-table-th-xs">Użytkownik</th>
                <th class="crm-table-th crm-table-th-xs">Rola</th>
                <th class="crm-table-th crm-table-th-xs text-right">Akcje</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
              <tr v-for="u in filteredUsers" :key="u.id" class="hover:bg-slate-50 transition">
                <td class="crm-table-td whitespace-nowrap">
                  <div class="text-sm font-bold text-slate-900">{{ u.name }}</div>
                  <div class="text-xs text-slate-500">{{ u.email }}</div>
                </td>
                <td class="crm-table-td whitespace-nowrap text-sm text-slate-500">
                  <span class="px-2.5 py-1 inline-flex text-xs leading-5 font-bold rounded-full bg-slate-100 text-slate-600 border border-slate-200">{{ u.role }}</span>
                </td>
                <td class="crm-table-td whitespace-nowrap text-right text-sm font-medium">
                  <button type="button" class="text-indigo-600 hover:text-indigo-900 mr-3 font-bold" @click="editUser(u)">Edytuj</button>
                  <button v-if="u.isBlocked" type="button" class="text-green-600 hover:text-green-900 font-bold" @click="toggleBlock(u)">Odblokuj</button>
                  <button v-else type="button" class="text-red-500 hover:text-red-700 font-bold" @click="toggleBlock(u)">Zablokuj</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-sm border border-slate-200 h-fit">
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
          <h3 class="font-bold text-slate-700">Konfiguracja Prowizji (Globalna)</h3>
        </div>
        <div class="p-4 md:p-6 space-y-4">
          <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Prowizja Handlowca (Umowa &lt;= 14 dni)</label>
            <div class="flex items-center">
              <input v-model.number="config.salesCommissionFirstMonthLt14" type="number" step="0.01" class="flex-1 border border-slate-300 p-2.5 rounded-lg text-sm bg-white focus:outline-none focus:border-stratton-gold" />
              <span class="ml-2 text-xs font-bold text-slate-500 uppercase">% (dziesiętnie)</span>
            </div>
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Prowizja Handlowca (Umowa &gt; 14 dni)</label>
            <div class="flex items-center">
              <input v-model.number="config.salesCommissionFirstMonthGt14" type="number" step="0.01" class="flex-1 border border-slate-300 p-2.5 rounded-lg text-sm bg-white focus:outline-none focus:border-stratton-gold" />
            </div>
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Prowizja Odnowieniowa (2 msc+)</label>
            <div class="flex items-center">
              <input v-model.number="config.salesCommissionRenewal" type="number" step="0.01" class="flex-1 border border-slate-300 p-2.5 rounded-lg text-sm bg-white focus:outline-none focus:border-stratton-gold" />
            </div>
          </div>
          <button type="button" class="w-full bg-slate-900 text-white py-2.5 rounded-xl hover:bg-slate-800 text-sm font-bold mt-4 shadow-sm transition" @click="saveConfig">
            Zapisz Konfigurację
          </button>
        </div>
      </div>
    </div>

    <!-- Edit Modal -->
    <div v-if="selectedUserForEdit" class="fixed inset-0 z-50 flex items-start justify-end">
      <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="closeEditPanel"></div>
      <div class="relative h-full w-full max-w-md bg-white shadow-2xl flex flex-col animate-slide-in-right border-l border-slate-200">
        <div class="p-6 bg-white border-b border-slate-100 flex-shrink-0">
          <div class="flex justify-between items-start">
            <div>
              <h3 class="text-xl font-bold text-slate-900">Edytuj Użytkownika</h3>
              <p class="text-sm text-slate-500 font-medium">{{ selectedUserForEdit.email }}</p>
            </div>
            <button type="button" class="p-2 text-slate-400 hover:bg-slate-100 rounded-full transition" @click="closeEditPanel">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
          </div>
        </div>

        <div class="flex-1 overflow-y-auto p-4 md:p-8 space-y-4 md:space-y-6">
          <div class="bg-slate-50/50 p-4 md:p-6 border border-slate-200 rounded-xl space-y-4 md:space-y-5">
            <div>
              <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Imię i Nazwisko</label>
              <input v-model="selectedUserForEdit.name" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:border-stratton-gold" />
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Rola</label>
              <select v-model="selectedUserForEdit.role" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:border-stratton-gold cursor-pointer bg-white">
                <option value="SALES">Handlowiec</option>
                <option value="MANAGER">Menadżer</option>
                <option value="DIRECTOR">Dyrektor</option>
                <option value="ADMIN">Admin</option>
                <option value="CLIENT_HR">Klient HR</option>
                <option value="LEADOWIEC">Leadowiec</option>
              </select>
            </div>

            <!-- Opiekun dropdown — only for LEADOWIEC -->
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
        </div>

        <div class="p-6 bg-white border-t border-slate-100 flex-shrink-0 flex justify-end gap-3">
          <button type="button" class="px-6 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-600 hover:bg-slate-50 transition" @click="closeEditPanel">Anuluj</button>
          <button type="button" class="px-6 py-2.5 bg-stratton-gold text-white rounded-xl text-sm font-bold hover:bg-yellow-600 shadow-sm transition" @click="saveUser">Zapisz Zmiany</button>
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
