<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';

const props = defineProps<{
  modelValue: string;
}>();

const emit = defineEmits(['update:modelValue']);

const editor = ref<HTMLElement | null>(null);

const exec = (command: string, value: string | undefined = undefined) => {
  document.execCommand(command, false, value);
  updateValue();
};

const updateValue = () => {
  if (editor.value) {
    emit('update:modelValue', editor.value.innerHTML);
  }
};

watch(() => props.modelValue, (newVal) => {
  if (editor.value && editor.value.innerHTML !== newVal) {
    editor.value.innerHTML = newVal;
  }
});

onMounted(() => {
  if (editor.value) {
    editor.value.innerHTML = props.modelValue || '';
  }
});
</script>

<template>
  <div class="border border-slate-300 rounded-xl overflow-hidden bg-white focus-within:ring-2 focus-within:ring-stratton-gold focus-within:border-transparent transition-all">
    <div class="flex items-center gap-1 p-2 bg-slate-50 border-b border-slate-200">
      <button type="button" @click="exec('bold')" class="p-1.5 rounded hover:bg-slate-200 text-slate-600 font-bold" title="Pogrubienie">B</button>
      <button type="button" @click="exec('italic')" class="p-1.5 rounded hover:bg-slate-200 text-slate-600 italic" title="Kursywa">I</button>
      <button type="button" @click="exec('underline')" class="p-1.5 rounded hover:bg-slate-200 text-slate-600 underline" title="Podkreślenie">U</button>
      <div class="w-px h-4 bg-slate-300 mx-1"></div>
      <button type="button" @click="exec('justifyLeft')" class="p-1.5 rounded hover:bg-slate-200 text-slate-600" title="Do lewej">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="17" y1="10" x2="3" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="17" y1="18" x2="3" y2="18"></line></svg>
      </button>
      <button type="button" @click="exec('justifyCenter')" class="p-1.5 rounded hover:bg-slate-200 text-slate-600" title="Wyśrodkowanie">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="21" y1="10" x2="3" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="21" y1="18" x2="3" y2="18"></line></svg>
      </button>
       <div class="w-px h-4 bg-slate-300 mx-1"></div>
       <select @change="(e) => exec('foreColor', (e.target as HTMLSelectElement).value)" class="bg-transparent text-xs p-1 rounded border border-slate-300 text-slate-600 outline-none">
          <option value="#0f172a">Czarny</option>
          <option value="#dc2626">Czerwony</option>
          <option value="#2563eb">Niebieski</option>
          <option value="#16a34a">Zielony</option>
          <option value="#d97706">Bursztynowy</option>
       </select>
       <select @change="(e) => exec('fontName', (e.target as HTMLSelectElement).value)" class="bg-transparent text-xs p-1 rounded border border-slate-300 text-slate-600 outline-none w-24">
          <option value="Sans-Serif">Sans Serif</option>
          <option value="Serif">Serif</option>
          <option value="Monospace">Monospace</option>
       </select>
    </div>
    <div 
      ref="editor"
      class="p-4 min-h-[150px] outline-none text-slate-800 text-sm max-h-[400px] overflow-y-auto"
      contenteditable="true"
      @input="updateValue"
    ></div>
  </div>
</template>
