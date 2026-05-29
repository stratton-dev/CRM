<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useRouter, useRoute } from 'vue-router'
import { useToastStore } from '@/stores/toast'
import { useAuthStore } from '@/stores/auth'
import { useSessionStore } from '@/stores/session'
import { useMailboxStore } from '@/stores/mailbox'
import { useClientStore } from '@/stores/client'
import { useCalculatorStore } from '@/components/calculator/store/useCalculatorStore'
import { api } from '@/api/client'

const router = useRouter()
const route = useRoute()
const toast = useToastStore()
const auth = useAuthStore()
const session = useSessionStore()
const mailbox = useMailboxStore()
const clientStore = useClientStore()
const calculatorStore = useCalculatorStore()
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
const attachmentSummary = ref<Array<{ name: string }>>([])

const meetingId = computed(() => {
  const raw = route.query.meetingId
  return Array.isArray(raw) ? raw[0] : raw
})

const clientIdFromQuery = computed(() => {
  const raw = route.query.clientId
  return Array.isArray(raw) ? raw[0] : raw
})

const templateKey = computed(() => {
  const raw = route.query.template
  return Array.isArray(raw) ? raw[0] : raw
})

const decisionEmailFromQuery = computed(() => {
  const raw = route.query.decisionEmail
  return Array.isArray(raw) ? raw[0] : raw
})

const decisionNameFromQuery = computed(() => {
  const raw = route.query.decisionName
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
3. Wdrożenie rozwiązania Eliton Prime™ pozwoli nie tylko na oszczędności, ale również na zwiększenie realnego wynagrodzenia pracowników.

W załączeniu przesyłam szczegółową ofertę oraz kalkulację w formacie PDF.

Jestem do dyspozycji w przypadku pytań. Kiedy moglibyśmy umówić się na krótkie spotkanie wdrożeniowe, aby omówić formalności?

Z poważaniem,
Zespół Stratton
`
}

const buildUserSignature = () => {
  const user = session.currentUser
  if (!user) return ''
  const lines = [user.name, user.phone || '', user.email || ''].filter((line) => line && String(line).trim().length > 0)
  return lines.join('\n')
}

const buildOfferCalculatorContent = () => {
  const signature = buildUserSignature()
  return `Zgodnie z ustaleniami podjętymi podczas naszej rozmowy przesyłam na wskazany adres mailowy omówione informacje. Jednocześnie informuję o prawie do odwołania zgody na przekazywanie kolejnych informacji na ten adres mailowy.

Przygotowane dokumenty są dostępne w załączeniu do tej wiadomości.

Pozdrawiam
${signature || 'Zespół Stratton'}
`
}

const setDefaultContent = () => {
  if (hasPrefilled.value || content.value.trim()) return
  if (templateKey.value === 'offer-calculator') {
    content.value = buildOfferCalculatorContent()
  } else {
    content.value = buildEmailContent(clientData.value?.contactName || null)
  }
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
    if (!clientData.value.contactEmail) {
      try {
        const { data: contactsData } = await api.get(`/v1/clients/${clientId}/contacts`)
        const contacts = Array.isArray(contactsData?.data) ? contactsData.data : Array.isArray(contactsData) ? contactsData : []
        const decision = contacts.find((c: any) => c?.is_decision_maker && c?.email)
        const fallback = contacts.find((c: any) => c?.email)
        const resolvedEmail = decision?.email || fallback?.email || null
        if (resolvedEmail) {
          clientData.value = {
            ...clientData.value,
            contactEmail: resolvedEmail,
          }
        }
      } catch (error) {
        // ignore contact lookup failures
      }
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
    emailTo.value = decisionEmailFromQuery.value || clientData.value.contactEmail || ''
  }
  if (subject.value === defaultSubject && clientData.value.name) {
    if (templateKey.value === 'offer-calculator') {
      subject.value = `Oferta i kalkulator - ${clientData.value.name}`
    } else {
      subject.value = `${defaultSubject} - ${clientData.value.name}`
    }
  }
  setDefaultContent()
}

const resolveClient = async () => {
  // Meetings are no longer the canonical source. Email compose now relies
  // on ?client_id= coming from ProcessStart / calculator / clients list.
  // Anything else falls through to the empty-state block below.
  const queryClientId = clientIdFromQuery.value
  if (queryClientId) {
    resolvedClientId.value = String(queryClientId)
  }

  if (!resolvedClientId.value) {
    if (decisionEmailFromQuery.value && (!emailTo.value || emailTo.value === defaultRecipient)) {
      emailTo.value = decisionEmailFromQuery.value
    }
    if (templateKey.value !== 'offer-calculator' && decisionNameFromQuery.value && !content.value.trim()) {
      content.value = buildEmailContent(decisionNameFromQuery.value)
      hasPrefilled.value = true
    }
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
  if (templateKey.value === 'offer-calculator') {
    calculatorStore.setContext({
      meetingId: meetingId.value ? String(meetingId.value) : null,
      clientId: clientIdFromQuery.value ? String(clientIdFromQuery.value) : null,
    })
    calculatorStore.buildOfferEmailAttachments().then((payload) => {
      if (!payload) return
      const list = [
        { name: payload.offerFileName },
        payload.excelBase64 ? { name: payload.excelFileName } : null,
      ].filter(Boolean) as Array<{ name: string }>
      attachmentSummary.value = list
    })
  }
})

const cancel = () => {
  router.push('/app/dashboard')
}

const updateClientProfileStatus = async (status: string) => {
  if (!auth.enabled || !resolvedClientId.value) return
  try {
    const { data } = await api.get('/v1/crm-client-profiles', { params: { client_id: resolvedClientId.value, per_page: 1 } })
    const list = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
    const profile = list.length ? list[0] : null

    // Ustawiamy datę końca rezerwacji na +30 dni od teraz
    const reservationEndDate = new Date()
    reservationEndDate.setDate(reservationEndDate.getDate() + 30)
    const reservationEndDateStr = reservationEndDate.toISOString().split('T')[0]

    const payload: Record<string, any> = {
      client_id: resolvedClientId.value,
      owner_user_id: session.currentUser?.id || null,
      status,
      reservation_end_date: reservationEndDateStr,
    }
    if (profile?.id) {
      await api.patch(`/v1/crm-client-profiles/${profile.id}`, payload)
    } else {
      await api.post('/v1/crm-client-profiles', payload)
    }
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się zaktualizować statusu klienta.'
    toast.warning(message)
  }
}

const sendEmail = async () => {
  // Meeting offer-status tracking removed — the calculation row carries
  // the canonical state now (status SENT after this send) and the
  // client's activity log records the email itself.

  const user = session.currentUser
  if (!user) {
    toast.error('Brak aktywnego użytkownika.')
    return
  }
  if (!emailTo.value || !subject.value) {
    toast.warning('Uzupełnij adres i temat wiadomości.')
    return
  }
  try {
    let attachments: Array<{ filename: string; content?: string; content_type?: string; encoding?: string; html?: string; convert_to_pdf?: boolean }> | undefined
    if (templateKey.value === 'offer-calculator') {
      const offerPayload = await calculatorStore.buildOfferEmailAttachments()
      if (!offerPayload) {
        toast.warning('Brak danych oferty do załączenia.')
      } else {
        attachments = [
          {
            filename: offerPayload.offerFileName,
            html: offerPayload.offerHtml,
            convert_to_pdf: true,
          },
        ]
        if (offerPayload.excelBase64) {
          attachments.push({
            filename: offerPayload.excelFileName,
            content: offerPayload.excelBase64,
            content_type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            encoding: 'base64',
          })
        }
      }
    }
    await mailbox.sendEmail(user, emailTo.value, subject.value, content.value, attachments)
    await updateClientProfileStatus('IN_TALKS')
    toast.success('Pomyślnie wysłano ofertę!')
  } catch (error: any) {
    const message = error?.response?.data?.message || error?.message || 'Nie udało się wysłać wiadomości.'
    toast.error(message)
    return
  }

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
        <h2 class="text-xl font-bold text-gray-800 flex items-center">
          <svg class="w-6 h-6 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
          Nowa Wiadomość
        </h2>
        <button type="button" class="text-gray-400 hover:text-gray-600" @click="cancel">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
      </div>

      <div class="p-6 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-1 gap-4">
          <div>
            <label class="block text-sm font-semibold text-gray-500 mb-1">Do:</label>
            <div class="relative">
              <input v-model="emailTo" type="email" class="w-full border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 pl-8 text-base py-3" />
              <svg class="w-5 h-5 absolute left-2.5 top-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
            </div>
          </div>
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-500 mb-1">Temat:</label>
          <input v-model="subject" type="text" class="w-full border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 font-medium text-gray-700 bg-gray-50 focus:bg-white transition-colors py-3 text-base" />
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-500 mb-2">Załączniki:</label>
          <div class="text-sm text-gray-500">
            <template v-if="attachmentSummary.length">
              <ul class="list-disc pl-4">
                <li v-for="file in attachmentSummary" :key="file.name">{{ file.name }}</li>
              </ul>
            </template>
            <template v-else>
              {{ isLoadingClient ? 'Ładowanie danych klienta...' : 'Brak załączników.' }}
            </template>
          </div>
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-500 mb-1">Treść wiadomości:</label>
          <textarea v-model="content" rows="12" class="w-full border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 text-base leading-relaxed p-4 font-sans text-gray-700"></textarea>
        </div>
      </div>

      <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end space-x-3">
        <button type="button" class="px-5 py-2.5 bg-white border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 transition-colors text-base" @click="cancel">
          Anuluj
        </button>
        <button type="button" class="px-8 py-2.5 bg-linear-to-r from-[#D4AF37] to-stratton-gold text-white rounded-xl font-bold shadow-md hover:brightness-110 hover:shadow-lg transition-all flex items-center text-base uppercase tracking-wider" @click="sendEmail">
          <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
          Wyślij Ofertę
        </button>
      </div>
    </div>
  </div>
</template>
