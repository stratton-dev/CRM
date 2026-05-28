<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { api } from '@/api/client'
import { useClientStore } from '@/stores/client'
import { useSessionStore } from '@/stores/session'
import { useToastStore } from '@/stores/toast'

const CONTACT_SOURCES = [
  'Kontakt własny',
  'Cold calling',
  'Polecenie od innego klienta',
  'Przypadkowa rozmowa',
  'Leadowiec',
]

const emit = defineEmits<{
  (e: 'created', clientId: string): void
}>()

const clientStore = useClientStore()
const session = useSessionStore()
const toast = useToastStore()
const { currentUser } = storeToRefs(session)

const initialForm = () => ({
  nip: '',
  name: '',
  addressLine1: '',
  city: '',
  postalCode: '',
  regon: '',
  krs: '',
  contactName: '',
  contactEmail: '',
  contactPhone: '',
  accountantName: '',
  accountantEmail: '',
  source: CONTACT_SOURCES[0],
})

const form = reactive(initialForm())
const isFetchingGus = ref(false)
const isSubmitting = ref(false)

const opiekunLabel = computed(() => currentUser.value?.name || '—')

const fetchGus = async () => {
  const nip = form.nip.replace(/\D/g, '')
  if (!nip) {
    toast.warning('Wpisz NIP, żeby pobrać dane z GUS.')
    return
  }
  isFetchingGus.value = true
  try {
    const { data } = await api.get('/v1/gus', { params: { nip } })
    form.name = data.name || form.name
    form.regon = data.regon || form.regon
    form.krs = data.krs || form.krs
    form.city = data.city || form.city
    form.postalCode = data.zipCode || form.postalCode
    const street = [data.street, data.houseNr].filter(Boolean).join(' ').trim()
    const apt = data.aptNr ? `/${data.aptNr}` : ''
    const fullAddress = [street + apt, data.zipCode, data.city].filter(Boolean).join(', ')
    if (fullAddress) form.addressLine1 = fullAddress
    if (!form.contactEmail && data.email) form.contactEmail = data.email
    if (!form.contactPhone && data.phone) form.contactPhone = data.phone
    toast.success('Pobrano dane z GUS.')
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się pobrać danych z GUS.'
    toast.error(message)
  } finally {
    isFetchingGus.value = false
  }
}

const submit = async () => {
  if (!form.name.trim()) {
    toast.warning('Podaj nazwę firmy.')
    return
  }
  isSubmitting.value = true
  try {
    const payload: Record<string, unknown> = {
      name: form.name.trim(),
      nip: form.nip.replace(/\D/g, '') || null,
      regon: form.regon || null,
      krs: form.krs || null,
      address_line1: form.addressLine1 || null,
      city: form.city || null,
      postal_code: form.postalCode || null,
      accountant_name: form.accountantName || null,
      accountant_email: form.accountantEmail || null,
      contact_name: form.contactName || null,
      contact_phone: form.contactPhone || null,
      contact_email: form.contactEmail || null,
      source: form.source || null,
    }
    const { data } = await api.post('/v1/clients', payload)
    toast.success(`Dodano klienta: ${form.name.trim()}`)
    Object.assign(form, initialForm())
    await clientStore.refreshApiData()
    emit('created', String(data?.id ?? ''))
  } catch (error: any) {
    const status = error?.response?.status
    if (status === 422) {
      const errors = error?.response?.data?.errors
      const firstError = errors ? Object.values(errors)[0] : null
      const message = Array.isArray(firstError) ? firstError[0] : 'Błąd walidacji formularza.'
      toast.error(message)
    } else if (status === 409) {
      toast.error('Klient o tym NIP-ie jest już w bazie.')
    } else {
      toast.error('Nie udało się dodać klienta.')
    }
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <aside class="w-full md:w-80 flex-shrink-0 bg-white border border-slate-200 rounded-card shadow-sm flex flex-col self-start">
    <div class="px-4 py-3 border-b border-slate-200 flex items-center gap-2">
      <span class="inline-flex w-7 h-7 items-center justify-center rounded-md bg-emerald-50 text-emerald-600 font-bold text-base">+</span>
      <h3 class="text-sm font-bold text-slate-800">Wprowadź nowego klienta</h3>
    </div>

    <form class="p-4 space-y-4 overflow-y-auto" @submit.prevent="submit">
      <fieldset class="border border-slate-200 rounded-lg p-3 space-y-2">
        <legend class="px-1 text-[10px] font-bold text-slate-500 uppercase tracking-wider">GUS API (Szybki odczyt NIP)</legend>
        <div class="flex gap-2">
          <input
            v-model="form.nip"
            type="text"
            inputmode="numeric"
            placeholder="NIP np. 1112223344"
            class="flex-1 border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
            @keydown.enter.prevent="fetchGus"
          />
          <button
            type="button"
            class="px-3 py-2 rounded-lg bg-slate-900 text-white text-xs font-bold hover:bg-slate-800 disabled:opacity-50"
            :disabled="isFetchingGus"
            @click="fetchGus"
          >
            <span v-if="isFetchingGus">…</span>
            <span v-else>Pobierz</span>
          </button>
        </div>
      </fieldset>

      <div>
        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Nazwa firmy <span class="text-rose-500">*</span></label>
        <input
          v-model="form.name"
          type="text"
          required
          placeholder="Pobierz z GUS lub wpisz"
          class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
        />
      </div>

      <div>
        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Adres rejestrowy</label>
        <input
          v-model="form.addressLine1"
          type="text"
          placeholder="Adres siedziby"
          class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
        />
      </div>

      <fieldset class="border border-slate-200 rounded-lg p-3 space-y-2">
        <legend class="px-1 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Osoba decyzyjna (Zarząd/HR)</legend>
        <input
          v-model="form.contactName"
          type="text"
          placeholder="Imię i Nazwisko"
          class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
        />
        <input
          v-model="form.contactEmail"
          type="email"
          placeholder="E-mail bezpośredni"
          class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
        />
        <input
          v-model="form.contactPhone"
          type="text"
          placeholder="Telefon"
          class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
        />
      </fieldset>

      <fieldset class="border border-slate-200 rounded-lg p-3 space-y-2">
        <legend class="px-1 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Dział finansów / Księgowość</legend>
        <input
          v-model="form.accountantName"
          type="text"
          placeholder="Nazwisko księgowej"
          class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
        />
        <input
          v-model="form.accountantEmail"
          type="email"
          placeholder="Adres e-mail księgowości"
          class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
        />
      </fieldset>

      <div>
        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Opiekun</label>
        <input
          :value="opiekunLabel"
          type="text"
          readonly
          class="w-full border border-slate-200 bg-slate-50 rounded-lg px-3 py-2 text-sm text-slate-700"
        />
      </div>

      <div>
        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Źródło kontaktu</label>
        <select
          v-model="form.source"
          class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
        >
          <option v-for="src in CONTACT_SOURCES" :key="src" :value="src">{{ src }}</option>
        </select>
      </div>

      <button
        type="submit"
        class="w-full px-4 py-2.5 rounded-lg bg-indigo-600 text-white text-sm font-bold shadow-sm hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
        :disabled="isSubmitting || !form.name.trim()"
      >
        <span v-if="isSubmitting">Zapisywanie...</span>
        <span v-else>Dodaj i zapisz klienta</span>
      </button>
    </form>
  </aside>
</template>
