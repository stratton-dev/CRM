import { ZapisanaKalkulacja } from '../models/history';
import { Pracownik } from '../models/employee';
import { formatPLN } from './formatters';
import { obliczWariantPodzial, obliczWariantStandard } from '../tax-engine';

export type OfferPdfMeta = {
  offerNumber?: string;
  validUntil?: string;
  advisorName?: string;
  advisorEmail?: string;
  advisorPhone?: string;
  includeCover?: boolean;
  includeTOC?: boolean;
  standardRate?: number;
  plusRate?: number;
  footerLine1?: string;
  footerLine2?: string;
  footerLogoUrl?: string;
  documentLayout?: 'horizontal' | 'vertical';
};

type OfferStats = {
  standard: {
    kosztPracodawcy: number;
    zusPracodawca: number;
    brutto: number;
    netto: number;
    zusPracownik: number;
    pit: number;
  };
  stratton: {
    kosztPracodawcy: number;
    zusPracodawca: number;
    brutto: number;
    netto: number;
    zusPracownik: number;
    pit: number;
    prowizja: number;
  };
  oszczednoscRoczna: number;
  oszczednoscMiesieczna: number;
  prowizja: number;
  totalCostModel: number;
  totalCostStandard: number;
};

type BaseTotals = {
  sumaKosztStandard: number;
  sumaKosztPodzial: number;
  sumaBruttoSwiadczen: number;
  standard: {
    kosztPracodawcy: number;
    zusPracodawca: number;
    brutto: number;
    netto: number;
    zusPracownik: number;
    pit: number;
  };
  stratton: {
    kosztPracodawcy: number;
    zusPracodawca: number;
    brutto: number;
    netto: number;
    zusPracownik: number;
    pit: number;
  };
};

const CONTENT_PAGE_COUNT = 8;

const formatDate = (value?: string) => {
  if (!value) {
    return new Date().toLocaleDateString('pl-PL');
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return value;
  }
  return date.toLocaleDateString('pl-PL');
};

const safeText = (value?: string) => value && value.trim().length > 0 ? value : '-';

const buildMonthSimulation = (monthlySavings: number) => {
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

const buildAnnualScenarios = (annualSavings: number) => {
  const years = [1, 3, 5, 10];
  return years.map((year) => ({
    years: year,
    total: annualSavings * year,
  }));
};

const buildPayrollRows = (pracownicy: Pracownik[], details: any[]) => {
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

const buildBaseTotals = (details: any[]): BaseTotals => {
  return {
    sumaKosztStandard: details.reduce((acc: number, w: any) => acc + w.standard.kosztPracodawcy, 0),
    sumaKosztPodzial: details.reduce((acc: number, w: any) => acc + w.podzial.kosztPracodawcy, 0),
    sumaBruttoSwiadczen: details.reduce((acc: number, w: any) => acc + w.podzial.swiadczenie.brutto, 0),
    standard: {
      kosztPracodawcy: details.reduce((acc: number, w: any) => acc + w.standard.kosztPracodawcy, 0),
      zusPracodawca: details.reduce((acc: number, w: any) => acc + w.standard.zusPracodawca.suma, 0),
      brutto: details.reduce((acc: number, w: any) => acc + w.standard.brutto, 0),
      netto: details.reduce((acc: number, w: any) => acc + w.standard.netto, 0),
      zusPracownik: details.reduce((acc: number, w: any) => acc + w.standard.zusPracownik.suma + w.standard.zdrowotna, 0),
      pit: details.reduce((acc: number, w: any) => acc + w.standard.pit, 0),
    },
    stratton: {
      kosztPracodawcy: details.reduce((acc: number, w: any) => acc + w.podzial.kosztPracodawcy, 0),
      zusPracodawca: details.reduce((acc: number, w: any) => acc + w.podzial.zasadnicza.zusPracodawca.suma, 0),
      brutto: details.reduce((acc: number, w: any) => acc + w.podzial.pit.lacznyPrzychod, 0),
      netto: details.reduce((acc: number, w: any) => acc + w.podzial.doWyplaty, 0),
      zusPracownik: details.reduce((acc: number, w: any) => acc + w.podzial.zasadnicza.zusPracownik.suma + w.podzial.zasadnicza.zdrowotna, 0),
      pit: details.reduce((acc: number, w: any) => acc + w.podzial.pit.kwota, 0),
    },
  };
};

const buildStats = (base: BaseTotals, prowizjaRate: number): OfferStats => {
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

const buildContractTotals = (pracownicy: Pracownik[], details: any[]) => {
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

const generateOfferHTML = (
  firma: any,
  base: BaseTotals,
  statsSelected: OfferStats,
  statsStandard: OfferStats,
  statsPlus: OfferStats,
  prowizjaProc: number,
  employeeCount: number,
  pracownicy: Pracownik[],
  details: any[],
  meta?: OfferPdfMeta,
) => {
  const date = new Date().toLocaleDateString('pl-PL');
  const offerNumber = safeText(meta?.offerNumber);
  const validUntil = safeText(meta?.validUntil ? formatDate(meta.validUntil) : undefined);
  const advisorName = safeText(meta?.advisorName);
  const advisorEmail = safeText(meta?.advisorEmail);
  const advisorPhone = safeText(meta?.advisorPhone);
  const includeCover = meta?.includeCover ?? true;
  const includeTOC = meta?.includeTOC ?? true;
  const footerLine1 = safeText(meta?.footerLine1);
  const footerLine2 = safeText(meta?.footerLine2);
  const footerLogoUrl = meta?.footerLogoUrl || '/logo_paper.png';
  const documentLayout = meta?.documentLayout || 'vertical';
  const isPortrait = documentLayout === 'vertical';
  const pageWidth = isPortrait ? '210mm' : '297mm';
  const pageHeight = isPortrait ? '297mm' : '210mm';
  const pageSize = isPortrait ? 'A4 portrait' : 'A4 landscape';

  const isPlus = prowizjaProc === 26;
  const totalProvision = statsSelected.prowizja;
  const decisionMaker = (firma?.kontakty || []).find((contact: any) =>
    contact?.is_decision_maker || contact?.isDecisionMaker
  );
  const preparedForName = safeText(decisionMaker?.name || firma.osobaKontaktowa);
  const splitProvision = (prowizja: number, isPlusVariant: boolean) => {
    if (!isPlusVariant) {
      return { fee: prowizja, raise: 0, admin: 0 };
    }
    return {
      fee: prowizja * (20 / 26),
      raise: prowizja * (4 / 26),
      admin: prowizja * (2 / 26),
    };
  };
  const monthlyFeesAfterWages = Math.max(0, base.standard.kosztPracodawcy - base.standard.brutto);
  const annualFeesAfterWages = monthlyFeesAfterWages * 12;

  const selectedSplit = splitProvision(totalProvision, isPlus);
  const feeAmount = selectedSplit.fee;
  const raiseAmount = selectedSplit.raise;
  const adminAmount = selectedSplit.admin;
  const standardSplit = splitProvision(statsStandard.prowizja, false);
  const plusSplit = splitProvision(statsPlus.prowizja, true);

  const currentNetto = base.standard.netto;
  const currentBrutto = base.standard.brutto;
  const currentEmployerContrib = base.standard.zusPracodawca;
  const currentEmployeeContrib = base.standard.zusPracownik;
  const currentTotalCost = base.standard.kosztPracodawcy;

  const modelNetto = base.stratton.netto;
  const modelBrutto = base.stratton.brutto;
  const modelEmployerContrib = base.stratton.zusPracodawca;
  const modelEmployeeContrib = base.stratton.zusPracownik;
  const modelBaseCost = base.stratton.kosztPracodawcy;

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

  const header = (title: string, pageIndex: number) => `
    <div class="page-header">
      <div class="header-grid">
        <div>
          <div class="header-label">Tytuł sekcji</div>
          <div class="header-value">${title}</div>
          <div class="header-sub">Data wykonania: ${date}</div>
        </div>
        <div>
          <div class="header-label">Przygotowano dla</div>
          <div class="header-value">${preparedForName}</div>
          <div class="header-sub">${firma.nazwa}</div>
          <div class="header-sub">NIP: ${firma.nip}</div>
        </div>
        <div>
          <div class="header-label">Opracowanie</div>
          <div class="header-value">${advisorName}</div>
          <div class="header-sub">${advisorPhone}</div>
          <div class="header-sub">${advisorEmail}</div>
        </div>
        <div class="header-logo">
          <img src="/logo_paper_navy.png" alt="Stratton Prime" />
        </div>
      </div>
    </div>
  `;

  const footerHtml = (pageIndex: number) => `
    <div class="footer footer-${documentLayout}">
      <div class="footer-left">
        <img src="${footerLogoUrl}" alt="Stratton Prime" />
      </div>
      <div class="footer-middle">
        <div class="footer-line footer-line-1">${footerLine1}</div>
        <div class="footer-line footer-line-2">${footerLine2}</div>
      </div>
      <div class="footer-right">
        <div class="footer-meta">Nr kalkulacji: ${offerNumber}</div>
        <div class="footer-meta">${pageIndex > 0 ? `Strona ${pageIndex}/${CONTENT_PAGE_COUNT}` : ''}</div>
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

        @page { size: ${pageSize}; margin: 0; }

        body {
          font-family: 'Inter', sans-serif;
          margin: 0;
          padding: 0;
          background: #333;
          -webkit-print-color-adjust: exact;
          print-color-adjust: exact;
        }

        .page {
          width: ${pageWidth};
          height: ${pageHeight};
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
          .page { margin: 0; box-shadow: none; page-break-after: always; height: ${pageHeight}; width: ${pageWidth}; }
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
          align-items: start;
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

        .header-sub {
          font-size: 9pt;
          color: #64748b;
          margin-top: 2px;
        }

        .header-logo {
          text-align: right;
          display: flex;
          flex-direction: column;
          align-items: flex-end;
          gap: 6px;
        }

        .header-logo img {
          max-height: 80px;
          object-fit: contain;
        }

        .header-logo-sub {
          font-size: 8pt;
          color: #94a3b8;
          font-family: monospace;
        }

        .header-page {
          font-size: 9pt;
          color: #94a3b8;
          text-align: right;
          font-family: monospace;
          margin-top: 6px;
        }

        .page-body { margin-top: 86px; height: calc(100% - 86px); }
        .page-pad { padding: 28px 40px; }

        .grid-two { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .grid-three { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }

        .info-card { border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px; background: #f8fafc; }
        .info-label { font-size: 9px; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.5px; }
        .info-value { font-size: 12px; font-weight: 600; color: var(--navy); margin-top: 6px; }
        .info-value.small { font-size: 9px; font-weight: 400; color: var(--navy); margin-top: 6px; }

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

        .footer { position: absolute; bottom: 12px; left: 40px; right: 40px; border-top: 1px solid #e2e8f0; padding-top: 4px; display: flex; gap: 18px; font-size: 7pt; color: #94a3b8; text-transform: uppercase; }
        .footer-left { display: flex; align-items: center; }
        .footer-left img { max-height: 60px; object-fit: contain; }
        .footer-middle { display: flex; flex-direction: column; gap: 1px; text-transform: none; flex: 1; }
        .footer-right { display: flex; flex-direction: column; gap: 1px; text-transform: none; text-align: right; font-family: monospace; }
        .footer-line { font-size: 7pt; color: #64748b; line-height: 1.2; }
        .footer-line-1 { font-weight: 700; color: #475569; text-transform: uppercase; }
        .footer-meta { font-size: 7pt; color: #64748b; }
        .footer-vertical { flex-direction: row; align-items: center; }
        .toc-list { display: grid; grid-template-columns: 1fr; gap: 10px; margin-top: 20px; }
        .toc-item { display: flex; justify-content: space-between; border-bottom: 1px dashed #e2e8f0; padding-bottom: 6px; font-size: 10pt; }
        .cover {
          background: radial-gradient(circle at 0% 0%, #1e293b 0%, var(--navy) 60%);
          color: white;
          padding: 60px;
          display: flex;
          flex-direction: column;
          justify-content: space-between;
          position: relative;
          overflow: hidden;
          height: 33%
        }
        .cover > * { position: relative; z-index: 2; }
        .cover-watermark {
          position: absolute;
          right: -15%;
          top: 5%;
          width: 60%;
          opacity: 0.3;
          z-index: 1;
        }
        .cover-title { font-size: 34px; font-weight: 700; color: var(--gold); letter-spacing: 2px; }
        .cover-sub { font-size: 18px; opacity: 0.8; }

        .compare-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .compare-card { border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; background: #f8fafc; }
        .compare-card.highlight { background: #fff7ed; border-color: #fdba74; }
        .compare-title { font-size: 10pt; font-weight: 700; color: var(--navy); margin-bottom: 10px; }
        .compare-row { display: flex; justify-content: space-between; gap: 8px; font-size: 9pt; color: var(--text-main); margin-bottom: 6px; }
        .compare-row span { color: var(--text-muted); }
        .compare-row strong { color: var(--navy); font-weight: 600; text-align: right; }
        .compare-row.compare-total { margin-top: 6px; padding-top: 6px; border-top: 1px dashed #e2e8f0; }
        .compare-table { width: 100%; border-collapse: collapse; font-size: 8pt; }
        .compare-table th, .compare-table td { border: 1px solid #e2e8f0; padding: 6px; vertical-align: top; }
        .compare-table thead th { background: #f8fafc; color: var(--navy); font-weight: 700; text-transform: uppercase; font-size: 7pt; letter-spacing: 0.04em; }
        .compare-table tbody td:first-child { width: 34%; color: var(--text-muted); font-weight: 600; }
        .compare-table tbody td:not(:first-child) { text-align: right; color: var(--navy); font-weight: 600; }
        .compare-table tbody tr.row-total td { background: #fff7ed; }
        .compare-table th:nth-child(2), .compare-table td:nth-child(2) { background: #fee2e2; color: #991b1b; }
        .compare-table th:nth-child(4), .compare-table td:nth-child(4) { background: #dcfce7; color: #166534; }
        .legal-box { border: 1px solid #fee2e2; background: #fff5f5; padding: 12px; border-radius: 8px; font-size: 9pt; color: #7f1d1d; }
        .chart-wrap { width: 100%; height: 220px; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; background: #ffffff; }
        .chart-wrap canvas { width: 100%; height: 100%; }
      </style>
    </head>
    <body>

      ${includeCover ? `
      <div class="page">
        <div class="cover">
          <img class="cover-watermark" src="/logo.svg" alt="" />
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
        ${header('Spis treści', 0)}
        <div class="page-body page-pad">
          <div class="section-title">Spis treści</div>
          <div class="toc-list">
            ${tocItems.map((item, index) => `
              <div class="toc-item"><span>${index + 1}. ${item}</span><span>${index + 1}/${CONTENT_PAGE_COUNT}</span></div>
            `).join('')}
          </div>
          ${footerHtml(0)}
        </div>
      </div>
      ` : ''}

      <div class="page">
        ${header('Ilustracja finansowa oszczędności', 1)}
        <div class="page-body page-pad">
          <div class="grid-two">
            <div>
              <div class="section-title">Kalkulacja przewidywalnych oszczędności Eliton Prime</div>
              <div class="info-card" style="margin-bottom: 12px;">
                <div class="info-label">Numer kalkulacji</div>
                <div class="info-value">${offerNumber}</div>
                <div class="info-label" style="margin-top: 8px;">Data opracowania:</div>
                <div class="info-value">${date}</div>
                <div class="info-label" style="margin-top: 8px;">Osoba reprezentująca firmę podczas spotkania</div>
                <div class="info-value">${safeText(firma.osobaKontaktowa)}</div>
                <div class="info-value small" style="margin-top: 8px;">Kalkulacje wykonane za zgodą Firmy: ${safeText(firma.nazwa)} na podstawie danych podanych na spotkaniu.</div>
              </div>
              <div class="section-title">Oferta Eliton Prime</div>
              <div class="grid-two">
                <div class="kpi-card highlight">
                  <div class="kpi-label">Prognozowana oszczędność roczna</div>
                  <div class="kpi-val">${formatPLN(statsSelected.oszczednoscRoczna)}</div>
                  <div class="kpi-sub">Netto po prowizji</div>
                </div>
                <div class="kpi-card">
                  <div class="kpi-label">Prognozowana oszczędność miesięczna</div>
                  <div class="kpi-val">${formatPLN(statsSelected.oszczednoscMiesieczna)}</div>
                  <div class="kpi-sub">Netto po prowizji</div>
                </div>
                <div class="kpi-card">
                <div class="kpi-label">Średnia oszczędność generowana z jednego pracownika</div>
                <div class="kpi-val">${formatPLN(avgPerEmployee)}</div>
                <div class="kpi-sub">Miesięcznie</div>
              </div>
              <div class="kpi-card">
                <div class="kpi-label">Efekt oszczędności 3-letni</div>
                <div class="kpi-val">${formatPLN(threeYearEffect)}</div>
                <div class="kpi-sub">Prognoza 36 mies.</div>
              </div>
              </div>
              <div style="margin-top: 10px; display: flex; gap: 10px;">
                <span class="badge-success">${employeeCount} pracowników objętych programem</span>
                <span class="badge-blue">Model ${isPlus ? 'WIN-WIN' : 'STANDARD'}</span>
              </div>
              <div class="info-value small">
              Oferta Eliton	Prime i Eliton Prime PLUS
              <ul>
                <li> dane do kalkulacji zakładają wysokości wynagrodzeń przeszłych pokazując możliwości przyszłych o szczędności.</li>
                <li> umowa główna jest	umową otwartą opartą na comiesięcznych nowych kalkulacjach adekwatnych do wysokości prognozowanych wypłat.</li>
                <li> opcja PLUS gwarantuje podwyżki w wysokości +5% wynagrodzenia netto dla każdego pracownika korzystającego z modelu Eliton Prime FINANSOWANE PRZEZ STRATTONPRIME.</li>
              </div>
           </div>
            <div>
              <div class="section-title">Aktualne opłaty związane z zatrudieniem pracowników w Państwa firmie.</div>
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
                    <td>${formatPLN(contracts.UOP.koszt)}</td>
                  </tr>
                  <tr>
                    <td>UZ</td>
                    <td>${contracts.UZ.count}</td>
                    <td>${formatPLN(contracts.UZ.koszt)}</td>
                  </tr>
                  <tr class="row-total">
                    <td colspan="2">Suma</td>
                    <td>${formatPLN(statsSelected.totalCostStandard)}</td>
                  </tr>
                  <tr>
                    <td colspan="2">Miesięczne opłaty za wszystkich pracowników po odjęciu wynagrodzeń.</td>
                    <td>${formatPLN(monthlyFeesAfterWages)}</td>
                  </tr>
                  <tr>
                    <td colspan="2">Roczne opłaty za wszystkich pracowników po odjęciu wynagrodzeń.</td>
                    <td>${formatPLN(annualFeesAfterWages)}</td>
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
          ${footerHtml(1)}
        </div>
      </div>

      <div class="page">
        ${header('Wizualizacja opłat i wynagrodzeń', 2)}
      <div class="page-body page-pad">
            <div class="info-value small"> Niniejsza ilustracja przedstawia potencjał finansowy wynikający z wdrożenia modelu wynagradzania Eliton Prime™ 
            w Państwa firmie. KALKULACJA została przygotowana w oparciu o przekazane dane dotyczące struktury wynagrodzeń 
            oraz obowiązujące przepisy prawa pracy i podatkowego.
            </div>
         <div class="grid-two" style="margin-bottom:16px;">
            <div style="font-size:9px;">
            Model Eliton Prime™ pozwala na:
            <ul>
              <li>redukcję pozapłacowych kosztów zatrudnienia,</li>
              <li>zachowanie lub zwiększenie wynagrodzeń netto pracowników,</li>
              <li>pełną zgodność z obowiązującymi przepisami,</li>
              <li>poprawę płynności finansowej przedsiębiorstwa.</li>
            </div>
            <div class="info-card">
              <div class="info-label">ROCZNE OPŁATY ZA WSZYSTKICH PRACOWNIKÓW PO ODJĘCIU PENSJI: </div>
              <div class="info-value" style="margin-bottom:8px;">${formatPLN(monthlyFeesAfterWages)}</div>
              <div class="info-label" style="margin-bottom:8px;">MIESIĘCZNE OPŁATY ZA WSZYSTKICH PRACOWNIKÓW PO ODJĘCIU PENSJI:</div>
              <div class="info-value">${formatPLN(monthlyFeesAfterWages)}</div>
            </div>
          </div>
          <div class="section-title">Wizualizacja opłat i wynagrodzeń po wdrożeniu Eliton Prime</div>
          <table class="compare-table">
            <thead>
              <tr>
                <th>Pozycja</th>
                <th>Tak aktualnie rozlicza się Państwa firma</th>
                <th>Eliton Prime Standard</th>
                <th>Rekomendujemy Eliton Prime Plus</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>SUMA wynagrodzeń NETTO</td>
                <td>${formatPLN(currentNetto)}</td>
                <td>${formatPLN(modelNetto)}</td>
                <td>${formatPLN(modelNetto)}</td>
              </tr>
              <tr>
                <td>SUMA wynagrodzeń BRUTTO</td>
                <td>${formatPLN(currentBrutto)}</td>
                <td>${formatPLN(modelBrutto)}</td>
                <td>${formatPLN(modelBrutto)}</td>
              </tr>
              <tr>
                <td>Składki pracodawcy</td>
                <td>${formatPLN(currentEmployerContrib)}</td>
                <td>${formatPLN(modelEmployerContrib)}</td>
                <td>${formatPLN(modelEmployerContrib)}</td>
              </tr>
              <tr>
                <td>Składki pracownika (społeczna + zdrowotna)</td>
                <td>${formatPLN(currentEmployeeContrib)}</td>
                <td>${formatPLN(modelEmployeeContrib)}</td>
                <td>${formatPLN(modelEmployeeContrib)}</td>
              </tr>
              <tr class="row-total">
                <td>SUMA AKTUALNEGO KOSZTU ZATRUDNIENIA (wynagrodzenie + składki + podatek)</td>
                <td>${formatPLN(currentTotalCost)}</td>
                <td>${formatPLN(modelBaseCost)}</td>
                <td>${formatPLN(modelBaseCost)}</td>
              </tr>
              <tr>
                <td>Wdrożenie Eliton Prime w Państwa firmie</td>
                <td>—</td>
                <td>0 zł</td>
                <td>0 zł</td>
              </tr>
              <tr>
                <td>Oszczędność miesięczna po wdrożeniu modelu</td>
                <td>—</td>
                <td>${formatPLN(statsStandard.oszczednoscMiesieczna)}</td>
                <td>${formatPLN(statsPlus.oszczednoscMiesieczna)}</td>
              </tr>
              <tr>
                <td>Oszczędność roczna przy comiesięcznej współpracy</td>
                <td>—</td>
                <td>${formatPLN(statsStandard.oszczednoscRoczna)}</td>
                <td>${formatPLN(statsPlus.oszczednoscRoczna)}</td>
              </tr>
              <tr>
                <td>+ 4% Podwyżki dla pracowników</td>
                <td>—</td>
                <td>${formatPLN(standardSplit.raise)}</td>
                <td>${formatPLN(plusSplit.raise)}</td>
              </tr>
              <tr>
                <td>+ 2% Bonus dla działu księgowo-kadrowego</td>
                <td>—</td>
                <td>${formatPLN(standardSplit.admin)}</td>
                <td>${formatPLN(plusSplit.admin)}</td>
              </tr>
              <tr>
                <td>Opłata success fee za obsługę modelu</td>
                <td>—</td>
                <td>${formatPLN(standardSplit.fee)}</td>
                <td>${formatPLN(plusSplit.fee)}</td>
              </tr>
              <tr class="row-total">
                <td>Całkowity koszt pracodawcy (wynagrodzenie + składki + podatek + Eliton Prime)</td>
                <td>${formatPLN(currentTotalCost)}</td>
                <td>${formatPLN(statsStandard.totalCostModel)}</td>
                <td>${formatPLN(statsPlus.totalCostModel)}</td>
              </tr>
            </tbody>
          </table>
          <div class="info-card" style="margin-top: 12px;">
            <div class="info-value small" style="margin-top: 0;">
              <strong>Oferta Eliton Prime i Eliton Prime PLUS</strong><br/>
              Patrząc na powyższe zestawienie widzimy możliwość wygenerowania dla Państwa firmy oszczędności na poziomie
              <strong>${formatPLN(statsStandard.oszczednoscMiesieczna)}</strong> miesięcznie. Przy wyborze Eliton Prime Plus
              gwarantujemy podwyżki dla wszystkich pracowników na poziomie <strong>${formatPLN(plusSplit.raise)}</strong> oraz
              za wsparcie działu administracji dodatkowy bonus w wysokości <strong>${formatPLN(plusSplit.admin)}</strong>.
              Biorąc pod uwagę aktualny model rozliczania, podejmując z nami współpracę oszczędzają Państwo kapitał na inwestycję,
              podnoszą wynagrodzenia pracowników oraz otrzymają Państwo fakturę kosztową.
            </div>
          </div>
          <div class="section-title" style="margin-top: 16px;">Ważna informacja prawna</div>
          <div class="legal-box">
            Powyższe wyliczenia oparte są na obowiązujących przepisach podatkowych. W kalkulacji uwzględniamy wymagane
            składki oraz podatki i zapewniamy pełną zgodność rozliczeń z aktualnym stanem prawnym.
          </div>
          ${footerHtml(2)}
        </div>
      </div>

      <div class="page">
        ${header('Scenariusze rocznych oszczędności', 3)}
      <div class="page-body page-pad">
          <div class="info-card" style="margin-bottom: 12px;">
            <div class="info-label">Oferta Eliton Prime</div>
            <div class="info-value small" style="margin-top: 6px;">
              Wysokość prezentowanych oszczędności oraz efektów finansowych wynikających z wdrożenia modelu Eliton Prime™ ma charakter orientacyjny i została obliczona na podstawie danych przekazanych przez Klienta, obowiązujących przepisów
              prawa oraz założeń przyjętych na dzień sporządzenia niniejszej ilustracji.<br/>
              W przypadku zmiany parametrów wejściowych, w szczególności: struktury zatrudnienia, rodzaju umów, wysokości
              wynagrodzeń, liczby pracowników objętych modelem, przepisów prawa pracy, podatkowego lub ubezpieczeniowego,
              wartości prezentowanych oszczędności mogą ulec zmianie.<br/>
              Całkowity koszt zatrudnienia po wdrożeniu modelu Eliton Prime™ nie będzie wyższy niż koszt zatrudnienia w&nbspaktualnym systemie, przy zachowaniu zgodności z&nbsp§2 ust. 1 pkt 26 Rozporządzenia MPiPS oraz obowiązkiem wykazania przychodu w PIT-11 z zaliczką 12% podatku dochodowego.<br/>
              Analiza została przygotowana przy założeniu: zachowania obecnych wynagrodzeń netto pracowników, pełnej zgodności
              wdrożenia z dokumentacją opracowaną przez Stratton Prime, standardowego profilu ryzyka podatkowego i&nbsp
              ubezpieczeniowego, wdrożenia modelu zgodnie z rekomendacjami doradczymi.
            </div>
          </div>
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
                <td>${formatPLN(statsStandard.oszczednoscRoczna)}</td>
              </tr>
              <tr>
                <td>Eliton Prime Plus</td>
                <td>${formatPLN(statsPlus.oszczednoscRoczna)}</td>
              </tr>
            </tbody>
          </table>
          <div class="section-title" style="margin-top: 16px;">Przewidywane wartości skumulowanych oszczędności rocznych</div>
          <div class="chart-wrap">
            <canvas id="yearByYearChart"></canvas>
          </div>
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
                  <td>${formatPLN(row.total)}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
          <div class="section-title" style="margin-top: 16px;">Ważna informacja prawna</div>
          <div class="legal-box">
            Oszczędności obliczone są w oparciu o aktualne stawki. Faktury za usługę podlegają standardowym zasadom
            księgowym i mogą być rozliczane zgodnie z przepisami podatkowymi.
          </div>
          ${footerHtml(3)}
        </div>
      </div>

      <div class="page">
        ${header('Symulacja miesiąc do miesiąca', 4)}
        <div class="page-body page-pad">
          <div class="muted" style="margin-bottom: 10px;">
            Opłaty na przestrzeni 10 lat
          </div>
          <div class="chart-wrap" style="margin-bottom: 12px;">
            <canvas id="tenYearComparisonMonth"></canvas>
          </div>
          <div class="muted" style="margin-bottom: 10px;">
            Symulacja miesiąc do miesiąca
          </div>
          <table class="fin-table">
            <thead>
              <tr>
                <th>Miesiąc</th>
                <th>Koszt bez modelu narastająco</th>
                <th>Koszt Eliton Prime narastająco</th>
                <th>Koszt Eliton Prime Plus narastająco</th>
                <th>Oszczędność Eliton Prime narastająco</th>
                <th>Oszczędność Eliton Prime Plus narastająco</th>
              </tr>
            </thead>
            <tbody>
              ${monthSimulation.map((row) => `
                <tr>
                  <td>${row.name}</td>
                  <td>${formatPLN(currentTotalCost * row.monthIndex)}</td>
                  <td>${formatPLN(statsStandard.totalCostModel * row.monthIndex)}</td>
                  <td>${formatPLN(statsPlus.totalCostModel * row.monthIndex)}</td>
                  <td>${formatPLN(statsStandard.oszczednoscMiesieczna * row.monthIndex)}</td>
                  <td>${formatPLN(statsPlus.oszczednoscMiesieczna * row.monthIndex)}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
          ${footerHtml(4)}
        </div>
      </div>

      <div class="page">
        ${header('Tabela listy płac (10 pracowników)', 5)}
        <div class="page-body page-pad">
          <div class="muted" style="margin-bottom: 10px;">
            Tabela listy płac dla 10 pracowników w modelu Eliton
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
                  <td>${formatPLN(row.standardBrutto)}</td>
                  <td>${formatPLN(row.standardNetto)}</td>
                  <td>${formatPLN(row.modelNetto)}</td>
                  <td>${formatPLN(row.oszczednosc)}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
          ${footerHtml(5)}
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
                    ? `Opłata za usługę: ${formatPLN(feeAmount)}; Podwyżki: ${formatPLN(raiseAmount)}; Administracja: ${formatPLN(adminAmount)}.`
                    : `Opłata za usługę: ${formatPLN(totalProvision)}.`}
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
          ${footerHtml(6)}
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
              <div class="info-value">${formatPLN(statsStandard.prowizja)} / mies. (prowizja)</div>
            </div>
            <div class="info-card">
              <div class="info-label">Cena Eliton Plus</div>
              <div class="info-value">${formatPLN(statsPlus.prowizja)} / mies. (prowizja)</div>
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
          ${footerHtml(7)}
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
          ${footerHtml(8)}
        </div>
      </div>
    </body>
    <script>
      const formatPln = (value) => new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(value);

      const resizeCanvas = (canvas) => {
        const rect = canvas.getBoundingClientRect();
        const ratio = window.devicePixelRatio || 1;
        canvas.width = rect.width * ratio;
        canvas.height = rect.height * ratio;
        const ctx = canvas.getContext('2d');
        ctx.scale(ratio, ratio);
        return ctx;
      };

      const drawBarChart = (canvas, labels, values, color) => {
        const ctx = resizeCanvas(canvas);
        const w = canvas.getBoundingClientRect().width;
        const h = canvas.getBoundingClientRect().height;
        const padding = 32;
        const maxVal = Math.max(...values, 1);
        const barWidth = (w - padding * 2) / values.length - 12;

        ctx.clearRect(0, 0, w, h);
        ctx.fillStyle = '#0B1020';
        ctx.font = '10px Inter, sans-serif';
        ctx.fillText('Oszczędność (PLN)', padding, 14);

        // grid
        ctx.strokeStyle = '#E2E8F0';
        ctx.lineWidth = 1;
        const gridSteps = options.gridSteps ?? 8;
        for (let i = 0; i <= gridSteps; i++) {
          const y = padding + ((h - padding * 2) / gridSteps) * i;
          ctx.beginPath();
          ctx.moveTo(padding, y);
          ctx.lineTo(w - padding, y);
          ctx.stroke();
          const value = Math.round(maxVal - (maxVal / gridSteps) * i);
          ctx.fillStyle = '#94a3b8';
          ctx.font = '7px Inter, sans-serif';
          ctx.fillText(formatPln(value), padding - 24, y + 3);
        }

        values.forEach((val, i) => {
          const x = padding + i * (barWidth + 12);
          const barHeight = (val / maxVal) * (h - padding * 2);
          const y = h - padding - barHeight;
          ctx.fillStyle = color;
          ctx.fillRect(x, y, barWidth, barHeight);
          ctx.fillStyle = '#64748B';
          ctx.font = '9px Inter, sans-serif';
          ctx.fillText(labels[i], x, h - padding + 12);
          ctx.font = '8px Inter, sans-serif';
          ctx.fillText(formatPln(val), x, y - 6);
        });
      };

      const drawGroupedBars = (canvas, labels, series, options = {}) => {
        const ctx = resizeCanvas(canvas);
        const w = canvas.getBoundingClientRect().width;
        const h = canvas.getBoundingClientRect().height;
        const padding = 32;
        const maxVal = Math.max(...series.flatMap((s) => s.values), 1);
        const groupWidth = (w - padding * 2) / labels.length;
        const barMaxWidth = options.barMaxWidth ?? 28;
        const barWidth = Math.min(barMaxWidth, (groupWidth - 16) / series.length);
        const highlightLabels = Array.isArray(options.highlightLabels) ? options.highlightLabels : [];
        const rotateLabels = options.rotateLabels === true;
        const labelFontSize = options.labelFontSize ?? 8;
        const labelOffset = options.labelOffset ?? 10;

        ctx.clearRect(0, 0, w, h);
        ctx.fillStyle = '#0B1020';
        ctx.font = '10px Inter, sans-serif';
        ctx.fillText('Oszczędność (PLN)', padding, 14);

        ctx.strokeStyle = '#E2E8F0';
        ctx.lineWidth = 1;
        const gridSteps = options.gridSteps ?? 8;
        for (let i = 0; i <= gridSteps; i++) {
          const y = padding + ((h - padding * 2) / gridSteps) * i;
          ctx.beginPath();
          ctx.moveTo(padding, y);
          ctx.lineTo(w - padding, y);
          ctx.stroke();
          const value = Math.round(maxVal - (maxVal / gridSteps) * i);
          ctx.fillStyle = '#94a3b8';
          ctx.font = '7px Inter, sans-serif';
          ctx.fillText(formatPln(value), padding - 24, y + 3);
        }

        labels.forEach((label, i) => {
          const groupStart = padding + i * groupWidth + 8;
          if (highlightLabels.includes(label)) {
            ctx.fillStyle = 'rgba(198, 161, 91, 0.08)';
            ctx.fillRect(groupStart - 6, padding, groupWidth - 4, h - padding * 2);
          }
          series.forEach((serie, idx) => {
            const val = serie.values[i];
            const barHeight = (val / maxVal) * (h - padding * 2);
            const x = groupStart + idx * (barWidth + 6);
            const y = h - padding - barHeight;
            ctx.fillStyle = serie.color;
            ctx.fillRect(x, y, barWidth, barHeight);
            ctx.fillStyle = highlightLabels.includes(label) ? '#C6A15B' : '#64748B';
            ctx.font = labelFontSize + 'px Inter, sans-serif';
            if (rotateLabels) {
              const labelX = groupStart + barWidth;
              const labelY = h - padding + labelOffset;
              ctx.save();
              ctx.translate(labelX, labelY);
              ctx.rotate(-Math.PI / 2);
              ctx.fillText(label, 0, 0);
              ctx.restore();
            } else {
              ctx.fillText(label, groupStart, h - padding + 12);
            }
          });
        });

        // Legend
        let legendX = padding;
        const legendY = 20;
        series.forEach((serie) => {
          ctx.fillStyle = serie.color;
          ctx.fillRect(legendX, legendY, 10, 10);
          ctx.fillStyle = '#475569';
          ctx.font = '8px Inter, sans-serif';
          ctx.fillText(serie.label, legendX + 14, legendY + 9);
          legendX += ctx.measureText(serie.label).width + 40;
        });
      };

      const drawCostComparison = (canvas, items) => {
        const ctx = resizeCanvas(canvas);
        const w = canvas.getBoundingClientRect().width;
        const h = canvas.getBoundingClientRect().height;
        const padding = 32;
        const maxVal = Math.max(...items.map((i) => i.value), 1);
        const barHeight = 24;
        const gap = 16;
        const chartTop = padding + 18;
        const labelFont = '9px Inter, sans-serif';
        const valueFont = '9px Inter, sans-serif';
        const gridSteps = 5;
        const labelColumnWidth = 160;
        const chartWidth = w - padding * 2 - labelColumnWidth;

        ctx.clearRect(0, 0, w, h);
        ctx.fillStyle = '#0B1020';
        ctx.font = '10px Inter, sans-serif';
        ctx.fillText('Koszty 10 lat (PLN)', padding, 14);

        // subtle grid for readability
        ctx.strokeStyle = '#E2E8F0';
        ctx.lineWidth = 1;
        for (let i = 0; i <= gridSteps; i++) {
          const x = padding + labelColumnWidth + (chartWidth / gridSteps) * i;
          ctx.beginPath();
          ctx.moveTo(x, chartTop - 10);
          ctx.lineTo(x, h - padding);
          ctx.stroke();
          const value = Math.round((maxVal / gridSteps) * i);
          ctx.fillStyle = '#94a3b8';
          ctx.font = '7px Inter, sans-serif';
          const label = formatPln(value);
          const labelWidth = ctx.measureText(label).width;
          ctx.fillText(label, x - labelWidth / 2, chartTop - 14);
        }

        items.forEach((item, idx) => {
          const y = chartTop + idx * (barHeight + gap);
          const barWidth = (chartWidth * item.value) / maxVal;
          // bar
          ctx.fillStyle = item.color;
          ctx.beginPath();
          ctx.roundRect(padding + labelColumnWidth, y, barWidth, barHeight, 6);
          ctx.fill();

          // label
          const labelY = y + barHeight - 7;
          ctx.fillStyle = '#334155';
          ctx.font = labelFont;
          ctx.fillText(item.label, padding, labelY);

          // value pill
          const valueText = formatPln(item.value);
          ctx.font = valueFont;
          const valueWidth = ctx.measureText(valueText).width;
          let valueX = padding + labelColumnWidth + barWidth + 8;
          if (valueX + valueWidth + 10 > w - 6) {
            valueX = Math.max(padding + labelColumnWidth + 6, padding + labelColumnWidth + barWidth - valueWidth - 12);
          }
          const pillY = y + 4;
          ctx.fillStyle = '#ffffff';
          ctx.beginPath();
          ctx.roundRect(valueX - 6, pillY, valueWidth + 12, barHeight - 8, 6);
          ctx.fill();
          ctx.fillStyle = '#0B1020';
          ctx.fillText(valueText, valueX, labelY);
        });
      };

      const monthlySavingsStandard = ${Math.round(statsStandard.oszczednoscMiesieczna)};
      const monthlySavingsPlus = ${Math.round(statsPlus.oszczednoscMiesieczna)};
      const annualSavingsStandard = monthlySavingsStandard * 12;
      const annualSavingsPlus = monthlySavingsPlus * 12;
      const tenYearSavingsStandard = annualSavingsStandard * 10;
      const tenYearSavingsPlus = annualSavingsPlus * 10;
      const yearByYearLabels = Array.from({ length: 25 }, (_, i) => String(i + 1));
      const yearByYearStandard = Array.from({ length: 25 }, (_, i) => (i + 1) * annualSavingsStandard);
      const yearByYearPlus = Array.from({ length: 25 }, (_, i) => (i + 1) * annualSavingsPlus);

      window.addEventListener('load', () => {
        const tenYearCurrentCost = ${Math.round(statsSelected.totalCostStandard)} * 12 * 10;
        const tenYearStandardCost = ${Math.round(statsStandard.totalCostModel)} * 12 * 10;
        const tenYearPlusCost = ${Math.round(statsPlus.totalCostModel)} * 12 * 10;

        const comparisonCanvas = document.getElementById('tenYearComparison');
        if (comparisonCanvas) drawCostComparison(comparisonCanvas, [
          { label: 'Koszty zatrudnienia', value: tenYearCurrentCost, color: '#EF4444' },
          { label: 'Suma opłat Eliton Prime', value: tenYearStandardCost, color: '#64748B' },
          { label: 'Suma opłat Eliton Prime Plus', value: tenYearPlusCost, color: '#16A34A' },
        ]);

        const comparisonMonthCanvas = document.getElementById('tenYearComparisonMonth');
        if (comparisonMonthCanvas) drawCostComparison(comparisonMonthCanvas, [
          { label: 'Koszty zatrudnienia', value: tenYearCurrentCost, color: '#EF4444' },
          { label: 'Suma opłat Eliton Prime', value: tenYearStandardCost, color: '#64748B' },
          { label: 'Suma opłat Eliton Prime Plus', value: tenYearPlusCost, color: '#16A34A' },
        ]);

        const yearByYearCanvas = document.getElementById('yearByYearChart');
        if (yearByYearCanvas) drawGroupedBars(yearByYearCanvas, yearByYearLabels, [
          { label: 'Standard', color: '#64748B', values: yearByYearStandard },
          { label: 'Plus', color: '#C6A15B', values: yearByYearPlus },
        ], {
          barMaxWidth: 10,
          highlightLabels: ['3', '5', '10', '20'],
          rotateLabels: true,
          labelFontSize: 7,
          labelOffset: 12,
        });
      });
    </script>
    </html>
  `;
};

export const offerPdfGenerator = {
  generateOfferPDF: (item: ZapisanaKalkulacja, meta?: OfferPdfMeta) => {
    const tempPracownicy = item.dane.pracownicy;
    const tempFirma = item.dane.firma;
    const tempConfig = item.dane.config;
    const tempProwizja = item.dane.prowizjaProc || 28;
    const standardRate = meta?.standardRate ?? 28;
    const plusRate = meta?.plusRate ?? 26;

    const details = tempPracownicy.map((p: any) => {
      const standard = obliczWariantStandard(p, tempFirma.stawkaWypadkowa, tempConfig);
      const podzial = obliczWariantPodzial(p, tempFirma.stawkaWypadkowa, p.nettoZasadnicza, tempConfig);
      return { standard, podzial };
    });

    const base = buildBaseTotals(details);
    const statsSelected = buildStats(base, tempProwizja);
    const statsStandard = buildStats(base, standardRate);
    const statsPlus = buildStats(base, plusRate);

    const htmlContent = buildOfferPdfHtml(item, meta);

    const printWindow = window.open('', '_blank');
    if (printWindow) {
      printWindow.document.open();
      printWindow.document.write(htmlContent);
      printWindow.document.close();
      printWindow.focus();
      setTimeout(() => { printWindow.print(); }, 500);
    }
  },
};

export const buildOfferPdfHtml = (item: ZapisanaKalkulacja, meta?: OfferPdfMeta) => {
  const tempPracownicy = item.dane.pracownicy;
  const tempFirma = item.dane.firma;
  const tempConfig = item.dane.config;
  const tempProwizja = item.dane.prowizjaProc || 28;
  const standardRate = meta?.standardRate ?? 28;
  const plusRate = meta?.plusRate ?? 26;

  const details = tempPracownicy.map((p: any) => {
    const standard = obliczWariantStandard(p, tempFirma.stawkaWypadkowa, tempConfig);
    const podzial = obliczWariantPodzial(p, tempFirma.stawkaWypadkowa, p.nettoZasadnicza, tempConfig);
    return { standard, podzial };
  });

  const base = buildBaseTotals(details);
  const statsSelected = buildStats(base, tempProwizja);
  const statsStandard = buildStats(base, standardRate);
  const statsPlus = buildStats(base, plusRate);

  return generateOfferHTML(
    tempFirma,
    base,
    statsSelected,
    statsStandard,
    statsPlus,
    tempProwizja,
    tempPracownicy.length,
    tempPracownicy,
    details,
    meta,
  );
};
