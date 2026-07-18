<script setup lang="ts">
import { ref, computed } from 'vue'
import { api } from '@/api/client'
import { useClientStore } from '@/stores/client'
import { useMailboxStore } from '@/stores/mailbox'
import { useToastStore } from '@/stores/toast'
import {
  ARP_PACKAGES, ARP_GUARANTEE, ARP_CREDIT, ARP_FOUNDER_PACK, ARP_ICP, ARP_CONTACT,
  ARP_QUALIFICATION_FIELDS, recommendArpPackage, type ArpPackage,
} from '@/config/arp'

const clientStore = useClientStore()
const mailbox = useMailboxStore()
const toast = useToastStore()

const headcount = ref<number>(0)
const selectedCode = ref<string>('')

const recommended = computed<ArpPackage>(() => recommendArpPackage(headcount.value || 0))
const selectedPackage = computed<ArpPackage>(() =>
  ARP_PACKAGES.find((p) => p.code === selectedCode.value) || recommended.value)

const fmt = (n: number) => new Intl.NumberFormat('pl-PL').format(n)

// —— Kwalifikacja (15 min) zapisywana do analysis_json wybranego klienta ——
const clients = computed(() => clientStore.clients)
const selectedClientId = ref<string>('')
const qual = ref<Record<string, any>>({})
const savingQual = ref(false)

const selectClient = () => {
  const c = clients.value.find((x) => String(x.id) === selectedClientId.value)
  if (!c) return
  headcount.value = Number(c.employeesTotal || (Number(c.employeesUop || 0) + Number(c.employeesUz || 0))) || 0
  const existing = c.analysis ?? {}
  qual.value = {
    arpEmployeesUop: existing.arpEmployeesUop ?? c.employeesUop ?? null,
    arpEmployeesUz: existing.arpEmployeesUz ?? c.employeesUz ?? null,
    arpGoal: existing.arpGoal ?? '',
    arpWageStructure: existing.arpWageStructure ?? '',
    arpBiggestChallenge: existing.arpBiggestChallenge ?? '',
    arpQualified: existing.arpQualified ?? false,
  }
}

const saveQualification = async () => {
  if (!selectedClientId.value) { toast.error('Wybierz klienta.'); return }
  savingQual.value = true
  try {
    // Dociągnij bieżący profil, żeby zmergować analysis_json (bez kasowania innych pól).
    const { data } = await api.get('/v1/crm-client-profiles', { params: { client_id: selectedClientId.value, per_page: 1 } })
    const list = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
    const profile = list[0]
    const mergedAnalysis = { ...(profile?.analysis_json || {}), ...qual.value }
    if (profile?.id) {
      await api.patch(`/v1/crm-client-profiles/${profile.id}`, { client_id: Number(selectedClientId.value), analysis_json: mergedAnalysis })
    } else {
      await api.post('/v1/crm-client-profiles', { client_id: Number(selectedClientId.value), analysis_json: mergedAnalysis })
    }
    toast.success('Kwalifikacja zapisana w karcie klienta.')
    await clientStore.fetchClients().catch(() => {})
  } catch (e: any) {
    toast.error(e?.response?.data?.message || 'Nie udało się zapisać kwalifikacji.')
  } finally {
    savingQual.value = false
  }
}

// —— Oferta ARP (HTML) → globalny modal compose ——
const buildArpQuoteHtml = (pkg: ArpPackage): string => {
  const reserve = fmt(pkg.guaranteedReserveYearly)
  const price = fmt(pkg.priceNet)
  return `<!DOCTYPE html><html lang="pl"><head><meta charset="utf-8"><style>
    body{font-family:Arial,Helvetica,sans-serif;color:#0f172a;margin:0;padding:32px;font-size:13px;line-height:1.5}
    h1{color:#001f3d;font-size:22px;margin:0 0 4px} h2{color:#C5A059;font-size:15px;margin:20px 0 6px}
    .box{border:1px solid #e2e8f0;border-radius:10px;padding:16px;margin:12px 0}
    .price{font-size:26px;font-weight:800;color:#001f3d} .muted{color:#64748b}
    table{width:100%;border-collapse:collapse;margin-top:8px} td{padding:6px 4px;border-bottom:1px solid #eef2f7}
    .gold{background:#faf6ee;border-color:#e6d6b3}
  </style></head><body>
    <h1>Audyt Rezerw Płacowych™ — oferta</h1>
    <div class="muted">Stratton Prime Sp. z o.o. · pakiet ${pkg.label}</div>
    <div class="box"><div class="muted">Cena audytu (netto)</div><div class="price">${price} zł</div>
      <div class="muted">Gwarantowany próg rezerw: <b>${reserve} zł / rok</b></div></div>
    <h2>${ARP_GUARANTEE.label}</h2><p>${ARP_GUARANTEE.description}</p>
    <div class="box gold"><b>${ARP_CREDIT.label}.</b> ${ARP_CREDIT.description}</div>
    <h2>Co otrzymujecie</h2>
    <table>
      <tr><td>Wizyta doradcy w firmie (analiza struktury zatrudnienia, umów, regulaminów)</td></tr>
      <tr><td>Kalkulacja rezerw na Waszej liście płac (dane bez nazwisk, RODO/DPA)</td></tr>
      <tr><td>Raport Rezerw Płacowych (PDF 15–20 str.) z podstawą prawną i planem wdrożenia</td></tr>
      <tr><td>Prezentacja wyników z udziałem Waszej księgowości</td></tr>
    </table>
    <p class="muted" style="margin-top:20px">Kontakt: ${ARP_CONTACT.name}, ${ARP_CONTACT.title}, tel. ${ARP_CONTACT.phone}.<br>
    Niniejsza oferta ma charakter informacyjny i nie stanowi oferty w rozumieniu art. 66 KC.</p>
  </body></html>`
}

const sendArpOffer = () => {
  const pkg = selectedPackage.value
  const client = clients.value.find((x) => String(x.id) === selectedClientId.value)
  const to = client?.contactEmail || ''
  mailbox.composeState = {
    open: true,
    to,
    subject: `Oferta — Audyt Rezerw Płacowych (${pkg.label})`,
    body: `<p>Dzień dobry,</p><p>w załączeniu oferta audytu w pakiecie <b>${pkg.label}</b> — cena ${fmt(pkg.priceNet)} zł netto, gwarantowany próg rezerw ${fmt(pkg.guaranteedReserveYearly)} zł/rok, z Gwarancją 10× i mechanizmem „Audyt za 0 zł" przy wdrożeniu.</p><p>Pozdrawiam,<br>${ARP_CONTACT.name}<br>${ARP_CONTACT.title} · ${ARP_CONTACT.phone}</p>`,
    attachments: [{ filename: `oferta-ARP-${pkg.label.replace(/\s+/g, '-')}.pdf`, html: buildArpQuoteHtml(pkg), content_type: 'application/pdf', convert_to_pdf: true }],
  } as any
  toast.success('Otwieram compose z ofertą ARP.')
}
</script>

<template>
  <div class="p-6 max-w-6xl mx-auto space-y-6">
    <header>
      <h1 class="text-2xl font-bold text-slate-900">Audyt Rezerw Płacowych (ARP)</h1>
      <p class="text-slate-500 text-sm">Płatny audyt → wdrożenie EBS. ICP: firmy {{ ARP_ICP.minEmployees }}–{{ ARP_ICP.maxEmployees }} osób na UoP i zleceniu.</p>
    </header>

    <!-- Pakiety -->
    <section class="grid sm:grid-cols-3 gap-4">
      <button
        v-for="p in ARP_PACKAGES" :key="p.code" type="button"
        class="text-left rounded-xl border p-5 transition-all"
        :class="selectedPackage.code === p.code ? 'border-stratton-gold ring-2 ring-stratton-gold/40 bg-amber-50' : 'border-slate-200 bg-white hover:border-slate-300'"
        @click="selectedCode = p.code"
      >
        <div class="text-sm font-bold text-slate-500">{{ p.label }}</div>
        <div class="text-2xl font-extrabold text-[#001f3d] mt-1">{{ fmt(p.priceNet) }} zł</div>
        <div class="text-xs text-slate-500 mt-1">{{ p.headcountMin === 0 ? 'do' : p.headcountMin + '–' }}{{ p.headcountMax }} osób</div>
        <div class="text-xs mt-2 text-emerald-700 font-semibold">Próg rezerw: {{ fmt(p.guaranteedReserveYearly) }} zł/rok</div>
      </button>
    </section>

    <section class="grid md:grid-cols-2 gap-4">
      <div class="rounded-xl border border-amber-200 bg-amber-50 p-5">
        <div class="font-bold text-[#001f3d]">{{ ARP_GUARANTEE.label }}</div>
        <p class="text-sm text-slate-600 mt-1">{{ ARP_GUARANTEE.description }}</p>
      </div>
      <div class="rounded-xl border border-slate-200 bg-white p-5">
        <div class="font-bold text-[#001f3d]">{{ ARP_CREDIT.label }}</div>
        <p class="text-sm text-slate-600 mt-1">{{ ARP_CREDIT.description }}</p>
        <p class="text-xs text-slate-400 mt-2">{{ ARP_FOUNDER_PACK.label }}: {{ ARP_FOUNDER_PACK.description }}</p>
      </div>
    </section>

    <!-- Dobór pakietu -->
    <section class="rounded-xl border border-slate-200 bg-white p-5">
      <h2 class="font-bold text-slate-800 mb-3">Dobór pakietu</h2>
      <div class="flex flex-wrap items-end gap-4">
        <label class="text-sm">
          <span class="block text-slate-500 mb-1">Liczba zatrudnionych (UoP + zlecenia)</span>
          <input v-model.number="headcount" type="number" min="0" class="w-40 rounded-lg border-slate-300 text-sm" />
        </label>
        <div class="text-sm">Rekomendacja: <b class="text-[#001f3d]">{{ recommended.label }}</b> — {{ fmt(recommended.priceNet) }} zł</div>
      </div>
    </section>

    <!-- Kwalifikacja 15 min -->
    <section class="rounded-xl border border-slate-200 bg-white p-5">
      <h2 class="font-bold text-slate-800 mb-3">Kwalifikacja (15 min) — zapis do karty klienta</h2>
      <div class="flex flex-wrap items-end gap-4 mb-4">
        <label class="text-sm">
          <span class="block text-slate-500 mb-1">Klient</span>
          <select v-model="selectedClientId" class="w-72 rounded-lg border-slate-300 text-sm" @change="selectClient">
            <option value="">— wybierz —</option>
            <option v-for="c in clients" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
          </select>
        </label>
      </div>
      <div v-if="selectedClientId" class="grid sm:grid-cols-2 gap-3">
        <label v-for="f in ARP_QUALIFICATION_FIELDS" :key="f.key" class="text-sm">
          <span class="block text-slate-500 mb-1">{{ f.label }}</span>
          <input v-if="f.type === 'number'" v-model.number="qual[f.key]" type="number" class="w-full rounded-lg border-slate-300 text-sm" />
          <input v-else-if="f.type === 'text'" v-model="qual[f.key]" type="text" class="w-full rounded-lg border-slate-300 text-sm" />
          <input v-else type="checkbox" v-model="qual[f.key]" class="w-4 h-4 rounded border-slate-300 text-stratton-gold" />
        </label>
      </div>
      <div v-if="selectedClientId" class="mt-4">
        <button type="button" :disabled="savingQual" class="bg-[#001f3d] text-white px-4 py-2 rounded-lg text-sm font-semibold disabled:opacity-50" @click="saveQualification">
          {{ savingQual ? 'Zapisuję…' : 'Zapisz kwalifikację' }}
        </button>
      </div>
    </section>

    <!-- Oferta -->
    <section class="rounded-xl border border-slate-200 bg-white p-5 flex items-center justify-between">
      <div>
        <h2 class="font-bold text-slate-800">Oferta ARP — {{ selectedPackage.label }}</h2>
        <p class="text-sm text-slate-500">{{ fmt(selectedPackage.priceNet) }} zł netto · próg {{ fmt(selectedPackage.guaranteedReserveYearly) }} zł/rok</p>
      </div>
      <button type="button" class="bg-stratton-gold text-white px-5 py-2.5 rounded-lg text-sm font-bold" @click="sendArpOffer">
        Wyślij ofertę mailem
      </button>
    </section>
  </div>
</template>
