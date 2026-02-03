"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.buildOfferPdfHtml = exports.offerPdfGenerator = void 0;
const formatters_1 = require("./formatters");
const tax_engine_1 = require("../tax-engine");
const CONTENT_PAGE_COUNT = 8;
const formatDate = (value) => {
    if (!value) {
        return new Date().toLocaleDateString('pl-PL');
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return value;
    }
    return date.toLocaleDateString('pl-PL');
};
const safeText = (value) => value && value.trim().length > 0 ? value : '-';
const buildMonthSimulation = (monthlySavings) => {
    const monthNames = [
        'Styczeń',
        'Luty',
        'Marzec',
        'Kwiecień',
        'Maj',
        'Czerwiec',
        'Lipiec',
        'Sierpień',
        'Wrzesień',
        'Październik',
        'Listopad',
        'Grudzień',
    ];
    return monthNames.map((name, index) => {
        const monthIndex = index + 1;
        const cumulative = monthlySavings * monthIndex;
        return {
            name,
            monthIndex,
            monthlySavings,
            cumulative,
        };
    });
};
const buildAnnualScenarios = (annualSavings) => {
    const years = [1, 3, 5, 10];
    return years.map((year) => ({
        years: year,
        total: annualSavings * year,
    }));
};
const buildPayrollRows = (pracownicy, details) => {
    return pracownicy.slice(0, 10).map((pracownik, index) => {
        const detail = details[index];
        const standard = detail?.standard;
        const podzial = detail?.podzial;
        return {
            index: index + 1,
            name: `${pracownik.imie} ${pracownik.nazwisko}`.trim(),
            contract: pracownik.typUmowy === 'UZ' ? 'UZ' : 'UOP',
            standardBrutto: standard?.brutto ?? 0,
            standardNetto: standard?.netto ?? 0,
            modelNetto: podzial?.doWyplaty ?? 0,
            oszczednosc: (standard?.kosztPracodawcy ?? 0) - (podzial?.kosztPracodawcy ?? 0),
        };
    });
};
const buildBaseTotals = (details) => {
    return {
        sumaKosztStandard: details.reduce((acc, w) => acc + w.standard.kosztPracodawcy, 0),
        sumaKosztPodzial: details.reduce((acc, w) => acc + w.podzial.kosztPracodawcy, 0),
        sumaBruttoSwiadczen: details.reduce((acc, w) => acc + w.podzial.swiadczenie.brutto, 0),
        standard: {
            kosztPracodawcy: details.reduce((acc, w) => acc + w.standard.kosztPracodawcy, 0),
            zusPracodawca: details.reduce((acc, w) => acc + w.standard.zusPracodawca.suma, 0),
            brutto: details.reduce((acc, w) => acc + w.standard.brutto, 0),
            netto: details.reduce((acc, w) => acc + w.standard.netto, 0),
            zusPracownik: details.reduce((acc, w) => acc + w.standard.zusPracownik.suma + w.standard.zdrowotna, 0),
            pit: details.reduce((acc, w) => acc + w.standard.pit, 0),
        },
        stratton: {
            kosztPracodawcy: details.reduce((acc, w) => acc + w.podzial.kosztPracodawcy, 0),
            zusPracodawca: details.reduce((acc, w) => acc + w.podzial.zasadnicza.zusPracodawca.suma, 0),
            brutto: details.reduce((acc, w) => acc + w.podzial.pit.lacznyPrzychod, 0),
            netto: details.reduce((acc, w) => acc + w.podzial.doWyplaty, 0),
            zusPracownik: details.reduce((acc, w) => acc + w.podzial.zasadnicza.zusPracownik.suma + w.podzial.zasadnicza.zdrowotna, 0),
            pit: details.reduce((acc, w) => acc + w.podzial.pit.kwota, 0),
        },
    };
};
const buildStats = (base, prowizjaRate) => {
    const prowizja = base.sumaBruttoSwiadczen * (prowizjaRate / 100);
    const oszczednoscBrutto = base.sumaKosztStandard - base.sumaKosztPodzial;
    const oszczednoscNetto = oszczednoscBrutto - prowizja;
    const totalCostModel = base.sumaKosztPodzial + prowizja;
    return {
        standard: { ...base.standard },
        stratton: { ...base.stratton, prowizja },
        oszczednoscRoczna: oszczednoscNetto * 12,
        oszczednoscMiesieczna: oszczednoscNetto,
        prowizja,
        totalCostModel,
        totalCostStandard: base.sumaKosztStandard,
    };
};
const buildContractTotals = (pracownicy, details) => {
    const byType = {
        UOP: { count: 0, koszt: 0 },
        UZ: { count: 0, koszt: 0 },
    };
    pracownicy.forEach((pracownik, index) => {
        const typ = pracownik.typUmowy === 'UZ' ? 'UZ' : 'UOP';
        byType[typ].count += 1;
        const standard = details[index]?.standard;
        byType[typ].koszt += standard?.kosztPracodawcy ?? 0;
    });
    return byType;
};
const generateOfferHTML = (firma, base, statsSelected, statsStandard, statsPlus, prowizjaProc, employeeCount, pracownicy, details, meta) => {
    const date = new Date().toLocaleDateString('pl-PL');
    const offerNumber = safeText(meta?.offerNumber);
    const validUntil = safeText(meta?.validUntil ? formatDate(meta.validUntil) : undefined);
    const advisorName = safeText(meta?.advisorName);
    const advisorEmail = safeText(meta?.advisorEmail);
    const advisorPhone = safeText(meta?.advisorPhone);
    const includeCover = meta?.includeCover ?? true;
    const includeTOC = meta?.includeTOC ?? true;
    const isPlus = prowizjaProc === 26;
    const totalProvision = statsSelected.prowizja;
    let feeAmount = totalProvision;
    let raiseAmount = 0;
    let adminAmount = 0;
    if (isPlus) {
        feeAmount = totalProvision * (20 / 26);
        raiseAmount = totalProvision * (4 / 26);
        adminAmount = totalProvision * (2 / 26);
    }
    const monthSimulation = buildMonthSimulation(statsSelected.oszczednoscMiesieczna);
    const annualScenarios = buildAnnualScenarios(statsSelected.oszczednoscRoczna);
    const payrollRows = buildPayrollRows(pracownicy, details);
    const contracts = buildContractTotals(pracownicy, details);
    const avgPerEmployee = employeeCount > 0 ? statsSelected.oszczednoscMiesieczna / employeeCount : 0;
    const threeYearEffect = statsSelected.oszczednoscRoczna * 3;
    const tocItems = [
        'Ilustracja finansowa oszczędności',
        'Wizualizacja opłat i wynagrodzeń',
        'Scenariusze rocznych oszczędności',
        'Symulacja miesiąc do miesiąca',
        'Tabela listy płac (10 pracowników)',
        'Gwarancje / korzyści / konstrukcja prawna',
        'Harmonogram wdrożenia i warunki',
        'Firmy podobne i kontakt',
    ];
    const header = (title, pageIndex) => `
    <div class="page-header">
      <div class="header-grid">
        <div>
          <div class="header-label">Data wykonania</div>
          <div class="header-value">${date}</div>
        </div>
        <div>
          <div class="header-label">Przygotowano dla</div>
          <div class="header-value">${firma.nazwa}</div>
        </div>
        <div>
          <div class="header-label">Opracowanie</div>
          <div class="header-value">${advisorName}</div>
        </div>
        <div class="header-logo">STRATTON PRIME</div>
      </div>
      <div class="header-title">
        <div class="ph-title">${title}</div>
        <div class="ph-meta">Strona ${pageIndex}/${CONTENT_PAGE_COUNT} • Nr kalkulacji: ${offerNumber}</div>
      </div>
    </div>
  `;
    return `
    <!DOCTYPE html>
    <html lang="pl">
    <head>
      <meta charset="UTF-8">
      <title>Oferta Optymalizacji - ${firma.nazwa}</title>
      <link rel="preconnect" href="https://fonts.googleapis.com">
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
      <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
      <style>
        :root {
          --navy: #0B1020;
          --navy-light: #162035;
          --gold: #C6A15B;
          --gold-light: #E5C585;
          --white: #FFFFFF;
          --gray-bg: #F9FAFB;
          --gray-border: #E5E7EB;
          --text-main: #1F2937;
          --text-muted: #64748B;
          --success: #059669;
          --danger: #b91c1c;
        }

        @page { size: A4 landscape; margin: 0; }

        body {
          font-family: 'Inter', sans-serif;
          margin: 0;
          padding: 0;
          background: #333;
          -webkit-print-color-adjust: exact;
          print-color-adjust: exact;
        }

        .page {
          width: 297mm;
          height: 210mm;
          background: var(--white);
          margin: 20px auto;
          position: relative;
          overflow: hidden;
          box-shadow: 0 20px 40px rgba(0,0,0,0.2);
          display: flex;
          flex-direction: column;
          page-break-after: always;
        }

        @media print {
          body { background: white; }
          .page { margin: 0; box-shadow: none; page-break-after: always; height: 210mm; width: 297mm; }
        }

        h1, h2, h3 { font-family: 'Cinzel', serif; color: var(--navy); margin: 0; text-transform: uppercase; letter-spacing: 1px; }
        .text-gold { color: var(--gold); }
        .text-navy { color: var(--navy); }
        .muted { color: #64748b; font-size: 10pt; line-height: 1.6; }

        .page-header {
          position: absolute;
          top: 0;
          left: 0;
          right: 0;
          padding: 18px 40px 12px;
          border-bottom: 1px solid #e2e8f0;
          background: white;
          z-index: 10;
        }

        .header-grid {
          display: grid;
          grid-template-columns: repeat(4, 1fr);
          gap: 12px;
          align-items: center;
          margin-bottom: 10px;
        }

        .header-label {
          font-size: 8pt;
          text-transform: uppercase;
          color: #94a3b8;
          letter-spacing: 1px;
        }

        .header-value {
          font-size: 10pt;
          font-weight: 600;
          color: var(--navy);
        }

        .header-logo {
          font-family: 'Cinzel', serif;
          font-weight: 700;
          text-align: right;
          font-size: 12pt;
          color: var(--gold);
        }

        .header-title {
          display: flex;
          justify-content: space-between;
          align-items: baseline;
        }

        .ph-title {
          font-size: 16pt;
          font-weight: 700;
          color: var(--navy);
        }

        .ph-meta {
          font-size: 9pt;
          color: #94a3b8;
          text-align: right;
          font-family: monospace;
        }

        .page-body { margin-top: 86px; height: calc(100% - 86px); }
        .page-pad { padding: 28px 40px; }

        .grid-two { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .grid-three { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }

        .info-card { border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px; background: #f8fafc; }
        .info-label { font-size: 9px; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.5px; }
        .info-value { font-size: 12px; font-weight: 600; color: var(--navy); margin-top: 6px; }

        .kpi-card { background: #f8fafc; padding: 14px; border-radius: 10px; border: 1px solid #e2e8f0; }
        .kpi-label { font-size: 9px; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.5px; }
        .kpi-val { font-size: 18px; font-weight: 800; color: var(--navy); margin-top: 6px; }
        .kpi-sub { font-size: 9px; color: #94a3b8; margin-top: 2px; }

        .kpi-card.highlight { background: var(--navy); color: white; border: none; }
        .kpi-card.highlight .kpi-label { color: rgba(255,255,255,0.6); }
        .kpi-card.highlight .kpi-val { color: var(--gold); }
        .kpi-card.highlight .kpi-sub { color: rgba(255,255,255,0.4); }

        .fin-table { width: 100%; border-collapse: collapse; font-size: 9pt; }
        .fin-table th { text-align: left; padding: 10px 12px; border-bottom: 2px solid var(--navy); color: var(--navy); font-weight: 700; text-transform: uppercase; font-size: 8pt; background: #f1f5f9; }
        .fin-table td { padding: 9px 12px; border-bottom: 1px solid #e2e8f0; color: #334155; }
        .col-highlight { background: rgba(198, 161, 91, 0.08); font-weight: 600; color: var(--navy); }
        .row-total td { font-weight: 800; border-top: 2px solid var(--navy); background: #f8fafc; font-size: 10pt; color: var(--navy); }

        .small-table { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
        .small-table th { text-align: left; padding: 8px 10px; border-bottom: 1px solid #cbd5f5; background: #f8fafc; font-size: 7.5pt; text-transform: uppercase; color: #64748b; }
        .small-table td { padding: 7px 10px; border-bottom: 1px solid #e2e8f0; }

        .badge-success { background: #ecfdf5; color: #047857; padding: 3px 8px; border-radius: 4px; font-size: 8pt; font-weight: 700; display: inline-block; }
        .badge-blue { background: #eff6fc; color: #1e3a8a; padding: 3px 8px; border-radius: 4px; font-size: 8pt; font-weight: 700; display: inline-block; }

        .section-title { font-size: 12pt; font-weight: 700; color: var(--navy); margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px; font-family: 'Cinzel', serif; }

        .timeline-container { margin-top: 24px; position: relative; padding: 20px 0; }
        .timeline-line { position: absolute; top: 32px; left: 60px; right: 60px; height: 3px; background: #e2e8f0; z-index: 0; }
        .timeline-steps { display: flex; justify-content: space-between; position: relative; z-index: 1; }
        .tl-step { text-align: center; flex: 1; padding: 0 10px; }
        .tl-dot-wrapper { display: flex; justify-content: center; margin-bottom: 14px; }
        .tl-dot { width: 16px; height: 16px; background: var(--navy); border: 4px solid white; border-radius: 50%; box-shadow: 0 0 0 3px var(--navy); }
        .tl-week { font-size: 8pt; color: var(--gold); font-weight: 700; text-transform: uppercase; margin-bottom: 6px; display: block; letter-spacing: 1px; }
        .tl-title { font-size: 10pt; font-weight: 700; color: var(--navy); margin-bottom: 6px; font-family: 'Cinzel', serif; }
        .tl-desc { font-size: 8pt; color: #64748b; line-height: 1.4; max-width: 200px; margin: 0 auto; }

        .footer { position: absolute; bottom: 16px; left: 40px; right: 40px; border-top: 1px solid #e2e8f0; padding-top: 8px; display: flex; justify-content: space-between; font-size: 8pt; color: #94a3b8; text-transform: uppercase; }
        .toc-list { display: grid; grid-template-columns: 1fr; gap: 10px; margin-top: 20px; }
        .toc-item { display: flex; justify-content: space-between; border-bottom: 1px dashed #e2e8f0; padding-bottom: 6px; font-size: 10pt; }
        .cover {
          background: radial-gradient(circle at 0% 0%, #1e293b 0%, var(--navy) 60%);
          color: white;
          padding: 60px;
          display: flex;
          flex-direction: column;
          justify-content: space-between;
        }
        .cover-title { font-size: 34px; font-weight: 700; color: var(--gold); letter-spacing: 2px; }
        .cover-sub { font-size: 18px; opacity: 0.8; }

        .compare-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .compare-card { border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; background: #f8fafc; }
        .compare-card.highlight { background: #fff7ed; border-color: #fdba74; }
        .compare-title { font-size: 10pt; font-weight: 700; color: var(--navy); margin-bottom: 10px; }
        .legal-box { border: 1px solid #fee2e2; background: #fff5f5; padding: 12px; border-radius: 8px; font-size: 9pt; color: #7f1d1d; }
      </style>
    </head>
    <body>

      ${includeCover ? `
      <div class="page">
        <div class="cover">
          <div>
            <div class="cover-title">STRATTON PRIME</div>
            <div class="cover-sub">Oferta Eliton Prime™</div>
          </div>
          <div>
            <div style="font-size: 12px; text-transform: uppercase; opacity: 0.7;">Przygotowano dla:</div>
            <div style="font-size: 24px; font-weight: 600;">${firma.nazwa}</div>
            <div style="font-size: 14px; opacity: 0.8;">NIP: ${firma.nip}</div>
          </div>
          <div style="font-size: 10px; opacity: 0.6;">${date}</div>
        </div>
      </div>
      ` : ''}

      ${includeTOC ? `
      <div class="page">
        <div class="page-pad" style="padding-top: 40px;">
          <div class="section-title">Spis treści</div>
          <div class="toc-list">
            ${tocItems.map((item, index) => `
              <div class="toc-item"><span>${index + 1}. ${item}</span><span>${index + 1}/${CONTENT_PAGE_COUNT}</span></div>
            `).join('')}
          </div>
        </div>
      </div>
      ` : ''}

      <div class="page">
        ${header('Ilustracja finansowa oszczędności', 1)}
        <div class="page-body page-pad">
          <div class="grid-two">
            <div>
              <div class="section-title">Podsumowanie oferty</div>
              <div class="info-card" style="margin-bottom: 12px;">
                <div class="info-label">Numer kalkulacji</div>
                <div class="info-value">${offerNumber}</div>
                <div class="info-label" style="margin-top: 8px;">Osoba reprezentująca firmę</div>
                <div class="info-value">${safeText(firma.osobaKontaktowa)}</div>
              </div>
              <div class="section-title">Oferta Eliton Prime</div>
              <div class="grid-two">
                <div class="kpi-card highlight">
                  <div class="kpi-label">Prognozowana oszczędność roczna</div>
                  <div class="kpi-val">${(0, formatters_1.formatPLN)(statsSelected.oszczednoscRoczna)}</div>
                  <div class="kpi-sub">Netto po prowizji</div>
                </div>
                <div class="kpi-card">
                  <div class="kpi-label">Prognozowana oszczędność miesięczna</div>
                  <div class="kpi-val">${(0, formatters_1.formatPLN)(statsSelected.oszczednoscMiesieczna)}</div>
                  <div class="kpi-sub">Netto po prowizji</div>
                </div>
                <div class="kpi-card">
                  <div class="kpi-label">Średnia na pracownika</div>
                  <div class="kpi-val">${(0, formatters_1.formatPLN)(avgPerEmployee)}</div>
                  <div class="kpi-sub">Miesięcznie</div>
                </div>
                <div class="kpi-card">
                  <div class="kpi-label">Efekt 3-letni</div>
                  <div class="kpi-val">${(0, formatters_1.formatPLN)(threeYearEffect)}</div>
                  <div class="kpi-sub">Prognoza 36 mies.</div>
                </div>
              </div>
              <div style="margin-top: 10px; display: flex; gap: 10px;">
                <span class="badge-success">${employeeCount} pracowników objętych programem</span>
                <span class="badge-blue">Model ${isPlus ? 'WIN-WIN' : 'STANDARD'}</span>
              </div>
            </div>
            <div>
              <div class="section-title">Aktualne opłaty zatrudnienia</div>
              <table class="fin-table">
                <thead>
                  <tr>
                    <th>Rodzaj umowy</th>
                    <th>Liczba</th>
                    <th>Koszt miesięczny</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>UOP</td>
                    <td>${contracts.UOP.count}</td>
                    <td>${(0, formatters_1.formatPLN)(contracts.UOP.koszt)}</td>
                  </tr>
                  <tr>
                    <td>UZ</td>
                    <td>${contracts.UZ.count}</td>
                    <td>${(0, formatters_1.formatPLN)(contracts.UZ.koszt)}</td>
                  </tr>
                  <tr class="row-total">
                    <td colspan="2">Suma</td>
                    <td>${(0, formatters_1.formatPLN)(statsSelected.totalCostStandard)}</td>
                  </tr>
                </tbody>
              </table>
              <div class="section-title" style="margin-top: 16px;">Informacje o Państwa firmie</div>
              <div class="grid-two">
                <div class="info-card">
                  <div class="info-label">Branża</div>
                  <div class="info-value">${safeText(firma.branza)}</div>
                </div>
                <div class="info-card">
                  <div class="info-label">Benefity</div>
                  <div class="info-value">${safeText(firma.benefity)}</div>
                </div>
                <div class="info-card">
                  <div class="info-label">Udział w projekcie</div>
                  <div class="info-value">${safeText(firma.udzialWProjekcie)}</div>
                </div>
                <div class="info-card">
                  <div class="info-label">Przeszłe oszczędności</div>
                  <div class="info-value">${safeText(firma.oszczednosciPrzeszle)}</div>
                </div>
                <div class="info-card">
                  <div class="info-label">Aktualne oszczędności</div>
                  <div class="info-value">${safeText(firma.oszczednosciAktualne)}</div>
                </div>
                <div class="info-card">
                  <div class="info-label">Planowane inwestycje</div>
                  <div class="info-value">${safeText(firma.inwestycjePlanowane)}</div>
                </div>
                <div class="info-card">
                  <div class="info-label">Deklarowana kwota oszczędności</div>
                  <div class="info-value">${safeText(firma.kwotaOszczednosciDeklarowana)}</div>
                </div>
                <div class="info-card">
                  <div class="info-label">Zadłużenia</div>
                  <div class="info-value">${safeText(firma.zadluzenia)}</div>
                </div>
                <div class="info-card">
                  <div class="info-label">Ryczałt / VAT</div>
                  <div class="info-value">${safeText(firma.ryczaltVat)}</div>
                </div>
              </div>
            </div>
          </div>
          <div class="footer">
            <div>Stratton Prime • Raport poufny</div>
            <div>${date}</div>
          </div>
        </div>
      </div>

      <div class="page">
        ${header('Wizualizacja opłat i wynagrodzeń', 2)}
        <div class="page-body page-pad">
          <div class="section-title">Porównanie modeli</div>
          <div class="compare-grid">
            <div class="compare-card">
              <div class="compare-title">Aktualny model</div>
              <div class="muted">Koszt całkowity (mies.):</div>
              <div class="kpi-val">${(0, formatters_1.formatPLN)(statsSelected.totalCostStandard)}</div>
              <div class="muted">Oszczędność: 0 zł</div>
            </div>
            <div class="compare-card">
              <div class="compare-title">Eliton Prime Standard</div>
              <div class="muted">Koszt całkowity (mies.):</div>
              <div class="kpi-val">${(0, formatters_1.formatPLN)(statsStandard.totalCostModel)}</div>
              <div class="muted">Oszczędność: ${(0, formatters_1.formatPLN)(statsStandard.oszczednoscMiesieczna)}</div>
            </div>
            <div class="compare-card highlight">
              <div class="compare-title">Eliton Prime Plus (Rekomendujemy)</div>
              <div class="muted">Koszt całkowity (mies.):</div>
              <div class="kpi-val">${(0, formatters_1.formatPLN)(statsPlus.totalCostModel)}</div>
              <div class="muted">Oszczędność: ${(0, formatters_1.formatPLN)(statsPlus.oszczednoscMiesieczna)}</div>
            </div>
          </div>
          <div class="section-title" style="margin-top: 16px;">Ważna informacja prawna</div>
          <div class="legal-box">
            Powyższe wyliczenia oparte są na obowiązujących przepisach podatkowych. W kalkulacji uwzględniamy wymagane
            składki oraz podatki i zapewniamy pełną zgodność rozliczeń z aktualnym stanem prawnym.
          </div>
          <div class="footer">
            <div>Stratton Prime • Raport poufny</div>
            <div>${date}</div>
          </div>
        </div>
      </div>

      <div class="page">
        ${header('Scenariusze rocznych oszczędności', 3)}
        <div class="page-body page-pad">
          <div class="section-title">Scenariusze roczne</div>
          <table class="fin-table">
            <thead>
              <tr>
                <th>Wariant</th>
                <th>Oszczędność roczna</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Aktualne rozliczenie</td>
                <td>0 zł</td>
              </tr>
              <tr>
                <td>Eliton Prime Standard</td>
                <td>${(0, formatters_1.formatPLN)(statsStandard.oszczednoscRoczna)}</td>
              </tr>
              <tr>
                <td>Eliton Prime Plus</td>
                <td>${(0, formatters_1.formatPLN)(statsPlus.oszczednoscRoczna)}</td>
              </tr>
            </tbody>
          </table>
          <div class="section-title" style="margin-top: 16px;">Prognoza wieloletnia</div>
          <table class="fin-table">
            <thead>
              <tr>
                <th>Horyzont</th>
                <th>Oszczędność łączna</th>
              </tr>
            </thead>
            <tbody>
              ${annualScenarios.map((row) => `
                <tr>
                  <td>${row.years} ${row.years === 1 ? 'rok' : row.years < 5 ? 'lata' : 'lat'}</td>
                  <td>${(0, formatters_1.formatPLN)(row.total)}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
          <div class="section-title" style="margin-top: 16px;">Ważna informacja prawna</div>
          <div class="legal-box">
            Oszczędności obliczone są w oparciu o aktualne stawki. Faktury za usługę podlegają standardowym zasadom
            księgowym i mogą być rozliczane zgodnie z przepisami podatkowymi.
          </div>
          <div class="footer">
            <div>Stratton Prime • Raport poufny</div>
            <div>${date}</div>
          </div>
        </div>
      </div>

      <div class="page">
        ${header('Symulacja miesiąc do miesiąca', 4)}
        <div class="page-body page-pad">
          <table class="fin-table">
            <thead>
              <tr>
                <th>Miesiąc</th>
                <th>Oszczędność miesięczna</th>
                <th>Oszczędność narastająco</th>
              </tr>
            </thead>
            <tbody>
              ${monthSimulation.map((row) => `
                <tr>
                  <td>${row.name}</td>
                  <td>${(0, formatters_1.formatPLN)(row.monthlySavings)}</td>
                  <td>${(0, formatters_1.formatPLN)(row.cumulative)}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
          <div class="footer">
            <div>Stratton Prime • Raport poufny</div>
            <div>${date}</div>
          </div>
        </div>
      </div>

      <div class="page">
        ${header('Tabela listy płac (10 pracowników)', 5)}
        <div class="page-body page-pad">
          <div class="muted" style="margin-bottom: 10px;">
            Prezentacja pierwszych 10 pracowników (lub top 10 według kosztu). Jeśli lista zawiera więcej osób, pełne dane
            są dostępne w załącznikach.
          </div>
          <table class="small-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Pracownik</th>
                <th>UOP/UZ</th>
                <th>Brutto standard</th>
                <th>Netto standard</th>
                <th>Netto Eliton</th>
                <th>Oszczędność (mies.)</th>
              </tr>
            </thead>
            <tbody>
              ${payrollRows.map((row) => `
                <tr>
                  <td>${row.index}</td>
                  <td>${row.name || `Pracownik ${row.index}`}</td>
                  <td>${row.contract}</td>
                  <td>${(0, formatters_1.formatPLN)(row.standardBrutto)}</td>
                  <td>${(0, formatters_1.formatPLN)(row.standardNetto)}</td>
                  <td>${(0, formatters_1.formatPLN)(row.modelNetto)}</td>
                  <td>${(0, formatters_1.formatPLN)(row.oszczednosc)}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
          <div class="footer">
            <div>Stratton Prime • Raport poufny</div>
            <div>${date}</div>
          </div>
        </div>
      </div>

      <div class="page">
        ${header('Gwarancje / korzyści / konstrukcja prawna', 6)}
        <div class="page-body page-pad">
          <div class="grid-two">
            <div>
              <div class="section-title">Podział prowizji</div>
              <div class="info-card" style="margin-bottom: 12px;">
                <div class="info-label">Struktura prowizyjna</div>
                <div class="info-value">
                  ${isPlus
        ? `Opłata za usługę: ${(0, formatters_1.formatPLN)(feeAmount)}; Podwyżki: ${(0, formatters_1.formatPLN)(raiseAmount)}; Administracja: ${(0, formatters_1.formatPLN)(adminAmount)}.`
        : `Opłata za usługę: ${(0, formatters_1.formatPLN)(totalProvision)}.`}
                </div>
              </div>
              <div class="info-card">
                <div class="info-label">Podstawy prawne</div>
                <div class="info-value">Model oparty o interpretacje podatkowe i przepisy prawa pracy.</div>
              </div>
            </div>
            <div>
              <div class="section-title">Korzyści</div>
              <div class="info-card" style="margin-bottom: 12px;">
                <div class="info-label">Bezpieczeństwo</div>
                <div class="info-value">Pełna dokumentacja i wsparcie w czasie wdrożenia.</div>
              </div>
              <div class="info-card" style="margin-bottom: 12px;">
                <div class="info-label">Transparentność</div>
                <div class="info-value">Czytelne zestawienia dla działu kadr i pracowników.</div>
              </div>
              <div class="info-card">
                <div class="info-label">Kontrola</div>
                <div class="info-value">Miesięczne raporty oszczędności i kosztów.</div>
              </div>
            </div>
          </div>
          <div class="footer">
            <div>Stratton Prime • Raport poufny</div>
            <div>${date}</div>
          </div>
        </div>
      </div>

      <div class="page">
        ${header('Harmonogram wdrożenia i warunki', 7)}
        <div class="page-body page-pad">
          <div class="section-title">Etapy wdrożenia</div>
          <div class="timeline-container">
            <div class="timeline-line"></div>
            <div class="timeline-steps">
              <div class="tl-step">
                <div class="tl-dot-wrapper"><div class="tl-dot"></div></div>
                <span class="tl-week">Tydzień 1</span>
                <div class="tl-title">Analiza</div>
                <div class="tl-desc">Weryfikacja danych kadrowych i przygotowanie procesu.</div>
              </div>
              <div class="tl-step">
                <div class="tl-dot-wrapper"><div class="tl-dot"></div></div>
                <span class="tl-week">Tydzień 2</span>
                <div class="tl-title">Komunikacja</div>
                <div class="tl-desc">Przedstawienie modelu pracownikom i zebranie zgód.</div>
              </div>
              <div class="tl-step">
                <div class="tl-dot-wrapper"><div class="tl-dot"></div></div>
                <span class="tl-week">Tydzień 3</span>
                <div class="tl-title">Wdrożenie</div>
                <div class="tl-desc">Uruchomienie świadczeń i pierwsze rozliczenie.</div>
              </div>
              <div class="tl-step">
                <div class="tl-dot-wrapper"><div class="tl-dot"></div></div>
                <span class="tl-week">Tydzień 4</span>
                <div class="tl-title">Monitoring</div>
                <div class="tl-desc">Raportowanie efektów i optymalizacje.</div>
              </div>
            </div>
          </div>
          <div class="grid-two" style="margin-top: 18px;">
            <div class="info-card">
              <div class="info-label">Cena Eliton Standard</div>
              <div class="info-value">${(0, formatters_1.formatPLN)(statsStandard.prowizja)} / mies. (prowizja)</div>
            </div>
            <div class="info-card">
              <div class="info-label">Cena Eliton Plus</div>
              <div class="info-value">${(0, formatters_1.formatPLN)(statsPlus.prowizja)} / mies. (prowizja)</div>
            </div>
            <div class="info-card">
              <div class="info-label">Ważność oferty</div>
              <div class="info-value">${validUntil}</div>
            </div>
            <div class="info-card">
              <div class="info-label">Umowa</div>
              <div class="info-value">Podpisanie umowy następuje po akceptacji oferty.</div>
            </div>
          </div>
          <div class="footer">
            <div>Stratton Prime • Raport poufny</div>
            <div>${date}</div>
          </div>
        </div>
      </div>

      <div class="page">
        ${header('Firmy podobne i kontakt', 8)}
        <div class="page-body page-pad">
          <div class="section-title">Firmy podobne i osiągnięcia</div>
          <div class="muted" style="margin-bottom: 18px;">
            Referencje z branż produkcyjnych, usługowych i logistycznych dostępne są na życzenie. Wyniki wdrożeń
            obejmują średnie oszczędności rzędu 12–18% w skali roku.
          </div>
          <div class="section-title">Kontakt</div>
          <div class="grid-two">
            <div class="info-card">
              <div class="info-label">Opiekun oferty</div>
              <div class="info-value">${advisorName}</div>
              <div class="info-value">${advisorEmail}</div>
              <div class="info-value">${advisorPhone}</div>
            </div>
            <div class="info-card">
              <div class="info-label">Numer oferty</div>
              <div class="info-value">${offerNumber}</div>
              <div class="info-label" style="margin-top: 8px;">Data sporządzenia</div>
              <div class="info-value">${date}</div>
            </div>
          </div>
          <div class="footer">
            <div>Stratton Prime • Raport poufny</div>
            <div>${date}</div>
          </div>
        </div>
      </div>
    </body>
    </html>
  `;
};
exports.offerPdfGenerator = {
    generateOfferPDF: (item, meta) => {
        const tempPracownicy = item.dane.pracownicy;
        const tempFirma = item.dane.firma;
        const tempConfig = item.dane.config;
        const tempProwizja = item.dane.prowizjaProc || 28;
        const standardRate = meta?.standardRate ?? 28;
        const plusRate = meta?.plusRate ?? 26;
        const details = tempPracownicy.map((p) => {
            const standard = (0, tax_engine_1.obliczWariantStandard)(p, tempFirma.stawkaWypadkowa, tempConfig);
            const podzial = (0, tax_engine_1.obliczWariantPodzial)(p, tempFirma.stawkaWypadkowa, p.nettoZasadnicza, tempConfig);
            return { standard, podzial };
        });
        const base = buildBaseTotals(details);
        const statsSelected = buildStats(base, tempProwizja);
        const statsStandard = buildStats(base, standardRate);
        const statsPlus = buildStats(base, plusRate);
        const htmlContent = (0, exports.buildOfferPdfHtml)(item, meta);
        const printWindow = window.open('', '_blank');
        if (printWindow) {
            printWindow.document.write(htmlContent);
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => { printWindow.print(); }, 500);
        }
    },
};
const buildOfferPdfHtml = (item, meta) => {
    const tempPracownicy = item.dane.pracownicy;
    const tempFirma = item.dane.firma;
    const tempConfig = item.dane.config;
    const tempProwizja = item.dane.prowizjaProc || 28;
    const standardRate = meta?.standardRate ?? 28;
    const plusRate = meta?.plusRate ?? 26;
    const details = tempPracownicy.map((p) => {
        const standard = (0, tax_engine_1.obliczWariantStandard)(p, tempFirma.stawkaWypadkowa, tempConfig);
        const podzial = (0, tax_engine_1.obliczWariantPodzial)(p, tempFirma.stawkaWypadkowa, p.nettoZasadnicza, tempConfig);
        return { standard, podzial };
    });
    const base = buildBaseTotals(details);
    const statsSelected = buildStats(base, tempProwizja);
    const statsStandard = buildStats(base, standardRate);
    const statsPlus = buildStats(base, plusRate);
    return generateOfferHTML(tempFirma, base, statsSelected, statsStandard, statsPlus, tempProwizja, tempPracownicy.length, tempPracownicy, details, meta);
};
exports.buildOfferPdfHtml = buildOfferPdfHtml;
