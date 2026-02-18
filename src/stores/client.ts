import { defineStore, storeToRefs } from 'pinia'
import { computed, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useDataStore } from '@/stores/data'
import { useNotificationStore } from '@/stores/notification'
import { useSessionStore } from '@/stores/session'
import { useGamificationStore } from '@/stores/gamification'
import { useToastStore } from '@/stores/toast'
import { useFinanceStore } from '@/stores/finance'
import { api } from '@/api/client'
import type { Client, SavedOffer, ClientActivity } from '@/types/models'

type ApiClient = {
  id: number | string
  name?: string
  nip?: string
  city?: string
  address_line1?: string
  address_line2?: string | null
  postal_code?: string
  email?: string | null
  phone?: string | null
  employee_count?: number | null
  created_at?: string
  updated_at?: string
}

type ApiMeeting = {
  id: number | string
  client_id: number | string
  user_id: number | string
  status?: 'open' | 'completed' | 'expired'
  calculation_shown?: boolean | null
  offer_status?: 'preparing' | 'generated' | 'sent' | null
  valid_until?: string | null
  resume_at?: string | null
  paused_at?: string | null
  created_at?: string
  updated_at?: string
  user?: { id: number | string; name?: string; keycloak_id?: string | null }
}

export const useClientStore = defineStore('client', () => {
  const auth = useAuthStore()
  const data = useDataStore()
  const notify = useNotificationStore()
  const session = useSessionStore()
  const gamification = useGamificationStore()
  const toast = useToastStore()
  const finance = useFinanceStore()

  const { clients: dataClients, users } = storeToRefs(data)
  const { notifications } = storeToRefs(notify)
  const { currentUser } = storeToRefs(session)
  const apiClients = ref<ApiClient[]>([])
  const apiMeetings = ref<ApiMeeting[]>([])
  const apiActivities = ref<any[]>([])
  const apiSavedOffers = ref<any[]>([])
  const clientPage = ref(1)
  const clientPagination = ref({
    currentPage: 1,
    lastPage: 1,
    total: 0,
    perPage: 50,
  })

  const extractApiList = (payload: any): any[] => {
    if (Array.isArray(payload)) return payload
    if (Array.isArray(payload?.data)) return payload.data
    return []
  }

  const mapMeetingToActivity = (meeting: ApiMeeting): ClientActivity => {
    const resumeAt = meeting.resume_at || null
    const now = new Date()
    const resumeDate = resumeAt ? new Date(resumeAt) : null
    const isPaused = meeting.status === 'open' && resumeDate && resumeDate.getTime() > now.getTime()
    const description = isPaused
      ? `Spotkanie wstrzymane (wznowienie: ${resumeDate.toLocaleString('pl-PL')})`
      : `Spotkanie (${meeting.status || 'open'})`
    return {
      id: `meeting-${meeting.id}`,
      type: 'MEETING',
      description,
      date: resumeAt || meeting.updated_at || meeting.created_at || new Date().toISOString(),
      authorId: meeting.user?.keycloak_id ? String(meeting.user.keycloak_id) : meeting.user_id ? String(meeting.user_id) : '',
      resumeAt,
    }
  }

  const mapApiActivity = (activity: any): ClientActivity => ({
    id: `activity-${activity.id}`,
    type: activity.type,
    description: activity.description,
    date: activity.occurred_at || activity.created_at || new Date().toISOString(),
    authorId: activity.user?.keycloak_id ? String(activity.user.keycloak_id) : String(activity.user_id || ''),
    isCompleted: !!activity.is_completed,
  })

  const mapMeetingToStatus = (meeting?: ApiMeeting): Client['status'] => {
    if (!meeting) return 'NEW'
    if (meeting.offer_status === 'preparing') return 'OFFER_PREPARING'
    if (meeting.offer_status === 'generated') return 'OFFER_GENERATED'
    if (meeting.offer_status === 'sent') return 'CALCULATION_SENT'
    if (meeting.status === 'open') return 'IN_TALKS'
    if (meeting.status === 'completed') return meeting.calculation_shown ? 'CALCULATION_SENT' : 'IN_TALKS'
    if (meeting.status === 'expired') return 'RESIGNED'
    return 'NEW'
  }

  const buildApiClients = (): Client[] => {
    const meetingsByClient = new Map<string, ApiMeeting[]>()
    apiMeetings.value.forEach((meeting) => {
      const key = String(meeting.client_id)
      const list = meetingsByClient.get(key) || []
      list.push(meeting)
      meetingsByClient.set(key, list)
    })

    return apiClients.value.map((client) => {
      const meetings = meetingsByClient.get(String(client.id)) || []
      meetings.sort((a, b) => {
        const timeA = new Date(a.updated_at || a.created_at || 0).getTime()
        const timeB = new Date(b.updated_at || b.created_at || 0).getTime()
        return timeB - timeA
      })
      const latestMeeting = meetings[0]
      const activityHistory = [
        ...meetings.map(mapMeetingToActivity),
        ...apiActivities.value
          .filter((act) => String(act.client_id) === String(client.id))
          .map(mapApiActivity),
      ].sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime())
      const lastActionDate =
        latestMeeting?.updated_at ||
        latestMeeting?.created_at ||
        client.updated_at ||
        client.created_at ||
        new Date().toISOString()

      const profile = (client as any).crm_profile || {}
      const profileOwnerKeycloak = profile.owner?.keycloak_id ? String(profile.owner.keycloak_id) : null

      const savedOffers = apiSavedOffers.value
        .filter((offer) => String(offer.client_id) === String(client.id))
        .map((offer) => ({
          id: String(offer.id),
          date: offer.created_at || new Date().toISOString(),
          employeesUop: Number(offer.employees_uop || 0),
          avgWageUop: Number(offer.avg_wage_uop || 0),
          employeesUz: Number(offer.employees_uz || 0),
          estimatedSavings: Number(offer.estimated_savings || 0),
          name: offer.name,
        }))

      return {
        id: String(client.id),
        name: client.name || '',
        nip: client.nip || '',
        status: profile.status || mapMeetingToStatus(latestMeeting),
        ownerId: profileOwnerKeycloak
          ? profileOwnerKeycloak
          : profile.owner_user_id
            ? String(profile.owner_user_id)
          : latestMeeting?.user?.keycloak_id
            ? String(latestMeeting.user.keycloak_id)
            : latestMeeting?.user_id
              ? String(latestMeeting.user_id)
              : '',
        meetingId: latestMeeting?.id ? String(latestMeeting.id) : undefined,
        meetingStatus: latestMeeting?.status,
        meetingValidUntil: latestMeeting?.valid_until || null,
        meetingResumeAt: latestMeeting?.resume_at || null,
        contactName: profile.contact_name || client.name || '',
        contactPhone: profile.contact_phone || client.phone || '',
        contactEmail: profile.contact_email || client.email || '',
        street: client.address_line1 || '',
        buildingNr: client.address_line2 || '',
        zip: client.postal_code || '',
        city: client.city || '',
        employeesTotal: profile.employees_total ?? client.employee_count ?? 0,
        employeesUop: profile.employees_uop ?? 0,
        employeesUz: profile.employees_uz ?? 0,
        avgWageUop: profile.avg_wage_uop ?? 0,
        avgWageUz: profile.avg_wage_uz ?? 0,
        source: profile.source || '',
        industry: profile.industry || (client as any).industry || '',
        companySize: profile.company_size || '',
        contactPosition: profile.contact_position || '',
        isDecisionMaker: !!profile.is_decision_maker,
        lastActionDate,
        serviceFeePercent: profile.service_fee_percent ?? 0,
        offerSentDate: profile.offer_sent_date || undefined,
        contractSignedDate: profile.contract_signed_date || undefined,
        reservationEndDate: profile.reservation_end_date || latestMeeting?.valid_until || null,
        analysis: profile.analysis_json || undefined,
        savedOffers,
        activityHistory,
      }
    })
  }

  const clients = computed<Client[]>(() => (auth.enabled ? buildApiClients() : dataClients.value))

  const prospects = computed(() => {
    return clients.value.filter(c => c.status !== 'SIGNED' && c.status !== 'TERMINATED')
  })

  const customers = computed(() => {
    return clients.value.filter(c => c.status === 'SIGNED' || c.status === 'TERMINATED')
  })

  const fetchClients = async (options?: { perPage?: number; page?: number }) => {
    if (!auth.enabled) return
    if (!auth.isAuthenticated) return // Ensure we are authenticated

    try {
      const perPage = options?.perPage || clientPagination.value.perPage
      const page = options?.page || clientPage.value
      const { data } = await api.get('/v1/clients', { params: { per_page: perPage, page } })
      apiClients.value = extractApiList(data) as ApiClient[]
      clientPage.value = data?.current_page || page
      clientPagination.value = {
        currentPage: data?.current_page || page,
        lastPage: data?.last_page || 1,
        total: data?.total || apiClients.value.length,
        perPage: data?.per_page || perPage,
      }
    } catch(e) {
      console.error('Failed to fetch clients', e)
    }
  }

  const fetchMeetings = async (options?: { perPage?: number }) => {
    if (!auth.enabled) return
    if (!auth.isAuthenticated) return
    try {
      const { data } = await api.get('/v1/meetings', { params: { per_page: options?.perPage || 500 } })
      apiMeetings.value = extractApiList(data) as ApiMeeting[]
    } catch(e) { console.error('Failed to fetch meetings', e) }
  }

  const fetchActivities = async (options?: { perPage?: number }) => {
    if (!auth.enabled) return
    if (!auth.isAuthenticated) return
    try {
      const { data } = await api.get('/v1/crm-client-activities', { params: { per_page: options?.perPage || 500 } })
      apiActivities.value = extractApiList(data)
    } catch(e) { console.error('Failed to fetch activities', e) }
  }

  const fetchSavedOffers = async (options?: { perPage?: number }) => {
    if (!auth.enabled) return
    if (!auth.isAuthenticated) return
    try {
      const { data } = await api.get('/v1/crm-saved-offers', { params: { per_page: options?.perPage || 500 } })
      apiSavedOffers.value = extractApiList(data)
    } catch(e) { console.error('Failed to fetch saved offers', e) }
  }

  const refreshApiData = async () => {
    if (!auth.enabled) return true

    const tasks = [
      { key: 'clients', run: () => fetchClients({ page: clientPage.value }), reset: () => { apiClients.value = [] } },
      { key: 'meetings', run: () => fetchMeetings(), reset: () => { apiMeetings.value = [] } },
      { key: 'activities', run: () => fetchActivities(), reset: () => { apiActivities.value = [] } },
      { key: 'savedOffers', run: () => fetchSavedOffers(), reset: () => { apiSavedOffers.value = [] } },
    ] as const

    const results = await Promise.allSettled(tasks.map((task) => task.run()))
    let criticalFailure = false

    results.forEach((result, index) => {
      if (result.status === 'rejected') {
        const task = tasks[index]
        task.reset()

        if (task.key === 'clients') {
          criticalFailure = true
          toast.error('Nie udało się pobrać listy klientów. Spróbuj ponownie.')
        } else {
          console.warn(`[clientStore] Nie udało się pobrać ${task.key}:`, result.reason)
        }
      }
    })

    return !criticalFailure
  }

  watch(
    () => auth.isAuthenticated,
    (isAuthed) => {
      if (!auth.enabled) return
      if (isAuthed) {
        refreshApiData()
      } else {
        apiClients.value = []
        apiMeetings.value = []
        apiActivities.value = []
        apiSavedOffers.value = []
      }
    },
    { immediate: true }
  )

  const createClient = (clientData: Omit<Client, 'id' | 'lastActionDate' | 'savedOffers' | 'activityHistory'>) => {
    if (auth.enabled) {
      return api.post('/v1/clients', {
        nip: clientData.nip,
        name: clientData.name,
        address_line1: clientData.street,
        address_line2: clientData.buildingNr,
        postal_code: clientData.zip,
        city: clientData.city,
        email: clientData.contactEmail || null,
        phone: clientData.contactPhone || null,
        employee_count: clientData.employeesTotal || 0,
      }).then(async ({ data: created }) => {
        await api.post('/v1/crm-client-profiles', {
          client_id: created.id,
          owner_user_id: clientData.ownerId || null,
          status: clientData.status,
          contact_name: clientData.contactName,
          contact_phone: clientData.contactPhone,
          contact_email: clientData.contactEmail,
          employees_total: clientData.employeesTotal,
          employees_uop: clientData.employeesUop,
          employees_uz: clientData.employeesUz,
          avg_wage_uop: clientData.avgWageUop,
          avg_wage_uz: clientData.avgWageUz,
          service_fee_percent: clientData.serviceFeePercent,
          reservation_end_date: clientData.reservationEndDate || null,
        })
        await refreshApiData()
        return created
      })
    }

    const existingReserved = dataClients.value.find(
      (client) => client.nip === clientData.nip && client.reservationEndDate && new Date(client.reservationEndDate) > new Date()
    )

    if (existingReserved && existingReserved.ownerId !== clientData.ownerId) {
      const owner = users.value.find((user) => user.id === existingReserved.ownerId)
      const endDate = new Date(existingReserved.reservationEndDate!).toLocaleDateString()
      toast.error(`NIP jest zarezerwowany dla ${owner?.name || 'innego handlowca'} do ${endDate}.`)
      return null
    }

    const newClient: Client = {
      ...clientData,
      id: Math.random().toString(36).substr(2, 9),
      lastActionDate: new Date().toISOString(),
      savedOffers: [],
      activityHistory: [],
    }

    data.rawAddClient(newClient)
    addContactToOwnerAddressBook(newClient)
    gamification.recalculateRanks()
    data.logAction(clientData.ownerId, 'ADD_CLIENT', `Dodano klienta ${clientData.name}`, newClient.id)

    return newClient
  }

  const updateClient = (id: string, partial: Partial<Client>) => {
    if (auth.enabled) {
      const apiClient = apiClients.value.find((item) => String(item.id) === id)
      if (!apiClient) {
        toast.error('Nie znaleziono klienta w API.')
        return
      }
      const profile = (apiClient as any).crm_profile
      const payload: any = { client_id: apiClient.id }
      if (partial.status) {
        payload.status = partial.status
        if (partial.status === 'SIGNED') payload.contract_signed_date = new Date().toISOString().slice(0, 10)
        if (partial.status === 'CALCULATION_SENT') payload.offer_sent_date = new Date().toISOString().slice(0, 10)
        if (partial.status === 'RESIGNED') payload.reservation_end_date = null
      }
      if (typeof partial.serviceFeePercent === 'number') payload.service_fee_percent = partial.serviceFeePercent
      if (typeof partial.employeesTotal === 'number') payload.employees_total = partial.employeesTotal
      if (typeof partial.employeesUop === 'number') payload.employees_uop = partial.employeesUop
      if (typeof partial.employeesUz === 'number') payload.employees_uz = partial.employeesUz
      if (typeof partial.avgWageUop === 'number') payload.avg_wage_uop = partial.avgWageUop
      if (typeof partial.avgWageUz === 'number') payload.avg_wage_uz = partial.avgWageUz
      if (partial.contactName) payload.contact_name = partial.contactName
      if (partial.contactPhone) payload.contact_phone = partial.contactPhone
      if (partial.contactEmail) payload.contact_email = partial.contactEmail
      if (partial.analysis) payload.analysis_json = partial.analysis

      const request = profile
        ? api.patch(`/v1/crm-client-profiles/${profile.id}`, payload)
        : api.post('/v1/crm-client-profiles', payload)
      request.then(refreshApiData).catch(() => toast.error('Nie udało się zaktualizować klienta.'))
      return
    }

    const client = dataClients.value.find((item) => item.id === id)
    if (!client) return
    const oldStatus = client.status

    const updateData: Partial<Client> = { ...partial, lastActionDate: new Date().toISOString() }

    if (partial.status && client.status === 'NEW' && ['IN_TALKS', 'OFFER_PREPARING', 'OFFER_GENERATED', 'CALCULATION_SENT'].includes(partial.status)) {
      const endDate = new Date()
      endDate.setDate(new Date().getDate() + 90)
      updateData.reservationEndDate = endDate.toISOString()
      toast.info(`Klient ${client.name} został zarezerwowany na 90 dni.`)
      data.logAction(client.ownerId, 'RESERVE_CLIENT', `Zarezerwowano klienta ${client.name} na 90 dni.`, client.id)
    }

    if (partial.status && ['SIGNED', 'TERMINATED', 'RESIGNED'].includes(partial.status)) {
      if (client.reservationEndDate) {
        updateData.reservationEndDate = null
      }
    }

    data.rawUpdateClient(id, updateData)

    if (partial.status && partial.status !== oldStatus) {
      notify.add({
        userId: client.ownerId,
        type: 'INFO',
        message: `Status klienta '${client.name}' został zmieniony na: ${partial.status}.`,
      })
    }

    const updatedClient = { ...client, ...updateData }
    addContactToOwnerAddressBook(updatedClient as Client)

    if (partial.status === 'SIGNED') {
      gamification.recalculateRanks()
    }
  }

  const addContactToOwnerAddressBook = (client: Client) => {
    const owner = users.value.find((user) => user.id === client.ownerId)
    if (owner && client.contactEmail) {
      const newContact = {
        email: client.contactEmail,
        name: client.contactName || client.name,
        clientId: client.id,
      }
      const addressBook = owner.addressBook || []
      const exists = addressBook.some((contact) => contact.email === newContact.email)
      if (!exists) {
        data.rawUpdateUser(owner.id, { addressBook: [...addressBook, newContact] })
      }
    }
  }

  const signContract = (clientId: string) => {
    const client = clients.value.find((item) => item.id === clientId)
    if (!client) return

    updateClient(clientId, {
      status: 'SIGNED',
      contractSignedDate: new Date().toISOString(),
    })

    const firstInvoiceServiceFee = 500
    finance.createInvoiceForClient(clientId, firstInvoiceServiceFee, 0)
    toast.success('Umowa podpisana. Wygenerowano fakturę za aktywację.')

    if (currentUser.value) {
      data.logAction(currentUser.value.id, 'SIGN_CONTRACT', 'Zarejestrowano podpisanie umowy i wygenerowano 1. FV', clientId)
    }
  }

  const saveOffer = (clientId: string, offer: Omit<SavedOffer, 'id' | 'date'>) => {
    if (auth.enabled) {
      api.post('/v1/crm-saved-offers', {
        client_id: clientId,
        name: offer.name,
        employees_uop: offer.employeesUop,
        avg_wage_uop: offer.avgWageUop,
        employees_uz: offer.employeesUz,
        estimated_savings: offer.estimatedSavings,
      }).then(() => fetchSavedOffers()).catch(() => toast.error('Nie udało się zapisać wariantu.'))
      return
    }
    const newOffer: SavedOffer = {
      ...offer,
      id: Math.random().toString(36).substr(2, 9),
      date: new Date().toISOString(),
    }

    const client = clients.value.find((item) => item.id === clientId)
    if (client) {
      data.rawUpdateClient(clientId, {
        savedOffers: [newOffer, ...(client.savedOffers || [])],
        lastActionDate: new Date().toISOString(),
      })
    }
  }

  const addActivity = async (clientId: string, activity: Omit<ClientActivity, 'id' | 'date'>, customDate?: string) => {
    if (auth.enabled) {
      const dateToUse = customDate || new Date().toISOString()
      try {
        const isUuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(activity.authorId)
        // const userIdPayload = isUuid ? { user_keycloak_id: activity.authorId } : { user_id: activity.authorId }

        if (activity.type === 'MEETING') {
             await api.post('/v1/meetings', {
                client_id: clientId,
                user_id: isUuid ? undefined : activity.authorId,
                user_keycloak_id: isUuid ? activity.authorId : undefined,
                status: 'open',
                offer_status: 'preparing',
                resume_at: dateToUse,
                valid_until: new Date(new Date(dateToUse).getTime() + 14 * 24 * 60 * 60 * 1000).toISOString(),
             })
        } else {
             await api.post('/v1/crm-client-activities', {
                client_id: clientId,
                user_id: activity.authorId, // Activities controller handles both via resolveUserId
                type: activity.type,
                description: activity.description,
                occurred_at: dateToUse,
             })
        }
        await fetchActivities()
        await fetchMeetings()
        toast.success('Dodano aktywność')
      } catch(e: any) { 
        // Fallback: gdy klient ma już otwarte spotkanie (HTTP 409), zapisz jako zwykłą aktywność MEETING
        const status = e?.response?.status
        const msg = e?.response?.data?.message as string | undefined
        if (activity.type === 'MEETING' && status === 409) {
          try {
            await api.post('/v1/crm-client-activities', {
              client_id: clientId,
              user_id: activity.authorId,
              type: 'MEETING',
              description: activity.description,
              occurred_at: dateToUse,
            })
            await fetchActivities()
            await fetchMeetings()
            toast.info('Klient ma już otwarte spotkanie – zapisano jako aktywność.')
            return
          } catch (fallbackErr) {
            console.error('Fallback activity save failed', fallbackErr)
          }
        }

        console.error('Failed to add activity', e)
        toast.error(msg || 'Nie udało się dodać aktywności.')
      }
      return
    }

    const dateToUse = customDate || new Date().toISOString()

    const newActivity: ClientActivity = {
      ...activity,
      id: Math.random().toString(36).substr(2, 9),
      date: dateToUse,
    }

    const client = dataClients.value.find((item) => item.id === clientId)
    if (client) {
      const newHistory = [newActivity, ...(client.activityHistory || [])].sort(
        (a, b) => new Date(b.date).getTime() - new Date(a.date).getTime()
      )

      data.rawUpdateClient(clientId, {
        lastActionDate: new Date() > new Date(dateToUse) ? new Date().toISOString() : client.lastActionDate,
        activityHistory: newHistory,
      })

      data.logAction(activity.authorId, 'ADD_ACTIVITY', `Dodano aktywność ${activity.type} dla klienta ${client.name}`, clientId)

      const activityTypePolish: Record<string, string> = {
        CALL: 'rozmowę tel.',
        MEETING: 'spotkanie',
        EMAIL: 'e-mail',
        NOTE: 'notatkę',
      }

      notify.add({
        userId: activity.authorId,
        type: 'INFO',
        message: `Zarejestrowano działanie: dodano ${activityTypePolish[activity.type] || 'aktywność'} dla klienta '${client.name}'.`,
      })
    }
  }

  const checkSla = () => {
    const now = new Date()
    const threeDaysMs = 3 * 24 * 60 * 60 * 1000

    const list = Array.isArray(clients.value) ? clients.value : []
    list.forEach((client) => {
      if (client.status === 'NEW' || client.status === 'IN_TALKS') {
        const lastAction = new Date(client.lastActionDate).getTime()
        if (now.getTime() - lastAction > threeDaysMs) {
          const exists = notifications.value.some(
            (n) => n.userId === client.ownerId && n.type === 'CRITICAL' && n.message.includes(client.name)
          )
          if (!exists) {
            notify.add({ userId: client.ownerId, type: 'CRITICAL', message: `Brak działania na kliencie ${client.name} od 3 dni!` })
          }
        }
      }
    })
  }

  const checkReservations = () => {
    const now = new Date()
    const sevenDaysMs = 7 * 24 * 60 * 60 * 1000

    const list = Array.isArray(clients.value) ? clients.value : []
    list.forEach((client) => {
      if (client.reservationEndDate) {
        const endDate = new Date(client.reservationEndDate).getTime()
        const diff = endDate - now.getTime()
        if (diff > 0 && diff <= sevenDaysMs) {
          const daysLeft = Math.ceil(diff / (1000 * 60 * 60 * 24))
          const message = `Rezerwacja NIP dla klienta ${client.name} wygasa za ${daysLeft} dni.`

          const exists = notifications.value.some(
            (n) => n.userId === client.ownerId && n.type === 'WARNING' && n.message.includes(client.name) && n.message.includes('wygasa')
          )

          if (!exists) {
            notify.add({ userId: client.ownerId, type: 'WARNING', message })
          }
        }
      }
    })
  }

  const removeActivity = async (clientId: string, activityId: string) => {
    if (auth.enabled) {
      const idStr = String(activityId)
      try {
        if (idStr.startsWith('meeting-')) {
          const id = idStr.replace('meeting-', '')
          await api.delete(`/v1/meetings/${id}`)
        } else if (idStr.startsWith('activity-')) {
          const id = idStr.replace('activity-', '')
          await api.delete(`/v1/crm-client-activities/${id}`)
        } else {
          console.warn('Nieznany format ID:', idStr)
          throw new Error('Unknown ID format')
        }
        await refreshApiData()
        toast.success('Usunięto zdarzenie')
      } catch (e) {
        console.error('Remove activity error:', e)
        toast.error('Nie udało się usunąć zdarzenia')
      }
      return
    }

    const client = dataClients.value.find((item) => item.id === clientId)
    if (client && client.activityHistory) {
      const newHistory = client.activityHistory.filter((a) => a.id !== activityId)
      data.rawUpdateClient(clientId, {
        activityHistory: newHistory,
      })
      toast.success('Usunięto zdarzenie')
    }
  }

  const updateActivity = async (clientId: string, activity: ClientActivity) => {
    if (auth.enabled) {
      const idStr = String(activity.id)
      try {
        if (idStr.startsWith('meeting-')) {
          const id = idStr.replace('meeting-', '')
          const updatePayload: any = {
             resume_at: activity.date,
          }
          if (activity.isCompleted !== undefined) {
             updatePayload.status = activity.isCompleted ? 'completed' : 'open'
          }
          await api.patch(`/v1/meetings/${id}`, updatePayload)
        } else if (idStr.startsWith('activity-')) {
          const id = idStr.replace('activity-', '')
          await api.patch(`/v1/crm-client-activities/${id}`, {
            type: activity.type,
            description: activity.description,
            occurred_at: activity.date,
            user_id: activity.authorId,
            is_completed: activity.isCompleted
          })
        }
        await refreshApiData()
        toast.success('Zaktualizowano zdarzenie')
      } catch (e) {
        console.error('Update activity error:', e)
        toast.error('Nie udało się zaktualizować zdarzenia')
      }
      return
    }

    const client = dataClients.value.find((item) => item.id === clientId)
    if (client) {
        const activityHistory = client.activityHistory || []
        const index = activityHistory.findIndex(a => a.id === activity.id)
        if (index !== -1) {
            const newHistory = [...activityHistory]
            newHistory[index] = { ...newHistory[index], ...activity }
             data.rawUpdateClient(clientId, {
                activityHistory: newHistory,
              })
             toast.success('Zaktualizowano zdarzenie')
        }
    }
  }

  watch(() => auth.isAuthenticated, (newVal) => {
    if (newVal) {
      fetchClients()
      fetchMeetings()
      fetchActivities()
      fetchSavedOffers()
    }
  })

  return {
    clients,
    clientPage,
    clientPagination,
    createClient,
    updateClient,
    signContract,
    saveOffer,
    addActivity,
    removeActivity,
    updateActivity,
    checkSla,
    checkReservations,
    fetchClients,
    fetchMeetings,
    fetchActivities,
    fetchSavedOffers,
    refreshApiData,
    prospects,
    customers,
  }
})
