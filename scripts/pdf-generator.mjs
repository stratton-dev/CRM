/**
 * Stratton Prime PDF Generator
 * Generates offer PDFs using Puppeteer + HTML/CSS
 * Usage: node pdf-generator.mjs --type short|long|product-card --data-file /tmp/data.json
 */

import { readFileSync } from 'fs';
import { parseArgs } from 'util';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));

// ── Parse args ──────────────────────────────────────────────────────────────
const { values } = parseArgs({
  args: process.argv.slice(2),
  options: {
    type: { type: 'string' },
    'data-file': { type: 'string' },
  },
});

const dataFile = values['data-file'];
const type = values['type'];

if (!dataFile || !type) {
  process.stderr.write('Usage: node pdf-generator.mjs --type <type> --data-file <path>\n');
  process.exit(1);
}

const data = JSON.parse(readFileSync(dataFile, 'utf8'));

// ── Load assets ──────────────────────────────────────────────────────────────
let LOGO_OFERTA_B64 = '';
let radoslawZukPhotoB64 = '';
let tarczaOchronnaB64 = '';
let agaB64 = '';
let PARTNERS = {};

try {
  const logoMod = await import('./assets/logoOfertaB64.mjs');
  LOGO_OFERTA_B64 = logoMod.LOGO_OFERTA_B64 || '';
} catch { /* asset not found, skip */ }

try {
  const radMod = await import('./assets/radoslawZukB64.mjs');
  radoslawZukPhotoB64 = radMod.radoslawZukPhotoB64 || '';
} catch { /* skip */ }

try {
  const tarczaMod = await import('./assets/tarczaB64.mjs');
  const raw = tarczaMod.tarczaOchronnaB64 || '';
  tarczaOchronnaB64 = raw.startsWith('data:') ? raw : `data:image/png;base64,${raw}`;
} catch { /* skip */ }

try {
  const agaRaw = readFileSync(join(__dirname, 'assets/agaCieciaraPhoto.jpg'));
  agaB64 = `data:image/jpeg;base64,${agaRaw.toString('base64')}`;
} catch { /* skip */ }

try {
  const partnersMod = await import('./assets/partners/partnersB64.mjs');
  PARTNERS = {
    pzu:          partnersMod.pzu_B64 || '',
    ergoHestia:   partnersMod.ergo_hestia_B64 || '',
    allianz:      partnersMod.allianz_logo_B64 || '',
    generali:     partnersMod.generali_logo_big_B64 || '',
    warta:        partnersMod.logo_warta_B64 || '',
    lloyds:       partnersMod.lloyds_logo_sized_nav__1__B64 || '',
    ladenhall:    partnersMod.ladenhall_B64 || '',
    laven:        partnersMod.Laven_logo_dark_B64 || '',
    viennaLife:   partnersMod.vienna_life_logo_B64 || '',
    uniqa:        partnersMod.uniqa_logo_B64 || '',
    signalIduna:  partnersMod.signal_iduna_polska_logo_B64 || '',
    unum:         partnersMod.unum_B64 || '',
    luxmed:       partnersMod.luxmed_B64 || '',
    orange:       partnersMod.orange_B64 || '',
  };
} catch { /* skip */ }

// ── Formatters ───────────────────────────────────────────────────────────────
function formatPLN(amount) {
  if (typeof amount !== 'number' || isNaN(amount)) return '0 zł';
  return new Intl.NumberFormat('pl-PL', {
    style: 'currency',
    currency: 'PLN',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount);
}

function formatPercent(value, decimals = 1) {
  if (typeof value !== 'number' || isNaN(value)) return '0%';
  return value.toFixed(decimals).replace('.', ',') + '%';
}

// ── Common CSS ───────────────────────────────────────────────────────────────
const BASE_CSS = `
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap');
  @page { size: A4 portrait; margin: 0; }
  :root {
    --navy: #05162e;
    --navy-light: #0D1F3C;
    --gold: #C5A059;
    --gold-light: #d4b06a;
    --green: #16a34a;
    --gray-bg: #f3f4f6;
    --gray-text: #374151;
  }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: 'Inter', sans-serif;
    font-weight: 300;
    color: #111827;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .serif { font-family: 'Playfair Display', Georgia, serif; }
  .page {
    background: white;
    width: 210mm;
    min-height: 297mm;
    height: 297mm;
    overflow: hidden;
    position: relative;
    display: flex;
    flex-direction: column;
    page-break-after: always;
    break-after: page;
  }
  @media print {
    html, body { padding: 0 !important; margin: 0 !important; background: white !important; }
    .page { margin: 0 !important; box-shadow: none !important; page-break-after: always !important; }
  }
  .pre-tag {
    font-size: 9px; font-weight: 700; letter-spacing: 0.3em;
    text-transform: uppercase; color: var(--gold); display: block; margin-bottom: 10px;
  }
  .page-header {
    background: var(--navy); color: white;
    padding: 32px 48px; border-bottom: 3px solid var(--gold);
  }
  .page-section { padding: 24px 48px; flex-grow: 1; }
  .page-footer {
    padding: 10px 48px; border-top: 1px solid #d1d5db;
    font-size: 9px; color: #9ca3af; display: flex;
    justify-content: space-between; text-transform: uppercase;
    font-weight: 700; letter-spacing: 0.1em;
  }
  table { width: 100%; border-collapse: collapse; }
  thead tr { background: var(--navy); color: white; font-size: 9px; font-weight: 700; letter-spacing: 0.15em; text-transform: uppercase; }
  thead th { padding: 12px 14px; text-align: left; }
  tbody td { padding: 11px 14px; border-bottom: 1px solid #f3f4f6; font-size: 12px; }
  tbody tr:nth-child(even) { background: #fafafa; }
  .kpi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin: 14px 0; }
  .kpi-card { background: #f8f9fa; border: 1px solid #d1d5db; border-top: 3px solid var(--gold); padding: 14px 12px; text-align: center; }
  .kpi-card.dark { background: var(--navy); color: white; border-color: var(--navy); border-top-color: var(--gold); }
  .kpi-value { font-family: 'Playfair Display', serif; font-size: 22px; font-weight: 700; margin-top: 4px; color: var(--navy); font-variant-numeric: tabular-nums; }
  .kpi-label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.18em; color: #6b7280; font-weight: 700; }
  .photo-circle { border-radius: 50%; border: 2px solid var(--gold); object-fit: cover; }
  .partner-logo { height: 28px; object-fit: contain; filter: grayscale(100%) opacity(0.65); }
  .partner-logo-color { height: 32px; object-fit: contain; }
`;

// ── KRÓTKA OFERTA (Short Offer — V13 style) ──────────────────────────────────
function generateShortOfferHtml(d) {
  const firma = d.firma || {};
  const p = d.podsumowanie || {};
  const h = d.handlowiec || { imie: 'Agnieszka', nazwisko: 'Cięciara', email: 'a.cieciara@stratton-prime.pl' };
  const dataW = d.dataWystawienia || new Date().toLocaleDateString('pl-PL');
  const dataWaz = d.dataWaznosci || new Date(Date.now() + 14 * 86400000).toLocaleDateString('pl-PL');

  const oszczMies = p.oszczednoscMiesieczna || 0;
  const oszczRocz = p.oszczednoscRoczna || (oszczMies * 12);
  const prowizja = p.prowizja || 0;
  const prowizjaProc = p.prowizjaProc || 28;
  const liczbaPrac = p.liczbaObjetchPracownikow || 0;
  const roi = p.roi || (prowizja > 0 ? Math.round((oszczRocz / (prowizja * 12)) * 100) : 0);
  const zyskNetto = p.zyskNetto || (oszczRocz - prowizja * 12);

  const partnerLogos = `
    <div style="display:flex; gap:16px; align-items:center; flex-wrap:wrap; margin-top:12px;">
      ${PARTNERS.pzu ? `<img src="${PARTNERS.pzu}" class="partner-logo" style="height:24px;" alt="PZU">` : '<span style="font-size:9px;color:#9ca3af;">PZU</span>'}
      ${PARTNERS.ergoHestia ? `<img src="${PARTNERS.ergoHestia}" class="partner-logo" style="height:24px;" alt="Ergo Hestia">` : '<span style="font-size:9px;color:#9ca3af;">Ergo Hestia</span>'}
      ${PARTNERS.allianz ? `<img src="${PARTNERS.allianz}" class="partner-logo" style="height:24px;" alt="Allianz">` : '<span style="font-size:9px;color:#9ca3af;">Allianz</span>'}
      ${PARTNERS.generali ? `<img src="${PARTNERS.generali}" class="partner-logo" style="height:24px;" alt="Generali">` : '<span style="font-size:9px;color:#9ca3af;">Generali</span>'}
      ${PARTNERS.warta ? `<img src="${PARTNERS.warta}" class="partner-logo" style="height:24px;" alt="Warta">` : '<span style="font-size:9px;color:#9ca3af;">Warta</span>'}
      ${PARTNERS.lloyds ? `<img src="${PARTNERS.lloyds}" class="partner-logo" style="height:24px;" alt="Lloyd's">` : "<span style=\"font-size:9px;color:#9ca3af;\">Lloyd's</span>"}
    </div>`;

  // Page 1 — Cover
  const page1 = `
<div class="page">
  <header style="background:var(--navy);color:white;padding:36px 48px 24px;position:relative;overflow:hidden;flex-shrink:0;">
    <div style="position:absolute;top:0;right:0;width:220px;height:100%;background:linear-gradient(135deg,transparent 60%,rgba(197,160,89,0.08) 60%);pointer-events:none;"></div>
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;">
      <div>
        ${LOGO_OFERTA_B64 ? `<img src="${LOGO_OFERTA_B64}" style="height:36px;" alt="Stratton Prime">` : `<div style="font-size:18px;font-weight:900;letter-spacing:0.2em;text-transform:uppercase;">STRATTON <span style="color:var(--gold);">PRIME</span></div>`}
        <div style="font-size:8px;letter-spacing:0.3em;color:rgba(255,255,255,0.45);text-transform:uppercase;margin-top:4px;">ARCHITEKCI WARTOŚCI BIZNESOWEJ</div>
      </div>
      <div style="text-align:right;font-size:9px;letter-spacing:0.1em;text-transform:uppercase;line-height:2;color:rgba(255,255,255,0.55);">
        <div>DATA: ${dataW}</div>
        <div>WAŻNA DO: ${dataWaz}</div>
        <div>PRZYGOTOWAŁA: <span style="color:var(--gold);">${h.imie} ${h.nazwisko}</span></div>
      </div>
    </div>
    <div style="font-size:9px;font-weight:700;letter-spacing:0.3em;text-transform:uppercase;color:var(--gold);margin-bottom:10px;">OFERTA SZACUNKOWA · ANALIZA LISTY PŁAC</div>
    <h1 class="serif" style="font-size:36px;line-height:1.0;letter-spacing:-0.02em;color:white;margin-bottom:8px;">Eliton Benefits System™</h1>
    <p style="font-size:13px;font-weight:300;color:rgba(255,255,255,0.7);max-width:460px;line-height:1.5;">
      Szacujemy Twój potencjał oszczędności bez konieczności podawania pełnej listy płac.
    </p>
    <div style="margin-top:12px;font-size:8px;font-weight:700;letter-spacing:0.25em;text-transform:uppercase;color:rgba(255,255,255,0.45);">
      PRZYGOTOWANO DLA: <span style="color:white;font-size:13px;letter-spacing:0.08em;font-weight:600;">${firma.nazwa || 'Twoja Firma'}</span>
      ${firma.nip ? `<span style="color:rgba(255,255,255,0.4);font-size:10px;margin-left:8px;">NIP: ${firma.nip}</span>` : ''}
    </div>
    <div style="position:absolute;bottom:0;left:0;right:0;height:3px;background:var(--gold);"></div>
  </header>

  <section class="page-section">
    <!-- Hero KPI strip -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0;margin-bottom:16px;border:1px solid #e5e7eb;border-radius:3px;overflow:hidden;">
      <div style="padding:14px 16px;text-align:center;background:white;border-right:1px solid #e5e7eb;">
        <div style="font-size:8px;font-weight:700;letter-spacing:0.2em;text-transform:uppercase;color:#6b7280;margin-bottom:8px;">Oszczędność · miesięcznie</div>
        <div class="serif" style="font-size:26px;font-weight:700;color:var(--green);">${formatPLN(oszczMies)}</div>
        <div style="font-size:9px;color:#9ca3af;margin-top:2px;">/miesiąc dla firmy</div>
      </div>
      <div style="padding:14px 16px;text-align:center;background:var(--navy);border-right:1px solid #0e2a4e;">
        <div style="font-size:8px;font-weight:700;letter-spacing:0.2em;text-transform:uppercase;color:rgba(255,255,255,0.45);margin-bottom:8px;">Potencjał roczny</div>
        <div class="serif" style="font-size:26px;font-weight:700;color:var(--gold);">${formatPLN(oszczRocz)}</div>
        <div style="font-size:9px;color:rgba(255,255,255,0.4);margin-top:2px;">/rok oszczędności</div>
      </div>
      <div style="padding:14px 16px;text-align:center;background:white;">
        <div style="font-size:8px;font-weight:700;letter-spacing:0.2em;text-transform:uppercase;color:#6b7280;margin-bottom:8px;">Oferta ważna do</div>
        <div class="serif" style="font-size:22px;font-weight:700;color:var(--navy);">${dataWaz}</div>
        <div style="font-size:9px;color:#9ca3af;margin-top:2px;">termin ważności</div>
      </div>
    </div>

    <!-- Summary table -->
    <div style="margin-bottom:10px;">
      <span class="pre-tag">Wynik szacunkowy · ${liczbaPrac} pracownik(ów)</span>
      <h2 class="serif" style="font-size:24px;line-height:1.1;color:var(--navy);">Twoje liczby. Twoja decyzja.</h2>
    </div>
    <table style="margin-bottom:12px;">
      <thead>
        <tr>
          <th style="text-align:left;">Parametr</th>
          <th style="text-align:center;">Wartość</th>
        </tr>
      </thead>
      <tbody>
        <tr><td>Pracownicy objęci programem</td><td style="text-align:center;font-weight:600;">${liczbaPrac}</td></tr>
        <tr style="background:#fafafa;"><td>Oszczędność miesięczna netto</td><td style="text-align:center;color:var(--green);font-weight:700;">${formatPLN(oszczMies)}</td></tr>
        <tr><td>Stawka prowizji za zarządzanie</td><td style="text-align:center;font-weight:600;">${prowizjaProc}%</td></tr>
        <tr style="background:#fafafa;"><td>ROI (zwrot z inwestycji)</td><td style="text-align:center;color:var(--gold);font-weight:700;">${roi}%</td></tr>
      </tbody>
    </table>

    <!-- Pull quote -->
    <div style="background:var(--navy);color:white;padding:14px 28px;font-family:'Playfair Display',serif;font-size:16px;font-style:italic;line-height:1.5;text-align:center;border-top:3px solid var(--gold);border-bottom:3px solid var(--gold);margin:10px 0;">
      Każdy miesiąc zwłoki to <span style="color:var(--gold);font-style:normal;font-weight:700;">${formatPLN(oszczMies)}</span> które oddajesz do ZUS bezpowrotnie.
    </div>
  </section>

  <footer class="page-footer">
    <span>Stratton Prime · Architekci Wartości Biznesowej</span>
    <span>01</span>
  </footer>
</div>`;

  // Page 2 — Scope + ROI + Partners
  const page2 = `
<div class="page">
  <header class="page-header">
    <span class="pre-tag">Co zawiera wdrożenie</span>
    <h2 class="serif" style="font-size:28px;line-height:1.1;color:white;letter-spacing:-0.02em;">Pełny pakiet Eliton Benefits System™</h2>
  </header>

  <section class="page-section">
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:32px;">
      <div>
        ${['Pełna dokumentacja: Regulamin wynagradzania, aneksy do umów, tabele stanowisk.',
           'Obsługa kadrowa: Gotowe instrukcje księgowania i rozliczania składek.',
           'Szkolenia: Przygotowanie HR i księgowości w 15 minut miesięcznie.',
           'Asekuracja prawna: Przejęcie odpowiedzialności za komunikację z ZUS/KAS.',
           'Polisa D&O: Ochrona osobista Zarządu do kwoty 1 000 000 zł.',
           'Opieka post-wdrożeniowa: Dedykowany opiekun i comiesięczny audyt.',
        ].map(item => {
          const [title, ...rest] = item.split(': ');
          return `<div style="display:flex;gap:10px;margin-bottom:8px;align-items:flex-start;">
            <span style="color:var(--gold);font-weight:700;font-size:15px;line-height:1.4;flex-shrink:0;">✓</span>
            <span style="font-size:12px;color:#374151;line-height:1.5;"><strong>${title}:</strong> ${rest.join(': ')}</span>
          </div>`;
        }).join('')}

        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;padding:14px 18px;background:#f8f9fa;border-left:3px solid var(--gold);">
          <div>
            <div style="font-size:8px;font-weight:700;letter-spacing:0.2em;text-transform:uppercase;color:#6b7280;margin-bottom:3px;">Prowizja za zarządzanie · miesięcznie</div>
            <div style="font-weight:600;color:var(--navy);font-size:14px;">Inwestycja w system</div>
          </div>
          <div class="serif" style="font-size:28px;font-weight:700;color:var(--navy);white-space:nowrap;">${formatPLN(prowizja)}</div>
        </div>
      </div>

      <!-- ROI box -->
      <div style="background:var(--navy);color:white;padding:28px 20px;border-bottom:5px solid var(--gold);text-align:center;">
        <span class="pre-tag" style="margin-bottom:20px;display:block;">Analiza ROI · I rok</span>
        <div style="margin-bottom:12px;">
          <div style="font-size:8px;text-transform:uppercase;letter-spacing:0.18em;color:rgba(255,255,255,0.45);margin-bottom:4px;">Oszczędność roczna</div>
          <div class="serif" style="font-size:26px;font-weight:700;">${formatPLN(oszczRocz)}</div>
        </div>
        <div style="border-top:1px solid rgba(255,255,255,0.1);margin:12px 0;"></div>
        <div style="margin-bottom:12px;">
          <div style="font-size:8px;text-transform:uppercase;letter-spacing:0.18em;color:rgba(255,255,255,0.45);margin-bottom:4px;">Inwestycja roczna</div>
          <div class="serif" style="font-size:26px;font-weight:700;">${formatPLN(prowizja * 12)}</div>
        </div>
        <div style="background:var(--gold);color:var(--navy);padding:16px;margin-top:12px;">
          <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:0.2em;margin-bottom:4px;">Zysk netto / rok</div>
          <div class="serif" style="font-size:30px;font-weight:700;">+${formatPLN(zyskNetto)}</div>
        </div>
        <div style="margin-top:14px;padding:12px;background:rgba(197,160,89,0.15);border-radius:2px;">
          <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:0.25em;color:rgba(255,255,255,0.5);margin-bottom:4px;">ROI</div>
          <div class="serif" style="font-size:44px;font-weight:700;color:var(--gold);line-height:1;">${roi}%</div>
        </div>
      </div>
    </div>

    <!-- Partners strip -->
    <div style="margin-top:16px;padding-top:14px;border-top:1px solid #e5e7eb;">
      <div style="font-size:8px;font-weight:700;letter-spacing:0.25em;text-transform:uppercase;color:#6b7280;margin-bottom:8px;">Partnerzy ubezpieczeniowi</div>
      ${partnerLogos}
    </div>
  </section>

  <footer class="page-footer">
    <span>Stratton Prime · Architekci Wartości Biznesowej</span>
    <span>02</span>
  </footer>
</div>`;

  // Page 3 — Implementation + Contact
  const callDate = new Date(Date.now() + 7 * 86400000).toLocaleDateString('pl-PL');
  const page3 = `
<div class="page">
  <header class="page-header">
    <span class="pre-tag">Platforma &amp; harmonogram</span>
    <h2 class="serif" style="font-size:28px;line-height:1.1;color:white;letter-spacing:-0.02em;">Vouchery Eliton Benefits &amp; Wdrożenie</h2>
  </header>

  <section class="page-section">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:32px;">
      <div>
        <span class="pre-tag">Harmonogram wdrożenia</span>
        ${[
          ['Etap 1', 'Przygotowanie dokumentacji i aneksów'],
          ['Etap 2', 'Podpisanie umowy i aktywacja platformy'],
          ['Etap 3', 'Szkolenie HR i pierwsze oszczędności'],
          ['Efekt', 'Regularne oszczędności w każdym cyklu płac'],
        ].map(([label, text], i) => `
          <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border:1px solid #d1d5db;margin-bottom:8px;font-size:12px;${i === 1 ? 'background:var(--navy);color:white;border-color:var(--navy);border-bottom:3px solid var(--gold);' : i === 3 ? 'background:transparent;border-left:3px solid var(--gold);font-style:italic;' : 'background:#f8f9fa;'}">
            <span style="font-weight:700;font-size:8px;text-transform:uppercase;letter-spacing:0.2em;width:60px;flex-shrink:0;${i === 3 ? 'color:var(--gold);' : ''}">${label}</span>
            <span>${text}</span>
          </div>`
        ).join('')}
      </div>
      <div>
        <span class="pre-tag">Platforma kafeteryjna EBS</span>
        <p style="font-size:12px;color:#4b5563;margin-bottom:14px;line-height:1.6;font-weight:300;">
          Pracownicy otrzymują dostęp do platformy kafeteryjnej z najlepszymi dostawcami w Polsce. System działa 24/7.
        </p>
        <div style="padding:16px;background:#fffbeb;border:1px solid #fcd34d;border-left:4px solid var(--gold);font-size:12px;line-height:1.6;color:var(--navy);">
          <strong>Zasada Transparentności:</strong> Środki niewykorzystane przez pracowników są zwracane firmie w całości.
        </div>
        <div style="margin-top:16px;padding:16px;background:#f8f9fa;border-left:3px solid var(--navy);">
          <div style="font-size:8px;font-weight:700;letter-spacing:0.2em;text-transform:uppercase;color:#6b7280;margin-bottom:6px;">Proponowany termin rozmowy</div>
          <div class="serif" style="font-size:20px;font-weight:700;color:var(--navy);">${callDate}</div>
          <div style="font-size:10px;color:#6b7280;margin-top:3px;font-weight:300;">Spotkanie weryfikacyjne · omówienie warunków</div>
        </div>
      </div>
    </div>
  </section>

  <!-- Contact footer -->
  <footer style="background:var(--navy);color:white;padding:20px 48px;border-top:3px solid var(--gold);">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:32px;">
      <div style="display:flex;align-items:center;gap:14px;">
        ${agaB64 ? `<img src="${agaB64}" class="photo-circle" style="width:40px;height:40px;" alt="Agnieszka Cięciara">` : ''}
        <div>
          <div style="font-size:8px;font-weight:700;letter-spacing:0.3em;text-transform:uppercase;color:var(--gold);margin-bottom:4px;">Opiekun Projektu</div>
          <div class="serif" style="font-size:18px;font-weight:700;">${h.imie} ${h.nazwisko}</div>
          <div style="font-size:10px;color:rgba(255,255,255,0.55);letter-spacing:0.05em;text-transform:uppercase;">Dyrektor ds. Wdrożeń i Relacji Biznesowych</div>
        </div>
      </div>
      <div style="text-align:right;font-size:9px;line-height:2;letter-spacing:0.1em;text-transform:uppercase;">
        <div style="color:var(--gold);font-weight:700;">STRATTON PRIME</div>
        <div>EMAIL: ${h.email}</div>
        ${h.telefon ? `<div>TEL: ${h.telefon}</div>` : ''}
        <div style="color:var(--gold);">WWW.STRATTON-PRIME.PL</div>
      </div>
    </div>
  </footer>
</div>`;

  return buildHtmlDoc([page1, page2, page3], `Oferta — ${firma.nazwa || 'Stratton Prime'}`);
}

// ── DŁUGA OFERTA (Long Offer — V8 style, 9 pages) ────────────────────────────
function generateLongOfferHtml(d) {
  const firma = d.firma || {};
  const p = d.podsumowanie || {};
  const pracownicy = d.pracownicy || [];
  const h = d.handlowiec || { imie: 'Agnieszka', nazwisko: 'Cięciara', email: 'a.cieciara@stratton-prime.pl' };
  const dataW = d.dataWystawienia || new Date().toLocaleDateString('pl-PL');
  const dataWaz = d.dataWaznosci || new Date(Date.now() + 14 * 86400000).toLocaleDateString('pl-PL');

  const oszczMies = p.oszczednoscMiesieczna || 0;
  const oszczRocz = p.oszczednoscRoczna || (oszczMies * 12);
  const prowizja = p.prowizja || 0;
  const prowizjaRoczna = p.prowizjaRoczna || (prowizja * 12);
  const prowizjaProc = p.prowizjaProc || 26;
  const zyskNetto = p.zyskNetto || (oszczRocz - prowizjaRoczna);
  const roi = p.roi || (prowizjaRoczna > 0 ? Math.round((oszczRocz / prowizjaRoczna) * 100) : 0);
  const zwrotMies = p.zwrotWMiesiacach || (oszczMies > 0 ? Math.ceil(prowizja / oszczMies) : 0);
  const sumaZusStd = p.sumaZusPracodawcyStandard || 0;
  const liczbaPrac = p.liczbaObjetchPracownikow || pracownicy.length;

  // Page 1 — Cover
  const page1 = `
<div class="page">
  <div style="background:var(--navy);flex-grow:1;display:flex;flex-direction:column;justify-content:center;padding:56px 48px;position:relative;overflow:hidden;">
    <div style="position:absolute;top:0;right:0;width:300px;height:100%;background:linear-gradient(135deg,transparent 55%,rgba(197,160,89,0.06) 55%);pointer-events:none;"></div>
    <div style="position:absolute;bottom:0;left:0;right:0;height:3px;background:var(--gold);"></div>

    ${LOGO_OFERTA_B64 ? `<img src="${LOGO_OFERTA_B64}" style="height:40px;margin-bottom:40px;" alt="Stratton Prime">` : `<div style="font-size:20px;font-weight:900;letter-spacing:0.2em;text-transform:uppercase;color:white;margin-bottom:40px;">STRATTON <span style="color:var(--gold);">PRIME</span></div>`}

    <div style="font-size:9px;font-weight:700;letter-spacing:0.3em;text-transform:uppercase;color:var(--gold);margin-bottom:14px;">OFERTA KOŃCOWA · PEŁNA ANALIZA LISTY PŁAC</div>
    <h1 class="serif" style="font-size:52px;line-height:0.95;letter-spacing:-0.03em;color:white;margin-bottom:16px;">
      Eliton Benefits<br>System™
    </h1>
    <div style="width:60px;height:3px;background:var(--gold);margin:16px 0;"></div>
    <p style="font-size:15px;font-weight:300;color:rgba(255,255,255,0.7);max-width:420px;line-height:1.6;margin-bottom:32px;">
      Przeanalizowaliśmy Twoją listę płac pracownik po pracowniku i wyliczamy dokładnie, ile zostaje w firmie po wdrożeniu systemu.
    </p>

    <div style="display:flex;gap:32px;margin-bottom:32px;">
      <div>
        <div style="font-size:8px;font-weight:700;letter-spacing:0.25em;text-transform:uppercase;color:rgba(255,255,255,0.4);">Pracownicy</div>
        <div class="serif" style="font-size:32px;font-weight:700;color:white;">${liczbaPrac}</div>
      </div>
      <div>
        <div style="font-size:8px;font-weight:700;letter-spacing:0.25em;text-transform:uppercase;color:rgba(255,255,255,0.4);">Oszczędność roczna</div>
        <div class="serif" style="font-size:32px;font-weight:700;color:var(--gold);">${formatPLN(oszczRocz)}</div>
      </div>
    </div>

    <div style="font-size:9px;font-weight:700;letter-spacing:0.2em;text-transform:uppercase;color:rgba(255,255,255,0.4);">
      PRZYGOTOWANO DLA: <span style="color:white;font-size:12px;letter-spacing:0.08em;">${firma.nazwa || ''}</span>
    </div>
    <div style="font-size:9px;margin-top:6px;color:rgba(255,255,255,0.35);">Data: ${dataW} · Ważna do: ${dataWaz} · Przygotowała: <span style="color:var(--gold);">${h.imie} ${h.nazwisko}</span></div>
  </div>
</div>`;

  // Page 2 — Diagnoza + Radosław Żuk
  const page2 = `
<div class="page">
  <header class="page-header">
    <span class="pre-tag">Diagnoza kosztów</span>
    <h2 class="serif" style="font-size:28px;color:white;">Twój obecny stan finansowy</h2>
  </header>
  <section class="page-section">
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:32px;align-items:start;">
      <div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:20px;">
          <div class="kpi-card">
            <div class="kpi-label">Miesięczny ZUS pracodawcy</div>
            <div class="kpi-value">${formatPLN(sumaZusStd)}</div>
          </div>
          <div class="kpi-card dark">
            <div class="kpi-label" style="color:rgba(255,255,255,0.5);">Koszt wynagrodzeń</div>
            <div class="kpi-value" style="color:var(--gold);">${formatPLN(p.kosztObecny || 0)}</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">Koszt bezczynności / rok</div>
            <div class="kpi-value" style="color:#dc2626;">${formatPLN(oszczRocz)}</div>
          </div>
        </div>

        <div style="padding:20px;background:#fef2f2;border-left:4px solid #dc2626;margin-bottom:16px;">
          <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:0.2em;color:#dc2626;margin-bottom:6px;">UWAGA</div>
          <p style="font-size:12px;color:#374151;line-height:1.6;">
            Każdy miesiąc bez systemu Eliton to <strong style="color:#dc2626;">${formatPLN(oszczMies)}</strong> które firma oddaje do ZUS zamiast zatrzymać. W skali roku to <strong>${formatPLN(oszczRocz)}</strong>.
          </p>
        </div>

        <div style="padding:18px;background:#f8f9fa;border-left:3px solid var(--gold);">
          <div style="font-size:9px;font-weight:700;letter-spacing:0.2em;text-transform:uppercase;color:#6b7280;margin-bottom:8px;">Opinia eksperta</div>
          <p style="font-size:12px;color:#374151;line-height:1.6;font-style:italic;">
            "System podziału wynagrodzenia to legalna, sprawdzona metoda optymalizacji kosztów pracowniczych oparta na art. 21 ust. 1 pkt 67 ustawy o PIT. Stosowana w setkach polskich firm od lat."
          </p>
        </div>
      </div>

      <!-- Radosław Żuk photo -->
      <div style="text-align:center;padding:24px 16px;background:var(--navy);border-bottom:4px solid var(--gold);">
        ${radoslawZukPhotoB64 ? `<img src="${radoslawZukPhotoB64}" class="photo-circle" style="width:80px;height:80px;margin:0 auto 12px;" alt="Radosław Żuk">` : '<div style="width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,0.1);margin:0 auto 12px;border:2px solid var(--gold);"></div>'}
        <div class="serif" style="font-size:16px;color:white;font-weight:700;">Radosław Żuk</div>
        <div style="font-size:9px;color:var(--gold);letter-spacing:0.1em;text-transform:uppercase;margin-top:3px;">Doradca Podatkowy</div>
        <div style="font-size:9px;color:rgba(255,255,255,0.5);margin-top:10px;line-height:1.6;">Specjalista ds. prawa podatkowego. Autor opinii prawnych dla systemu EBS.</div>
      </div>
    </div>
  </section>
  <footer class="page-footer"><span>Stratton Prime · Pełna Oferta</span><span>02</span></footer>
</div>`;

  // Page 3 — Employee table (Obraz Finansowy)
  const empRows = pracownicy.slice(0, 15).map(emp => `
    <tr>
      <td>
        <span style="font-weight:600;">${emp.imie} ${emp.nazwisko}</span>
        ${emp.typUmowy ? `<span style="font-size:9px;font-weight:700;background:${emp.typUmowy === 'UOP' ? '#dbeafe' : '#fef3c7'};color:${emp.typUmowy === 'UOP' ? '#1d4ed8' : '#92400e'};padding:1px 5px;border-radius:3px;margin-left:4px;">${emp.typUmowy}</span>` : ''}
      </td>
      <td style="text-align:right;color:#6b7280;">${formatPLN(emp.kosztStandard || 0)}</td>
      <td style="text-align:right;font-weight:600;">${formatPLN(emp.kosztEliton || 0)}</td>
      <td style="text-align:right;color:var(--green);font-weight:700;">${formatPLN(emp.oszczednosc || 0)}</td>
      <td style="text-align:right;color:var(--gold);font-weight:600;">${formatPLN(emp.podwyzka || 0)}</td>
    </tr>`).join('');

  const page3 = `
<div class="page">
  <header class="page-header">
    <span class="pre-tag">Analiza pracownik po pracowniku</span>
    <h2 class="serif" style="font-size:28px;color:white;">Obraz Finansowy Firmy</h2>
  </header>
  <section class="page-section" style="padding:16px 48px;">
    <table>
      <thead>
        <tr>
          <th>Pracownik</th>
          <th style="text-align:right;">Koszt obecny</th>
          <th style="text-align:right;">Koszt Eliton</th>
          <th style="text-align:right;">Oszczędność</th>
          <th style="text-align:right;">Podwyżka</th>
        </tr>
      </thead>
      <tbody>
        ${empRows}
        <tr style="background:#f3f4f6;font-weight:700;border-top:2px solid var(--navy);">
          <td>SUMA (${liczbaPrac} pracowników)</td>
          <td style="text-align:right;">${formatPLN(p.kosztObecny || 0)}</td>
          <td style="text-align:right;">${formatPLN(p.kosztNowy || 0)}</td>
          <td style="text-align:right;color:var(--green);">${formatPLN(oszczMies)}</td>
          <td style="text-align:right;color:var(--gold);">${formatPLN(p.sredniaOszczednoscNaEtat || 0)}</td>
        </tr>
      </tbody>
    </table>
  </section>
  <footer class="page-footer"><span>Stratton Prime · Pełna Oferta</span><span>03</span></footer>
</div>`;

  // Page 4 — Solution (Eliton Prime + tarcza)
  const page4 = `
<div class="page">
  <header class="page-header">
    <span class="pre-tag">Rozwiązanie</span>
    <h2 class="serif" style="font-size:28px;color:white;">System Eliton Prime™</h2>
  </header>
  <section class="page-section">
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:36px;align-items:start;">
      <div>
        <p style="font-size:13px;color:#374151;line-height:1.7;font-weight:300;margin-bottom:16px;">
          Eliton Benefits System™ to system podziału wynagrodzenia na część zasadniczą i świadczenie pozapłacowe, wolne od składek ZUS i zaliczki PIT — zgodnie z art. 21 ust. 1 pkt 67 ustawy o PIT.
        </p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
          ${[
            ['Brak ZUS od świadczeń', 'Oszczędność do 24% kosztów'],
            ['Brak PIT od voucherów', 'Wyższe netto pracownika'],
            ['Pełna zgodność prawna', 'Sprawdzony w 500+ firmach'],
            ['Ochrona D&O 1 MLN PLN', 'Gwarancja zarządu'],
          ].map(([t, v]) => `
            <div style="padding:12px;background:#f8f9fa;border-left:3px solid var(--gold);">
              <div style="font-size:10px;font-weight:700;color:var(--navy);margin-bottom:3px;">${t}</div>
              <div style="font-size:11px;color:#6b7280;">${v}</div>
            </div>`).join('')}
        </div>
        <div style="padding:16px;background:var(--navy);color:white;border-bottom:3px solid var(--gold);">
          <div style="font-size:8px;font-weight:700;letter-spacing:0.25em;text-transform:uppercase;color:rgba(255,255,255,0.5);margin-bottom:6px;">Podstawa prawna</div>
          <p style="font-size:12px;line-height:1.6;color:rgba(255,255,255,0.8);">
            Art. 21 ust. 1 pkt 67 ustawy o PIT · §2 ust. 1 pkt 26 Rozp. MPiPS z 1998 r. · Interpretacje indywidualne KAS. System pozytywnie zaopiniowany przez niezależną Kancelarię Doradztwa Podatkowego.
          </p>
        </div>
      </div>
      <div style="text-align:center;">
        ${tarczaOchronnaB64 ? `<img src="${tarczaOchronnaB64}" style="width:120px;height:auto;margin:0 auto 14px;display:block;" alt="Ochrona prawna">` : '<div style="width:120px;height:120px;border-radius:50%;background:var(--gold);margin:0 auto 14px;opacity:0.3;"></div>'}
        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.15em;color:var(--navy);margin-bottom:8px;">Gwarancje systemu</div>
        ${['ZUS ✓', 'PIT ✓', 'KP ✓'].map(g => `<div style="padding:8px;background:#f0fdf4;border:1px solid #86efac;margin-bottom:6px;font-size:12px;font-weight:600;color:var(--green);">${g}</div>`).join('')}
      </div>
    </div>
  </section>
  <footer class="page-footer"><span>Stratton Prime · Pełna Oferta</span><span>04</span></footer>
</div>`;

  // Page 5 — ROI
  const page5 = `
<div class="page">
  <header class="page-header">
    <span class="pre-tag">Zwrot z inwestycji</span>
    <h2 class="serif" style="font-size:28px;color:white;">Wartość ROI dla Twojej firmy</h2>
  </header>
  <section class="page-section">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:20px;">
      <div>
        <table>
          <thead><tr><th>Parametr</th><th style="text-align:right;">Wartość</th></tr></thead>
          <tbody>
            <tr><td>Oszczędność miesięczna</td><td style="text-align:right;color:var(--green);font-weight:700;">${formatPLN(oszczMies)}</td></tr>
            <tr><td>Oszczędność roczna</td><td style="text-align:right;color:var(--green);font-weight:700;">${formatPLN(oszczRocz)}</td></tr>
            <tr><td>Prowizja miesięczna</td><td style="text-align:right;">${formatPLN(prowizja)}</td></tr>
            <tr><td>Prowizja roczna</td><td style="text-align:right;">${formatPLN(prowizjaRoczna)}</td></tr>
            <tr style="background:#f3f4f6;font-weight:700;"><td>Zysk netto roczny</td><td style="text-align:right;color:var(--green);">${formatPLN(zyskNetto)}</td></tr>
          </tbody>
        </table>
      </div>
      <div style="display:flex;flex-direction:column;gap:12px;">
        <div style="padding:20px;background:var(--navy);color:white;text-align:center;border-bottom:4px solid var(--gold);">
          <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:0.25em;color:rgba(255,255,255,0.5);margin-bottom:6px;">ROI · zwrot z inwestycji</div>
          <div class="serif" style="font-size:56px;font-weight:700;color:var(--gold);line-height:1;">${roi}%</div>
        </div>
        <div style="padding:16px;background:#f0fdf4;border:1px solid #86efac;text-align:center;">
          <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:0.2em;color:var(--green);margin-bottom:4px;">Zwrot inwestycji</div>
          <div class="serif" style="font-size:28px;font-weight:700;color:var(--navy);">${zwrotMies} mies.</div>
        </div>
      </div>
    </div>

    <div style="background:var(--navy);color:white;padding:16px 28px;text-align:center;font-family:'Playfair Display',serif;font-size:18px;font-style:italic;border-top:3px solid var(--gold);border-bottom:3px solid var(--gold);">
      Inwestujesz <span style="color:var(--gold);font-style:normal;font-weight:700;">${formatPLN(prowizjaRoczna)}</span> rocznie i odzyskujesz <span style="color:var(--green);font-style:normal;font-weight:700;">${formatPLN(oszczRocz)}</span>.
    </div>
  </section>
  <footer class="page-footer"><span>Stratton Prime · Pełna Oferta</span><span>05</span></footer>
</div>`;

  // Page 6 — Partners (Wiarygodność)
  const partnerEntries = [
    ['pzu', 'PZU'], ['ergoHestia', 'Ergo Hestia'], ['allianz', 'Allianz'], ['generali', 'Generali'],
    ['warta', 'Warta'], ['lloyds', "Lloyd's"], ['ladenhall', 'Ladenhall'], ['laven', 'Laven'],
    ['viennaLife', 'Vienna Life'], ['uniqa', 'Uniqa'], ['signalIduna', 'Signal Iduna'],
    ['unum', 'Unum'], ['luxmed', 'LuxMed'], ['orange', 'Orange'],
  ];

  const partnerGrid = partnerEntries.map(([key, name]) => `
    <div style="padding:14px;border:1px solid #e5e7eb;display:flex;align-items:center;justify-content:center;min-height:60px;">
      ${PARTNERS[key] ? `<img src="${PARTNERS[key]}" class="partner-logo-color" style="max-height:32px;max-width:100px;object-fit:contain;" alt="${name}">` : `<span style="font-size:10px;font-weight:700;color:#6b7280;">${name}</span>`}
    </div>`).join('');

  const page6 = `
<div class="page">
  <header class="page-header">
    <span class="pre-tag">Wiarygodność i partnerzy</span>
    <h2 class="serif" style="font-size:28px;color:white;">Zaufali nam liderzy branży</h2>
  </header>
  <section class="page-section">
    <p style="font-size:13px;color:#4b5563;margin-bottom:20px;line-height:1.6;font-weight:300;">
      Platforma kafeteryjna EBS współpracuje z wiodącymi firmami ubezpieczeniowymi, medycznymi i usługowymi w Polsce.
    </p>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;">
      ${partnerGrid}
    </div>
    <div style="margin-top:16px;padding:14px 20px;background:#f8f9fa;border-left:3px solid var(--gold);">
      <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:0.2em;color:#6b7280;margin-bottom:4px;">Gwarancje prawne</div>
      <div style="display:flex;gap:16px;">
        ${['ZUS — brak dodatkowych składek', 'PIT — zwolnienie podatkowe', 'KP — pełna zgodność z Kodeksem Pracy'].map(g => `<span style="font-size:11px;color:var(--green);font-weight:600;">✓ ${g}</span>`).join('')}
      </div>
    </div>
  </section>
  <footer class="page-footer"><span>Stratton Prime · Pełna Oferta</span><span>06</span></footer>
</div>`;

  // Page 7 — Offer Terms
  const page7 = `
<div class="page">
  <header class="page-header">
    <span class="pre-tag">Warunki oferty</span>
    <h2 class="serif" style="font-size:28px;color:white;">Oferta Końcowa</h2>
  </header>
  <section class="page-section">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
      <div>
        <span class="pre-tag">Warunki współpracy</span>
        <table>
          <tbody>
            <tr><td style="font-weight:600;">Prowizja za zarządzanie</td><td style="text-align:right;color:var(--navy);font-weight:700;">${prowizjaProc}% netto świadczeń</td></tr>
            <tr><td>Płatność prowizji</td><td style="text-align:right;">miesięcznie z dołu</td></tr>
            <tr><td>Termin wdrożenia</td><td style="text-align:right;">2–4 tygodnie</td></tr>
            <tr><td>Umowa</td><td style="text-align:right;">bezterminowa (wypowiedzenie 30 dni)</td></tr>
            <tr><td style="font-weight:600;">Oferta ważna do</td><td style="text-align:right;color:var(--gold);font-weight:700;">${dataWaz}</td></tr>
          </tbody>
        </table>
      </div>
      <div>
        <span class="pre-tag">Podsumowanie finansowe</span>
        <div style="padding:20px;background:var(--navy);color:white;border-bottom:4px solid var(--gold);">
          <div style="margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid rgba(255,255,255,0.1);">
            <div style="font-size:8px;text-transform:uppercase;letter-spacing:0.2em;color:rgba(255,255,255,0.45);margin-bottom:4px;">Oszczędność miesięczna</div>
            <div class="serif" style="font-size:28px;color:var(--green);">${formatPLN(oszczMies)}</div>
          </div>
          <div style="margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid rgba(255,255,255,0.1);">
            <div style="font-size:8px;text-transform:uppercase;letter-spacing:0.2em;color:rgba(255,255,255,0.45);margin-bottom:4px;">Prowizja miesięczna</div>
            <div class="serif" style="font-size:28px;">${formatPLN(prowizja)}</div>
          </div>
          <div>
            <div style="font-size:8px;text-transform:uppercase;letter-spacing:0.2em;color:rgba(255,255,255,0.45);margin-bottom:4px;">ROI · I rok</div>
            <div class="serif" style="font-size:36px;color:var(--gold);">${roi}%</div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <footer class="page-footer"><span>Stratton Prime · Pełna Oferta</span><span>07</span></footer>
</div>`;

  // Page 8 — Roadmap (Twoja Droga)
  const steps = [
    ['Tydzień 1', 'Audyt dokumentacji', 'Przeglądamy listy płac, umowy, regulaminy. Przygotowujemy projekt aneksów i regulaminu wynagradzania.'],
    ['Tydzień 2', 'Podpisanie dokumentów', 'Przekazujemy gotowe dokumenty do podpisu. Konfigurujemy platformę kafeteryjną.'],
    ['Tydzień 3', 'Szkolenie zespołu', 'Szkolimy HR i księgowość (max. 2h). System gotowy do uruchomienia.'],
    ['Tydzień 4+', 'Pierwsze oszczędności', 'System aktywny. Pierwsze oszczędności widoczne w rozliczeniu płac.'],
  ];
  const page8 = `
<div class="page">
  <header class="page-header">
    <span class="pre-tag">Harmonogram wdrożenia</span>
    <h2 class="serif" style="font-size:28px;color:white;">Twoja Droga do Oszczędności</h2>
  </header>
  <section class="page-section">
    ${steps.map(([period, title, desc], i) => `
      <div style="display:flex;gap:20px;margin-bottom:16px;align-items:flex-start;">
        <div style="flex-shrink:0;width:80px;text-align:center;padding:10px 6px;background:${i === 0 ? 'var(--gold)' : 'var(--navy)'};color:white;">
          <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:0.15em;opacity:0.7;">${period.split(' ')[0]}</div>
          <div style="font-size:14px;font-weight:700;">${period.split(' ').slice(1).join(' ')}</div>
        </div>
        <div style="padding:16px 20px;background:#f8f9fa;border-left:3px solid ${i === 3 ? 'var(--green)' : 'var(--gold)'};flex-grow:1;">
          <div style="font-size:12px;font-weight:700;color:var(--navy);margin-bottom:4px;">${title}</div>
          <div style="font-size:11px;color:#4b5563;line-height:1.6;">${desc}</div>
        </div>
      </div>`).join('')}
  </section>
  <footer class="page-footer"><span>Stratton Prime · Pełna Oferta</span><span>08</span></footer>
</div>`;

  // Page 9 — CTA (Hook page) + both photos
  const page9 = `
<div class="page">
  <div style="flex-grow:1;background:var(--navy);display:flex;flex-direction:column;justify-content:center;align-items:center;padding:48px;text-align:center;position:relative;">
    <div style="position:absolute;bottom:0;left:0;right:0;height:4px;background:var(--gold);"></div>

    <div style="font-size:9px;font-weight:700;letter-spacing:0.3em;text-transform:uppercase;color:var(--gold);margin-bottom:16px;">DOKUMENTY SĄ GOTOWE</div>
    <h2 class="serif" style="font-size:44px;color:white;line-height:1.0;margin-bottom:14px;">Zostaje tylko<br>Twój podpis.</h2>
    <p style="font-size:14px;color:rgba(255,255,255,0.65);max-width:400px;margin-bottom:36px;line-height:1.6;font-weight:300;">
      Oferta ważna do <strong style="color:var(--gold);">${dataWaz}</strong>. Każdy kolejny miesiąc bez EBS to <strong style="color:white;">${formatPLN(oszczMies)}</strong> stracone bezpowrotnie.
    </p>

    <div style="display:flex;gap:36px;justify-content:center;margin-bottom:36px;">
      <div style="text-align:center;">
        ${agaB64 ? `<img src="${agaB64}" class="photo-circle" style="width:70px;height:70px;margin:0 auto 10px;" alt="Agnieszka Cięciara">` : '<div style="width:70px;height:70px;border-radius:50%;background:rgba(255,255,255,0.1);border:2px solid var(--gold);margin:0 auto 10px;"></div>'}
        <div class="serif" style="font-size:15px;color:white;">${h.imie} ${h.nazwisko}</div>
        <div style="font-size:9px;color:var(--gold);letter-spacing:0.1em;text-transform:uppercase;margin-top:2px;">Opiekun Projektu</div>
        <div style="font-size:10px;color:rgba(255,255,255,0.5);margin-top:4px;">${h.email}</div>
      </div>
      <div style="text-align:center;">
        ${radoslawZukPhotoB64 ? `<img src="${radoslawZukPhotoB64}" class="photo-circle" style="width:70px;height:70px;margin:0 auto 10px;" alt="Radosław Żuk">` : '<div style="width:70px;height:70px;border-radius:50%;background:rgba(255,255,255,0.1);border:2px solid var(--gold);margin:0 auto 10px;"></div>'}
        <div class="serif" style="font-size:15px;color:white;">Radosław Żuk</div>
        <div style="font-size:9px;color:var(--gold);letter-spacing:0.1em;text-transform:uppercase;margin-top:2px;">Doradca Podatkowy</div>
        <div style="font-size:10px;color:rgba(255,255,255,0.5);margin-top:4px;">doradztwo@stratton-prime.pl</div>
      </div>
    </div>

    <div style="font-size:9px;color:rgba(255,255,255,0.35);letter-spacing:0.2em;text-transform:uppercase;">STRATTON PRIME · WWW.STRATTON-PRIME.PL</div>
  </div>
</div>`;

  return buildHtmlDoc([page1, page2, page3, page4, page5, page6, page7, page8, page9], `Pełna Oferta — ${firma.nazwa || 'Stratton Prime'}`);
}

// ── KARTA PRODUKTU (Product Card — ofertaZgrubna style) ──────────────────────
function generateProductCardHtml(d) {
  const firma = d.firma || {};
  const h = d.handlowiec || { imie: 'Agnieszka', nazwisko: 'Cięciara', email: 'a.cieciara@stratton-prime.pl' };
  const dataW = d.dataWystawienia || new Date().toLocaleDateString('pl-PL');

  const partnerEntries = [
    ['pzu', 'PZU'], ['ergoHestia', 'Ergo Hestia'], ['allianz', 'Allianz'], ['generali', 'Generali'],
    ['warta', 'Warta'], ['lloyds', "Lloyd's"], ['ladenhall', 'Ladenhall'], ['laven', 'Laven'],
    ['viennaLife', 'Vienna Life'], ['uniqa', 'Uniqa'], ['signalIduna', 'Signal Iduna'],
    ['unum', 'Unum'], ['luxmed', 'LuxMed'], ['orange', 'Orange'],
  ];

  // Page 1 — Cover
  const page1 = `
<div class="page">
  <div style="background:var(--navy);flex-grow:1;display:flex;flex-direction:column;justify-content:center;align-items:center;padding:56px 48px;text-align:center;position:relative;overflow:hidden;">
    <div style="position:absolute;top:0;left:0;right:0;height:4px;background:var(--gold);"></div>
    <div style="position:absolute;bottom:0;left:0;right:0;height:4px;background:var(--gold);"></div>
    <div style="position:absolute;top:0;right:0;width:180px;height:100%;background:linear-gradient(135deg,transparent 60%,rgba(197,160,89,0.06) 60%);"></div>

    ${LOGO_OFERTA_B64 ? `<img src="${LOGO_OFERTA_B64}" style="height:44px;margin-bottom:36px;" alt="Stratton Prime">` : `<div style="font-size:22px;font-weight:900;letter-spacing:0.2em;text-transform:uppercase;color:white;margin-bottom:36px;">STRATTON <span style="color:var(--gold);">PRIME</span></div>`}

    <div style="padding:6px 20px;background:var(--gold);color:var(--navy);font-size:9px;font-weight:700;letter-spacing:0.3em;text-transform:uppercase;margin-bottom:24px;">PLUS</div>

    <h1 class="serif" style="font-size:48px;line-height:1.0;color:white;margin-bottom:16px;letter-spacing:-0.02em;">
      Platforma Benefitów<br>Pracowniczych
    </h1>
    <div style="width:60px;height:3px;background:var(--gold);margin:0 auto 20px;"></div>
    <p style="font-size:15px;font-weight:300;color:rgba(255,255,255,0.7);max-width:420px;line-height:1.6;margin-bottom:28px;">
      System optymalizacji kosztów pracowniczych oparty na podstawie prawnej art. 21 ust. 1 pkt 67 ustawy o PIT.
    </p>

    ${firma.nazwa ? `<div style="padding:12px 24px;border:1px solid rgba(197,160,89,0.4);color:rgba(255,255,255,0.7);font-size:12px;margin-bottom:20px;">PRZYGOTOWANO DLA: <strong style="color:white;">${firma.nazwa}</strong></div>` : ''}

    <div style="font-size:9px;color:rgba(255,255,255,0.35);letter-spacing:0.2em;text-transform:uppercase;margin-top:16px;">STRATTON PRIME · ${dataW}</div>
  </div>
</div>`;

  // Page 2 — Jak działa + Eksperci
  const page2 = `
<div class="page">
  <header class="page-header">
    <span class="pre-tag">Mechanizm systemu</span>
    <h2 class="serif" style="font-size:28px;color:white;">Jak działa Eliton Prime™</h2>
  </header>
  <section class="page-section">
    <!-- Flow schema -->
    <div style="display:flex;align-items:center;gap:0;margin-bottom:24px;border:1px solid #e5e7eb;border-radius:3px;overflow:hidden;">
      ${[['Firma', 'Wynagrodzenie + Świadczenia', '#05162e', 'white'],
         ['Platforma EBS', 'Optimizacja ZUS i PIT', '#C5A059', '#05162e'],
         ['Pracownik', 'Wyższe netto + Vouchery', '#f8f9fa', '#111827'],
      ].map(([title, sub, bg, color], i, arr) => `
        <div style="flex:1;padding:16px 14px;background:${bg};color:${color};text-align:center;position:relative;">
          <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.15em;margin-bottom:4px;">${title}</div>
          <div style="font-size:10px;opacity:0.7;">${sub}</div>
          ${i < arr.length - 1 ? `<div style="position:absolute;right:-12px;top:50%;transform:translateY(-50%);font-size:20px;color:var(--gold);z-index:1;">→</div>` : ''}
        </div>`).join('')}
    </div>

    <p style="font-size:12px;color:#4b5563;line-height:1.7;margin-bottom:20px;font-weight:300;">
      System Eliton Prime™ polega na podziale wynagrodzenia na dwie części: zasadniczą (objętą ZUS) oraz świadczenie pozapłacowe w formie vouchera (wolne od ZUS i PIT). Rezultat: firma płaci mniej, pracownik dostaje więcej.
    </p>

    <!-- Expert section -->
    <div style="border-top:1px solid #e5e7eb;padding-top:18px;">
      <div style="font-size:9px;font-weight:700;letter-spacing:0.25em;text-transform:uppercase;color:var(--gold);margin-bottom:14px;">NASI EKSPERCI</div>
      <div style="display:flex;gap:32px;align-items:flex-start;">
        <div style="text-align:center;flex:1;">
          ${agaB64 ? `<img src="${agaB64}" class="photo-circle" style="width:90px;height:90px;margin:0 auto 12px;" alt="Agnieszka Cięciara">` : '<div style="width:90px;height:90px;border-radius:50%;background:#e5e7eb;border:2px solid var(--gold);margin:0 auto 12px;"></div>'}
          <div class="serif" style="font-size:18px;color:var(--navy);font-weight:700;">${h.imie} ${h.nazwisko}</div>
          <div style="font-size:9px;color:var(--gold);letter-spacing:0.12em;text-transform:uppercase;margin-top:3px;">Opiekun Wdrożenia</div>
          <div style="font-size:10px;color:#6b7280;margin-top:6px;line-height:1.5;">Koordynuje cały proces wdrożenia i jest Twoim pierwszym punktem kontaktu.</div>
        </div>
        <div style="text-align:center;flex:1;">
          ${radoslawZukPhotoB64 ? `<img src="${radoslawZukPhotoB64}" class="photo-circle" style="width:90px;height:90px;margin:0 auto 12px;" alt="Radosław Żuk">` : '<div style="width:90px;height:90px;border-radius:50%;background:#e5e7eb;border:2px solid var(--gold);margin:0 auto 12px;"></div>'}
          <div class="serif" style="font-size:18px;color:var(--navy);font-weight:700;">Radosław Żuk</div>
          <div style="font-size:9px;color:var(--gold);letter-spacing:0.12em;text-transform:uppercase;margin-top:3px;">Doradca Podatkowy</div>
          <div style="font-size:10px;color:#6b7280;margin-top:6px;line-height:1.5;">Odpowiada za zgodność prawną systemu i opinie podatkowe dla klientów.</div>
        </div>
      </div>
    </div>
  </section>
  <footer class="page-footer"><span>Stratton Prime · Karta Produktu</span><span>02</span></footer>
</div>`;

  // Page 3 — Comparison table
  const page3 = `
<div class="page">
  <header class="page-header">
    <span class="pre-tag">Porównanie wariantów</span>
    <h2 class="serif" style="font-size:28px;color:white;">Bez Eliton vs Z Eliton Prime™</h2>
  </header>
  <section class="page-section">
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:32px;align-items:start;">
      <div>
        <table>
          <thead>
            <tr>
              <th>Element kosztowy</th>
              <th style="text-align:center;">Bez Eliton</th>
              <th style="text-align:center;color:var(--gold);">Z Eliton Prime™</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>ZUS pracodawcy (np. 5 000 zł brutto)</td>
              <td style="text-align:center;color:#dc2626;font-weight:600;">~1 040 zł</td>
              <td style="text-align:center;color:var(--green);font-weight:600;">~520 zł</td>
            </tr>
            <tr>
              <td>ZUS pracownika</td>
              <td style="text-align:center;color:#dc2626;">~690 zł</td>
              <td style="text-align:center;color:var(--green);">~345 zł</td>
            </tr>
            <tr>
              <td>Zaliczka PIT</td>
              <td style="text-align:center;color:#dc2626;">~460 zł</td>
              <td style="text-align:center;color:var(--green);">~230 zł</td>
            </tr>
            <tr>
              <td>Netto pracownika</td>
              <td style="text-align:center;">~3 450 zł</td>
              <td style="text-align:center;color:var(--green);font-weight:600;">~3 750 zł</td>
            </tr>
            <tr style="background:#f3f4f6;font-weight:700;">
              <td>Koszt pracodawcy łącznie</td>
              <td style="text-align:center;color:#dc2626;">~6 040 zł</td>
              <td style="text-align:center;color:var(--green);">~5 520 zł</td>
            </tr>
            <tr style="background:var(--navy);color:white;font-weight:700;">
              <td>OSZCZĘDNOŚĆ miesięczna</td>
              <td style="text-align:center;color:rgba(255,255,255,0.4);">—</td>
              <td style="text-align:center;color:var(--gold);">~520 zł / os.</td>
            </tr>
          </tbody>
        </table>
        <p style="font-size:10px;color:#9ca3af;margin-top:8px;font-style:italic;">* Przykładowe wartości dla pracownika UoP z wynagrodzeniem 5 000 zł brutto. Dokładne kwoty zależą od listy płac.</p>
      </div>

      <div style="text-align:center;">
        ${tarczaOchronnaB64 ? `<img src="${tarczaOchronnaB64}" style="width:100px;height:auto;margin:0 auto 16px;display:block;" alt="Ochrona">` : ''}
        <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:0.15em;color:var(--navy);margin-bottom:10px;">Gwarancje systemu</div>
        ${['ZUS ✓ Brak składek od voucherów', 'PIT ✓ Zwolnienie podatkowe', 'KP ✓ Zgodność z Kodeksem Pracy', 'D&O ✓ Polisa dla Zarządu'].map(g => `
          <div style="padding:8px 10px;background:#f0fdf4;border-left:3px solid var(--green);margin-bottom:6px;font-size:10px;font-weight:500;color:var(--navy);text-align:left;">${g}</div>`).join('')}
      </div>
    </div>
  </section>
  <footer class="page-footer"><span>Stratton Prime · Karta Produktu</span><span>03</span></footer>
</div>`;

  // Page 4 — Partners grid
  const partnerCells = partnerEntries.map(([key, name]) => `
    <div style="padding:12px;border:1px solid #e5e7eb;display:flex;align-items:center;justify-content:center;min-height:56px;">
      ${PARTNERS[key] ? `<img src="${PARTNERS[key]}" style="max-height:30px;max-width:90px;object-fit:contain;" alt="${name}">` : `<span style="font-size:10px;font-weight:700;color:#6b7280;">${name}</span>`}
    </div>`).join('');

  const page4 = `
<div class="page">
  <header class="page-header">
    <span class="pre-tag">Nasi partnerzy</span>
    <h2 class="serif" style="font-size:28px;color:white;">Zaufali nam</h2>
  </header>
  <section class="page-section">
    <p style="font-size:12px;color:#4b5563;margin-bottom:16px;line-height:1.6;font-weight:300;">
      Platforma EBS współpracuje z wiodącymi firmami ubezpieczeniowymi, medycznymi i usługowymi. Pracownicy wybierają spośród setek produktów i usług.
    </p>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:16px;">
      ${partnerCells}
    </div>
    <div style="padding:12px 18px;background:#f8f9fa;border-left:3px solid var(--gold);">
      <div style="display:flex;gap:20px;flex-wrap:wrap;">
        ${['ZUS ✓', 'PIT ✓', 'KP ✓'].map(g => `<span style="font-size:11px;color:var(--green);font-weight:700;">${g}</span>`).join('')}
        <span style="font-size:10px;color:#6b7280;margin-left:8px;">Pełna zgodność prawna potwierdzona przez niezależną kancelarię</span>
      </div>
    </div>
  </section>
  <footer class="page-footer"><span>Stratton Prime · Karta Produktu</span><span>04</span></footer>
</div>`;

  // Page 5 — Contact + CTA
  const page5 = `
<div class="page">
  <div style="background:var(--navy);flex-grow:1;display:flex;flex-direction:column;justify-content:center;align-items:center;padding:48px;text-align:center;position:relative;">
    <div style="position:absolute;bottom:0;left:0;right:0;height:4px;background:var(--gold);"></div>

    <div style="font-size:9px;font-weight:700;letter-spacing:0.3em;text-transform:uppercase;color:var(--gold);margin-bottom:14px;">NASTĘPNY KROK</div>
    <h2 class="serif" style="font-size:40px;color:white;line-height:1.0;margin-bottom:12px;">Umów bezpłatną<br>analizę listy płac</h2>
    <p style="font-size:13px;color:rgba(255,255,255,0.65);max-width:380px;margin-bottom:36px;line-height:1.6;font-weight:300;">
      W ciągu 24h przygotujemy dokładne wyliczenie oszczędności dla Twojej firmy — bez zobowiązań.
    </p>

    <div style="display:flex;gap:36px;justify-content:center;margin-bottom:32px;">
      <div style="text-align:center;">
        ${agaB64 ? `<img src="${agaB64}" class="photo-circle" style="width:80px;height:80px;margin:0 auto 10px;" alt="Agnieszka Cięciara">` : '<div style="width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,0.1);border:2px solid var(--gold);margin:0 auto 10px;"></div>'}
        <div class="serif" style="font-size:16px;color:white;">${h.imie} ${h.nazwisko}</div>
        <div style="font-size:9px;color:var(--gold);letter-spacing:0.1em;text-transform:uppercase;margin-top:2px;">Opiekun Wdrożenia</div>
        <div style="font-size:10px;color:rgba(255,255,255,0.5);margin-top:4px;">${h.email}</div>
        ${h.telefon ? `<div style="font-size:10px;color:rgba(255,255,255,0.5);">${h.telefon}</div>` : ''}
      </div>
      <div style="text-align:center;">
        ${radoslawZukPhotoB64 ? `<img src="${radoslawZukPhotoB64}" class="photo-circle" style="width:80px;height:80px;margin:0 auto 10px;" alt="Radosław Żuk">` : '<div style="width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,0.1);border:2px solid var(--gold);margin:0 auto 10px;"></div>'}
        <div class="serif" style="font-size:16px;color:white;">Radosław Żuk</div>
        <div style="font-size:9px;color:var(--gold);letter-spacing:0.1em;text-transform:uppercase;margin-top:2px;">Doradca Podatkowy</div>
        <div style="font-size:10px;color:rgba(255,255,255,0.5);margin-top:4px;">doradztwo@stratton-prime.pl</div>
      </div>
    </div>

    <div style="font-size:9px;color:rgba(255,255,255,0.35);letter-spacing:0.2em;text-transform:uppercase;">STRATTON PRIME · WWW.STRATTON-PRIME.PL</div>
  </div>
</div>`;

  return buildHtmlDoc([page1, page2, page3, page4, page5], `Karta Produktu — Eliton Prime™`);
}

// ── HTML wrapper ──────────────────────────────────────────────────────────────
function buildHtmlDoc(pages, title) {
  return `<!DOCTYPE html>
<html lang="pl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>${title}</title>
  <style>${BASE_CSS}</style>
</head>
<body>
  ${pages.join('\n')}
</body>
</html>`;
}

// ── Generate PDF with Puppeteer ───────────────────────────────────────────────
let htmlContent;
if (type === 'short') {
  htmlContent = generateShortOfferHtml(data);
} else if (type === 'long') {
  htmlContent = generateLongOfferHtml(data);
} else if (type === 'product-card') {
  htmlContent = generateProductCardHtml(data);
} else {
  process.stderr.write(`Unknown type: ${type}\n`);
  process.exit(1);
}

try {
  const puppeteer = await import('puppeteer');
  const browser = await puppeteer.default.launch({
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
    executablePath: process.env.PUPPETEER_EXECUTABLE_PATH || undefined,
  });
  const page = await browser.newPage();
  await page.setContent(htmlContent, { waitUntil: 'networkidle0', timeout: 30000 });
  const pdfBuffer = await page.pdf({
    format: 'A4',
    printBackground: true,
    margin: { top: 0, right: 0, bottom: 0, left: 0 },
  });
  await browser.close();
  process.stdout.write(Buffer.from(pdfBuffer).toString('base64'));
} catch (err) {
  const msg = err.message || '';
  const isPuppeteerUnavailable =
    err.code === 'ERR_MODULE_NOT_FOUND' ||
    msg.includes('Cannot find package') ||
    msg.includes('Failed to launch') ||
    msg.includes('executable doesn') ||
    msg.includes('No usable sandbox') ||
    msg.includes('ENOENT') ||
    msg.includes('spawn') ||
    msg.includes('chrome') ||
    msg.includes('chromium') ||
    msg.includes('browser');
  if (isPuppeteerUnavailable) {
    // Puppeteer / Chrome not available — return HTML for window.print() fallback
    process.stdout.write('PUPPETEER_UNAVAILABLE:' + htmlContent);
  } else {
    process.stderr.write('PDF generation error: ' + msg + '\n');
    process.exit(1);
  }
}
