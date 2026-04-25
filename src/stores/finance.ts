import { defineStore, storeToRefs } from 'pinia'
import { computed, ref, watch } from 'vue'
import { useDataStore } from '@/stores/data'
import { useStructureStore } from '@/stores/structure'
import { useNotificationStore } from '@/stores/notification'
import { useAuthStore } from '@/stores/auth'
import { api } from '@/api/client'
import type { Invoice, User } from '@/types/models'

export interface SettlementRow {
  invoiceNumber: string
  clientId: string
  clientName: string
  issueDate: string
  amountNet: number
  serviceFee: number
  commissionBase: number
  repCommission: number
  repId: string
  repName: string
  status: string
  isFastTrack: boolean
  daysDiff: number | null
  commissionType: 'DIRECT_FIRST' | 'DIRECT_RENEWAL' | 'OVERRIDE'
}

export const useFinanceStore = defineStore('finance', () => {
  const auth = useAuthStore()
  const data = useDataStore()
  const structure = useStructureStore()
  const notify = useNotificationStore()

  const { invoices: localInvoices, clients: localClients, users: localUsers, commissionConfig: localCommissionConfig } = storeToRefs(data)
  const { users: structureUsers } = storeToRefs(structure)

  const apiInvoices = ref<Invoice[]>([])
  const apiClients = ref<any[]>([])
  const apiMeetings = ref<any[]>([])
  const apiCommissionConfig = ref(localCommissionConfig.value)

  const clients = computed(() => (auth.enabled ? buildApiClients() : localClients.value))
  const invoices = computed(() => (auth.enabled ? apiInvoices.value : localInvoices.value))
  const users = computed(() => (auth.enabled ? structureUsers.value : localUsers.value))
  const commissionConfig = computed(() => (auth.enabled ? apiCommissionConfig.value : localCommissionConfig.value))

  const extractApiList = (payload: any): any[] => {
    if (Array.isArray(payload)) return payload
    if (Array.isArray(payload?.data)) return payload.data
    return []
  }

  const buildApiClients = () => {
    const meetingsByClient = new Map<string, any[]>()
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
      const status = (() => {
        if (!latestMeeting) return 'NEW'
        if (latestMeeting.offer_status === 'preparing') return 'OFFER_PREPARING'
        if (latestMeeting.offer_status === 'generated') return 'OFFER_GENERATED'
        if (latestMeeting.offer_status === 'sent') return 'CALCULATION_SENT'
        if (latestMeeting.status === 'open') return 'IN_TALKS'
        if (latestMeeting.status === 'completed') return latestMeeting.calculation_shown ? 'CALCULATION_SENT' : 'IN_TALKS'
        if (latestMeeting.status === 'expired') return 'RESIGNED'
        return 'NEW'
      })()

      const profile = (client as any).crm_profile || {}
      const profileOwnerSupabase = profile.owner?.supabase_id ? String(profile.owner.supabase_id) : null

      return {
        id: String(client.id),
        name: client.name || '',
        status: profile.status || status,
        ownerId: profileOwnerSupabase
          ? profileOwnerSupabase
          : profile.owner_user_id
            ? String(profile.owner_user_id)
            : latestMeeting?.user?.supabase_id
              ? String(latestMeeting.user.supabase_id)
              : latestMeeting?.user_id
                ? String(latestMeeting.user_id)
                : '',
        employeesTotal: profile.employees_total ?? client.employee_count ?? 0,
        serviceFeePercent: profile.service_fee_percent ?? 0,
        offerSentDate: profile.offer_sent_date || latestMeeting?.created_at || null,
        contractSignedDate: profile.contract_signed_date || latestMeeting?.updated_at || null,
      }
    })
  }

  const fetchApiClients = async () => {
    if (!auth.enabled) return
    const { data } = await api.get('/v1/clients', { params: { per_page: 200 } })
    apiClients.value = extractApiList(data)
  }

  const fetchApiMeetings = async () => {
    if (!auth.enabled) return
    const { data } = await api.get('/v1/meetings', { params: { per_page: 200 } })
    apiMeetings.value = extractApiList(data)
  }

  const fetchApiInvoices = async () => {
    if (!auth.enabled) return
    const { data } = await api.get('/v1/crm-invoices', { params: { per_page: 200 } })
    const list = extractApiList(data)
    apiInvoices.value = list.map((item) => ({
      id: String(item.id),
      number: item.number,
      clientId: String(item.client_id),
      issueDate: item.issue_date,
      amountNet: Number(item.amount_net),
      amountGross: Number(item.amount_gross),
      serviceFeeNet: Number(item.service_fee_net),
      status: item.status,
      pdfUrl: item.pdf_url || '#',
    }))
  }

  const fetchApiCommissionConfig = async () => {
    if (!auth.enabled) return
    const { data } = await api.get('/v1/crm-commission-config')
    apiCommissionConfig.value = {
      salesCommissionFirstMonthLt14: Number(data.sales_commission_first_month_lt14 ?? 0),
      salesCommissionFirstMonthGt14: Number(data.sales_commission_first_month_gt14 ?? 0),
      salesCommissionRenewal: Number(data.sales_commission_renewal ?? 0),
    }
  }

  const updateCommissionConfig = async (config: { salesCommissionFirstMonthLt14: number; salesCommissionFirstMonthGt14: number; salesCommissionRenewal: number }) => {
    if (auth.enabled) {
      const { data } = await api.put('/v1/crm-commission-config', {
        sales_commission_first_month_lt14: config.salesCommissionFirstMonthLt14,
        sales_commission_first_month_gt14: config.salesCommissionFirstMonthGt14,
        sales_commission_renewal: config.salesCommissionRenewal,
      })
      apiCommissionConfig.value = {
        salesCommissionFirstMonthLt14: Number(data.sales_commission_first_month_lt14 ?? 0),
        salesCommissionFirstMonthGt14: Number(data.sales_commission_first_month_gt14 ?? 0),
        salesCommissionRenewal: Number(data.sales_commission_renewal ?? 0),
      }
      return
    }
    data.updateCommissionConfig(config)
  }

  const getAdminStats = () => {
    const invoiceList = invoices.value
    const clientList = clients.value
    const userList = users.value

    const revenue = invoiceList.filter((inv) => inv.status === 'PAID').reduce((sum, inv) => sum + inv.amountNet, 0)
    const commission = invoiceList.reduce((sum, inv) => sum + inv.serviceFeeNet * 0.85, 0)
    const activeContracts = clientList.filter((client) => client.status === 'SIGNED').length
    const salesCount = userList.filter((user) => user.role === 'SALES').length

    return { revenue, commission, activeContracts, salesCount }
  }

  const getTopPerformers = () => {
    const userList = users.value
    const clientList = clients.value
    const directors = userList.filter((user) => user.role === 'DIRECTOR')

    const leaderBoard = directors.map((dir) => {
      const subIds = structure.getSubtreeUserIds(dir.id)
      const teamIds = [dir.id, ...subIds]
      const teamClients = clientList.filter((client) => teamIds.includes(client.ownerId) && client.status === 'SIGNED')
      const revenue = teamClients.reduce((sum, client) => sum + client.employeesTotal * 1000, 0)

      return {
        id: dir.id,
        name: dir.name,
        region: dir.region,
        salesCount: teamClients.length,
        revenue,
      }
    })

    return leaderBoard.sort((a, b) => b.revenue - a.revenue).slice(0, 5)
  }

  const createInvoice = async (payload: {
    number: string
    clientId: string
    issueDate: string
    amountNet: number
    amountGross: number
    serviceFeeNet: number
    status: Invoice['status']
    pdfUrl?: string
  }) => {
    if (auth.enabled) {
      await api.post('/v1/crm-invoices', {
        number: payload.number,
        client_id: payload.clientId,
        issue_date: payload.issueDate,
        amount_net: payload.amountNet,
        amount_gross: payload.amountGross,
        service_fee_net: payload.serviceFeeNet,
        status: payload.status,
        pdf_url: payload.pdfUrl || null,
      })
      return fetchApiInvoices()
    }

    const newInvoice: Invoice = {
      id: `inv_${Math.random().toString(36).substr(2, 9)}`,
      clientId: payload.clientId,
      number: payload.number,
      issueDate: payload.issueDate,
      amountNet: payload.amountNet,
      amountGross: payload.amountGross,
      serviceFeeNet: payload.serviceFeeNet,
      status: payload.status,
      pdfUrl: payload.pdfUrl || '#',
    }
    data.rawAddInvoice(newInvoice)
    return newInvoice
  }

  const updateInvoice = async (
    invoiceId: string,
    payload: {
      number: string
      clientId: string
      issueDate: string
      amountNet: number
      amountGross: number
      serviceFeeNet: number
      status: Invoice['status']
      pdfUrl?: string
    }
  ) => {
    if (auth.enabled) {
      await api.put(`/v1/crm-invoices/${invoiceId}`, {
        number: payload.number,
        client_id: payload.clientId,
        issue_date: payload.issueDate,
        amount_net: payload.amountNet,
        amount_gross: payload.amountGross,
        service_fee_net: payload.serviceFeeNet,
        status: payload.status,
        pdf_url: payload.pdfUrl || null,
      })
      return fetchApiInvoices()
    }

    data.rawUpdateInvoice(invoiceId, {
      number: payload.number,
      clientId: payload.clientId,
      issueDate: payload.issueDate,
      amountNet: payload.amountNet,
      amountGross: payload.amountGross,
      serviceFeeNet: payload.serviceFeeNet,
      status: payload.status,
      pdfUrl: payload.pdfUrl || '#',
    })
  }

  const createInvoiceForClient = (
    clientId: string,
    serviceFeeNet: number,
    totalBenefitNet: number
  ) => {
    const client = clients.value.find((item) => item.id === clientId)
    if (!client) return null

    const amountNet = totalBenefitNet + serviceFeeNet
    const amountGross = amountNet * 1.23

    const newInvoice: Invoice = {
      id: `inv_${Math.random().toString(36).substr(2, 9)}`,
      clientId,
      number: `FV/${new Date().getMonth() + 1}/${new Date().getFullYear()}/${Math.floor(Math.random() * 1000)}`,
      issueDate: new Date().toISOString(),
      amountNet,
      amountGross,
      serviceFeeNet,
      status: 'UNPAID',
      pdfUrl: '#',
    }

    if (auth.enabled) {
      return createInvoice({
        number: newInvoice.number,
        clientId,
        issueDate: newInvoice.issueDate,
        amountNet,
        amountGross,
        serviceFeeNet,
        status: newInvoice.status,
        pdfUrl: newInvoice.pdfUrl,
      })
    }

    data.rawAddInvoice(newInvoice)

    notify.add({
      userId: client.ownerId,
      type: 'INFO',
      message: `Wystawiono nowa fakture dla ${client.name} na kwote ${amountGross.toFixed(2)} PLN Brutto.`,
    })

    return newInvoice
  }

  const getSettlementsForUser = (userId: string, role: string): SettlementRow[] => {
    const allInvoices = invoices.value
    const allClients = clients.value
    const allUsers = users.value
    const cfg = commissionConfig.value
    const viewingUser = allUsers.find((user) => user.id === userId)
    if (!viewingUser) return []

    const finalRows: SettlementRow[] = []
    const processedInvoices = new Set<string>()

    const myClientIds = allClients.filter((client) => client.ownerId === userId).map((client) => client.id)
    const myInvoices = allInvoices.filter((inv) => myClientIds.includes(inv.clientId))

    myInvoices.forEach((inv) => {
      const client = allClients.find((c) => c.id === inv.clientId)!
      const owner = viewingUser
      const clientInvoices = allInvoices
        .filter((i) => i.clientId === client.id)
        .sort((a, b) => new Date(a.issueDate).getTime() - new Date(b.issueDate).getTime())
      const isFirstMonth = clientInvoices.length > 0 && clientInvoices[0].id === inv.id

      let rate = 0
      let type: SettlementRow['commissionType'] = 'DIRECT_RENEWAL'

      if (isFirstMonth) {
        type = 'DIRECT_FIRST'
        const daysDiff = getDaysDiff(client.offerSentDate, client.contractSignedDate)
        rate = daysDiff !== null && daysDiff <= 14 ? cfg.salesCommissionFirstMonthLt14 : cfg.salesCommissionFirstMonthGt14
      } else {
        rate = owner.renewalCommissionRate ?? cfg.salesCommissionRenewal
      }

      finalRows.push(buildSettlementRow(inv, client, owner, rate, type))
      processedInvoices.add(inv.id)
    })

    if (role === 'MANAGER' || role === 'DIRECTOR' || role === 'ADMIN') {
      const subordinateIds = structure.getSubtreeUserIds(userId)
      const subordinateInvoices = allInvoices.filter((inv) => {
        const client = allClients.find((c) => c.id === inv.clientId)
        return client && subordinateIds.includes(client.ownerId) && !processedInvoices.has(inv.id)
      })

      subordinateInvoices.forEach((inv) => {
        const client = allClients.find((c) => c.id === inv.clientId)!
        const owner = allUsers.find((user) => user.id === client.ownerId)!
        const overrideRate = viewingUser.overrideCommissionRate ?? 0

        if (overrideRate > 0) {
          finalRows.push(buildSettlementRow(inv, client, owner, overrideRate, 'OVERRIDE', viewingUser))
        }
      })
    }

    return finalRows.sort((a, b) => new Date(b.issueDate).getTime() - new Date(a.issueDate).getTime())
  }

  const getStructureTotalCommission = (userId: string, role: string): number => {
    const allInvoices = invoices.value
    const allClients = clients.value
    const allUsers = users.value
    const cfg = commissionConfig.value

    let relevantUserIds: string[] = []

    if (role === 'ADMIN') {
      relevantUserIds = allUsers.map((u) => u.id)
    } else if (role === 'DIRECTOR' || role === 'MANAGER') {
      const subtree = structure.getSubtreeUserIds(userId)
      relevantUserIds = [userId, ...subtree]
    } else {
      relevantUserIds = [userId]
    }

    const relevantUserIdSet = new Set(relevantUserIds)
    let total = 0

    allInvoices.forEach((inv) => {
      const client = allClients.find((c) => c.id === inv.clientId)
      if (!client || !relevantUserIdSet.has(client.ownerId)) return

      const owner = allUsers.find((u) => u.id === client.ownerId)
      if (!owner) return

      const clientInvoices = allInvoices
        .filter((i) => i.clientId === client.id)
        .sort((a, b) => new Date(a.issueDate).getTime() - new Date(b.issueDate).getTime())
      const isFirstMonth = clientInvoices.length > 0 && clientInvoices[0].id === inv.id

      let rate = 0
      if (isFirstMonth) {
        const daysDiff = getDaysDiff(client.offerSentDate, client.contractSignedDate)
        rate = daysDiff !== null && daysDiff <= 14 ? cfg.salesCommissionFirstMonthLt14 : cfg.salesCommissionFirstMonthGt14
      } else {
        rate = owner.renewalCommissionRate ?? cfg.salesCommissionRenewal
      }

      total += inv.serviceFeeNet * rate
    })

    return total
  }

  const getDaysDiff = (start?: string, end?: string) => {
    if (!start || !end) return null
    const startTime = new Date(start).getTime()
    const endTime = new Date(end).getTime()
    return Math.ceil((endTime - startTime) / (1000 * 60 * 60 * 24))
  }

  const buildSettlementRow = (
    inv: Invoice,
    client: any,
    owner: User,
    rate: number,
    type: SettlementRow['commissionType'],
    commissionRecipient?: User
  ): SettlementRow => {
    const daysDiff = getDaysDiff(client.offerSentDate, client.contractSignedDate)
    const isFastTrack = type === 'DIRECT_FIRST' && daysDiff !== null && daysDiff <= 14
    const recipient = commissionRecipient || owner

    return {
      invoiceNumber: inv.number,
      clientId: client.id,
      clientName: client.name,
      issueDate: inv.issueDate,
      amountNet: inv.amountNet,
      serviceFee: inv.serviceFeeNet,
      commissionBase: inv.serviceFeeNet,
      repCommission: inv.serviceFeeNet * rate,
      repId: recipient.id,
      repName: recipient.name,
      status: inv.status,
      isFastTrack,
      daysDiff,
      commissionType: type,
    }
  }

  watch(
    () => auth.isAuthenticated,
    (isAuthed) => {
      if (auth.enabled && isAuthed) {
        fetchApiClients()
        fetchApiMeetings()
        fetchApiInvoices()
        fetchApiCommissionConfig()
      }
      if (auth.enabled && !isAuthed) {
        apiClients.value = []
        apiMeetings.value = []
        apiInvoices.value = []
      }
    },
    { immediate: true }
  )

  return {
    invoices,
    commissionConfig,
    getAdminStats,
    getTopPerformers,
    createInvoice,
    updateInvoice,
    createInvoiceForClient,
    getSettlementsForUser,
    fetchApiInvoices,
    fetchApiCommissionConfig,
    updateCommissionConfig,
    fetchApiClients,
    fetchApiMeetings,
    getStructureTotalCommission,
  }
})
