export type UserRole = 'SALES' | 'MANAGER' | 'DIRECTOR' | 'ADMIN' | 'CLIENT_HR' | 'LEADOWIEC'
export type Rank = 'JUNIOR' | 'REGULAR' | 'SENIOR' | 'MASTER' | 'LEGEND'
export type EntityType = 'PRIVATE' | 'B2B' | 'COMPANY'
export type ContractStatus = 'DRAFT' | 'SENT_TO_AUTENTI' | 'SIGNED' | 'REJECTED'

export interface UserPermissions {
  canViewGlobalStructure?: boolean
}

export interface UserAddress {
  street: string
  houseNr: string
  aptNr?: string
  zipCode: string
  city: string
}

export interface UserDocuments {
  nda: boolean
  cooperationAgreement: boolean
  careerPath: boolean
  otherFileName?: string
  otherTemplateId?: string
}

export interface AddressBookContact {
  email: string
  name: string
  clientId: string
}

export interface User {
  id: string
  email: string
  name: string
  role: UserRole
  phone?: string | null
  parentId?: string | null
  parentSupabaseId?: string | null
  hierarchicalId?: string | null
  hierarchicalCode?: string | null
  crmNumber?: string
  password?: string
  region?: string
  permissions?: UserPermissions
  renewalCommissionRate?: number
  overrideCommissionRate?: number
  type: EntityType
  pesel?: string
  firstName?: string
  lastName?: string
  nip?: string
  regon?: string
  krs?: string
  addressData?: UserAddress | null
  contractStatus?: ContractStatus | null
  documents?: UserDocuments | null
  isBlocked?: boolean
  isRemovedFromStructure?: boolean
  linkedClientId?: string
  rank?: Rank | null
  points?: number
  addressBook?: AddressBookContact[]
  teamId?: string | null
  teamGroupPath?: string | null
  inviteSent?: boolean | null
  inviteError?: string | null
  enabled?: boolean
  active?: boolean
  isTeamNode?: boolean
  leadowiecOpiekunId?: number | null
  leadowiecCommissionRate?: number | null
}

export interface SavedOffer {
  id: string
  date: string
  employeesUop: number
  avgWageUop: number
  employeesUz: number
  estimatedSavings: number
  name: string
}

export interface ClientActivity {
  id: string
  type: 'CALL' | 'MEETING' | 'EMAIL' | 'NOTE'
  description: string
  date: string
  authorId: string
  resumeAt?: string | null
  isCompleted?: boolean
}

export interface Client {
  id: string
  name: string
  nip: string
  regon?: string | null
  krs?: string | null
  status: 'NEW' | 'OFFER_PREPARING' | 'CALCULATION_SENT' | 'RESIGNED' | 'SIGNED' | 'TERMINATED' | 'IN_TALKS' | 'OFFER_GENERATED' | 'SPECIAL_OFFER'
  ownerId: string
  ownerName?: string
  meetingId?: string
  meetingStatus?: 'open' | 'completed' | 'expired'
  meetingValidUntil?: string | null
  meetingResumeAt?: string | null
  contactName: string
  contactPhone: string
  contactEmail: string
  contactPosition?: string
  isDecisionMaker?: boolean
  source?: string
  industry?: string
  companySize?: string
  street: string
  buildingNr: string
  localeNr?: string
  zip: string
  city: string
  employeesTotal: number
  employeesUop: number
  employeesUz: number
  avgWageUop: number
  avgWageUz: number
  lastActionDate: string
  serviceFeePercent: number
  offerSentDate?: string
  contractSignedDate?: string
  reservationEndDate?: string | null
  savedOffers?: SavedOffer[]
  activityHistory?: ClientActivity[]
  consents?: {
    marketing: boolean
    dataProcessing: boolean
    gdprPhone: boolean
    gdprEmail: boolean
  }
  analysis?: {
    currentOperator?: string
    currentCost?: number
    contractEndDate?: string
    decisionMaker?: string
    notes?: string
  }
}

export interface Employee {
  id: string
  clientId: string
  name: string
  pesel?: string
  contractType: 'UoP' | 'UZ'
  benefitAmount: number
}

export interface Invoice {
  id: string
  number: string
  clientId: string
  issueDate: string
  amountNet: number
  amountGross: number
  serviceFeeNet: number
  status: 'PAID' | 'UNPAID'
  pdfUrl: string
}

export interface CommissionConfig {
  salesCommissionFirstMonthLt14: number
  salesCommissionFirstMonthGt14: number
  salesCommissionRenewal: number
}

export interface Notification {
  id: string
  userId: string
  type: 'INFO' | 'WARNING' | 'CRITICAL' | 'TASK' | 'NOTE' | 'REMINDER' | 'CONTACT' | 'ATTENTION' | 'REPRIMAND'
  message: string
  date: string
  read: boolean
}

export interface AuditLog {
  id: string
  actorId: string
  action: string
  targetId?: string
  details: string
  date: string
}

export interface Email {
  id: string
  fromName: string
  fromEmail: string
  toEmail: string
  subject: string
  body: string
  hasAttachments?: boolean
  attachments?: Array<{
    filename: string
    contentType: string
    size: number
    cid?: string
    isInline?: boolean
  }>
  date: string
  read: boolean
  folder: 'INBOX' | 'SENT' | 'TRASH'
}

export type FileCategory = 'UMOWY' | 'PROCESY' | 'PRAWO' | 'MARKETING' | 'CASH_FLOW' | 'LEGAL' | 'GRAPHIC' | 'VIDEO'
export type FileType = 'pdf' | 'docx' | 'xlsx' | 'pptx'

export interface KnowledgeFile {
  id: string
  name: string
  description: string
  category: FileCategory
  fileType: FileType
  fileUrl: string
  addedDate: string
  size: string
}

export type AutentiStatus = 'DRAFT' | 'SENT' | 'VIEWED' | 'SIGNED' | 'REJECTED'

export interface AutentiDocument {
  id: string
  recipientName: string
  recipientEmail: string
  documentList: string
  status: AutentiStatus
  sentDate: string
  viewedDate?: string
  signedDate?: string
  userId: string
  initiatorId: string
  autentiProcessId?: string
  autentiStatus?: string
}

export interface DocumentTemplate {
  id: string
  name: string
  slug: string
  type: 'pdf' | 'html'
  is_active: boolean
  html_content?: string
  autenti_key?: string | null
  autenti_mapped?: boolean
}
