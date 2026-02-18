<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useNewsStore } from '@/stores/news'
import RichTextEditor from '@/components/RichTextEditor.vue'
import AppIcon from '@/components/AppIcon.vue'

const newsStore = useNewsStore()
const isEditing = ref(false)
const isSaving = ref(false)
const editingId = ref<number | null>(null)

const form = ref({
  title: '',
  content: '',
  category: 'ANNOUNCEMENT',
  target_roles: [] as string[],
  expires_at: ''
})

const categories = [
  { label: 'Wydarzenia', value: 'EVENTS', color: 'bg-purple-500' },
  { label: 'Aktualizacje', value: 'UPDATE', color: 'bg-blue-500' },
  { label: 'Sprzedaż', value: 'SALES', color: 'bg-green-500' },
  { label: 'Ogłoszenie', value: 'ANNOUNCEMENT', color: 'bg-amber-500' }
]

const roles = [
  { label: 'Handlowiec', value: 'SALES' },
  { label: 'Manager', value: 'MANAGER' },
  { label: 'Dyrektor', value: 'DIRECTOR' },
  { label: 'Wszyscy', value: 'ALL' }
]

const openEditor = (item: any = null) => {
  if (item) {
    editingId.value = item.id
    form.value = {
      title: item.title,
      content: item.content,
      category: item.category,
      target_roles: item.target_roles || [],
      expires_at: item.expires_at ? item.expires_at.slice(0, 10) : ''
    }
  } else {
    editingId.value = null
    form.value = {
      title: '',
      content: '',
      category: 'ANNOUNCEMENT',
      target_roles: ['ALL'],
      expires_at: ''
    }
  }
  isEditing.value = true
}

const toggleRole = (roleValue: string) => {
  if (roleValue === 'ALL') {
    if (form.value.target_roles.includes('ALL')) {
        form.value.target_roles = []
    } else {
        form.value.target_roles = ['ALL']
    }
    return
  }

  // If ALL was selected, remove it first
  if (form.value.target_roles.includes('ALL')) {
      form.value.target_roles = form.value.target_roles.filter(r => r !== 'ALL')
  }

  if (form.value.target_roles.includes(roleValue)) {
    form.value.target_roles = form.value.target_roles.filter(r => r !== roleValue)
  } else {
    form.value.target_roles.push(roleValue)
  }
}

const selectedImage = ref<File | null>(null)
const selectedAttachment = ref<File | null>(null)
const imagePreview = ref('')
const attachmentName = ref('')

const fileInputImage = ref<HTMLInputElement | null>(null)
const fileInputAttachment = ref<HTMLInputElement | null>(null)

const triggerImageSelect = () => {
    fileInputImage.value?.click()
}

const triggerAttachmentSelect = () => {
    fileInputAttachment.value?.click()
}

const onImageSelected = (event: Event) => {
    const target = event.target as HTMLInputElement
    if (target.files && target.files.length > 0) {
        selectedImage.value = target.files[0]
        imagePreview.value = URL.createObjectURL(selectedImage.value)
    }
}

const onAttachmentSelected = (event: Event) => {
    const target = event.target as HTMLInputElement
    if (target.files && target.files.length > 0) {
        selectedAttachment.value = target.files[0]
        attachmentName.value = selectedAttachment.value.name
    }
}

const save = async () => {
    // Validation
    if (!form.value.title || !form.value.content) {
        alert('Tytuł i treść są wymagane')
        return
    }

    isSaving.value = true
    
    // Use FormData for file upload
    const fd = new FormData()
    fd.append('title', form.value.title)
    fd.append('content', form.value.content)
    fd.append('category', form.value.category)
    // Convert target_roles array to JSON string for consistent backend handling
    fd.append('target_roles', JSON.stringify(form.value.target_roles))
    
    if (form.value.expires_at) {
        fd.append('expires_at', form.value.expires_at + ' 23:59:59')
    }

    if (selectedImage.value) {
        fd.append('image', selectedImage.value)
    }
    
    if (selectedAttachment.value) {
        fd.append('attachment', selectedAttachment.value)
    }

    try {
        if (editingId.value) {
            await newsStore.update(editingId.value, fd)
        } else {
            await newsStore.create(fd)
        }
        isEditing.value = false
        // Reset state
        selectedImage.value = null
        selectedAttachment.value = null
        imagePreview.value = ''
        attachmentName.value = ''
    } catch (e: any) {
        console.error(e)
        alert('Wystąpił błąd podczas zapisywania: ' + (e.response?.data?.message || e.message))
    } finally {
        isSaving.value = false
    }
}

const remove = async (id: number) => {
    if (confirm('Czy na pewno chcesz usunąć to ogłoszenie?')) {
        await newsStore.remove(id)
    }
}

const getCategoryLabel = (val: string) => categories.find(c => c.value === val)?.label || val
const getCategoryColor = (val: string) => categories.find(c => c.value === val)?.color || 'bg-gray-500'

import { useRouter } from 'vue-router'
const router = useRouter()

const handleBack = () => {
  if (isEditing.value) {
    isEditing.value = false
  } else {
    router.push('/app/dashboard')
  }
}

onMounted(() => {
  newsStore.fetchAdminItems()
})
</script>

<template>
  <div class="p-4 max-w-7xl mx-auto space-y-6">
    <!-- Premium Header -->
    <div class="bg-slate-900 rounded-[2rem] shadow-xl border border-slate-800 p-6 flex flex-col md:flex-row justify-between items-center gap-6 relative overflow-hidden group isolate">
      <!-- Background Decor -->
      <div class="absolute inset-0 overflow-hidden pointer-events-none z-0">
         <div class="absolute top-0 right-0 w-64 h-64 bg-stratton-gold rounded-full mix-blend-overlay filter blur-3xl opacity-10 -translate-y-1/2 translate-x-1/2 group-hover:opacity-20 transition-opacity duration-1000"></div>
         <div class="absolute bottom-0 left-0 w-48 h-48 bg-purple-500 rounded-full mix-blend-overlay filter blur-3xl opacity-5 translate-y-1/2 -translate-x-1/2"></div>
      </div>

      <div class="relative z-10 flex items-center gap-5">
        <button @click="handleBack" class="w-10 h-10 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-700 transition-all shadow-sm group/btn">
          <AppIcon name="arrow-left" class="w-4 h-4 transition-transform group-hover/btn:-translate-x-1" />
        </button>
        <div>
          <h1 class="font-serif font-bold text-3xl text-white tracking-tight leading-none">Zarządzanie Aktualnościami</h1>
          <p class="text-[10px] text-slate-500 font-bold uppercase tracking-[0.2em] mt-2">Personalizacja komunikatów systemowych</p>
        </div>
      </div>
      
      <div class="relative z-10 flex items-center gap-4">
        <button 
          v-if="!isEditing"
          @click="openEditor()" 
          class="bg-stratton-gold text-white px-6 py-2.5 rounded-xl font-bold hover:bg-amber-600 transition shadow-lg shadow-amber-900/20 flex items-center gap-2 group/add"
        >
          <AppIcon name="plus" class="w-4 h-4 group-hover/add:rotate-90 transition-transform duration-300" />
          Dodaj Ogłoszenie
        </button>
      </div>
    </div>

    <!-- Editor Mode (Glassmorphism) -->
    <div v-if="isEditing" class="bg-white/90 backdrop-blur-md rounded-[2rem] shadow-xl border border-white/50 p-8 animate-fade-in-up">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
        <div class="col-span-2">
            <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2 ml-1">Tytuł ogłoszenia</label>
            <input v-model="form.title" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-3.5 text-slate-900 font-bold focus:outline-none focus:ring-2 focus:ring-stratton-gold/20 focus:border-stratton-gold transition-all" placeholder="Np. Nowy konkurs sprzedażowy..." />
        </div>

        <div>
            <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2 ml-1">Kategoria</label>
            <div class="relative">
              <select v-model="form.category" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-3.5 text-slate-900 font-bold focus:outline-none focus:ring-2 focus:ring-stratton-gold/20 focus:border-stratton-gold transition-all appearance-none cursor-pointer">
                  <option v-for="cat in categories" :key="cat.value" :value="cat.value">{{ cat.label }}</option>
              </select>
              <AppIcon name="chevron-down" class="absolute right-5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" />
            </div>
        </div>

        <div>
            <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2 ml-1">Wygasa (opcjonalne)</label>
            <div class="relative">
              <input v-model="form.expires_at" type="date" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-3.5 text-slate-900 font-bold focus:outline-none focus:ring-2 focus:ring-stratton-gold/20 focus:border-stratton-gold transition-all" />
            </div>
        </div>

        <div class="col-span-2">
            <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2 ml-1">Odbiorcy (Targetowane Role)</label>
            <div class="flex flex-wrap gap-2 p-1.5 bg-slate-50 rounded-2xl border border-slate-100">
                <button 
                  v-for="role in roles" 
                  :key="role.value"
                  type="button"
                  @click="toggleRole(role.value)"
                  class="px-5 py-2.5 rounded-xl text-xs font-bold transition-all duration-300 flex items-center gap-2"
                  :class="form.target_roles.includes(role.value) ? 'bg-slate-900 text-white shadow-md scale-[1.02]' : 'bg-white text-slate-500 hover:text-slate-800 hover:shadow-sm'"
                >
                    {{ role.label }}
                    <div v-if="form.target_roles.includes(role.value)" class="w-4 h-4 rounded-full bg-stratton-gold flex items-center justify-center">
                      <AppIcon name="check" class="w-2.5 h-2.5 text-slate-900" />
                    </div>
                </button>
            </div>
        </div>

        <div class="col-span-2">
             <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2 ml-1">Treść głównego komunikatu</label>
             <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden focus-within:ring-2 focus-within:ring-stratton-gold/20 focus-within:border-stratton-gold transition-all">
                <RichTextEditor v-model="form.content" />
             </div>
        </div>
      </div>

      <div class="flex flex-col md:flex-row justify-between items-center gap-5 pt-8 border-t border-slate-100">
         <div class="flex items-center gap-4">
             <input
                ref="fileInputImage"
                type="file"
                accept="image/*"
                class="hidden"
                @change="onImageSelected"
             />
             <button 
                type="button" 
                @click="triggerImageSelect" 
                class="flex items-center gap-3 px-6 py-2.5 rounded-xl text-xs font-bold uppercase tracking-widest transition-all duration-300 bg-white border border-slate-200 hover:border-purple-500 hover:text-purple-600 shadow-sm hover:shadow-md group/img"
             >
                <div class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center group-hover/img:bg-purple-600 transition-colors">
                  <AppIcon :name="imagePreview ? 'image' : 'plus'" class="w-4 h-4 text-purple-600 group-hover/img:text-white" />
                </div>
                <span>{{ imagePreview ? 'Zmień zdjęcie' : 'Dodaj tło' }}</span>
             </button>
             
             <input
                ref="fileInputAttachment"
                type="file"
                class="hidden"
                @change="onAttachmentSelected"
             />
             <button 
                type="button" 
                @click="triggerAttachmentSelect" 
                class="flex items-center gap-3 px-6 py-2.5 rounded-xl text-xs font-bold uppercase tracking-widest transition-all duration-300 bg-white border border-slate-200 hover:border-purple-500 hover:text-purple-600 shadow-sm hover:shadow-md group/att"
             >
                <div class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center group-hover/att:bg-purple-600 transition-colors">
                  <AppIcon :name="attachmentName ? 'paperclip' : 'plus'" class="w-4 h-4 text-purple-600 group-hover/att:text-white" />
                </div>
                <span>{{ attachmentName ? 'Zmień plik' : 'Załącznik PDF' }}</span>
             </button>

             <span v-if="attachmentName" class="text-[10px] font-medium text-slate-400 italic flex items-center gap-1.5 ml-2">
                <div class="w-2 h-2 rounded-full bg-stratton-gold animate-pulse"></div>
                {{ attachmentName }}
             </span>
         </div>

         <div class="flex items-center gap-4">
             <button @click="isEditing = false" class="px-6 py-3 rounded-xl font-bold text-slate-400 hover:text-slate-600 hover:bg-slate-50 transition-colors" :disabled="isSaving">Anuluj</button>
             <button @click="save" class="bg-linear-to-r from-green-600 to-emerald-600 text-white px-10 py-3 rounded-xl font-bold hover:shadow-lg hover:shadow-green-500/20 active:scale-95 transition-all flex items-center gap-3 disabled:opacity-50" :disabled="isSaving">
                <AppIcon v-if="!isSaving" name="check" class="w-5 h-5" />
                <AppIcon v-else name="refresh" class="w-5 h-5 animate-spin" />
                <span>{{ isSaving ? 'Przetwarzanie...' : (editingId ? 'Zatwierdź zmiany' : 'Opublikuj teraz') }}</span>
             </button>
         </div>
      </div>
      
      <div v-if="imagePreview || attachmentName" class="mt-4 flex gap-4 text-xs text-slate-500">
          <span v-if="imagePreview">Wybrano zdjęcie tła</span>
          <span v-if="attachmentName">Załącznik: {{ attachmentName }}</span>
      </div>
    </div>

    <!-- History List (Cards) -->
    <div v-else class="space-y-4">
        <div v-if="newsStore.isLoading" class="flex flex-col items-center justify-center py-20 bg-white/50 backdrop-blur-sm rounded-[2rem] border border-dashed border-slate-200">
            <div class="w-10 h-10 border-4 border-stratton-gold border-t-transparent rounded-full animate-spin"></div>
            <p class="mt-4 text-xs font-bold text-slate-400 uppercase tracking-widest">Wczytywanie bazy...</p>
        </div>
        <div v-else-if="newsStore.adminItems.length === 0" class="text-center py-20 bg-white shadow-sm rounded-[2rem] border border-dashed border-slate-200">
            <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
               <AppIcon name="inbox" class="w-8 h-8 text-slate-300" />
            </div>
            <p class="text-slate-400 font-bold uppercase tracking-widest text-xs">Brak aktywnych ogłoszeń</p>
            <button @click="openEditor()" class="mt-4 text-stratton-gold font-bold text-xs uppercase hover:underline">Dodaj pierwsze ogłoszenie</button>
        </div>
        
        <div v-else v-for="item in newsStore.adminItems" :key="item.id" class="relative bg-white/80 backdrop-blur-sm rounded-2xl shadow-sm border border-white/50 p-6 flex flex-col md:flex-row gap-6 group hover:shadow-xl hover:shadow-slate-500/10 hover:-translate-y-1 transition-all duration-300 overflow-hidden">
            <!-- Left accent strip -->
            <div class="absolute left-0 top-0 bottom-0 w-1.5 opacity-0 group-hover:opacity-100 transition-opacity" :class="getCategoryColor(item.category)"></div>

            <div class="flex-1">
                <div class="flex items-center gap-3 mb-3">
                    <span class="px-2.5 py-1 rounded-lg text-[9px] uppercase font-bold text-white tracking-widest shadow-sm" :class="getCategoryColor(item.category)">
                        {{ getCategoryLabel(item.category) }}
                    </span>
                    <div class="flex items-center gap-1.5 text-[10px] text-slate-400 font-bold uppercase tracking-wider">
                      <AppIcon name="calendar" class="w-2.5 h-2.5" />
                      {{ new Date(item.created_at).toLocaleDateString() }}
                    </div>
                    <span v-if="item.expires_at && new Date(item.expires_at) < new Date()" class="text-[9px] bg-red-50 text-red-600 px-2.5 py-1 rounded-lg font-bold uppercase tracking-wider border border-red-100">Wygasłe</span>
                </div>
                
                <h3 class="text-xl font-serif font-bold text-slate-900 mb-2 group-hover:text-stratton-gold transition-colors">{{ item.title }}</h3>
                <div class="text-sm text-slate-500 line-clamp-2 overflow-hidden italic leading-relaxed" v-html="item.content"></div> 
                
                <div class="mt-5 flex items-center gap-4 border-t border-slate-50 pt-4">
                    <div class="flex items-center gap-2">
                      <span class="text-[10px] uppercase font-bold text-slate-300 tracking-widest">Odbiorcy:</span>
                      <div class="flex gap-1.5">
                        <span v-for="role in item.target_roles || []" :key="role" class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded text-[10px] font-bold">
                            {{ role === 'ALL' ? 'Wszyscy' : (role === 'SALES' ? 'Handlowiec' : (role === 'MANAGER' ? 'Manager' : (role === 'DIRECTOR' ? 'Dyrektor' : role))) }}
                        </span>
                      </div>
                    </div>
                </div>
            </div>
            
            <div class="flex md:flex-col items-center justify-center gap-2 bg-slate-50/50 p-3 rounded-2xl border border-slate-100 group-hover:bg-white group-hover:shadow-sm transition-all">
                <button @click="openEditor(item)" class="w-10 h-10 flex items-center justify-center hover:bg-stratton-gold hover:text-white rounded-xl text-slate-400 transition-all duration-300 shadow-sm hover:shadow-stratton-gold/30" title="Edytuj">
                    <AppIcon name="edit" class="w-4 h-4" />
                </button>
                <div class="hidden md:block w-full h-px bg-slate-100"></div>
                <button @click="remove(item.id)" class="w-10 h-10 flex items-center justify-center hover:bg-red-500 hover:text-white rounded-xl text-slate-400 transition-all duration-300 shadow-sm hover:shadow-red-500/30" title="Usuń">
                    <AppIcon name="trash" class="w-4 h-4" />
                </button>
            </div>
        </div>
    </div>
  </div>
</template>
