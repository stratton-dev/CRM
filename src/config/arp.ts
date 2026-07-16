// Model sprzedażowy Audyt Rezerw Płacowych (ARP) → EBS — źródło prawdy frontendu.
// Lustro backendowego config/arp.php (endpoint GET /v1/arp-config oddaje to samo).
// CZYSTA KONFIGURACJA — bez DB. Lejek ARP jest zmapowany na ISTNIEJĄCE wartości
// enuma crm_client_profiles.status (nie dodajemy wartości do typu w DB).

export type ClientStatus =
  | 'NEW'
  | 'IN_TALKS'
  | 'OFFER_PREPARING'
  | 'OFFER_GENERATED'
  | 'CALCULATION_SENT'
  | 'SPECIAL_OFFER'
  | 'RESIGNED'
  | 'SIGNED'
  | 'TERMINATED'

export interface ArpFunnelStage {
  status: ClientStatus
  key: string
  label: string
  badge: string // klasy Tailwind dla „pigułki" statusu
  adminOnly?: boolean // kolumna widoczna tylko dla ADMIN
}

// Kolejność = kolejność kolumn kanbana. Etapy 3–6 używają dotąd NIEUŻYWANYCH
// wartości enuma (OFFER_PREPARING/OFFER_GENERATED/CALCULATION_SENT/SPECIAL_OFFER).
export const ARP_FUNNEL: ArpFunnelStage[] = [
  { status: 'NEW',              key: 'lead',          label: 'Lead',                  badge: 'bg-slate-100 text-slate-700' },
  { status: 'IN_TALKS',         key: 'qualification', label: 'Kwalifikacja (15 min)', badge: 'bg-amber-100 text-amber-800' },
  { status: 'OFFER_PREPARING',  key: 'audit_sold',    label: 'Audyt sprzedany',       badge: 'bg-blue-100 text-blue-800' },
  { status: 'OFFER_GENERATED',  key: 'post_visit',    label: 'Po wizycie',            badge: 'bg-indigo-100 text-indigo-800' },
  { status: 'CALCULATION_SENT', key: 'report',        label: 'Raport / prezentacja',  badge: 'bg-violet-100 text-violet-800' },
  { status: 'SPECIAL_OFFER',    key: 'decision',      label: 'Decyzja / zaliczenie',  badge: 'bg-fuchsia-100 text-fuchsia-800' },
  { status: 'SIGNED',           key: 'ebs_signed',    label: 'Wdrożenie EBS',         badge: 'bg-emerald-100 text-emerald-800' },
  { status: 'RESIGNED',         key: 'resigned',      label: 'Rezygnacja',            badge: 'bg-red-100 text-red-800' },
  { status: 'TERMINATED',       key: 'terminated',    label: 'Zakończony',            badge: 'bg-gray-200 text-gray-800', adminOnly: true },
]

export const arpStatusLabel = (status: string): string =>
  ARP_FUNNEL.find((s) => s.status === status)?.label ?? status

export const arpStatusBadge = (status: string): string =>
  ARP_FUNNEL.find((s) => s.status === status)?.badge ?? 'bg-slate-100 text-slate-700'

// —— Produkt ARP: pakiety, gwarancja, zaliczenie ——

export interface ArpPackage {
  code: string
  label: string
  headcountMin: number
  headcountMax: number
  priceNet: number
  guaranteedReserveYearly: number
}

export const ARP_PACKAGES: ArpPackage[] = [
  { code: 'ARP_50',  label: 'ARP 50',   headcountMin: 0,   headcountMax: 50,  priceNet: 3900,  guaranteedReserveYearly: 39000 },
  { code: 'ARP_150', label: 'ARP 150',  headcountMin: 51,  headcountMax: 150, priceNet: 6900,  guaranteedReserveYearly: 69000 },
  { code: 'ARP_300', label: 'ARP 300+', headcountMin: 151, headcountMax: 500, priceNet: 11900, guaranteedReserveYearly: 119000 },
]

export const ARP_GUARANTEE = {
  multiplier: 10,
  refundDays: 7,
  label: 'Gwarancja 10×',
  description:
    'Jeśli raport nie wykaże legalnych rezerw wartych co najmniej 10-krotność ceny audytu rocznie — zwracamy 100% ceny w 7 dni, bez pytań.',
}

export const ARP_CREDIT = {
  fullWithinDays: 30,
  halfWithinDays: 60,
  label: 'Audyt za 0 zł',
  description:
    'Przy podpisaniu umowy wdrożeniowej EBS w 30 dni od prezentacji raportu — 100% ceny audytu zaliczone na wdrożenie (31–60 dni: 50%; po 60 dniach wygasa).',
}

export const ARP_FOUNDER_PACK = {
  count: 10,
  priceNet: 3900,
  label: 'Pakiet Założycielski',
  description: 'Pierwszych 10 firm — każdy pakiet w cenie ARP 50 w zamian za zgodę na anonimowe case study.',
}

export const ARP_ICP = { minEmployees: 25, maxEmployees: 500, note: 'Poniżej 25 zatrudnionych zwykle odmawiamy.' }

export const ARP_CONTACT = { name: 'Maciej Hagno', title: 'Dyrektor Handlowy', phone: '883 408 132' }

// Prowizje (referencja UI; źródło prawdy: backend config/commission.php).
export const ARP_COMMISSION = { arpPartnerRate: 0.15, ebsPartnerLevels: [0.1, 0.05, 0.02] }

// Pola formularza kwalifikacyjnego (15 min). Zapisywane w istniejącej,
// nullable kolumnie crm_client_profiles.analysis_json (bez nowych kolumn).
export interface ArpQualificationField {
  key: string
  label: string
  type: 'number' | 'text' | 'boolean'
}

export const ARP_QUALIFICATION_FIELDS: ArpQualificationField[] = [
  { key: 'arpEmployeesUop',     label: 'Pracownicy na UoP',                 type: 'number' },
  { key: 'arpEmployeesUz',      label: 'Zleceniobiorcy (oskładkowani)',     type: 'number' },
  { key: 'arpGoal',             label: 'Główny cel klienta',                type: 'text' },
  { key: 'arpWageStructure',    label: 'Struktura wynagrodzeń',             type: 'text' },
  { key: 'arpBiggestChallenge', label: 'Największe wyzwanie kadry/płac',     type: 'text' },
  { key: 'arpQualified',        label: 'Kwalifikuje się (ICP 25–500)',      type: 'boolean' },
]

/** Dobór pakietu ARP po liczbie zatrudnionych (UoP + zlecenia). */
export function recommendArpPackage(headcount: number): ArpPackage {
  return (
    ARP_PACKAGES.find((p) => headcount >= p.headcountMin && headcount <= p.headcountMax) ??
    ARP_PACKAGES[ARP_PACKAGES.length - 1]
  )
}
