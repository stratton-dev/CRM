<script setup lang="ts">
import AppIcon from '@/components/AppIcon.vue'

const props = defineProps<{
  show: boolean
  title: string
  type: string
  // New props for dynamic content
  description?: string
  fileUrl?: string
  fileType?: string
}>()

const emit = defineEmits(['close'])

const isVideo = (type: string) => type === 'video' || type?.startsWith('video/')
const isPdf = (type: string) => type === 'pdf' || type === 'application/pdf'
const isImage = (type: string) => type === 'image' || type?.startsWith('image/')
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-[100] bg-slate-50 flex flex-col animate-fade-in-up overflow-hidden">
    <!-- Header -->
    <div class="flex items-center justify-between px-8 py-5 border-b border-slate-200 bg-white shadow-sm shrink-0">
      <div class="flex items-center gap-4">
        <button @click="emit('close')" class="w-10 h-10 rounded-full bg-slate-50 border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition">
          <AppIcon name="arrow-left" class="w-5 h-5" />
        </button>
        <div class="h-8 w-px bg-slate-200 mx-2"></div>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-stratton-gold/10 flex items-center justify-center text-stratton-gold">
              <AppIcon v-if="type === 'CASH_FLOW'" name="chart-pie" class="w-6 h-6" />
              <AppIcon v-else-if="type === 'LEGAL'" name="scale" class="w-6 h-6" />
              <AppIcon v-else-if="type === 'GRAPHIC'" name="presentation-chart-line" class="w-6 h-6" />
              <AppIcon v-else-if="isVideo(fileType || type)" name="video-camera" class="w-6 h-6" />
              <AppIcon v-else-if="isPdf(fileType || type)" name="document-text" class="w-6 h-6" />
              <AppIcon v-else name="document" class="w-6 h-6" />
          </div>
          <div>
             <h3 class="font-serif font-bold text-xl text-slate-800">{{ title }}</h3>
             <p class="text-xs text-slate-500 uppercase tracking-widest font-bold">Tryb Prezentacji</p>
          </div>
        </div>
      </div>
      <div class="flex items-center gap-4">
         <span class="text-xs font-bold text-slate-400 bg-slate-50 px-3 py-1 rounded border border-slate-100 uppercase tracking-wider">{{ new Date().toLocaleDateString() }}</span>
      </div>
    </div>

    <!-- Content -->
    <div class="flex-1 overflow-y-auto p-10 relative bg-slate-100">
      <div class="max-w-7xl mx-auto h-full flex flex-col gap-6">
        
        <!-- Description Card if present -->
        <div v-if="description" class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
           <h4 class="font-bold text-lg text-slate-800 mb-2">Opis</h4>
           <p class="text-slate-600 leading-relaxed max-w-4xl">{{ description }}</p>
        </div>

        <!-- Dynamic File Content -->
        <div v-if="fileUrl" class="flex-1 min-h-[500px] bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden relative">
            
            <!-- Video Player -->
            <div v-if="isVideo(fileType || type)" class="w-full h-full flex items-center justify-center bg-black">
                <video controls class="max-w-full max-h-full" :src="fileUrl">
                    Twoja przeglądarka nie obsługuje elementu wideo.
                </video>
            </div>
            
            <!-- PDF Viewer -->
            <div v-else-if="isPdf(fileType || type)" class="w-full h-full">
                <iframe :src="fileUrl" class="w-full h-full border-none"></iframe>
            </div>

            <!-- Image Viewer -->
             <div v-else-if="isImage(fileType || type)" class="w-full h-full flex items-center justify-center bg-slate-50 p-4">
                <img :src="fileUrl" class="max-w-full max-h-full object-contain rounded-lg shadow-sm" />
             </div>

             <!-- Fallback / Download -->
            <div v-else class="w-full h-full flex flex-col items-center justify-center p-10 text-center">
                <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center text-slate-400 mb-6">
                    <AppIcon name="document-arrow-down" class="w-10 h-10" />
                </div>
                <h3 class="text-xl font-bold text-slate-800 mb-2">Plik do pobrania</h3>
                <p class="text-slate-500 mb-6">Ten typ pliku nie może być wyświetlony bezpośrednio w podglądzie.</p>
                <a :href="fileUrl" target="_blank" download class="px-6 py-3 bg-stratton-gold text-white font-bold rounded-xl hover:bg-stratton-gold-dark transition shadow-lg shadow-stratton-gold/20 flex items-center gap-2">
                    <AppIcon name="arrow-down-tray" class="w-5 h-5" />
                    Pobierz plik
                </a>
            </div>
        </div>

        <!-- Legacy Hardcoded Support (Fallback) -->
        <template v-else>
           <div v-if="type === 'CASH_FLOW'" class="space-y-6">
              <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-200">
                 <h4 class="font-bold text-lg text-slate-800 mb-4">Analiza Cash Flow</h4>
                 <p class="text-slate-600 leading-relaxed mb-6">
                   Szczegółowa symulacja przepływów finansowych pokazuje potencjalne oszczędności wynikające z wdrożenia modelu.
                   Dzięki optymalizacji, środki mogą zostać reinwestowane w rozwój przedsiębiorstwa.
                 </p>
                 <div class="h-64 bg-slate-100 rounded-xl flex items-center justify-center border border-dashed border-slate-300">
                    <span class="text-slate-400 font-medium">Wykres przepływów (Symulacja)</span>
                 </div>
              </div>
           </div>

           <div v-else-if="type === 'LEGAL'" class="space-y-6">
               <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-200">
                 <h4 class="font-bold text-lg text-slate-800 mb-4">Podstawy Prawne</h4>
                 <p class="text-slate-600 leading-relaxed mb-4">
                   Nasze rozwiązania opierają się na sprawdzonych modelach prawnych, zgodnych z obowiązującymi przepisami prawa pracy i ustawami podatkowymi.
                 </p>
                 <ul class="space-y-3">
                    <li class="flex items-start gap-3">
                       <AppIcon name="check-circle" class="w-5 h-5 text-emerald-500 mt-0.5" />
                       <span class="text-slate-700 text-sm">Zgodność z Kodeksem Pracy, Art. 22 § 1</span>
                    </li>
                    <li class="flex items-start gap-3">
                       <AppIcon name="check-circle" class="w-5 h-5 text-emerald-500 mt-0.5" />
                       <span class="text-slate-700 text-sm">Indywidualne interpretacje podatkowe</span>
                    </li>
                    <li class="flex items-start gap-3">
                       <AppIcon name="check-circle" class="w-5 h-5 text-emerald-500 mt-0.5" />
                       <span class="text-slate-700 text-sm">Pełne ubezpieczenie ZUS i NFZ dla pracowników</span>
                    </li>
                 </ul>
              </div>
           </div>

        <div v-else-if="type === 'GRAPHIC'" class="space-y-6">
            <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-200">
               <h4 class="font-bold text-lg text-slate-800 mb-4">Model Współpracy</h4>
               <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                  <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl text-center">
                     <div class="w-12 h-12 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center mx-auto mb-3">1</div>
                     <h5 class="font-bold text-slate-900 text-sm">Audyt</h5>
                     <p class="text-xs text-slate-500 mt-1">Analiza struktury</p>
                  </div>
                  <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl text-center">
                     <div class="w-12 h-12 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center mx-auto mb-3">2</div>
                     <h5 class="font-bold text-slate-900 text-sm">Wdrożenie</h5>
                     <p class="text-xs text-slate-500 mt-1">Implementacja modelu</p>
                  </div>
                  <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl text-center">
                     <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto mb-3">3</div>
                     <h5 class="font-bold text-slate-900 text-sm">Oszczędności</h5>
                     <p class="text-xs text-slate-500 mt-1">Comiesięczny zysk</p>
                  </div>
               </div>
            </div>
        </div>

        <div v-else-if="type === 'VIDEO'" class="flex items-center justify-center h-full">
            <div class="w-full max-w-2xl bg-black rounded-xl overflow-hidden aspect-video shadow-2xl flex items-center justify-center relative group cursor-pointer">
               <div class="absolute inset-0 bg-cover bg-center opacity-60" style="background-image: url('https://images.unsplash.com/photo-1557804506-669a67965ba0?ixlib=rb-4.0.3&auto=format&fit=crop&w=1674&q=80')"></div>
               <div class="w-20 h-20 rounded-full bg-white/20 backdrop-blur-sm border-2 border-white flex items-center justify-center pl-1 group-hover:scale-110 transition-transform">
                  <AppIcon name="play" class="w-8 h-8 text-white" />
               </div>
               <span class="absolute bottom-4 left-4 text-white font-bold text-sm">Prezentacja Wideo (Demo)</span>
            </div>
        </div>
        </template>

      </div>
    </div>
    
    <!-- Footer Navigation -->
    <div class="p-6 border-t border-slate-200 bg-white flex justify-between items-center shrink-0">
       <button @click="emit('close')" class="flex items-center gap-2 text-slate-500 hover:text-slate-800 font-bold text-sm px-4 py-2 rounded-lg hover:bg-slate-50 transition">
          <AppIcon name="arrow-left" class="w-4 h-4" />
          Powrót do kafelków
       </button>
       <div class="flex gap-2">
          <!-- Optional Next/Prev buttons logic could go here -->
       </div>
    </div>
  </div>
</template>
