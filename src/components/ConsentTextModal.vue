<script setup lang="ts">
import { defineProps, defineEmits } from 'vue'
import AppIcon from './AppIcon.vue'

defineProps<{
  show: boolean
  title: string
  content: string
}>()

const emit = defineEmits(['close'])
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="emit('close')"></div>
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[80vh] z-10 flex flex-col overflow-hidden animate-fade-in-up">
      <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
        <h3 class="font-bold text-slate-800 text-lg">{{ title }}</h3>
        <button @click="emit('close')" class="text-slate-400 hover:text-slate-600 transition p-1">
          <AppIcon name="xmark" class="w-5 h-5" />
        </button>
      </div>
      <div class="p-6 overflow-y-auto whitespace-pre-line text-sm text-slate-600 leading-relaxed font-mono bg-white">
        {{ content }}
      </div>
      <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end">
        <button @click="emit('close')" class="px-4 py-2 bg-slate-800 text-white text-sm font-bold rounded-lg hover:bg-slate-700 transition">
          Zamknij
        </button>
      </div>
    </div>
  </div>
</template>
