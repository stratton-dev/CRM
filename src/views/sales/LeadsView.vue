<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useLeadStore } from '@/stores/lead'
import type { Lead } from '@/stores/lead'
import { useStructureStore } from '@/stores/structure'
import { useSessionStore } from '@/stores/session'
import { useToastStore } from '@/stores/toast'
import AppIcon from '@/components/AppIcon.vue'
import TabHeader from '@/components/ui/TabHeader.vue'
import * as ExcelJS from 'exceljs'

// Stores
const router = useRouter()
const leadStore = useLeadStore()
const structureStore = useStructureStore()
const sessionStore = useSessionStore()
const toast = useToastStore()

const { leads, loading } = storeToRefs(leadStore)
const { currentUser } = storeToRefs(sessionStore)
const { users: structureUsers } = storeToRefs(structureStore)

// State
const searchQuery = ref('')
const showAddModal = ref(false)
const showImportModal = ref(false)
const showQualifyModal = ref(false)
const selectedLead = ref<Lead | null>(null)
const qualifyNote = ref('')
const dragOver = ref(false)

const isImporting = ref(false)
const importProgress = ref(0) // 0-100
const importFile = ref<File | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)

// Form for single lead
const newLeadForm = ref({
  nip: '',
  name: '', // companyName
  street: '',
  postal_code: '',
  city: '',
  contactDetails: '', // Unified field input
  contactPerson: '',
  assignedTo: '' // User ID
})

// Validation for UI Permissions
const canAddLeads = computed(() => {
  if (!currentUser.value) return false;
  const subordinates = structureStore.getSubtreeUserIds(currentUser.value.id).filter(id => id !== currentUser.value.id);
  // Manager or Admin or anyone with subs can add leads manually
  return subordinates.length > 0 || ['ADMIN', 'DIRECTOR', 'MANAGER'].includes(currentUser.value.role);
})

const mySubordinates = computed(() => {
  if (!currentUser.value) return [];
  const subIds = structureStore.getSubtreeUserIds(currentUser.value.id).filter(id => id !== currentUser.value.id);
  // Also include self if needed? Usually adding lead assigns to subordinate.
  const users = (structureUsers.value || []).filter(u => subIds.includes(u.id));
  return users;
})

// Filtered Leads
const filteredLeads = computed(() => {
    let list = leads.value || [];
    
    // Filter out converted ones if not already filtered by API
    list = list.filter(l => l.status !== 'converted');

    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(l => 
            l.name.toLowerCase().includes(q) || 
            (l.nip || '').includes(q) || 
            (l.contact_person || '').toLowerCase().includes(q)
        );
    }
    
    return list;
})

const getStatusLabel = (status: string) => {
    const map: Record<string, string> = {
        'new': 'Nowy',
        'processing': 'W trakcie',
        'qualified': 'Zakwalifikowany',
        'rejected': 'Odrzucony',
        'converted': 'Przekonwertowany'
    };
    return map[status] || status;
}

const getStatusClass = (status: string) => {
    const map: Record<string, string> = {
        'new': 'bg-blue-100 text-blue-800',
        'processing': 'bg-yellow-100 text-yellow-800',
        'qualified': 'bg-green-100 text-green-800',
        'rejected': 'bg-red-100 text-red-800',
        'converted': 'bg-gray-100 text-gray-800'
    };
    return map[status] || 'bg-gray-100 text-gray-800';
}

// Actions
const openAddModal = () => {
  newLeadForm.value = { nip: '', name: '', contactDetails: '', contactPerson: '', assignedTo: currentUser.value ? String(currentUser.value.id) : '' }
  showAddModal.value = true
}

const openQualifyModal = (lead: Lead) => {
    selectedLead.value = lead;
    qualifyNote.value = '';
    showQualifyModal.value = true;
}

const handleQualify = async () => {
    if (!selectedLead.value || !qualifyNote.value.trim()) {
        toast.warning('Wpisz notatkę z rozmowy');
        return;
    }
    
    try {
        await leadStore.qualifyLead(selectedLead.value.id, qualifyNote.value);
        showQualifyModal.value = false;
        toast.success('Lead zakwalifikowany');
    } catch (e) {
        // handled in store
    }
}

const handleConvert = async (lead: Lead) => {
    if (!confirm(`Czy na pewno chcesz przenieść firmę ${lead.name} do etapu Spotkania?`)) return;
    
    try {
        await leadStore.convertLead(lead.id);
        // Optionally redirect to meetings view?
        // router.push('/app/meetings'); 
    } catch (e) {
        // handled in store
    }
}

const addSingleLead = async () => {
  if (!newLeadForm.value.nip || !newLeadForm.value.name || !newLeadForm.value.contactDetails) {
     toast.error('Wypełnij wymagane pola (Nazwa, NIP, Adres, Kontakt).')
     // Allow saving without explicit address validation if desired, but user asked for address columns.
     // Let's assume address is important now.
  }

  try {
     const isEmail = newLeadForm.value.contactDetails.includes('@');
     
     await leadStore.createLead({
         name: newLeadForm.value.name,
         nip: newLeadForm.value.nip,
         street: newLeadForm.value.street,
         postal_code: newLeadForm.value.postal_code,
         city: newLeadForm.value.city,
         email: isEmail ? newLeadForm.value.contactDetails : undefined,
         phone: !isEmail ? newLeadForm.value.contactDetails : undefined,
         contact_person: newLeadForm.value.contactPerson,
         user_id: newLeadForm.value.assignedTo ? Number(newLeadForm.value.assignedTo) : undefined,
         status: 'new',
         source: 'Ręczne dodanie'
     });
     
     showAddModal.value = false;
  } catch (e: any) {
      // error handled in store
  }
}

// Import
const handleFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        importFile.value = target.files[0];
    }
}
const handleDrop = (e: DragEvent) => {
    dragOver.value = false;
    const files = e.dataTransfer?.files;
    if (files && files.length > 0) importFile.value = files[0];
}

const downloadTemplate = async () => {
    const workbook = new ExcelJS.Workbook();
    const sheet = workbook.addWorksheet('Szablon Leadów');
    sheet.columns = [
        { header: 'NIP', key: 'nip', width: 15 },
        { header: 'Nazwa Firmy', key: 'name', width: 30 },
        { header: 'Ulica', key: 'street', width: 25 },
        { header: 'Kod pocztowy', key: 'postal_code', width: 12 },
        { header: 'Miasto', key: 'city', width: 20 },
        { header: 'Email/Telefon', key: 'contact', width: 25 }, // Keeping contact as it's vital for CRM
        { header: 'Osoba Kontaktowa', key: 'person', width: 20 },
        { header: 'Notatki', key: 'notes', width: 40 }
    ];
    sheet.addRow({ 
        nip: '1234567890', 
        name: 'Firma Testowa', 
        street: 'ul. Prosta 1', 
        postal_code: '00-001', 
        city: 'Warszawa', 
        contact: 'biuro@firma.pl', 
        person: 'Jan Kowalski', 
        notes: 'Zainteresowani' 
    });
    
    const buffer = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'Szablon_Importu.xlsx';
    a.click();
    URL.revokeObjectURL(url);
}

const runImport = async () => {
    if (!importFile.value) return;
    isImporting.value = true;
    importProgress.value = 0;
    
    try {
        const buffer = await importFile.value.arrayBuffer();
        const workbook = new ExcelJS.Workbook();
        await workbook.xlsx.load(buffer);
        const worksheet = workbook.getWorksheet(1);
        if (!worksheet) throw new Error('Pusty arkusz');
        
        const rows: any[] = [];
        worksheet.eachRow((row, rowNumber) => {
            if (rowNumber === 1) return;
            // Map columns based on new template structure
            rows.push({
                nip: row.getCell(1).text,
                name: row.getCell(2).text,
                street: row.getCell(3).text,
                postal_code: row.getCell(4).text,
                city: row.getCell(5).text,
                contact: row.getCell(6).text,
                person: row.getCell(7).text,
                notes: row.getCell(8).text
            });
        });
        
        let i = 0;
        for (const r of rows) {
            if (!r.nip || !r.name) continue;
            const isEmail = r.contact && r.contact.includes('@');
            try {
                await leadStore.createLead({
                    name: r.name,
                    nip: r.nip.replace(/[^0-9]/g, ''),
                    street: r.street,
                    postal_code: r.postal_code,
                    city: r.city,
                    email: isEmail ? r.contact : undefined,
                    phone: !isEmail ? r.contact : undefined,
                    contact_person: r.person,
                    notes: r.notes,
                    status: 'new',
                    source: 'Import'
                });
            } catch (ignore) {}
            i++;
            importProgress.value = Math.round((i / rows.length) * 100);
        }
        toast.success(`Zaimportowano ${i} leadów.`);
        showImportModal.value = false;
        importFile.value = null;
    } catch (e: any) {
        toast.error('Błąd importu: ' + e.message);
    } finally {
        isImporting.value = false;
    }
}

onMounted(() => {
    leadStore.fetchLeads();
})
</script>

<template>
  <div class="flex flex-col h-[calc(100vh-112px)]">

    <TabHeader icon="user-group" title="Leady Sprzedażowe">
      <template #actions>
        <div class="flex items-center space-x-2">
          <button v-if="canAddLeads" type="button" class="flex items-center px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-100 rounded-lg border border-slate-200 bg-white font-medium transition-colors" @click="showImportModal = true">
            <AppIcon name="arrow-down-tray" class="w-4 h-4 mr-1.5 text-indigo-500" />
            <span>Importuj</span>
          </button>
          <button v-if="canAddLeads" type="button" class="flex items-center px-3 py-1.5 text-sm text-white bg-indigo-600 hover:bg-indigo-500 rounded-lg font-bold shadow-sm transition-colors" @click="openAddModal">
            <AppIcon name="plus" class="w-4 h-4 mr-1.5" />
            <span>Dodaj Leada</span>
          </button>
        </div>

        <div class="w-full md:w-96 relative">
          <AppIcon name="search" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 w-5 h-5 pointer-events-none" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Szukaj po nazwie firmy, NIPie lub kontakcie..."
            class="w-full border-slate-200 rounded-lg text-sm pl-10 pr-4 py-2.5 focus:ring-2 focus:ring-stratton-gold/20 focus:border-stratton-gold bg-white text-slate-800 shadow-sm text-right font-bold transition-all placeholder-slate-400"
          />
        </div>
      </template>
    </TabHeader>

    <div class="flex-1 overflow-y-auto p-4 md:p-6">

    <!-- Table -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        <!-- Mobile cards -->
        <div class="md:hidden divide-y divide-slate-100">
          <div v-for="lead in filteredLeads" :key="lead.id" class="p-4">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <div class="font-bold text-slate-800 truncate">{{ lead.name }}</div>
                <div class="text-xs text-slate-500 mt-0.5 flex items-center gap-1">
                  <AppIcon name="user" class="w-3 h-3 shrink-0" />
                  {{ lead.contact_person || 'Brak kontaktu' }}
                </div>
              </div>
              <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold uppercase shrink-0', getStatusClass(lead.status)]">
                {{ getStatusLabel(lead.status) }}
              </span>
            </div>
            <div class="mt-2 flex items-center justify-between">
              <span class="font-mono text-slate-500 text-xs bg-slate-100 px-2 py-0.5 rounded">{{ lead.nip }}</span>
              <div class="flex items-center gap-1">
                <button v-if="['new', 'processing'].includes(lead.status)" @click="openQualifyModal(lead)" class="p-2 text-indigo-600 hover:bg-indigo-50 rounded-xl min-w-9 min-h-9 flex items-center justify-center">
                  <AppIcon name="phone" class="w-4 h-4" />
                </button>
                <button v-if="lead.status === 'qualified'" @click="handleConvert(lead)" class="p-2 text-emerald-600 hover:bg-emerald-50 rounded-xl min-w-9 min-h-9 flex items-center justify-center">
                  <AppIcon name="arrow-right-circle" class="w-4 h-4" />
                </button>
                <button @click="leadStore.deleteLead(lead.id)" class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-xl min-w-9 min-h-9 flex items-center justify-center">
                  <AppIcon name="trash" class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
          <div v-if="filteredLeads.length === 0" class="p-8 text-center text-slate-400 text-sm">Brak wyników</div>
        </div>
        <!-- Desktop table -->
        <div class="overflow-x-auto hidden md:block">
            <table class="w-full text-left">
              <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                  <th class="px-8 py-5 text-xs font-bold text-slate-500 uppercase tracking-wider">Firma</th>
                  <th class="px-6 py-5 text-xs font-bold text-slate-500 uppercase tracking-wider">NIP</th>
                  <th class="px-6 py-5 text-xs font-bold text-slate-500 uppercase tracking-wider">Adres</th>
                  <th class="px-6 py-5 text-xs font-bold text-slate-500 uppercase tracking-wider">Status</th>
                  <th class="px-8 py-5 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Akcje</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="lead in filteredLeads" :key="lead.id" class="hover:bg-slate-50/80 transition-colors group">
                  <td class="px-8 py-5">
                    <div class="font-bold text-slate-800 text-base mb-1">{{ lead.name }}</div>
                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 text-xs font-medium">
                        <AppIcon name="user" class="w-3 h-3" />
                        {{ lead.contact_person || 'Brak osoby kontaktowej' }}
                    </div>
                  </td>
                  <td class="px-6 py-5">
                     <span class="font-mono text-slate-600 bg-slate-100 px-2 py-1 rounded text-sm">{{ lead.nip }}</span>
                  </td>
                  <td class="px-6 py-5">
                    <div class="flex flex-col gap-1 text-sm text-slate-700">
                        <div v-if="lead.street || lead.city">
                           <span v-if="lead.street" class="block font-medium">{{ lead.street }}</span>
                           <span v-if="lead.postal_code || lead.city" class="block text-slate-500">
                               {{ lead.postal_code }} {{ lead.city }}
                           </span>
                        </div>
                         <div v-else class="text-slate-400 italic">Brak adresu</div>
                    </div>
                  </td>
                  <td class="px-6 py-5">
                     <span :class="['inline-flex items-center px-3 py-1 rounded-full text-xs font-bold tracking-wide uppercase', getStatusClass(lead.status)]">
                       <span class="w-1.5 h-1.5 rounded-full bg-current mr-2 opacity-60"></span>
                       {{ getStatusLabel(lead.status) }}
                     </span>
                  </td>
                  <td class="px-8 py-5 text-right">
                     <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-all duration-200 transform translate-x-2 group-hover:translate-x-0">
                         <!-- Actions -->
                         <button v-if="['new', 'processing'].includes(lead.status)" @click="openQualifyModal(lead)" class="p-2 text-indigo-600 hover:bg-indigo-50 rounded-xl transition-colors tooltip" title="Wykonaj telefon / Notatka">
                            <AppIcon name="phone" class="w-5 h-5" />
                         </button>
                         
                         <button v-if="lead.status === 'qualified'" @click="handleConvert(lead)" class="p-2 text-emerald-600 hover:bg-emerald-50 rounded-xl transition-colors tooltip" title="Przenieś do Spotkań (Etap 2)">
                            <AppIcon name="arrow-right-circle" class="w-5 h-5" />
                         </button>

                         <button @click="leadStore.deleteLead(lead.id)" class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-colors tooltip" title="Usuń trwale">
                             <AppIcon name="trash" class="w-5 h-5" />
                         </button>
                     </div>
                  </td>
                </tr>
                <tr v-if="filteredLeads.length === 0">
                   <td colspan="5" class="px-8 py-16 text-center">
                       <div class="flex flex-col items-center justify-center max-w-sm mx-auto">
                           <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mb-4">
                               <AppIcon name="search" class="w-8 h-8 text-slate-300" />
                           </div>
                           <h3 class="text-slate-900 font-bold text-lg mb-1">Brak wyników</h3>
                           <p class="text-slate-500 text-center">Nie znaleziono żadnych leadów spełniających kryteria wyszukiwania.</p>
                       </div>
                   </td>
                </tr>
              </tbody>
            </table>
        </div>
    </div>
    </div>

    <!-- Add Modal -->
    <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-end md:items-center justify-center bg-slate-900/40 backdrop-blur-sm p-0 md:p-4 animate-fade-in">
        <div class="bg-white rounded-t-3xl md:rounded-3xl shadow-2xl w-full md:max-w-2xl overflow-hidden transform transition-all scale-100 max-h-[90vh] overflow-y-auto">
            <div class="px-8 py-6 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-bold text-slate-900">Dodaj Nowego Leada</h2>
                    <p class="text-slate-500 text-sm mt-1">Wprowadź podstawowe dane firmy do wstępnej weryfikacji</p>
                </div>
                <button @click="showAddModal = false" class="p-2 rounded-full hover:bg-slate-200/50 text-slate-400 transition-colors">
                    <AppIcon name="x-mark" class="w-6 h-6" />
                </button>
            </div>
            
            <div class="p-8 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                       <label class="block text-sm font-bold text-slate-700">Nazwa Firmy <span class="text-red-500">*</span></label>
                       <input v-model="newLeadForm.name" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all outline-none" placeholder="Np. Stratton Prime Sp. z o.o." />
                    </div>
                    <div class="space-y-2">
                       <label class="block text-sm font-bold text-slate-700">NIP <span class="text-red-500">*</span></label>
                       <input v-model="newLeadForm.nip" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all outline-none font-mono" placeholder="0000000000" />
                    </div>
                </div>

                <div class="space-y-2">
                   <label class="block text-sm font-bold text-slate-700">Adres Firmy <span class="text-red-500">*</span></label>
                   <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                       <input v-model="newLeadForm.street" class="md:col-span-3 w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all outline-none" placeholder="Ulica i numer" />
                       <input v-model="newLeadForm.postal_code" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all outline-none" placeholder="Kod pocztowy" />
                       <input v-model="newLeadForm.city" class="md:col-span-2 w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all outline-none" placeholder="Miejscowość" />
                   </div>
                </div>
                
                <div class="space-y-2">
                   <label class="block text-sm font-bold text-slate-700">Dane Kontaktowe (Email/Tel)</label>
                   <input v-model="newLeadForm.contactDetails" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all outline-none" placeholder="biuro@firma.pl lub +48 123 456 789" />
                </div>
                
                 <div class="space-y-2">
                   <label class="block text-sm font-bold text-slate-700">Osoba Kontaktowa</label>
                   <input v-model="newLeadForm.contactPerson" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all outline-none" placeholder="Imię i Nazwisko" />
                </div>
            </div>
            
            <div class="px-8 py-6 bg-slate-50 border-t border-slate-100 flex justify-end gap-3">
                <button @click="showAddModal = false" class="px-6 py-3 text-slate-600 font-bold hover:bg-slate-200 rounded-xl transition-colors">Anuluj</button>
                <button @click="addSingleLead" class="px-6 py-3 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 shadow-lg shadow-indigo-600/20 transition-all transform hover:-translate-y-0.5">Dodaj Leada</button>
            </div>
        </div>
    </div>

    <!-- Qualify Modal -->
    <div v-if="showQualifyModal" class="fixed inset-0 z-50 flex items-end md:items-center justify-center bg-slate-900/40 backdrop-blur-sm p-0 md:p-4 animate-fade-in">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden">
             <div class="px-8 py-6 border-b border-slate-100 bg-emerald-50/50 flex items-center gap-4">
                <div class="w-12 h-12 bg-emerald-100 rounded-2xl flex items-center justify-center text-emerald-600">
                    <AppIcon name="phone" class="w-6 h-6" />
                </div>
                <div>
                    <h2 class="text-xl font-bold text-slate-900">Kwalifikacja Leada</h2>
                    <p class="text-slate-500 text-sm">Zarejestruj kontakt z klientem</p>
                </div>
            </div>
            
            <div class="p-8">
                 <label class="block text-sm font-bold text-slate-700 mb-2">Notatka z rozmowy</label>
                <textarea v-model="qualifyNote" rows="5" class="w-full p-4 bg-slate-50 border border-slate-200 rounded-2xl focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all outline-none resize-none" placeholder="Wpisz przebieg rozmowy, ustalenia, zainteresowanie ofertą..."></textarea>
            </div>

            <div class="px-8 py-6 bg-slate-50 border-t border-slate-100 flex justify-end gap-3">
                <button @click="showQualifyModal = false" class="px-6 py-3 text-slate-600 font-bold hover:bg-slate-200 rounded-xl transition-colors">Anuluj</button>
                <button @click="handleQualify" class="px-6 py-3 bg-emerald-600 text-white font-bold rounded-xl hover:bg-emerald-700 shadow-lg shadow-emerald-600/20 transition-all transform hover:-translate-y-0.5">Zapisz i Zakwalifikuj</button>
            </div>
        </div>
    </div>
    
     <!-- Import Modal -->
    <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4 animate-fade-in">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-xl overflow-hidden relative">
             <button @click="showImportModal = false" class="absolute top-4 right-4 p-2 rounded-full hover:bg-slate-100 text-slate-400 transition-colors z-10">
                <AppIcon name="x-mark" class="w-6 h-6" />
            </button>
            
             <div class="p-5 md:p-10 text-center">
                 <div class="w-16 md:w-20 h-16 md:h-20 bg-indigo-50 text-indigo-600 rounded-3xl flex items-center justify-center mx-auto mb-4 md:mb-6 shadow-sm">
                     <AppIcon name="arrow-down-tray" class="w-8 md:w-10 h-8 md:h-10" />
                 </div>
                 <h3 class="text-xl md:text-2xl font-bold text-slate-900 mb-2">Import masowy</h3>
                 <p class="text-sm text-slate-500 mb-4 md:mb-8 max-w-xs mx-auto">Przeciągnij i upuść plik Excel (.xlsx) lub wybierz go z dysku.</p>
            
                 <div 
                    class="border-3 border-dashed rounded-3xl p-10 text-center cursor-pointer relative transition-all duration-300 group"
                    :class="dragOver ? 'border-indigo-500 bg-indigo-50/50 scale-[1.02]' : 'border-slate-200 hover:border-indigo-400 hover:bg-slate-50'"
                    @dragover.prevent="dragOver = true"
                    @dragleave.prevent="dragOver = false"
                    @drop.prevent="handleDrop"
                    @click="fileInput?.click()"
                >
                    <input ref="fileInput" type="file" @change="handleFileChange" class="hidden" accept=".xlsx, .xls" />
                    
                    <div v-if="!importFile" class="pointer-events-none">
                       <AppIcon name="cloud-arrow-up" class="w-12 h-12 text-slate-300 mx-auto mb-4 group-hover:text-indigo-500 transition-colors" />
                       <p class="text-slate-900 font-bold text-lg">Kliknij lub upuść plik</p>
                       <p class="text-slate-400 text-sm mt-1">Maksymalny rozmiar: 10MB</p>
                    </div>
                     <div v-else class="pointer-events-none">
                       <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-2xl flex items-center justify-center mx-auto mb-4">
                           <AppIcon name="document-text" class="w-8 h-8" />
                       </div>
                       <p class="text-slate-900 font-bold text-lg truncate px-4">{{ importFile.name }}</p>
                       <p class="text-slate-500 text-sm mt-1">{{ (importFile.size / 1024).toFixed(0) }} KB</p>
                    </div>
                </div>

                <div v-if="isImporting" class="mt-8 space-y-2">
                   <div class="flex justify-between text-xs font-bold text-slate-500 uppercase tracking-wider">
                       <span>Postęp importu</span>
                       <span>{{ importProgress }}%</span>
                   </div>
                   <div class="w-full bg-slate-100 rounded-full h-3 overflow-hidden">
                      <div class="bg-indigo-600 h-full rounded-full transition-all duration-300 ease-out" :style="{ width: importProgress + '%' }"></div>
                   </div>
                </div>
                
                <div class="flex justify-center mt-8">
                    <button @click="downloadTemplate" class="text-sm font-bold text-indigo-600 hover:text-indigo-700 hover:underline flex items-center gap-2 group">
                        <AppIcon name="document-arrow-down" class="w-4 h-4" />
                        Pobierz szablon Excel
                    </button>
                </div>
            </div>
            
            <div class="px-8 py-6 bg-slate-50 border-t border-slate-100 flex justify-end gap-3">
                 <button @click="showImportModal = false" class="px-6 py-3 text-slate-600 font-bold hover:bg-slate-200 rounded-xl transition-colors">Anuluj</button>
                 <button :disabled="!importFile || isImporting" @click="runImport" class="px-6 py-3 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed shadow-lg shadow-indigo-600/20 transition-all transform hover:-translate-y-0.5">
                    {{ isImporting ? 'Przetwarzanie...' : 'Rozpocznij Import' }}
                 </button>
            </div>
        </div>
    </div>
  </div>
</template>
