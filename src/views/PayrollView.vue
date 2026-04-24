<template>
  <div class="view-transition pb-10 space-y-4">
    <!-- Header -->
    <div class="w-full pt-2">
      <div class="bg-surface-dark rounded-card shadow-card-hover border border-slate-800 p-8 mb-6 flex flex-col md:flex-row justify-between items-center gap-6">
        <div class="flex items-center gap-6 self-start md:self-center">
            <button type="button" class="inline-flex items-center justify-center w-12 h-12 bg-slate-800 border border-slate-700 rounded-xl text-slate-400 hover:bg-slate-700 hover:text-white transition-all shadow-sm group" @click="router.back()">
              <AppIcon name="arrow-left" class="w-5 h-5 transition-transform group-hover:-translate-x-1" />
            </button>
            <div>
              <h1 class="font-serif font-bold text-3xl text-white mb-2 tracking-tight">Lista Płac</h1>
              <p class="text-slate-400 text-sm">Importuj listy płac i generuj symulacje oszczędności</p>
            </div>
        </div>
        <div>
          <button
            @click="downloadTemplate"
            class="flex items-center gap-2 px-5 py-2.5 text-sm font-bold text-white bg-primary hover:bg-primary-dark rounded-xl transition-all shadow-lg shadow-primary/20"
          >
            <AppIcon name="download" class="w-5 h-5" />
            Pobierz szablon Excel
          </button>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="px-2">
      <div class="space-y-6">
        
        <!-- Client Selector -->
        <div class="bg-white dark:bg-slate-800 rounded-card shadow-card border border-slate-100 dark:border-slate-700 p-6">
          <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2 uppercase tracking-wide">Wybierz klienta</label>
          <select
            v-model="selectedClientId"
            class="w-full max-w-md px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white font-medium focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-shadow"
          >
            <option :value="null" disabled>Wybierz klienta z listy...</option>
            <option v-for="client in availableClients" :key="client.id" :value="client.id">
              {{ client.name }} (NIP: {{ client.nip }})
            </option>
          </select>
          <p v-if="!selectedClientId" class="mt-3 text-xs font-medium text-slate-500 flex items-center gap-2">
            <AppIcon name="info" class="w-4 h-4 text-primary" />
            Wybierz klienta, aby rozpocząć importowanie listy płac. Widoczni są tylko klienci z wygenerowaną ofertą (lub wyższy status).
          </p>
        </div>

        <div v-if="selectedClientId" class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Drop Zone 1: Active -->
          <div 
            class="relative group cursor-pointer"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="handleDrop"
            @click="triggerFileInput"
          >
            <input 
              type="file" 
              ref="fileInput" 
              class="hidden" 
              accept=".xlsx,.xls,.csv" 
              @change="handleFileSelect"
            />
            <div 
              class="h-64 border-2 border-dashed rounded-card flex flex-col items-center justify-center text-center p-8 transition-all duration-300"
              :class="[
                isDragging 
                  ? 'border-primary bg-primary/5 scale-[1.02]' 
                  : 'border-slate-300 dark:border-slate-600 hover:border-primary hover:bg-slate-50 dark:hover:bg-slate-800/50 bg-white dark:bg-slate-800'
              ]"
            >
              <div class="p-4 bg-slate-100 dark:bg-slate-700 rounded-full mb-4 text-primary group-hover:scale-110 transition-transform duration-300">
                <AppIcon name="file-excel" class="w-10 h-10" />
              </div>
              <h3 class="font-bold text-lg text-slate-800 dark:text-white mb-2">Importuj listę płac</h3>
              <p class="text-sm text-slate-500 dark:text-slate-400 mb-4 max-w-xs leading-relaxed">Przeciągnij plik Excel tutaj lub kliknij, aby wybrać z dysku</p>
              
              <span class="px-4 py-2 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-lg text-xs font-bold uppercase tracking-wide">
                Obsługiwane: .xlsx, .xls, .csv
              </span>
            </div>
            
            <!-- Uploading State Overlay -->
            <div v-if="isUploading" class="absolute inset-0 bg-white/90 dark:bg-slate-800/90 backdrop-blur-sm rounded-card flex flex-col items-center justify-center z-10 transition-all">
              <AppIcon name="refresh" class="w-10 h-10 text-primary animate-spin mb-4" />
              <span class="text-base font-bold text-slate-800 dark:text-white">Wysyłanie pliku...</span>
            </div>
          </div>

          <!-- Drop Zone 2: Inactive (OCR) -->
          <div class="h-64 border-2 border-dashed border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 rounded-card flex flex-col items-center justify-center text-center p-8 grayscale opacity-60 cursor-not-allowed">
            <div class="p-4 bg-slate-200 dark:bg-slate-800 rounded-full mb-4 text-slate-400">
              <AppIcon name="camera" class="w-10 h-10" />
            </div>
            <h3 class="font-bold text-lg text-slate-700 dark:text-slate-300 mb-2">OCR - Skanuj dokumenty</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-4 max-w-xs">Automatyczne rozpoznawanie danych ze zdjęć lub PDF</p>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-400">
              <AppIcon name="lock-closed" class="w-3 h-3 mr-1" />
              Wkrótce dostępne
            </span>
          </div>
        </div>


        <!-- File List (History) - Visible always (shows all or prompts selection) -->
        <div class="bg-white dark:bg-slate-800 rounded-card shadow-card border border-slate-100 dark:border-slate-700 overflow-hidden mb-8">
          <div class="bg-slate-50 border-b border-slate-100 p-2 flex items-center shadow-sm shrink-0">
          <div class="flex items-center gap-3 ml-4 flex-1">
            <AppIcon name="wallet" class="w-5 h-5 text-primary" />
            <div class="flex flex-col sm:flex-row sm:items-baseline sm:gap-4">
              <h3 class="font-bold text-slate-800 text-lg tracking-tight">Ilustracje oszczędności</h3>
              <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest mt-0.5">Wygenerowane z list płac</p>
            </div>
          </div>
          <div class="flex items-center gap-2 mr-4">
                <select v-model="sortBy" class="bg-white text-xs font-bold border border-slate-200 rounded-lg px-2 py-1.5 outline-none">
                    <option value="date_desc">Data: Najnowsze</option>
                    <option value="date_asc">Data: Najstarsze</option>
                    <option value="name_asc">Nazwa: A-Z</option>
                </select>
                <button @click="forceRefreshHistory" class="p-2 text-slate-400 hover:text-primary hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition-all" title="Odśwież listę">
                <AppIcon name="refresh" class="w-5 h-5" />
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm text-left">
            <thead class="text-xs text-slate-400 uppercase bg-slate-50 border-b border-slate-200 font-bold tracking-wider">
              <tr>
                <th class="px-4 py-4">Firma</th>
                <th class="px-4 py-4">Miesiąc</th>
                <th class="px-4 py-4 text-right">Pracownicy</th>
                <th class="px-4 py-4 text-right">Suma wynagrodzeń</th>
                <th class="px-4 py-4 text-right">Oszczędność (Szac.)</th>
                <th class="px-4 py-4">Status</th>
                <th class="px-4 py-4 text-right">Akcje</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-600">
               <!-- Mock Data Rows -->
              <tr class="hover:bg-slate-50/80 transition group">
                <td class="px-4 py-4 font-bold text-slate-800 relative text-sm">GF FOOD SPÓŁKA Z O.O.</td>
                <td class="px-4 py-4 text-slate-500 font-mono text-xs">2026-01</td>
                <td class="px-4 py-4 text-slate-700 font-medium text-right">45</td>
                <td class="px-4 py-4 text-slate-700 font-medium text-right">245 000 PLN</td>
                <td class="px-4 py-4 text-green-600 font-bold text-right">12 450 PLN</td>
                <td class="px-4 py-4"><span class="text-[10px] font-bold text-green-600 bg-green-50 border border-green-200 px-2 py-1 rounded uppercase">Zatwierdzone</span></td>
                <td class="px-4 py-4 text-right"><button class="text-primary hover:text-white font-bold text-xs bg-blue-50 hover:bg-primary px-3 py-1.5 rounded transition">Pobierz PDF</button></td>
              </tr>
              <tr class="hover:bg-slate-50/80 transition group">
                <td class="px-4 py-4 font-bold text-slate-800 relative text-sm">UNITED MACHINING POLAND</td>
                <td class="px-4 py-4 text-slate-500 font-mono text-xs">2026-02</td>
                <td class="px-4 py-4 text-slate-700 font-medium text-right">128</td>
                <td class="px-4 py-4 text-slate-700 font-medium text-right">680 000 PLN</td>
                <td class="px-4 py-4 text-green-600 font-bold text-right">34 000 PLN</td>
                 <td class="px-4 py-4"><span class="text-[10px] font-bold text-amber-600 bg-amber-50 border border-amber-200 px-2 py-1 rounded uppercase">W trakcie</span></td>
                <td class="px-4 py-4 text-right"><button class="text-primary hover:text-white font-bold text-xs bg-blue-50 hover:bg-primary px-3 py-1.5 rounded transition">Edytuj</button></td>
              </tr>
              <tr class="hover:bg-slate-50/80 transition group">
                <td class="px-4 py-4 font-bold text-slate-800 relative text-sm">ELITON SERVICE SP. Z O.O.</td>
                <td class="px-4 py-4 text-slate-500 font-mono text-xs">2026-02</td>
                <td class="px-4 py-4 text-slate-700 font-medium text-right">12</td>
                <td class="px-4 py-4 text-slate-700 font-medium text-right">68 000 PLN</td>
                <td class="px-4 py-4 text-green-600 font-bold text-right">3 400 PLN</td>
                 <td class="px-4 py-4"><span class="text-[10px] font-bold text-slate-500 bg-slate-50 border border-slate-200 px-2 py-1 rounded uppercase">Szkic</span></td>
                <td class="px-4 py-4 text-right"><button class="text-primary hover:text-white font-bold text-xs bg-blue-50 hover:bg-primary px-3 py-1.5 rounded transition">Edytuj</button></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useClientStore } from '@/stores/client';
import { useCalculatorStore } from '@/components/calculator/store/useCalculatorStore';
import { storeToRefs } from 'pinia';
import AppIcon from '@/components/AppIcon.vue';
import { api } from '@/api/client';
import { useRouter } from 'vue-router';
import { useToastStore } from '@/stores/toast';

const clientStore = useClientStore();
const calculatorStore = useCalculatorStore();
const router = useRouter();
const toast = useToastStore();

const { clients } = storeToRefs(clientStore);

const selectedClientId = ref<string | null>(null);
const files = ref<any[]>([]);
const allHistoryFiles = ref<any[]>([]); // Store for global history
const isDragging = ref(false);
const isUploading = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
const sortBy = ref<string>('date_desc');
const LAST_CLIENT_KEY = 'payroll_last_selected_client';


const sortedFiles = computed(() => {
    // Always show global history in the table, effectively making it a permanent log
    // We prioritize the extensive list over just the current client's list context in this specific table
    let sorted = [...allHistoryFiles.value];
    
    if (sortBy.value === 'date_desc') {
        sorted.sort((a, b) => new Date(b.created_at || new Date()).getTime() - new Date(a.created_at || new Date()).getTime());
    } else if (sortBy.value === 'date_asc') {
        sorted.sort((a, b) => new Date(a.created_at || new Date()).getTime() - new Date(b.created_at || new Date()).getTime());
    } else if (sortBy.value === 'name_asc') {
        sorted.sort((a, b) => (a.original_filename || '').localeCompare(b.original_filename || ''));
    }
    return sorted;
});

const toggleSort = (field: string) => {
    if (field === 'date') {
        sortBy.value = sortBy.value === 'date_desc' ? 'date_asc' : 'date_desc';
    }
};

const loadGlobalHistory = () => {
    // Collect all payroll histories from local storage
    const allFiles: any[] = [];
    // Load explicitly from known keys if possible, or iterate all
    for (let i = 0; i < localStorage.length; i++) {
        const key = localStorage.key(i);
        if (key && key.startsWith('payroll_history_')) {
             try {
                const stored = localStorage.getItem(key);
                if (stored) {
                    const parsed = JSON.parse(stored);
                    if (Array.isArray(parsed)) {
                        allFiles.push(...parsed);
                    }
                }
             } catch (e) {
                console.error('Error parsing history key', key, e);
             }
        }
    }
    
    // Sort allFiles immediately by date to ensure consistent "Global View" experience
    // De-duplicate by ID (prefer newest version if meaningful, but ID is enough)
    const uniqueMap = new Map();
    // Sort raw list descending so we keep the "latest" version in map if duplicates exist
    allFiles.sort((a,b) => (b.id || 0) - (a.id || 0));
    allFiles.forEach(f => {
        if (!uniqueMap.has(f.id)) uniqueMap.set(f.id, f);
    });
    
    allHistoryFiles.value = Array.from(uniqueMap.values());
};

const saveToLocalStorage = () => {
    if (!selectedClientId.value) return;
    localStorage.setItem(`payroll_history_${selectedClientId.value}`, JSON.stringify(files.value));
    // Also update global history immediately
    loadGlobalHistory();
};

const loadFromLocalStorage = () => {
    if (!selectedClientId.value) return;
    const stored = localStorage.getItem(`payroll_history_${selectedClientId.value}`);
    if (stored) {
        try {
            const parsed = JSON.parse(stored);
            const existingIds = new Set(files.value.map(f => f.id));
            parsed.forEach((f: any) => {
                if (!existingIds.has(f.id)) {
                    files.value.push(f);
                }
            });
        } catch (e) {
            console.error('Failed to parse local history for client', e);
        }
    }
};

const currentClient = computed(() => clients.value.find(c => c.id === selectedClientId.value));

const availableClients = computed(() => {
  return clients.value; 
});

onMounted(async () => {
    await clientStore.fetchClients();
    
    // Restore logic: Check if we had a previous client selected
    const lastClient = localStorage.getItem(LAST_CLIENT_KEY);
    if (lastClient) {
         // We don't strictly need the client object to exist in the store to select the ID,
         // but it's better for UI consistency.
         selectedClientId.value = lastClient;
    }
    
    // Always load global history on mount so the table isn't empty if no client selected
    loadGlobalHistory();
});

watch(selectedClientId, () => {
    if (selectedClientId.value) {
        localStorage.setItem(LAST_CLIENT_KEY, String(selectedClientId.value));
        fetchFiles();
    } else {
        localStorage.removeItem(LAST_CLIENT_KEY);
        files.value = [];
    }
    // Always refresh the global view just in case internal state changed
    loadGlobalHistory();
});

const forceRefreshHistory = () => {
    if (selectedClientId.value) {
        fetchFiles();
    } else {
        loadGlobalHistory();
    }
};

const fetchFiles = async () => {
    if (!selectedClientId.value) return;
    
    // Clear lists to rebuild them
    files.value = [];

    // 1. Load Local Storage FIRST (Immediate Feedback)
    loadFromLocalStorage();

    // 2. Try backend sync (background update)
    try {
        const response = await api.get('/v1/payroll-spreadsheets', {
            params: { client_id: selectedClientId.value }
        });
        if (response.data && Array.isArray(response.data.data)) {
             const existingIds = new Set(files.value.map(f => f.id));
             response.data.data.forEach((f: any) => {
                if (!existingIds.has(f.id)) {
                    files.value.push(f);
                }
            });
        }
    } catch (e) {
        console.warn('Backend fetch failed, using local only', e);
    }
    
    // Update global view with what we have
    loadGlobalHistory();
};

const downloadTemplate = () => {
    try {
        calculatorStore.generateImportTemplate(10);
        toast.success('Szablon został pobrany.');
    } catch (e) {
        toast.error('Błąd generowania szablonu.');
    }
};

const triggerFileInput = () => {
    fileInput.value?.click();
};

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        uploadFile(target.files[0]);
    }
};

const handleDrop = (event: DragEvent) => {
    isDragging.value = false;
    if (event.dataTransfer?.files.length) {
        uploadFile(event.dataTransfer.files[0]);
    }
};

const getClientName = (file: any) => {
    if (file.client && file.client.name) return file.client.name;
    if (file.client_name) return file.client_name;
    const idToCheck = file.client_id || selectedClientId.value;
    if (idToCheck) {
        const c = clients.value.find(c => c.id == idToCheck);
        if (c) return c.name;
    }
    return 'Klient';
};

const getClientNip = (file: any) => {
    if (file.client && file.client.nip) return file.client.nip;
    if (file.client_nip) return file.client_nip;
    const idToCheck = file.client_id || selectedClientId.value;
    if (idToCheck) {
        const c = clients.value.find(c => c.id == idToCheck);
        if (c) return c.nip;
    }
    return '-';
};

const uploadFile = async (file: File) => {
    if (!selectedClientId.value) {
        toast.warning('Wybierz klienta przed przesłaniem pliku.');
        return;
    }
    
    isUploading.value = true;
    const formData = new FormData();
    formData.append('file', file);
    formData.append('client_id', selectedClientId.value.toString());

    try {
        await api.post('/v1/payroll-spreadsheets', formData, {
            headers: { 'Content-Type': 'multipart/form-data' } 
        });
        toast.success('Plik został przesłany.');
        // After success, fetch to update list which updates local storage naturally
        await fetchFiles();
    } catch (e: any) {
        console.warn('Upload failed, using mock data for demo mechanics', e);
        
        const c = clients.value.find(cl => cl.id === selectedClientId.value);
        const mockFile = {
            id: Date.now(), // Generate unique ID for sorting
            client_id: selectedClientId.value,
            client: c ? { 
                name: c.name, 
                nip: c.nip,
                crm_profile: (c as any).crm_profile 
            } : null,
            client_name: c?.name || 'Unknown',
            client_nip: c?.nip,
            original_filename: file.name,
            created_at: new Date().toISOString(),
            status: 'UPLOADED'
        };
        
        // Push strictly to current view
        files.value.unshift(mockFile); // Add to top
        saveToLocalStorage(); // Persist upload and update global view immediately
        
        toast.success('Plik został przesłany (Mock).');
    } finally {
        isUploading.value = false;
        if (fileInput.value) fileInput.value.value = '';
    }
};

const processFile = async (file: any) => {
    // 1. Update UI object immediately
    file.status = 'PROCESSING';
    
    // 2. Sync with local source of truth for persistence
    const localFile = files.value.find(f => f.id === file.id);
    if (localFile) localFile.status = 'PROCESSING';

    saveToLocalStorage(); 
    toast.info('Przesyłanie listy płac do silnika obliczeniowego...');

    setTimeout(async () => {
        const resultName = 'Ilustracja_oszczędności_' + (file.client_name || 'Klient') + '.pdf';
        
        try {
            // Optimistic update
            file.status = 'GENERATED';
            file.result_filename = resultName;
            file.result_pdf_path = '/dummy-result.pdf';
            
            if (localFile) {
                localFile.status = 'GENERATED';
                localFile.result_filename = resultName;
                localFile.result_pdf_path = '/dummy-result.pdf';
            }

            toast.success('Obliczenia zakończone. Ilustracja oszczędności gotowa!');
        } catch (e) {
            console.error('Processing error', e);
            file.status = 'GENERATED'; // Fallback to success for demo
        }
        
        saveToLocalStorage(); // Persist final state
        loadGlobalHistory(); // Ensure global view is consistent
    }, 10000);
};

const downloadPdf = (file: any) => {
    if (file.result_pdf_path) {
         toast.info('Pobieranie pliku PDF (Mock)...');
    }
};

const downloadFile = (file: any) => {
     toast.info(`Pobieranie pliku źródłowego: ${file.original_filename} (Mock)`);
};

const sendToClient = (file: any) => {
    const client = currentClient.value;
    const subject = "Ilustracja oszczędności - " + (client?.name || 'Klient');
    const body = `Dzień dobry,\n\nprzesyłam przygotowaną analizę listy płac i symulację oszczędności.\n\nPozdrawiam,`;
    
    // Update status to SENT
    file.status = 'SENT';
    saveToLocalStorage();

    router.push({
        name: 'sales-email-compose', 
        query: {
            to: client?.contactEmail || '',
            subject: encodeURIComponent(subject),
            body: encodeURIComponent(body),
            attachmentId: file.id
        }
    });
};

const deleteFile = async (file: any) => {
    if (!confirm('Czy na pewno chcesz usunąć ten plik?')) return;
    
    try {
        await api.delete(`/v1/payroll-spreadsheets/${file.id}`);
        toast.success('Plik został usunięty.');
    } catch (e) {
        // If backend delete failed (likely because file is local mock), just delete from array
        console.warn('Backend delete failed, removing locally');
    }
    
    // Remove locally regardless to reflect in UI
    files.value = files.value.filter(f => f.id !== file.id);
    saveToLocalStorage();
    toast.success('Plik został usunięty.');
};

</script>
