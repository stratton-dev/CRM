<script setup lang="ts">
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { useCalculatorStore } from '../store/useCalculatorStore';
import { validateNIP } from '../utils/validators';

const store = useCalculatorStore();
const nipCheck = validateNIP(store.firma.nip || '');

const newContact = ref({
  name: '',
  email: '',
  phone: '',
});

const contacts = computed(() => store.firma.kontakty || []);
const selectedIds = computed(() => store.firma.kontaktIds || []);

const syncLegacyContact = () => {
  const firstId = (store.firma.kontaktIds || [])[0];
  const contact = (store.firma.kontakty || []).find((item) => item.id === firstId);
  if (!contact) return;
  store.firma.osobaKontaktowa = contact.name;
  store.firma.email = contact.email || '';
  store.firma.telefon = contact.phone || '';
};

const toggleContact = (contactId: string) => {
  const current = store.firma.kontaktIds || [];
  if (current.includes(contactId)) {
    store.firma.kontaktIds = current.filter((id) => id !== contactId);
  } else {
    store.firma.kontaktIds = [...current, contactId];
  }
  syncLegacyContact();
};

const addContact = () => {
  if (!newContact.value.name.trim()) return;
  const contact = {
    id: `contact_${Date.now()}`,
    name: newContact.value.name.trim(),
    email: newContact.value.email.trim(),
    phone: newContact.value.phone.trim(),
  };
  store.firma.kontakty = [...(store.firma.kontakty || []), contact];
  store.firma.kontaktIds = [...new Set([...(store.firma.kontaktIds || []), contact.id])];
  newContact.value = { name: '', email: '', phone: '' };
  syncLegacyContact();
};
</script>

<template>
  <div class="space-y-6">
    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-bold text-slate-900">Dane firmy</h3>
        <div class="flex items-center gap-2 text-xs">
          <span class="px-2 py-1 rounded-full" :class="nipCheck.valid ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-400'">
            {{ nipCheck.valid ? 'NIP OK' : 'Brak weryfikacji' }}
          </span>
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">Nazwa firmy</label>
          <input v-model="store.firma.nazwa" type="text" class="mt-2 w-full border border-slate-200 rounded-lg px-4 py-3" />
        </div>
        <div>
          <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">NIP</label>
          <div class="mt-2 relative">
            <input v-model="store.firma.nip" type="text" class="w-full border border-slate-200 rounded-lg px-4 py-3 font-mono" />
            <AppIcon v-if="store.firma.nip" :name="nipCheck.valid ? 'check-circle' : 'x-circle'" class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400" />
          </div>
          <div class="text-xs text-slate-400 mt-1">{{ nipCheck.message }}</div>
        </div>
        <div>
          <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">Adres</label>
          <input v-model="store.firma.adres" type="text" class="mt-2 w-full border border-slate-200 rounded-lg px-4 py-3" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">Kod pocztowy</label>
            <input v-model="store.firma.kodPocztowy" type="text" class="mt-2 w-full border border-slate-200 rounded-lg px-4 py-3 font-mono" />
          </div>
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">Miasto</label>
            <input v-model="store.firma.miasto" type="text" class="mt-2 w-full border border-slate-200 rounded-lg px-4 py-3" />
          </div>
        </div>
      </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h4 class="text-lg font-bold text-slate-900">Osoby kontaktowe</h4>
          <p class="text-xs text-slate-500">Możesz wskazać więcej niż jedną osobę do kontaktu.</p>
        </div>
        <div class="text-xs text-slate-400">
          Wybrane: {{ selectedIds.length }}
        </div>
      </div>

      <div v-if="contacts.length === 0" class="text-sm text-slate-500">
        Brak dodanych osób kontaktowych. Dodaj pierwszą osobę poniżej.
      </div>
      <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <label
          v-for="contact in contacts"
          :key="contact.id"
          class="flex items-start gap-3 border border-slate-200 rounded-xl p-3 hover:border-slate-400 transition cursor-pointer"
        >
          <input
            type="checkbox"
            class="mt-1 h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-700"
            :checked="selectedIds.includes(contact.id)"
            @change="toggleContact(contact.id)"
          />
          <div>
            <div class="font-semibold text-slate-800">{{ contact.name }}</div>
            <div class="text-xs text-slate-500">
              <span v-if="contact.email">{{ contact.email }}</span>
              <span v-if="contact.email && contact.phone"> · </span>
              <span v-if="contact.phone">{{ contact.phone }}</span>
            </div>
          </div>
        </label>
      </div>

      <div class="border-t border-slate-200 pt-4">
        <div class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3">Dodaj nową osobę</div>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
          <div class="md:col-span-2">
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">Imię i nazwisko</label>
            <input v-model="newContact.name" type="text" class="mt-2 w-full border border-slate-200 rounded-lg px-4 py-3" />
          </div>
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">Email</label>
            <input v-model="newContact.email" type="email" class="mt-2 w-full border border-slate-200 rounded-lg px-4 py-3" />
          </div>
          <div>
            <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">Telefon</label>
            <input v-model="newContact.phone" type="text" class="mt-2 w-full border border-slate-200 rounded-lg px-4 py-3" />
          </div>
          <div>
            <button type="button" class="w-full bg-slate-900 text-white px-4 py-3 rounded-lg text-xs font-bold" @click="addContact">
              Dodaj i zaznacz
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm grid grid-cols-1 md:grid-cols-2 gap-6">
      <div>
        <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">Okres rozliczeniowy</label>
        <input v-model="store.firma.okres" type="month" class="mt-2 w-full border border-slate-200 rounded-lg px-4 py-3 font-mono" />
      </div>
      <div>
        <label class="text-xs font-bold text-slate-400 uppercase tracking-widest">Stawka wypadkowa (%)</label>
        <input v-model.number="store.firma.stawkaWypadkowa" type="number" step="0.01" class="mt-2 w-full border border-slate-200 rounded-lg px-4 py-3 font-mono" />
      </div>
    </div>
  </div>
</template>
