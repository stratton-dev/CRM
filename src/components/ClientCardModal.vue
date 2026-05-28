<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useClientStore } from '@/stores/client'
import { useToastStore } from '@/stores/toast'
import { useSessionStore } from '@/stores/session'
import { api } from '@/api/client'
import AppIcon from '@/components/AppIcon.vue'
import { industries, contactSources } from '@/constants/industries'
import type { Client } from '@/types/models'

const props = defineProps<{
  open: boolean
  client?: Client
}>()

const emit = defineEmits<{
  close: []
  saved: []
}>()

const clientStore = useClientStore()
const toast = useToastStore()
const session = useSessionStore()

const isEditing = computed(() => !!props.client)

const isSubmitting = ref(false)
const isFetchingGus = ref(false)
const wasValidated = ref(false)
const dateInput = ref<HTMLInputElement | null>(null)

const STATUS_LABELS: Partial<Record<Client['status'], string>> = {
  NEW: 'Nowy',
  IN_TALKS: 'W rozmowach',
  RESIGNED: 'Rezygnacja',
  SIGNED: 'Podpisano',
  TERMINATED: 'Zakończono',
}

const STATUS_COLORS: Partial<Record<Client['status'], string>> = {
  NEW: 'bg-slate-100 text-slate-700',
  IN_TALKS: 'bg-indigo-100 text-indigo-800',
  RESIGNED: 'bg-rose-100 text-rose-700',
  SIGNED: 'bg-emerald-100 text-emerald-800',
  TERMINATED: 'bg-gray-100 text-gray-600',
}

const initialForm = {
  contactName: '',
  contactPosition: '',
  contactPhone: '',
  contactEmail: '',
  isDecisionMaker: false,
  companyName: '',
  nip: '',
  address: '',
  industry: '',
  companySize: '',
  source: 'Kontakt własny',
  activityDate: '',
  activityNotes: '',
}

const form = ref({ ...initialForm })

const clientAddress = (c: Client) =>
  `${c.street || ''} ${c.buildingNr || ''}${c.localeNr ? '/' + c.localeNr : ''}, ${c.zip || ''} ${c.city || ''}`.trim().replace(/^,\s*/, '').replace(/,\s*$/, '')

watch(
  () => props.open,
  (open) => {
    if (!open) return
    wasValidated.value = false
    if (props.client) {
      const c = props.client
      form.value = {
        contactName: c.contactName || '',
        contactPosition: c.contactPosition || '',
        contactPhone: c.contactPhone || '',
        contactEmail: c.contactEmail || '',
        isDecisionMaker: c.isDecisionMaker || false,
        companyName: c.name || '',
        nip: c.nip || '',
        address: clientAddress(c),
        industry: c.industry || '',
        companySize: c.companySize || '',
        source: c.source || 'Kontakt własny',
        activityDate: new Date().toISOString().slice(0, 16),
        activityNotes: '',
      }
    } else {
      form.value = { ...initialForm, activityDate: new Date().toISOString().slice(0, 16) }
    }
  },
  { immediate: true },
)

const fetchGusData = async () => {
  if (!form.value.nip) return
  isFetchingGus.value = true
  try {
    const response = await api.get(`/v1/gus?nip=${form.value.nip}`)
    const data = response.data?.data || response.data
    if (data) {
      form.value.companyName = data.name || form.value.companyName
      form.value.address = `${data.street || ''} ${data.houseNr || ''}${data.aptNr ? '/' + data.aptNr : ''}, ${data.zipCode || ''} ${data.city || ''}`.trim()
      toast.success('Dane pobrane pomyślnie')
    }
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Nie znaleziono danych dla podanego NIP')
  } finally {
    isFetchingGus.value = false
  }
}

const handleCreate = async () => {
  wasValidated.value = true
  if (
    !form.value.contactName ||
    !form.value.source ||
    !form.value.activityDate ||
    !form.value.companySize ||
    !form.value.industry
  ) {
    toast.warning('Wypełnij wymagane pola zaznaczone na czerwono')
    return
  }

  isSubmitting.value = true
  try {
    await api.post('/v1/meetings/prospect', {
      name: form.value.companyName || form.value.contactName,
      nip: form.value.nip,
      contact_name: form.value.contactName,
      contact_phone: form.value.contactPhone,
      contact_email: form.value.contactEmail,
      contact_position: form.value.contactPosition,
      is_decision_maker: form.value.isDecisionMaker,
      address: form.value.address,
      industry: form.value.industry,
      company_size: form.value.companySize,
      source: form.value.source,
      status: 'IN_TALKS',
      initial_meeting: {
        date: new Date(form.value.activityDate).toISOString(),
        notes: form.value.activityNotes,
      },
    })
    await clientStore.refreshApiData()
    toast.success('Spotkanie i klient dodani pomyślnie')
    emit('saved')
    emit('close')
  } catch (error: any) {
    toast.error('Błąd podczas zapisywania: ' + (error.response?.data?.message || error.message))
  } finally {
    isSubmitting.value = false
  }
}

const handleUpdate = async () => {
  if (!props.client) return
  isSubmitting.value = true

  let noteSaved = false
  let clientUpdated = false

  try {
    if (form.value.activityNotes) {
      const activityDate = form.value.activityDate
        ? new Date(form.value.activityDate).toISOString()
        : new Date().toISOString()
      await clientStore.addActivity(
        props.client.id,
        {
          type: 'MEETING',
          description: form.value.activityNotes,
          authorId: session.currentUser?.id || '',
        },
        activityDate,
      )
      noteSaved = true
      toast.success('Zapisano notatkę')
    }

    try {
      await api.patch(`/v1/clients/${props.client.id}`, {
        name: form.value.companyName,
        nip: form.value.nip,
        contact_name: form.value.contactName,
        contact_phone: form.value.contactPhone,
        contact_email: form.value.contactEmail,
        contact_position: form.value.contactPosition,
        is_decision_maker: form.value.isDecisionMaker,
        address: form.value.address,
        industry: form.value.industry,
        company_size: form.value.companySize,
        source: form.value.source,
      })
      clientUpdated = true
      toast.success('Zaktualizowano dane klienta')
    } catch (clientError: any) {
      if (!noteSaved) {
        throw clientError
      }
      const msg = clientError.response?.data?.message || clientError.message
      if (!msg.includes('unauthorized') && !msg.includes('403') && !msg.includes('UNAUTHORIZED')) {
        toast.warning('Notatka zapisana, ale wystąpił błąd przy aktualizacji danych klienta: ' + msg)
      }
    }

    await clientStore.refreshApiData()

    if (noteSaved || clientUpdated) {
      emit('saved')
      emit('close')
    }
  } catch (error: any) {
    toast.error('Błąd zapisu: ' + (error.response?.data?.message || error.message))
  } finally {
    isSubmitting.value = false
  }
}

const handleSave = () => {
  if (isEditing.value) {
    handleUpdate()
  } else {
    handleCreate()
  }
}
</script>

<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="emit('close')"></div>
    <div class="relative bg-surface w-full max-w-4xl max-h-[90vh] overflow-y-auto rounded-card shadow-2xl flex flex-col animate-in fade-in zoom-in duration-300 border border-slate-200">

      <!-- Header -->
      <div class="p-6 border-b border-slate-200 flex justify-between items-center sticky top-0 bg-surface-dark z-10 text-white rounded-t-card">
        <div>
          <div class="flex items-center gap-3">
            <h2 class="text-xl font-bold">
              {{ isEditing ? 'Karta klienta' : 'Nowe Spotkanie' }}
            </h2>
            <span
              v-if="isEditing && client"
              class="text-xs font-bold px-2 py-0.5 rounded-full"
              :class="STATUS_COLORS[client.status] || 'bg-slate-100 text-slate-700'"
            >
              {{ STATUS_LABELS[client.status] || client.status }}
            </span>
          </div>
          <p class="text-slate-300 text-sm mt-1">
            {{ isEditing ? (client?.name || client?.contactName) : 'Uzupełnij dane spotkania i klienta' }}
          </p>
        </div>
        <button @click="emit('close')" class="text-slate-400 hover:text-white transition-colors">
          <AppIcon name="xmark" class="w-6 h-6" />
        </button>
      </div>

      <div class="p-4 md:p-8 space-y-4 md:space-y-8">

        <!-- Section 1: Osoba Kontaktowa -->
        <div class="space-y-4">
          <h3 class="flex items-center gap-2 text-lg font-bold text-slate-800">
            <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center border border-blue-100">
              <AppIcon name="user" class="w-4 h-4" />
            </div>
            Osoba Kontaktowa
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-1">
              <label class="text-xs font-bold text-slate-500 uppercase ml-1">Imię i Nazwisko</label>
              <input
                v-model="form.contactName"
                type="text"
                placeholder="Jan Kowalski"
                class="form-input border-slate-300 focus:border-primary focus:ring-primary rounded-lg"
                :class="{ 'border-rose-500 ring-1 ring-rose-500': wasValidated && !form.contactName }"
              />
            </div>
            <div class="space-y-1">
              <label class="text-xs font-bold text-slate-500 uppercase ml-1">Stanowisko</label>
              <input v-model="form.contactPosition" type="text" placeholder="Dyrektor HR" class="form-input border-slate-300 focus:border-primary focus:ring-primary rounded-lg" />
            </div>
            <div class="space-y-1">
              <label class="text-xs font-bold text-slate-500 uppercase ml-1">Telefon</label>
              <input v-model="form.contactPhone" type="text" placeholder="+48 000 000 000" class="form-input border-slate-300 focus:border-primary focus:ring-primary rounded-lg" />
            </div>
            <div class="space-y-1">
              <label class="text-xs font-bold text-slate-500 uppercase ml-1">Email</label>
              <input v-model="form.contactEmail" type="email" placeholder="email@firma.pl" class="form-input border-slate-300 focus:border-primary focus:ring-primary rounded-lg" />
            </div>
          </div>
          <label class="flex items-center gap-3 cursor-pointer group mt-2">
            <input v-model="form.isDecisionMaker" type="checkbox" class="w-5 h-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 transition-all shadow-sm" />
            <span class="text-slate-700 font-medium group-hover:text-emerald-700 transition-colors text-sm">Osoba decyzyjna</span>
          </label>
        </div>

        <!-- Section 2: Dane Firmy -->
        <div class="space-y-4 pt-8 border-t border-slate-100">
          <h3 class="flex items-center gap-2 text-lg font-bold text-slate-800">
            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center border border-emerald-100">
              <AppIcon name="building" class="w-4 h-4" />
            </div>
            Dane Firmy
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="space-y-1 md:col-span-2">
              <label class="text-xs font-bold text-slate-500 uppercase ml-1">NIP (GUS Autofill)</label>
              <div class="flex gap-2">
                <input v-model="form.nip" type="text" placeholder="10 cyfr" class="form-input border-slate-300 focus:border-primary focus:ring-primary rounded-lg" />
                <button
                  @click="fetchGusData"
                  :disabled="isFetchingGus"
                  class="bg-slate-800 text-white px-4 rounded-lg font-bold hover:bg-slate-700 transition-all disabled:opacity-50 flex items-center gap-2 whitespace-nowrap text-sm shadow-sm"
                >
                  <AppIcon v-if="!isFetchingGus" name="refresh" class="w-4 h-4" />
                  <div v-else class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                  Pobierz
                </button>
              </div>
            </div>
            <div class="space-y-1">
              <label class="text-xs font-bold text-slate-500 uppercase ml-1">Ilość pracowników *</label>
              <input
                v-model="form.companySize"
                type="text"
                placeholder="Np. 25"
                class="form-input border-slate-300 focus:border-primary focus:ring-primary rounded-lg"
                :class="{ 'border-rose-500 ring-1 ring-rose-500': wasValidated && !form.companySize }"
              />
            </div>
            <div class="space-y-1 md:col-span-3">
              <label class="text-xs font-bold text-slate-500 uppercase ml-1">Nazwa firmy *</label>
              <input
                v-model="form.companyName"
                type="text"
                placeholder="Firma Sp. z o.o."
                class="form-input border-slate-300 focus:border-primary focus:ring-primary rounded-lg"
                :class="{ 'border-rose-500 ring-1 ring-rose-500': wasValidated && !form.companyName }"
              />
            </div>
            <div class="space-y-1 md:col-span-2">
              <label class="text-xs font-bold text-slate-500 uppercase ml-1">Adres</label>
              <input
                v-model="form.address"
                type="text"
                placeholder="ul. Sezamkowa 1, 00-000 Warszawa"
                class="form-input border-slate-300 focus:border-primary focus:ring-primary rounded-lg"
              />
            </div>
            <div class="space-y-1">
              <label class="text-xs font-bold text-slate-500 uppercase ml-1">Branża *</label>
              <input
                v-model="form.industry"
                list="client-card-industry-options"
                type="text"
                placeholder="Wyszukaj branżę..."
                class="form-input border-slate-300 focus:border-primary focus:ring-primary rounded-lg"
                :class="{ 'border-rose-500 ring-1 ring-rose-500': wasValidated && !form.industry }"
              />
              <datalist id="client-card-industry-options">
                <option v-for="ind in industries" :key="ind" :value="ind"></option>
              </datalist>
            </div>
          </div>
        </div>

        <!-- Section 3: Aktywność / Spotkanie -->
        <div class="space-y-4 pt-8 border-t border-slate-100">
          <h3 class="flex items-center gap-2 text-lg font-bold text-slate-800">
            <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center border border-indigo-100">
              <AppIcon name="calendar" class="w-4 h-4" />
            </div>
            {{ isEditing ? 'Ostatnia Aktywność / Aktualizacja' : 'Informacje o Spotkaniu' }}
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-1">
              <label class="text-xs font-bold text-slate-500 uppercase ml-1">Źródło Kontaktu *</label>
              <select
                v-model="form.source"
                class="form-input font-medium text-slate-700 bg-white border-slate-300 focus:border-primary focus:ring-primary rounded-lg"
                :class="{ 'border-rose-500 ring-1 ring-rose-500': wasValidated && !form.source }"
              >
                <option v-for="src in contactSources" :key="src" :value="src">{{ src }}</option>
              </select>
            </div>
            <div class="space-y-1">
              <label class="text-xs font-bold text-slate-500 uppercase ml-1">
                {{ isEditing ? 'Data aktualizacji' : 'Data i Godzina *' }}
              </label>
              <div class="flex gap-2">
                <input
                  ref="dateInput"
                  v-model="form.activityDate"
                  type="datetime-local"
                  class="form-input flex-1 border-slate-300 focus:border-primary focus:ring-primary rounded-lg"
                  :class="{ 'border-rose-500 ring-1 ring-rose-500': wasValidated && !isEditing && !form.activityDate }"
                />
                <button
                  type="button"
                  @click="(dateInput as HTMLInputElement | null)?.blur()"
                  class="bg-emerald-600 text-white px-6 rounded-lg font-bold hover:bg-emerald-700 transition-all shadow-sm active:scale-95 flex items-center justify-center shrink-0 text-sm"
                >
                  OK
                </button>
              </div>
            </div>
            <div class="space-y-1 md:col-span-2">
              <label class="text-xs font-bold text-slate-500 uppercase ml-1">
                {{ isEditing ? 'Notatka z kontaktu' : 'Cel / Notatki' }}
              </label>
              <textarea
                v-model="form.activityNotes"
                rows="3"
                :placeholder="isEditing ? 'Wprowadź notatkę z ostatniego kontaktu lub aktualizację...' : 'Opisz cel spotkania lub dodaj ważne uwagi...'"
                class="form-input resize-none border-slate-300 focus:border-primary focus:ring-primary rounded-lg"
              ></textarea>
            </div>
          </div>
        </div>

      </div>

      <!-- Footer -->
      <div class="p-6 bg-slate-50 border-t border-slate-100 flex justify-end gap-3 sticky bottom-0 z-10 rounded-b-card">
        <button
          @click="emit('close')"
          class="px-5 py-2.5 rounded-lg font-bold text-slate-600 hover:bg-slate-200 transition-all text-sm"
        >
          Anuluj
        </button>
        <button
          @click="handleSave"
          :disabled="isSubmitting"
          class="bg-primary hover:bg-primary-dark text-white px-8 py-2.5 rounded-lg font-bold transition-all shadow-lg hover:shadow-xl disabled:opacity-50 flex items-center gap-2 text-sm"
        >
          <AppIcon v-if="!isSubmitting" name="check" class="w-4 h-4" />
          <div v-else class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
          {{ isEditing ? 'Zapisz Zmiany' : 'Zapisz Spotkanie' }}
        </button>
      </div>

    </div>
  </div>
</template>
