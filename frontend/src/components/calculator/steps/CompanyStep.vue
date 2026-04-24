<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { useCalculatorStore } from '../store/useCalculatorStore';
import { validateNIP } from '../utils/validators';
import { api } from '@/api/client';
import { useAuthStore } from '@/stores/auth';

const store = useCalculatorStore();
const auth = useAuthStore();
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

const loadClientContacts = async (clientId: string) => {
  if (!auth.enabled) return;
  if ((store.firma.kontakty || []).length > 0) return;
  try {
    const { data } = await api.get(`/v1/clients/${clientId}/contacts`);
    const contacts = Array.isArray(data)
      ? data.map((item: any) => ({
          id: String(item.id),
          name: String(item.name || ''),
          position: item.position || null,
          phone: item.phone || null,
          email: item.email || null,
          is_decision_maker: item.is_decision_maker ?? item.isDecisionMaker ?? null,
        }))
      : [];
    store.firma.kontakty = contacts;
    if (!store.firma.kontaktIds || store.firma.kontaktIds.length === 0) {
      const decisionIds = contacts.filter((c: any) => c.is_decision_maker).map((c: any) => c.id);
      store.firma.kontaktIds = decisionIds.length > 0 ? decisionIds : contacts.slice(0, 1).map((c: any) => c.id);
      syncLegacyContact();
    }
  } catch {
    // ignore if contacts cannot be loaded
  }
};

onMounted(() => {
  if (store.context.clientId) {
    void loadClientContacts(store.context.clientId);
  }
});

watch(
  () => store.context.clientId,
  (clientId) => {
    if (clientId) void loadClientContacts(clientId);
  }
);
</script>

<template>
  <div class="space-y-4">

    <!-- D365 Page Header -->
    <div class="mb-5">
      <div class="flex items-center gap-2 text-[10px] font-semibold text-slate-400 uppercase tracking-widest mb-2">
        <AppIcon name="building" class="w-3 h-3" />
        <span>{{ store.firma.nazwa || 'Firma' }}</span>
        <span class="text-slate-300">/</span>
        <span>Kalkulator</span>
        <span class="text-slate-300">/</span>
        <span class="text-slate-600">Krok 1 — Dane firmy</span>
      </div>
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-black text-slate-900 tracking-tight">Dane firmy</h1>
          <p class="text-[11px] text-slate-400 mt-0.5">Podstawowe informacje o kliencie i osobach kontaktowych</p>
        </div>
      </div>
    </div>

    <!-- Dane firmy -->
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
      <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100 bg-slate-50/60">
        <span class="text-[10px] font-black uppercase tracking-widest text-slate-700">Dane firmy</span>
        <span
          class="text-[9px] font-bold uppercase tracking-widest px-2 py-0.5 rounded border"
          :class="nipCheck.valid ? 'border-emerald-200 text-emerald-600 bg-emerald-50' : 'border-slate-200 text-slate-400 bg-white'"
        >
          {{ nipCheck.valid ? 'NIP zweryfikowany' : 'NIP niezweryfikowany' }}
        </span>
      </div>
      <div class="px-5 py-4 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
        <div>
          <label class="block text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1">Nazwa firmy</label>
          <input v-model="store.firma.nazwa" type="text" class="w-full h-8 border border-slate-200 rounded-md px-3 text-sm focus:outline-none focus:ring-1 focus:ring-stratton-gold focus:border-stratton-gold transition-colors" />
        </div>
        <div>
          <label class="block text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1">NIP</label>
          <div class="relative">
            <input v-model="store.firma.nip" type="text" class="w-full h-8 border border-slate-200 rounded-md px-3 pr-8 text-sm font-mono focus:outline-none focus:ring-1 focus:ring-stratton-gold focus:border-stratton-gold transition-colors" />
            <AppIcon
              v-if="store.firma.nip"
              :name="nipCheck.valid ? 'check-circle' : 'x-circle'"
              class="w-3.5 h-3.5 absolute right-2.5 top-1/2 -translate-y-1/2"
              :class="nipCheck.valid ? 'text-emerald-500' : 'text-rose-400'"
            />
          </div>
          <div v-if="store.firma.nip" class="text-[9px] text-slate-400 mt-1">{{ nipCheck.message }}</div>
        </div>
        <div>
          <label class="block text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1">Adres</label>
          <input v-model="store.firma.adres" type="text" class="w-full h-8 border border-slate-200 rounded-md px-3 text-sm focus:outline-none focus:ring-1 focus:ring-stratton-gold focus:border-stratton-gold transition-colors" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1">Kod pocztowy</label>
            <input v-model="store.firma.kodPocztowy" type="text" class="w-full h-8 border border-slate-200 rounded-md px-3 text-sm font-mono focus:outline-none focus:ring-1 focus:ring-stratton-gold focus:border-stratton-gold transition-colors" />
          </div>
          <div>
            <label class="block text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1">Miasto</label>
            <input v-model="store.firma.miasto" type="text" class="w-full h-8 border border-slate-200 rounded-md px-3 text-sm focus:outline-none focus:ring-1 focus:ring-stratton-gold focus:border-stratton-gold transition-colors" />
          </div>
        </div>
      </div>
    </div>

    <!-- Osoby kontaktowe -->
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
      <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100 bg-slate-50/60">
        <span class="text-[10px] font-black uppercase tracking-widest text-slate-700">Osoby kontaktowe</span>
        <span class="text-[9px] font-bold uppercase tracking-widest text-slate-400 border border-slate-200 rounded px-1.5 py-0.5 bg-white">
          {{ selectedIds.length }} wybranych z {{ contacts.length }}
        </span>
      </div>

      <!-- Contact grid -->
      <div class="px-5 pt-4">
        <div v-if="contacts.length === 0" class="text-[11px] text-slate-400 py-3">
          Brak dodanych osób kontaktowych — dodaj poniżej.
        </div>
        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-2 mb-4">
          <label
            v-for="contact in contacts"
            :key="contact.id"
            class="flex items-start gap-3 border rounded-lg px-3 py-2.5 cursor-pointer transition-all"
            :class="selectedIds.includes(contact.id) ? 'border-stratton-gold/40 bg-amber-50/40' : 'border-slate-200 hover:border-slate-300'"
          >
            <input
              type="checkbox"
              class="mt-0.5 h-3.5 w-3.5 rounded border-slate-300 text-stratton-gold focus:ring-amber-400 shrink-0"
              :checked="selectedIds.includes(contact.id)"
              @change="toggleContact(contact.id)"
            />
            <div class="min-w-0">
              <div class="text-xs font-bold text-slate-800 leading-tight">{{ contact.name }}</div>
              <div class="text-[10px] text-slate-400 mt-0.5 truncate">
                <span v-if="contact.email">{{ contact.email }}</span>
                <span v-if="contact.email && contact.phone"> · </span>
                <span v-if="contact.phone">{{ contact.phone }}</span>
              </div>
            </div>
          </label>
        </div>
      </div>

      <!-- Add contact form -->
      <div class="px-5 pb-4 border-t border-slate-100 pt-4">
        <div class="text-[9px] font-black uppercase tracking-widest text-slate-400 mb-3">Dodaj nową osobę</div>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
          <div class="md:col-span-2">
            <label class="block text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1">Imię i nazwisko</label>
            <input v-model="newContact.name" type="text" autocomplete="new-password" name="sp_contact_name" class="w-full h-8 border border-slate-200 rounded-md px-3 text-sm focus:outline-none focus:ring-1 focus:ring-stratton-gold focus:border-stratton-gold transition-colors" />
          </div>
          <div>
            <label class="block text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1">Email</label>
            <input v-model="newContact.email" type="email" autocomplete="new-password" name="sp_contact_email" class="w-full h-8 border border-slate-200 rounded-md px-3 text-sm focus:outline-none focus:ring-1 focus:ring-stratton-gold focus:border-stratton-gold transition-colors" />
          </div>
          <div>
            <label class="block text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1">Telefon</label>
            <input v-model="newContact.phone" type="text" autocomplete="new-password" name="sp_contact_phone" class="w-full h-8 border border-slate-200 rounded-md px-3 text-sm focus:outline-none focus:ring-1 focus:ring-stratton-gold focus:border-stratton-gold transition-colors" />
          </div>
          <div>
            <button
              type="button"
              class="w-full h-8 bg-linear-to-r from-[#D4AF37] to-stratton-gold text-white rounded-md text-[10px] font-black uppercase tracking-widest hover:brightness-110 transition-all shadow-[0_4px_12px_-2px_rgba(197,160,89,0.3)] active:scale-95"
              @click="addContact"
            >
              + Dodaj
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Parametry rozliczeniowe -->
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
      <div class="px-5 py-3 border-b border-slate-100 bg-slate-50/60">
        <span class="text-[10px] font-black uppercase tracking-widest text-slate-700">Parametry rozliczeniowe</span>
      </div>
      <div class="px-5 py-4 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
        <div>
          <label class="block text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1">Okres rozliczeniowy</label>
          <input v-model="store.firma.okres" type="month" class="w-full h-8 border border-slate-200 rounded-md px-3 text-sm font-mono focus:outline-none focus:ring-1 focus:ring-stratton-gold focus:border-stratton-gold transition-colors" />
        </div>
        <div>
          <label class="block text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1">Stawka wypadkowa (%)</label>
          <input v-model.number="store.firma.stawkaWypadkowa" type="number" step="0.01" class="w-full h-8 border border-slate-200 rounded-md px-3 text-sm font-mono focus:outline-none focus:ring-1 focus:ring-stratton-gold focus:border-stratton-gold transition-colors" />
        </div>
      </div>
    </div>

  </div>
</template>
