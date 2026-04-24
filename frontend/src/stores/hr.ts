import { defineStore, storeToRefs } from 'pinia'
import { ref, watch } from 'vue'
import { useDataStore } from '@/stores/data'
import { useClientStore } from '@/stores/client'
import { useFinanceStore } from '@/stores/finance'
import { useAuthStore } from '@/stores/auth'
import { api } from '@/api/client'
import type { Employee } from '@/types/models'

export const useHrStore = defineStore('hr', () => {
  const auth = useAuthStore()
  const data = useDataStore()
  const clientStore = useClientStore()
  const finance = useFinanceStore()

  const { clients: localClients, employees: localEmployees, invoices: localInvoices } = storeToRefs(data)
  const { clients: apiClients } = storeToRefs(clientStore)
  const apiEmployees = ref<Employee[]>([])
  const apiInvoices = ref(localInvoices.value || [])

  const employees = auth.enabled ? apiEmployees : localEmployees
  const invoices = auth.enabled ? apiInvoices : localInvoices
  const clients = auth.enabled ? apiClients : localClients

  const mapApiEmployee = (item: any): Employee => ({
    id: String(item.id),
    clientId: String(item.client_id),
    name: item.name,
    pesel: item.pesel || undefined,
    contractType: item.contract_type === 'UZ' ? 'UZ' : 'UoP',
    benefitAmount: Number(item.benefit_amount || 0),
  })

  const fetchEmployees = async (clientId?: string) => {
    if (!auth.enabled) return
    const { data } = await api.get('/v1/crm-employees', {
      params: { per_page: 200, client_id: clientId || undefined },
    })
    const list = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
    apiEmployees.value = list.map(mapApiEmployee)
  }

  const fetchInvoices = async (clientId?: string) => {
    if (!auth.enabled) return
    const { data } = await api.get('/v1/crm-invoices', {
      params: { per_page: 200, client_id: clientId || undefined },
    })
    const list = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
    apiInvoices.value = list.map((item: any) => ({
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

  const addEmployee = (employeeData: Omit<Employee, 'id'>) => {
    if (auth.enabled) {
      return api.post('/v1/crm-employees', {
        client_id: employeeData.clientId,
        name: employeeData.name,
        pesel: employeeData.pesel || null,
        contract_type: employeeData.contractType,
        benefit_amount: employeeData.benefitAmount,
      }).then(() => fetchEmployees())
    }
    const newEmp: Employee = { ...employeeData, id: Math.random().toString(36).substr(2, 9) }
    data.rawAddEmployee(newEmp)
    return newEmp
  }

  const generateInvoice = (clientId: string, totalBenefitNet: number) => {
    const client = clients.value.find((item) => item.id === clientId)
    if (!client) return null

    const serviceFeeNet = totalBenefitNet * (client.serviceFeePercent / 100)

    return finance.createInvoiceForClient(
      clientId,
      serviceFeeNet,
      totalBenefitNet
    )
  }

  watch(
    () => auth.isAuthenticated,
    (isAuthed) => {
      if (auth.enabled && isAuthed) {
        fetchEmployees()
        fetchInvoices()
      }
      if (auth.enabled && !isAuthed) {
        apiEmployees.value = []
        apiInvoices.value = []
      }
    },
    { immediate: true }
  )

  return { employees, invoices, addEmployee, generateInvoice, fetchEmployees, fetchInvoices }
})
