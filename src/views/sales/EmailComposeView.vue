<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useRouter, useRoute } from 'vue-router'
import { useToastStore } from '@/stores/toast'
import { useAuthStore } from '@/stores/auth'
import { useSessionStore } from '@/stores/session'
import { useMailboxStore } from '@/stores/mailbox'
import { useClientStore } from '@/stores/client'
import { api } from '@/api/client'

const router = useRouter()
const route = useRoute()
const toast = useToastStore()
const auth = useAuthStore()
const session = useSessionStore()
const mailbox = useMailboxStore()
const clientStore = useClientStore()
const { clients } = storeToRefs(clientStore)

const defaultRecipient = 'klient@firma.pl'
const defaultSubject = 'Twoja Analiza Biznesowa + Kalkulacja Oszczędności (Stratton)'
const emailTo = ref(defaultRecipient)
const subject = ref(defaultSubject)
const content = ref('')
const isLoadingClient = ref(false)
const resolvedClientId = ref<string | null>(null)
const clientData = ref<{ id: string; name: string; contactEmail?: string | null; contactName?: string | null } | null>(null)
const hasPrefilled = ref(false)

const meetingId = computed(() => {
  const raw = route.query.meetingId
  return Array.isArray(raw) ? raw[0] : raw
})

const clientIdFromQuery = computed(() => {
  const raw = route.query.clientId
  return Array.isArray(raw) ? raw[0] : raw
})

const buildEmailContent = (contactName?: string | null) => {
  const monthly = route.query.monthlySaving || '—'
  const annual = route.query.annualSaving || '—'
  const greeting = contactName ? `Dzień dobry ${contactName},` : 'Dzień dobry,'
  return `${greeting}

W nawiązaniu do naszego dzisiejszego spotkania i przeprowadzonej analizy sytuacji w Państwa firmie, przesyłam podsumowanie oraz przygotowaną symulację oszczędności.

Kluczowe wnioski:
1. Zidentyfikowaliśmy potencjał optymalizacji kosztów ZUS na poziomie ${monthly} PLN miesięcznie.
2. W skali roku daje to oszczędność rzędu ${annual} PLN.
3. Wdrożenie rozwiązania Eliton Prime pozwoli nie tylko na oszczędności, ale również na zwiększenie realnego wynagrodzenia pracowników.

W załączeniu przesyłam szczegółową ofertę oraz kalkulację w formacie PDF.

Jestem do dyspozycji w przypadku pytań. Kiedy moglibyśmy umówić się na krótkie spotkanie wdrożeniowe, aby omówić formalności?

Z poważaniem,
Zespół Stratton
`
}

const setDefaultContent = () => {
  if (hasPrefilled.value || content.value.trim()) return
  content.value = buildEmailContent(clientData.value?.contactName || null)
  hasPrefilled.value = true
}

const resolveClientFromApi = async (clientId: string) => {
  isLoadingClient.value = true
  try {
    const { data } = await api.get(`/v1/clients/${clientId}`)
    const profile = data?.crm_profile || {}
    clientData.value = {
      id: String(data?.id || clientId),
      name: data?.name || '',
      contactEmail: profile.contact_email || data?.email || null,
      contactName: profile.contact_name || null,
    }
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się pobrać danych klienta.'
    toast.warning(message)
  } finally {
    isLoadingClient.value = false
  }
}

const resolveClientFromStore = (clientId: string) => {
  const list = Array.isArray(clients.value) ? clients.value : []
  const match = list.find((client) => client.id === clientId)
  if (match) {
    clientData.value = {
      id: match.id,
      name: match.name,
      contactEmail: match.contactEmail || null,
      contactName: match.contactName || null,
    }
  }
}

const applyRecipientDefaults = () => {
  if (!clientData.value) return
  if (!emailTo.value || emailTo.value === defaultRecipient) {
    emailTo.value = clientData.value.contactEmail || ''
  }
  if (subject.value === defaultSubject && clientData.value.name) {
    subject.value = `${defaultSubject} - ${clientData.value.name}`
  }
  setDefaultContent()
}

const resolveClient = async () => {
  const queryClientId = clientIdFromQuery.value
  if (queryClientId) {
    resolvedClientId.value = String(queryClientId)
  } else if (meetingId.value && auth.enabled) {
    try {
      const { data } = await api.get(`/v1/meetings/${meetingId.value}`)
      if (data?.client_id) resolvedClientId.value = String(data.client_id)
    } catch (error: any) {
      const message = error?.response?.data?.message || error?.message || 'Nie udało się pobrać danych spotkania.'
      toast.warning(message)
    }
  }

  if (!resolvedClientId.value) {
    setDefaultContent()
    return
  }

  if (auth.enabled) {
    await resolveClientFromApi(resolvedClientId.value)
  } else {
    resolveClientFromStore(resolvedClientId.value)
  }
  applyRecipientDefaults()
}

onMounted(() => {
  resolveClient()
})

const cancel = () => {
  router.push('/app/dashboard')
}

const sendEmail = async () => {
  if (auth.enabled && meetingId.value) {
    try {
      await api.patch(`/v1/meetings/${meetingId.value}`, { offer_status: 'sent', calculation_shown: true })
    } catch (error: any) {
      const message = error?.response?.data?.message || error?.message || 'Nie udało się oznaczyć oferty jako wysłanej.'
      toast.error(message)
      return
    }
  }

  const user = session.currentUser
  if (!user) {
    toast.error('Brak aktywnego użytkownika.')
    return
  }
  if (!emailTo.value || !subject.value) {
    toast.warning('Uzupełnij adres i temat wiadomości.')
    return
  }
  await mailbox.sendEmail(user, emailTo.value, subject.value, content.value)
  toast.success('Pomyślnie wysłano ofertę!')

  setTimeout(() => {
    if (resolvedClientId.value) {
      router.push(`/app/contract-preview/${resolvedClientId.value}`)
      return
    }
    router.push({
      path: '/app/sales/contract-preview',
      query: {
        companyName: clientData.value?.name || route.query.companyName || '',
        nip: route.query.nip || '',
        address: route.query.address || '',
        city: route.query.city || '',
        zip: route.query.zip || '',
        employees: route.query.employees || '',
        annual: route.query.annualSaving || '',
      },
    })
  }, 1500)
}
</script>

<template>
  <div class="max-w-4xl mx-auto p-6 animate-fade-in-up">
    <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200">
      <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex justify-between items-center">
        <h2 class="text-lg font-bold text-gray-800 flex items-center">
          <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
          Nowa Wiadomość
        </h2>
        <button type="button" class="text-gray-400 hover:text-gray-600" @click="cancel">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
      </div>

      <div class="p-6 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Do:</label>
            <div class="relative">
              <input v-model="emailTo" type="email" class="w-full border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 pl-8 text-sm py-2" />
              <svg class="w-4 h-4 absolute left-2.5 top-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
            </div>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">DW:</label>
            <input type="email" value="kancelaria@stratton.pl" disabled class="w-full bg-gray-50 text-gray-500 border-gray-300 rounded-md text-sm py-2" />
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1">Temat:</label>
          <input v-model="subject" type="text" class="w-full border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 font-medium text-gray-700 bg-gray-50 focus:bg-white transition-colors py-2" />
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-2">Załączniki:</label>
          <div class="text-xs text-gray-500">
            {{ isLoadingClient ? 'Ładowanie danych klienta...' : 'Brak załączników.' }}
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1">Treść wiadomości:</label>
          <textarea v-model="content" rows="12" class="w-full border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 text-sm leading-relaxed p-4 font-sans text-gray-700"></textarea>
        </div>
      </div>

      <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end space-x-3">
        <button type="button" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 transition-colors" @click="cancel">
          Anuluj
        </button>
        <button type="button" class="px-6 py-2 bg-indigo-600 text-white rounded-lg font-bold shadow-md hover:bg-indigo-700 hover:shadow-lg transition-all flex items-center" @click="sendEmail">
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
          Wyślij Ofertę
        </button>
      </div>
    </div>
  </div>
</template>
