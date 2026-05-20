<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import { formatPLN } from '../utils/formatters';
import { useCalculatorStore } from '../store/useCalculatorStore';
import { useMailboxStore } from '@/stores/mailbox';
import { useSessionStore } from '@/stores/session';
import { obliczWariantPodzial, obliczWariantStandard } from '../tax-engine';
import { Pracownik } from '../models/employee';
import { usePdfGenerator } from '@/composables/usePdfGenerator';

type ContractType = 'UOP' | 'UZ' | 'MIXED';
type StrategyType = 'SAVINGS' | 'WIN_WIN';

const props = withDefaults(
  defineProps<{
    initialEmployees?: number;
    initialAvgWage?: number;
    initialContractType?: ContractType;
    initialSalaryMode?: 'NETTO' | 'BRUTTO';
    // Przekazane z QuickCalculatorView gdy wchodzimy z ProcessStart/ClientsView
    contactEmail?: string | null;
    contactName?: string | null;
    clientId?: string | null;
  }>(),
  {
    initialEmployees: 50,
    initialAvgWage: 6000,
    initialContractType: 'UOP',
    initialSalaryMode: 'NETTO',
    contactEmail: null,
    contactName: null,
    clientId: null,
  }
);

const emit = defineEmits<{ (event: 'transfer'): void }>();

const store = useCalculatorStore();
const mailboxStore = useMailboxStore();
const session = useSessionStore();
const router = useRouter();
const isLeadowiec = computed(() => String(session.currentUser?.role || '').toUpperCase() === 'LEADOWIEC');
const route = useRoute();
const pdfGen = usePdfGenerator();

const openEmailModal = () => {
  mailboxStore.composeState = {
    open: true,
    to: props.contactEmail || store.firma?.email || '',
    subject: `Oferta szacunkowa — ${store.firma?.nazwa || 'Twoja firma'}`,
    body: `<p>Dzień dobry${props.contactName ? `, ${props.contactName}` : ''},</p><p><br></p><p>W załączeniu przesyłam wstępną ofertę szacunkową przygotowaną na podstawie przekazanych informacji.</p><p><br></p><p>Z wyrazami szacunku</p>`,
    attachments: [{ filename: `oferta-szacunkowa-${store.firma?.nip || 'firma'}.pdf`, html: buildQuickSimHtml(), content_type: 'application/pdf', convert_to_pdf: true }],
  };
};

const empCount = ref(props.initialEmployees);
// Initialize two separate salary refs
const avgSalaryUop = ref(props.initialAvgWage);
const avgSalaryUz = ref(props.initialAvgWage);
const salaryMode = ref<'NETTO' | 'BRUTTO'>(props.initialSalaryMode);

const countUopInput = ref(props.initialContractType === 'UZ' ? 0 : (props.initialContractType === 'MIXED' ? Math.floor(props.initialEmployees / 2) : props.initialEmployees));
const countUzInput = ref(props.initialContractType === 'UOP' ? 0 : (props.initialContractType === 'MIXED' ? Math.ceil(props.initialEmployees / 2) : props.initialEmployees));

const strategy = ref<StrategyType>('SAVINGS');

const handleBack = () => {
  if (route.query.source === 'process') {
    const clientId = route.query.clientId
    const meetingId = route.query.meetingId
    router.push({
      path: '/app/sales/start',
      query: { clientId, meetingId, step: 4 }
    })
  } else {
    router.back()
  }
}

const isCountValid = computed(() => {
  return (countUopInput.value + countUzInput.value) <= empCount.value;
});

const simulation = computed(() => {
  const countUOP = countUopInput.value;
  const countUZ = countUzInput.value;

  const calculateOne = (type: 'UOP' | 'UZ') => {
    // Select the correct base wage
    let baseNetto = type === 'UOP' ? avgSalaryUop.value : avgSalaryUz.value;
    
    // Apply Brutto conversion based on which wage is being processed
    if (salaryMode.value === 'BRUTTO') {
      baseNetto = type === 'UOP' ? baseNetto * 0.71 : baseNetto * 0.78;
    }

    const dummyEmployee: Pracownik = {
      id: 0,
      imie: 'X',
      nazwisko: 'X',
      dataUrodzenia: '1990-01-01',
      plec: 'M',
      typUmowy: type,
      trybSkladek: 'PELNE',
      choroboweAktywne: true,
      pit2: '300',
      ulgaMlodych: false,
      kupTyp: type === 'UZ' ? 'PROC_20' : 'STANDARD',
      nettoDocelowe: baseNetto,
      nettoZasadnicza: type === 'UZ' ? store.config.minimalnaKwotaUZ.zasadniczaNetto : store.config.placaMinimalna.netto,
      pitMode: 'AUTO',
      skladkaFP: true,
      skladkaFGSP: true,
    };

    const std = obliczWariantStandard(dummyEmployee, store.firma.stawkaWypadkowa, store.config);
    const opt = obliczWariantPodzial(dummyEmployee, store.firma.stawkaWypadkowa, dummyEmployee.nettoZasadnicza, store.config);

    const provPercent = strategy.value === 'SAVINGS' ? 28 : 26;
    const provision = opt.swiadczenie.netto * (provPercent / 100);

    return {
      stdKoszt: std.kosztPracodawcy,
      optKosztTotal: opt.kosztPracodawcy + provision,
      provision,
      netto: baseNetto,
    };
  };

  const resUOP = calculateOne('UOP');
  const resUZ = calculateOne('UZ');

  const totalStd = resUOP.stdKoszt * countUOP + resUZ.stdKoszt * countUZ;
  const totalNew = resUOP.optKosztTotal * countUOP + resUZ.optKosztTotal * countUZ;
  const totalProv = resUOP.provision * countUOP + resUZ.provision * countUZ;
  const savings = totalStd - totalNew;

  return {
    countUOP,
    countUZ,
    totalStd,
    totalNew,
    totalProv,
    savings,
    monthlySavings: savings,
    yearlySavings: savings * 12,
    perEmployeeSavings: empCount.value > 0 ? savings / empCount.value : 0,
  };
});

const costRatio = computed(() => {
  if (!simulation.value.totalStd) return 0;
  return (simulation.value.totalNew / simulation.value.totalStd) * 100;
});

const handleTransfer = () => {
  const genUOP = simulation.value.countUOP;
  const genUZ = simulation.value.countUZ;

  const newEmployees: Pracownik[] = [];
  let idCounter = Date.now();

  const createEmp = (type: 'UOP' | 'UZ', i: number): Pracownik => {
    // Determine wage based on type
    const sourceWage = type === 'UOP' ? avgSalaryUop.value : avgSalaryUz.value;
    
    // Calculate netto target based on current mode
    let target = sourceWage;
    if (salaryMode.value === 'NETTO') {
      target = sourceWage;
    } else {
      // Brutto mode conversion
      target = type === 'UOP' ? sourceWage * 0.71 : sourceWage * 0.78;
    }
    
    return {
      id: idCounter + i,
      imie: 'Pracownik',
      nazwisko: `${type} ${i + 1}`,
      dataUrodzenia: '1990-01-01',
      plec: 'M',
      typUmowy: type,
      trybSkladek: 'PELNE',
      choroboweAktywne: true,
      pit2: '300',
      ulgaMlodych: false,
      kupTyp: type === 'UZ' ? 'PROC_20' : 'STANDARD',
      nettoDocelowe: target,
      nettoZasadnicza: type === 'UZ' ? store.config.minimalnaKwotaUZ.zasadniczaNetto : store.config.placaMinimalna.netto,
      pitMode: 'AUTO',
      skladkaFP: true,
      skladkaFGSP: true,
    };
  };

  for (let i = 0; i < genUOP; i++) newEmployees.push(createEmp('UOP', i));
  for (let i = 0; i < genUZ; i++) newEmployees.push(createEmp('UZ', genUOP + i));

  store.prowizjaProc = strategy.value === 'SAVINGS' ? 28 : 26;
  store.pracownicy = newEmployees;
  emit('transfer');
};

// ── Generowanie oferty szacunkowej ─────────────────────────────────────────
const isSendingOffer = ref(false);

// Określa czy jesteśmy w kontekście klienta (z ProcessStart/ClientsView)
const hasClientContext = computed(() => Boolean(props.clientId || store.context.clientId));

// Opens the premium HTML in a new tab using a blob URL (popup-blocker-safe)
const printPremiumOffer = (html: string) => {
  const blob = new Blob([html], { type: 'text/html;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const win = window.open(url, '_blank');
  if (win) {
    win.addEventListener('load', () => {
      setTimeout(() => {
        win.print();
        setTimeout(() => URL.revokeObjectURL(url), 5000);
      }, 800);
    });
  } else {
    // If still blocked, trigger a direct download fallback
    const a = document.createElement('a');
    a.href = url;
    a.download = `oferta-${store.firma.nip || 'stratton'}.html`;
    a.click();
    setTimeout(() => URL.revokeObjectURL(url), 5000);
  }
};

const buildQuickSimHtml = () => {
  const s = simulation.value;
  const firmaNazwa = store.firma.nazwa || 'Twoja Firma';
  const firmaNip = store.firma.nip || '';
  const date = new Date().toLocaleDateString('pl-PL');
  const dateWaz = new Date(Date.now() + 14 * 86400000).toLocaleDateString('pl-PL');
  const provPercent = strategy.value === 'SAVINGS' ? 28 : 26;
  const oszczMies = s.monthlySavings;
  const oszczRocz = s.yearlySavings;
  const prowizja = s.totalProv;
  const roi = prowizja > 0 ? Math.round((oszczRocz / (prowizja * 12)) * 100) : 0;
  const zyskNetto = oszczRocz - prowizja * 12;
  const liczbaPrac = s.countUOP + s.countUZ;

  return `<!DOCTYPE html>
<html lang="pl">
<head>
  <meta charset="UTF-8"/>
  <title>Oferta szacunkowa — ${firmaNazwa}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&family=Playfair+Display:ital,wght@0,700;1,400&display=swap" rel="stylesheet">
  <style>
    @page { size: A4 portrait; margin: 0; }
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'Inter',sans-serif;font-weight:300;color:#111827;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
    .serif{font-family:'Playfair Display',Georgia,serif;}
    .page{background:white;width:210mm;min-height:297mm;height:297mm;overflow:hidden;position:relative;display:flex;flex-direction:column;page-break-after:always;break-after:page;}
    @media print{html,body{padding:0!important;margin:0!important;background:white!important;}.page{margin:0!important;box-shadow:none!important;}}
    .pre-tag{font-size:9px;font-weight:700;letter-spacing:.3em;text-transform:uppercase;color:#C5A059;display:block;margin-bottom:10px;}
    .page-header{background:#05162e;color:white;padding:28px 44px;border-bottom:3px solid #C5A059;flex-shrink:0;}
    .page-section{padding:20px 44px;flex-grow:1;}
    .page-footer{padding:9px 44px;border-top:1px solid #d1d5db;font-size:9px;color:#9ca3af;display:flex;justify-content:space-between;text-transform:uppercase;font-weight:700;letter-spacing:.1em;}
    table{width:100%;border-collapse:collapse;}
    thead tr{background:#05162e;color:white;font-size:9px;font-weight:700;letter-spacing:.15em;text-transform:uppercase;}
    thead th{padding:10px 14px;text-align:left;}
    tbody td{padding:10px 14px;border-bottom:1px solid #f3f4f6;font-size:12px;}
    tbody tr:nth-child(even){background:#fafafa;}
  </style>
</head>
<body>

<!-- ══════════════════════ PAGE 1 ══════════════════════ -->
<div class="page">
  <!-- Header -->
  <header style="background:#05162e;color:white;padding:32px 44px 22px;position:relative;overflow:hidden;flex-shrink:0;">
    <div style="position:absolute;top:0;right:0;width:200px;height:100%;background:linear-gradient(135deg,transparent 60%,rgba(197,160,89,0.07) 60%);pointer-events:none;"></div>
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:18px;">
      <div>
        <div style="font-size:20px;font-weight:900;letter-spacing:.2em;text-transform:uppercase;">STRATTON <span style="color:#C5A059;">PRIME</span></div>
        <div style="font-size:8px;letter-spacing:.3em;color:rgba(255,255,255,.4);text-transform:uppercase;margin-top:3px;">ARCHITEKCI WARTOŚCI BIZNESOWEJ</div>
      </div>
      <div style="text-align:right;font-size:9px;letter-spacing:.1em;text-transform:uppercase;line-height:2;color:rgba(255,255,255,.5);">
        <div>DATA: ${date}</div>
        <div>WAŻNA DO: <span style="color:#C5A059;">${dateWaz}</span></div>
        <div>OPIEKUN: <span style="color:#C5A059;">Agnieszka Cięciara</span></div>
      </div>
    </div>
    <div style="font-size:9px;font-weight:700;letter-spacing:.3em;text-transform:uppercase;color:#C5A059;margin-bottom:8px;">OFERTA SZACUNKOWA · ANALIZA LISTY PŁAC</div>
    <h1 class="serif" style="font-size:32px;line-height:1;letter-spacing:-.02em;color:white;margin-bottom:6px;">Eliton Benefits System™</h1>
    <p style="font-size:12px;font-weight:300;color:rgba(255,255,255,.65);max-width:420px;line-height:1.5;">Szacujemy Twój potencjał oszczędności bez konieczności podawania pełnej listy płac.</p>
    <div style="margin-top:10px;font-size:8px;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:rgba(255,255,255,.4);">
      PRZYGOTOWANO DLA: <span style="color:white;font-size:12px;letter-spacing:.06em;font-weight:600;">${firmaNazwa}</span>
      ${firmaNip ? `<span style="color:rgba(255,255,255,.35);font-size:10px;margin-left:8px;">NIP: ${firmaNip}</span>` : ''}
    </div>
    <div style="position:absolute;bottom:0;left:0;right:0;height:3px;background:#C5A059;"></div>
  </header>

  <!-- KPI strip -->
  <section class="page-section">
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0;margin-bottom:14px;border:1px solid #e5e7eb;overflow:hidden;">
      <div style="padding:13px 15px;text-align:center;background:white;border-right:1px solid #e5e7eb;">
        <div style="font-size:8px;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#6b7280;margin-bottom:6px;">Oszczędność miesięczna</div>
        <div class="serif" style="font-size:24px;font-weight:700;color:#16a34a;">${formatPLN(oszczMies)}</div>
        <div style="font-size:9px;color:#9ca3af;margin-top:2px;">/miesiąc netto dla firmy</div>
      </div>
      <div style="padding:13px 15px;text-align:center;background:#05162e;border-right:1px solid #0e2a4e;">
        <div style="font-size:8px;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:rgba(255,255,255,.4);margin-bottom:6px;">Potencjał roczny</div>
        <div class="serif" style="font-size:24px;font-weight:700;color:#C5A059;">${formatPLN(oszczRocz)}</div>
        <div style="font-size:9px;color:rgba(255,255,255,.35);margin-top:2px;">/rok oszczędności</div>
      </div>
      <div style="padding:13px 15px;text-align:center;background:white;">
        <div style="font-size:8px;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#6b7280;margin-bottom:6px;">Pracownicy objęci</div>
        <div class="serif" style="font-size:24px;font-weight:700;color:#05162e;">${liczbaPrac}</div>
        <div style="font-size:9px;color:#9ca3af;margin-top:2px;">UoP: ${s.countUOP} · UZ: ${s.countUZ}</div>
      </div>
    </div>

    <!-- Table -->
    <div style="margin-bottom:10px;">
      <span class="pre-tag">Wynik szacunkowy · ${liczbaPrac} pracownik(ów)</span>
      <h2 class="serif" style="font-size:20px;line-height:1.1;color:#05162e;">Twoje liczby. Twoja decyzja.</h2>
    </div>
    <table style="margin-bottom:12px;">
      <thead><tr><th>Parametr</th><th style="text-align:right;">Stan obecny</th><th style="text-align:right;">Model Eliton Prime™</th><th style="text-align:right;">Różnica</th></tr></thead>
      <tbody>
        <tr><td>Pracownicy UoP</td><td style="text-align:right;">${s.countUOP}</td><td style="text-align:right;">${s.countUOP}</td><td style="text-align:right;color:#9ca3af;">—</td></tr>
        <tr><td>Pracownicy UZ</td><td style="text-align:right;">${s.countUZ}</td><td style="text-align:right;">${s.countUZ}</td><td style="text-align:right;color:#9ca3af;">—</td></tr>
        <tr><td>Koszt zatrudnienia / mies.</td><td style="text-align:right;">${formatPLN(s.totalStd)}</td><td style="text-align:right;">${formatPLN(s.totalNew)}</td><td style="text-align:right;color:#16a34a;font-weight:600;">${formatPLN(s.totalStd - s.totalNew)}</td></tr>
        <tr style="background:#f3f4f6;"><td><strong>Oszczędność netto / mies.</strong> <span style="font-size:9px;font-weight:700;background:#fef3c7;color:#92400e;padding:1px 6px;border-radius:3px;margin-left:4px;">Prowizja ${provPercent}%</span></td><td colspan="2" style="text-align:center;color:#9ca3af;">—</td><td style="text-align:right;color:#16a34a;font-weight:700;">${formatPLN(oszczMies)}</td></tr>
      </tbody>
    </table>

    <!-- Pull quote -->
    <div style="background:#05162e;color:white;padding:12px 24px;font-family:'Playfair Display',serif;font-size:14px;font-style:italic;line-height:1.5;text-align:center;border-top:3px solid #C5A059;border-bottom:3px solid #C5A059;">
      Każdy miesiąc zwłoki to <span style="color:#C5A059;font-style:normal;font-weight:700;">${formatPLN(oszczMies)}</span>, które oddajesz do ZUS bezpowrotnie.
    </div>
  </section>

  <footer class="page-footer"><span>Stratton Prime · Architekci Wartości Biznesowej</span><span>01</span></footer>
</div>

<!-- ══════════════════════ PAGE 2 ══════════════════════ -->
<div class="page">
  <header class="page-header">
    <span class="pre-tag">Co zawiera wdrożenie &amp; analiza ROI</span>
    <h2 class="serif" style="font-size:26px;line-height:1.1;color:white;">Pełny pakiet Eliton Benefits System™</h2>
  </header>

  <section class="page-section">
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:28px;">
      <div>
        ${['Pełna dokumentacja: Regulamin wynagradzania, aneksy do umów, tabele stanowisk.',
           'Obsługa kadrowa: Gotowe instrukcje księgowania i rozliczania składek.',
           'Szkolenia: Przygotowanie HR i księgowości w 15 minut miesięcznie.',
           'Asekuracja prawna: Przejęcie odpowiedzialności za komunikację z ZUS/KAS.',
           'Polisa D&O: Ochrona osobista Zarządu do kwoty 1 000 000 zł.',
           'Opieka post-wdrożeniowa: Dedykowany opiekun i comiesięczny audyt.',
        ].map(item => {
          const [title, ...rest] = item.split(': ');
          return `<div style="display:flex;gap:9px;margin-bottom:7px;align-items:flex-start;">
            <span style="color:#C5A059;font-weight:700;font-size:14px;line-height:1.4;flex-shrink:0;">✓</span>
            <span style="font-size:11px;color:#374151;line-height:1.5;"><strong>${title}:</strong> ${rest.join(': ')}</span>
          </div>`;
        }).join('')}

        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;padding:12px 16px;background:#f8f9fa;border-left:3px solid #C5A059;">
          <div>
            <div style="font-size:8px;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#6b7280;margin-bottom:2px;">Prowizja za zarządzanie · miesięcznie</div>
            <div style="font-weight:600;color:#05162e;font-size:12px;">Inwestycja w system</div>
          </div>
          <div class="serif" style="font-size:24px;font-weight:700;color:#05162e;white-space:nowrap;">${formatPLN(prowizja)}</div>
        </div>
      </div>

      <!-- ROI box -->
      <div style="background:#05162e;color:white;padding:24px 18px;border-bottom:5px solid #C5A059;text-align:center;">
        <span class="pre-tag" style="margin-bottom:16px;display:block;">Analiza ROI · I rok</span>
        <div style="margin-bottom:10px;">
          <div style="font-size:8px;text-transform:uppercase;letter-spacing:.18em;color:rgba(255,255,255,.4);margin-bottom:3px;">Oszczędność roczna</div>
          <div class="serif" style="font-size:22px;font-weight:700;">${formatPLN(oszczRocz)}</div>
        </div>
        <div style="border-top:1px solid rgba(255,255,255,.1);margin:10px 0;"></div>
        <div style="margin-bottom:10px;">
          <div style="font-size:8px;text-transform:uppercase;letter-spacing:.18em;color:rgba(255,255,255,.4);margin-bottom:3px;">Inwestycja roczna</div>
          <div class="serif" style="font-size:22px;font-weight:700;">${formatPLN(prowizja * 12)}</div>
        </div>
        <div style="background:#C5A059;color:#05162e;padding:13px;margin-top:10px;">
          <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.2em;margin-bottom:3px;">Zysk netto / rok</div>
          <div class="serif" style="font-size:26px;font-weight:700;">+${formatPLN(zyskNetto)}</div>
        </div>
        <div style="margin-top:12px;padding:10px;background:rgba(197,160,89,.15);">
          <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.25em;color:rgba(255,255,255,.45);margin-bottom:3px;">ROI</div>
          <div class="serif" style="font-size:40px;font-weight:700;color:#C5A059;line-height:1;">${roi}%</div>
        </div>
      </div>
    </div>

    <!-- Partners strip -->
    <div style="margin-top:14px;padding-top:12px;border-top:1px solid #e5e7eb;">
      <div style="font-size:8px;font-weight:700;letter-spacing:.25em;text-transform:uppercase;color:#6b7280;margin-bottom:4px;">Partnerzy ubezpieczeniowi platformy EBS</div>
      <div style="font-size:10px;color:#9ca3af;letter-spacing:.08em;">PZU · Ergo Hestia · Allianz · Generali · Warta · Lloyd's · Vienna Life · Uniqa · Signal Iduna · LuxMed · Orange</div>
    </div>
  </section>

  <!-- Contact footer -->
  <footer style="background:#05162e;color:white;padding:16px 44px;border-top:3px solid #C5A059;flex-shrink:0;">
    <div style="display:flex;justify-content:space-between;align-items:center;">
      <div>
        <div style="font-size:8px;font-weight:700;letter-spacing:.3em;text-transform:uppercase;color:#C5A059;margin-bottom:3px;">Opiekun Projektu</div>
        <div class="serif" style="font-size:16px;font-weight:700;">Agnieszka Cięciara</div>
        <div style="font-size:9px;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.04em;">Dyrektor ds. Wdrożeń i Relacji Biznesowych</div>
      </div>
      <div style="text-align:right;font-size:9px;line-height:2;letter-spacing:.1em;text-transform:uppercase;">
        <div style="color:#C5A059;font-weight:700;">STRATTON PRIME</div>
        <div>EMAIL: a.cieciara@stratton-prime.pl</div>
        <div style="color:#C5A059;">WWW.STRATTON-PRIME.PL</div>
      </div>
    </div>
  </footer>
</div>

</body>
</html>`;
};

const handleShortOffer = async () => {
  const s = simulation.value;
  const provPercent = strategy.value === 'SAVINGS' ? 28 : 26;
  const oszczMies = s.monthlySavings;
  const oszczRocz = s.yearlySavings;
  const prowizja = s.totalProv;
  const roi = prowizja > 0 ? Math.round((oszczRocz / (prowizja * 12)) * 100) : 0;
  const data = {
    firma: {
      nazwa: store.firma.nazwa || 'Twoja Firma',
      nip: store.firma.nip || '',
    },
    podsumowanie: {
      liczbaObjetchPracownikow: s.countUOP + s.countUZ,
      liczbaUop: s.countUOP,
      liczbaUz: s.countUZ,
      kosztObecny: s.totalStd,
      kosztNowy: s.totalNew,
      oszczednoscMiesieczna: oszczMies,
      oszczednoscRoczna: oszczRocz,
      prowizja: prowizja,
      prowizjaProc: provPercent,
      roi,
      zyskNetto: oszczRocz - prowizja * 12,
    },
    handlowiec: {
      imie: 'Agnieszka',
      nazwisko: 'Cięciara',
      email: 'a.cieciara@stratton-prime.pl',
      telefon: '',
    },
    dataWystawienia: new Date().toLocaleDateString('pl-PL'),
    dataWaznosci: new Date(Date.now() + 14 * 86400000).toLocaleDateString('pl-PL'),
  };
  try {
    await pdfGen.generatePdf('short', data);
  } catch {
    // API failed (Puppeteer unavailable on Railway) — fall back to client-side premium HTML
    printPremiumOffer(buildQuickSimHtml());
  }
};

const generateQuickOffer = async () => {
  if (isSendingOffer.value) return;
  isSendingOffer.value = true;
  try {
    const simData = {
      employeeCount: simulation.value.countUOP + simulation.value.countUZ,
      monthlySavings: simulation.value.monthlySavings,
    };

    // Zapisz do DB tylko gdy jest kontekst klienta (ProcessStart / ClientsView)
    if (hasClientContext.value) {
      const calc = await store.saveQuickSimToApi(simData);
      await store.updateMeetingOfferStatus('generated');
      await store.updateClientStatus('OFFER_GENERATED');
      if (calc?.id) {
        await store.updateCalculationStatus(String(calc.id), 'READY');
      }
    }

    const htmlContent = buildQuickSimHtml();

    if (hasClientContext.value) {
      // Otwórz skrzynkę z wypełnionym compose — oferta jako załącznik
      mailboxStore.composeState = {
        open: true,
        to: props.contactEmail || store.firma.email || '',
        subject: `Oferta szacunkowa — ${store.firma.nazwa || 'Twoja firma'}`,
        body: `Dzień dobry${props.contactName ? `, ${props.contactName}` : ''},\n\nW załączeniu przesyłam wstępną ofertę szacunkową przygotowaną na podstawie przekazanych informacji.\n\nZ wyrazami szacunku`,
        attachments: [
          {
            filename: `oferta-szacunkowa-${store.firma.nip || 'firma'}.pdf`,
            html: htmlContent,
            content_type: 'application/pdf',
            convert_to_pdf: true,
          },
        ],
      };
      await router.push('/app/mailbox');
    } else {
      // Bez klienta — premium offer print (blob URL, popup-blocker-safe)
      printPremiumOffer(htmlContent);
    }
  } finally {
    isSendingOffer.value = false;
  }
};</script>

<template>
  <div class="animate-fade-in">
    <div class="space-y-4 md:space-y-8">

      <!-- Top Header Area: Results & Controls (Full Width) -->
      <div class="rounded-card shadow-card-hover border border-[#003366] p-4 md:p-6" style="background: linear-gradient(135deg, #001f3d 0%, #002a52 50%, #003366 100%)">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 md:mb-6 gap-3 md:gap-6">
            <!-- Left: Back Button + Title -->
            <div class="flex items-center gap-3 md:gap-6 self-start md:self-center">
                <button type="button" class="hidden md:inline-flex items-center justify-center w-12 h-12 bg-slate-800 border border-slate-700 rounded-md text-slate-400 hover:bg-slate-700 hover:text-white transition-all shadow-sm group" @click="handleBack">
                    <AppIcon name="arrow-left" class="w-5 h-5 transition-transform group-hover:-translate-x-1" />
                </button>
                <div>
                    <h2 class="font-serif font-bold text-xl md:text-3xl text-white tracking-tight">Wyniki Symulacji</h2>
                    <p class="text-xs text-slate-500 mt-1 uppercase tracking-widest font-bold">Podsumowanie Oszczędności</p>
                </div>
            </div>

            <!-- Right: Action Buttons -->
            <div class="flex flex-col sm:flex-row items-end sm:items-center gap-3 self-end md:self-center">
            </div>
        </div>

        <!-- Content: Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 md:gap-6">
            <!-- Card 1: Monthly Savings (Dark Mode) -->
            <div class="rounded-2xl p-5 border border-[#003366] shadow-sm relative overflow-hidden group transition-colors" style="background: linear-gradient(135deg, #001f3d 0%, #002a52 50%, #003366 100%)">
                <div class="flex justify-between items-start mb-3">
                    <div>
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-widest">Oszczędność miesięczna</div>
                        <div class="text-sm text-slate-500 font-medium mt-1">
                            {{ strategy === 'WIN_WIN' ? 'Po wypłaceniu podwyżek' : 'Netto dla firmy' }}
                        </div>
                    </div>
                    <div class="p-2 bg-stratton-gold/10 text-stratton-gold rounded-xl border border-stratton-gold/20">
                        <AppIcon name="arrow-trending-up" class="w-6 h-6" />
                    </div>
                </div>
                <div class="text-2xl md:text-4xl lg:text-5xl font-extrabold text-white tracking-tight">
                    {{ formatPLN(simulation.monthlySavings) }}
                </div>
                 <div v-if="strategy === 'WIN_WIN'" class="mt-2 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-blue-500/20 text-blue-400 text-xs font-bold border border-blue-500/30">
                    <AppIcon name="users" class="w-3 h-3" />
                     + Zadowoleni pracownicy
                 </div>
            </div>

            <!-- Card 2: Yearly Potential (Dark Mode) -->
            <div class="rounded-2xl p-5 border border-[#003366] shadow-sm text-white relative overflow-hidden group transition-colors" style="background: linear-gradient(135deg, #001f3d 0%, #002a52 50%, #003366 100%)">
                <div class="absolute -right-10 -top-10 w-40 h-40 bg-stratton-gold rounded-full blur-[80px] opacity-10 group-hover:opacity-20 transition-opacity"></div>

                <div class="flex justify-between items-start mb-3 relative z-10">
                    <div>
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-widest">Potencjał roczny</div>
                        <div class="text-sm text-slate-500 font-medium mt-1">Skumulowana oszczędność</div>
                    </div>
                    <div class="p-2 bg-slate-700 text-white rounded-lg">
                         <AppIcon name="chart-pie" class="w-6 h-6" />
                    </div>
                </div>
                <div class="text-2xl md:text-4xl lg:text-5xl font-extrabold text-white tracking-tight relative z-10">
                    {{ formatPLN(simulation.yearlySavings) }}
                </div>
                <div class="mt-4 h-1.5 w-full bg-slate-700 rounded-full overflow-hidden relative z-10">
                    <div class="h-full bg-linear-to-r from-stratton-gold to-[#D4AF37] w-[70%] animate-pulse"></div>
                </div>
            </div>
        </div>
      </div>

      <!-- Bottom Module: Structure + Comparison -->
      <div class="max-w-screen-2xl mx-auto">
      <div class="bg-white rounded-card shadow-card border border-slate-200 p-3 md:p-6">
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 md:gap-8 items-start">
        
        <!-- Left Panel: Structure -->
        <div class="xl:col-span-4 text-white flex flex-col shrink-0 border border-[#003366] rounded-2xl shadow-xl" style="background: linear-gradient(135deg, #001f3d 0%, #002a52 50%, #003366 100%)">
          <div class="p-6 pb-2">
            <div class="flex items-center gap-2 mb-3 text-stratton-gold">
              <div class="p-2 bg-stratton-gold/10 rounded-xl border border-stratton-gold/20 shadow-[0_0_15px_rgba(197,160,89,0.1)]">
                <AppIcon name="bolt" class="w-5 h-5" />
              </div>
              <span class="font-extrabold uppercase tracking-widest text-xs">Szybka Symulacja v2.8</span>
            </div>
            <h2 class="text-xl md:text-2xl font-bold text-white leading-tight">Struktura zatrudnienia</h2>
            <p class="text-slate-400 text-sm mt-2 leading-relaxed">Skonfiguruj strukturę zatrudnienia i wybierz model wynagradzania</p>
          </div>

          <div class="p-6 space-y-6">
            <div class="space-y-4">
              <label class="text-xs font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                <AppIcon name="users" class="w-4 h-4 text-stratton-gold/60" />
                pracownicy zatrudnieni ogółem:
              </label>
              <div class="relative group">
                <input v-model.number="empCount" type="number" min="1" class="w-full bg-slate-800/50 border border-slate-700/50 rounded-xl py-3 px-4 text-white font-black text-xl focus:ring-4 focus:ring-stratton-gold/20 focus:border-stratton-gold outline-none transition-all group-hover:border-slate-600 shadow-inner" />
                <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none">
                  <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Wszystkich</span>
                </div>
              </div>
            </div>

            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <label class="text-xs font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                  <AppIcon name="filter" class="w-4 h-4 text-stratton-gold/60" />
                  Struktura Umów
                </label>
                <div class="flex items-center bg-slate-800 rounded-lg p-1 border border-slate-700/50">
                  <button 
                    type="button" 
                    class="px-3 py-1 text-[10px] font-black rounded uppercase tracking-widest transition-all" 
                    :class="salaryMode === 'NETTO' ? 'bg-stratton-gold text-white shadow-[0_0_10px_rgba(197,160,89,0.3)]' : 'text-slate-500 hover:text-white'" 
                    @click="salaryMode = 'NETTO'"
                  >
                    netto
                  </button>
                  <button 
                    type="button" 
                    class="px-3 py-1 text-[10px] font-black rounded uppercase tracking-widest transition-all" 
                    :class="salaryMode === 'BRUTTO' ? 'bg-stratton-gold text-white shadow-[0_0_10px_rgba(197,160,89,0.3)]' : 'text-slate-500 hover:text-white'" 
                    @click="salaryMode = 'BRUTTO'"
                  >
                    brutto
                  </button>
                </div>
              </div>
              <div v-if="!isCountValid" class="text-[11px] text-rose-400 font-black bg-rose-500/10 px-3 py-2 rounded-xl border border-rose-500/20 animate-pulse text-center uppercase tracking-widest">
                Przekroczono limit zatrudnienia
              </div>

              <div class="grid grid-cols-2 gap-4">
                <!-- Column UoP -->
                <div class="space-y-2">
                  <div class="space-y-1">
                    <label class="text-[10px] text-slate-500 font-black uppercase tracking-widest">Umowa o Pracę</label>
                    <input 
                      v-model.number="countUopInput" 
                      type="number" 
                      min="0"
                      class="w-full bg-slate-800/80 border rounded-xl py-3 px-4 text-white font-black focus:ring-4 outline-none transition-all shadow-inner text-lg"
                      :class="!isCountValid ? 'border-rose-500/50 focus:ring-rose-500/20' : 'border-slate-700 focus:ring-stratton-gold/20 focus:border-stratton-gold'"
                    />
                  </div>
                  <div class="space-y-1">
                    <label class="text-[10px] text-slate-500 font-black uppercase tracking-widest">Średnia płaca UoP</label>
                    <input 
                      v-model.number="avgSalaryUop" 
                      type="number" 
                      min="0"
                      class="w-full bg-slate-800/80 border border-slate-700 rounded-xl py-3 px-4 text-white font-black focus:ring-4 focus:ring-stratton-gold/20 focus:border-stratton-gold outline-none transition-all shadow-inner text-lg"
                    />
                  </div>
                </div>

                <!-- Column UZ -->
                <div class="space-y-2">
                  <div class="space-y-1">
                    <label class="text-[10px] text-slate-500 font-black uppercase tracking-widest">Umowa Zlecenie</label>
                    <input 
                      v-model.number="countUzInput" 
                      type="number" 
                      min="0"
                      class="w-full bg-slate-800/80 border rounded-xl py-3 px-4 text-white font-black focus:ring-4 outline-none transition-all shadow-inner text-lg"
                      :class="!isCountValid ? 'border-rose-500/50 focus:ring-rose-500/20' : 'border-slate-700 focus:ring-stratton-gold/20 focus:border-stratton-gold'" 
                    />
                  </div>
                  <div class="space-y-1">
                    <label class="text-[10px] text-slate-500 font-black uppercase tracking-widest">Średnia płaca UZ</label>
                    <input 
                      v-model.number="avgSalaryUz" 
                      type="number" 
                      min="0"
                      class="w-full bg-slate-800/80 border border-slate-700 rounded-xl py-3 px-4 text-white font-black focus:ring-4 focus:ring-stratton-gold/20 focus:border-stratton-gold outline-none transition-all shadow-inner text-lg"
                    />
                  </div>
                </div>
              </div>
            </div>

            <div class="space-y-3">
              <label class="text-xs font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                <AppIcon name="layers" class="w-4 h-4 text-stratton-gold/60" />
                Model optymalizacji
              </label>
              <div class="relative p-4 rounded-2xl border-2 bg-slate-800 border-stratton-gold shadow-[0_0_30px_rgba(197,160,89,0.15)] ring-1 ring-stratton-gold/20 flex items-start gap-4 overflow-hidden">
                <div class="absolute inset-0 bg-linear-to-br from-stratton-gold/5 to-transparent pointer-events-none"></div>
                <div class="mt-0.5 p-2 rounded-xl bg-stratton-gold text-white shrink-0">
                  <AppIcon name="arrow-trending-up" class="w-4 h-4" />
                </div>
                <div class="relative z-10">
                  <div class="text-sm font-black text-white uppercase tracking-wider">
                    Eliton Prime<sup class="text-[8px] ml-0.5 opacity-50">TM</sup>
                  </div>
                  <div class="mt-1.5 space-y-0.5">
                    <div class="text-[11px] text-stratton-gold font-extrabold uppercase tracking-widest">
                      Opłata serwisowa: 28%
                    </div>
                    <div class="text-[10px] text-slate-400 font-semibold">
                      od wartości nominalnej świadczenia
                    </div>
                  </div>
                </div>
                <div class="ml-auto shrink-0 flex items-center self-center">
                  <div class="w-5 h-5 rounded-full bg-stratton-gold flex items-center justify-center shadow-[0_0_12px_rgba(197,160,89,0.5)]">
                    <AppIcon name="check" class="w-3 h-3 text-white" />
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>

        <!-- Right Panel: Comparisons & Tables -->
        <div class="xl:col-span-8 space-y-4 md:space-y-8">
          <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 md:p-8">
            <div class="flex items-center justify-between mb-4 md:mb-8">
              <h3 class="font-bold text-slate-800 text-lg flex items-center gap-2">
                <div class="p-1.5 bg-stratton-gold/10 rounded-lg">
                  <AppIcon name="coins" class="text-stratton-gold w-5 h-5" />
                </div>
                Porównanie kosztów zatrudnienia
              </h3>
              <div class="text-xs font-bold text-slate-400 bg-slate-50 px-3 py-1 rounded-full border border-slate-100 uppercase tracking-widest">
                UoP: {{ countUopInput }} / UZ: {{ countUzInput }}
              </div>
            </div>

            <div class="space-y-5">
              <!-- Bar 1: DO TEJ PORY -->
              <div>
                <div class="flex items-baseline justify-between mb-1.5">
                  <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Do tej pory</span>
                  <span class="text-sm font-extrabold text-rose-500">{{ formatPLN(simulation.totalStd) }}</span>
                </div>
                <div class="h-9 w-full bg-rose-50 rounded-xl overflow-hidden border border-rose-100 shadow-inner relative">
                  <div class="absolute inset-0 bg-linear-to-r from-rose-500 to-rose-400 flex items-center px-4">
                    <span class="text-white text-[10px] font-black uppercase tracking-widest opacity-80">Obecny koszt zatrudnienia</span>
                  </div>
                </div>
              </div>

              <!-- Bar 2: Eliton Prime + Oszczędność -->
              <div>
                <div class="flex items-baseline justify-between mb-1.5">
                  <span class="text-[10px] font-black uppercase tracking-widest text-stratton-gold flex items-center gap-1">
                    Eliton Prime<sup class="text-[7px] ml-0.5">TM</sup>
                    <span class="ml-1 text-slate-400 font-semibold normal-case tracking-normal text-[9px]">· opłata 28%</span>
                  </span>
                  <span class="text-sm font-extrabold text-slate-800">{{ formatPLN(simulation.totalNew) }}</span>
                </div>
                <div class="h-9 w-full bg-slate-100 rounded-xl overflow-hidden border border-slate-200 shadow-inner flex relative">
                  <!-- Cost segment -->
                  <div
                    class="h-full bg-linear-to-r from-[#b8922a] to-stratton-gold flex items-center justify-center gap-1.5 shrink-0 transition-all duration-1000 border-r-2 border-white/40 shadow-[4px_0_12px_rgba(0,0,0,0.15)] z-10"
                    :style="{ width: `${costRatio}%` }"
                  >
                    <span v-if="costRatio > 22" class="text-white text-[9px] font-black uppercase tracking-widest truncate px-2 drop-shadow">
                      Eliton Prime<sup class="text-[6px] ml-0.5">TM</sup>
                    </span>
                  </div>
                  <!-- Savings segment -->
                  <div class="flex-1 flex items-center justify-center gap-1.5 relative overflow-hidden">
                    <div class="absolute inset-0 bg-linear-to-r from-emerald-400/20 to-emerald-500/30"></div>
                    <div class="absolute inset-0" style="background-image: repeating-linear-gradient(45deg, transparent, transparent 4px, rgba(16,185,129,0.07) 4px, rgba(16,185,129,0.07) 8px)"></div>
                    <AppIcon name="arrow-trending-up" class="w-3 h-3 text-emerald-600 relative z-10 shrink-0" />
                    <span class="text-emerald-700 text-[9px] font-black uppercase tracking-widest relative z-10 whitespace-nowrap">
                      Oszczędność {{ formatPLN(simulation.savings) }}
                    </span>
                  </div>
                </div>
                <!-- Savings % badge -->
                <div class="mt-2 flex justify-end">
                  <div class="inline-flex items-center gap-1 bg-emerald-50 border border-emerald-200 text-emerald-700 text-[10px] font-black px-2.5 py-0.5 rounded-full">
                    <AppIcon name="arrow-trending-up" class="w-3 h-3" />
                    {{ simulation.totalStd > 0 ? ((simulation.savings / simulation.totalStd) * 100).toFixed(1) : '0' }}% oszczędności miesięcznie
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-2xl border border-slate-200 shadow-xl overflow-hidden">
            <div class="overflow-x-auto">
              <table class="w-full text-sm text-left min-w-[640px]">
              <thead class="bg-slate-50 text-slate-400 text-[10px] uppercase font-black tracking-widest">
                <tr>
                  <th class="px-6 py-4">Kategoria</th>
                  <th class="px-6 py-4 text-right">DO TEJ PORY</th>
                  <th class="px-6 py-4 text-right">Eliton Prime<sup class="text-[7px] ml-0.5">TM</sup></th>
                  <th class="px-6 py-4 text-right text-stratton-gold">OSZCZĘDNOŚCI</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-bold">
                <tr class="hover:bg-slate-50/50 transition-colors">
                  <td class="px-6 py-4 text-slate-700">Całkowity koszt zatrudnienia</td>
                  <td class="px-6 py-4 text-right text-rose-500">{{ formatPLN(simulation.totalStd) }}</td>
                  <td class="px-6 py-4 text-right text-slate-900">{{ formatPLN(simulation.totalNew) }}</td>
                  <td class="px-6 py-4 text-right text-stratton-gold bg-amber-50/30">+{{ formatPLN(simulation.savings) }}</td>
                </tr>
                <tr class="hover:bg-slate-50/50 transition-colors italic">
                  <td class="px-6 py-4 text-slate-500 pl-10 text-xs">
                    Opłata serwisowa
                    <div class="text-[9px] text-slate-400 font-medium">Success fee</div>
                  </td>
                  <td class="px-6 py-4 text-right text-slate-300">-</td>
                  <td class="px-6 py-4 text-right text-amber-600/70">{{ formatPLN(simulation.totalProv) }}</td>
                  <td class="px-6 py-4 text-right"></td>
                </tr>
              </tbody>
              </table>
            </div>
          </div>

          <!-- Email Send Button -->
          <div class="mt-2">
            <button
              type="button"
              class="w-full h-11 bg-slate-50 border border-slate-200 text-slate-600 font-extrabold uppercase tracking-widest rounded-xl transition-all flex items-center justify-center gap-2 group hover:bg-blue-50 hover:border-blue-300 hover:text-blue-600 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="!isCountValid"
              @click="openEmailModal"
            >
              <AppIcon name="envelope" class="w-4 h-4" />
              <span class="text-[12px]">Wyślij email</span>
            </button>
          </div>

          <!-- Action Buttons -->
          <div class="flex flex-col sm:flex-row gap-3">
            <button
              type="button"
              class="flex-1 h-12 bg-white border border-stratton-gold/40 text-stratton-gold font-extrabold uppercase tracking-widest rounded-xl transition-all flex items-center justify-center gap-2 group disabled:opacity-50 disabled:cursor-not-allowed hover:bg-stratton-gold hover:text-white active:scale-95"
              :disabled="!isCountValid || pdfGen.isGenerating.value"
              @click="handleShortOffer"
            >
              <AppIcon v-if="pdfGen.isGenerating.value" name="arrow-path" class="w-4 h-4 animate-spin" />
              <AppIcon v-else name="document-text" class="w-4 h-4" />
              <span class="text-[12px]">{{ pdfGen.isGenerating.value ? 'Generowanie...' : 'Krótka oferta PDF' }}</span>
            </button>
            <button
              type="button"
              class="flex-1 h-12 bg-white border border-slate-200 text-slate-600 font-extrabold uppercase tracking-widest rounded-xl transition-all flex items-center justify-center gap-2 group disabled:opacity-50 disabled:cursor-not-allowed hover:bg-blue-50 hover:border-blue-300 hover:text-blue-600 active:scale-95"
              :disabled="!isCountValid || isSendingOffer"
              @click="generateQuickOffer"
            >
              <AppIcon v-if="isSendingOffer" name="arrow-path" class="w-4 h-4 animate-spin" />
              <AppIcon v-else name="envelope" class="w-4 h-4" />
              <span class="text-[12px]">{{ hasClientContext ? 'Wyślij ofertę szacunkową' : 'Drukuj / PDF' }}</span>
            </button>
            <button
              v-if="!isLeadowiec"
              type="button"
              class="flex-1 h-12 bg-linear-to-r from-[#D4AF37] to-stratton-gold text-white font-extrabold uppercase tracking-widest rounded-xl shadow-[0_12px_24px_-8px_rgba(197,160,89,0.5)] transition-all duration-300 flex items-center justify-center gap-3 group disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-slate-700 disabled:shadow-none border border-white/20 hover:brightness-110 active:scale-95"
              :disabled="!isCountValid"
              @click="handleTransfer"
            >
              <span>Przejdź do szczegółów</span>
              <AppIcon name="arrow-right" class="w-5 h-5 group-hover:translate-x-1 transition-transform" />
            </button>
          </div>
        </div>
      </div>
      </div>
      </div>
    </div>
  </div>

</template>
