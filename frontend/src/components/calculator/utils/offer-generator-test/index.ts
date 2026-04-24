import { ZapisanaKalkulacja } from '../../models/history';
import { Pracownik } from '../../models/employee';
import { obliczWariantPodzial, obliczWariantStandard } from '../../tax-engine';
import { OfferPdfMeta, OfferStats, BaseTotals, RenderContext } from './types';
import { offerStyles } from './styles';
import { formatDate, safeText } from './common';

// Templates
import { CoverPage } from './templates/CoverPage';
import { ExecutiveSummary } from './templates/ExecutiveSummary';
import { TableOfContents } from './templates/TableOfContents';
import { FinancialIllustration } from './templates/FinancialIllustration';
import { FeesAndSalaries } from './templates/FeesAndSalaries';
import { AnnualScenarios } from './templates/AnnualScenarios';
import { MonthSimulation } from './templates/MonthSimulation';
import { PayrollTable } from './templates/PayrollTable';
import { LegalAndBenefits } from './templates/LegalAndBenefits';
import { ImplementationSchedule } from './templates/ImplementationSchedule';
import { ContactPage } from './templates/ContactPage';

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

const buildBaseTotals = (details: any[]): BaseTotals => {
  return {
    sumaKosztStandard: details.reduce((acc: number, w: any) => acc + w.standard.kosztPracodawcy, 0),
    sumaKosztPodzial: details.reduce((acc: number, w: any) => acc + w.podzial.kosztPracodawcy, 0),
    sumaBruttoSwiadczen: details.reduce((acc: number, w: any) => acc + w.podzial.swiadczenie.brutto, 0),
    sumaNettoSwiadczen: details.reduce((acc: number, w: any) => acc + w.podzial.swiadczenie.netto, 0),
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
  const prowizja = base.sumaNettoSwiadczen * (prowizjaRate / 100);
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

const buildMonthSimulation = (monthlySavings: number) => {
  const monthNames = [
    'Styczeń', 'Luty', 'Marzec', 'Kwiecień', 'Maj', 'Czerwiec',
    'Lipiec', 'Sierpień', 'Wrzesień', 'Październik', 'Listopad', 'Grudzień',
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


const generateFullHtml = (ctx: RenderContext) => {
  const { meta, date, firma, advisor } = ctx;
  const documentLayout = meta?.documentLayout || 'vertical';
  const isPortrait = documentLayout === 'vertical';
  const pageWidth = isPortrait ? '210mm' : '297mm';
  const pageHeight = isPortrait ? '297mm' : '210mm';
  const pageSize = isPortrait ? 'A4 portrait' : 'A4 landscape';

  // Include scripts from original file (charts mainly)
  // I need to copy the script content from original file.
  // Since it's long, I'll allow myself to summarize or copy it fully.
  // Ideally, I should put this script in a separate file and read it or just include it here.
  // For now I'll include the script content I read earlier.

  return `
    <!DOCTYPE html>
    <html lang="pl">
    <head>
      <meta charset="UTF-8">
      <title>Oferta Optymalizacji - ${firma.nazwa}</title>
      <link rel="preconnect" href="https://fonts.googleapis.com">
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
      <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=DM+Sans:ital,wght@0,400;0,500;0,700;1,400&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
      <style>
        @page { size: ${pageSize}; margin: 0; }
        .page { width: ${pageWidth}; height: ${pageHeight}; }
        @media print { .page { width: ${pageWidth}; height: ${pageHeight}; } }
        ${offerStyles}
      </style>
    </head>
    <body class="${documentLayout}">
      ${CoverPage(ctx)}
      ${ExecutiveSummary(ctx)}
      ${FinancialIllustration(ctx)}
      ${MonthSimulation(ctx)}
      ${PayrollTable(ctx)}
      ${LegalAndBenefits(ctx)}
      ${ImplementationSchedule(ctx)}
      ${ContactPage(ctx)}
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
        // ... (standard bar chart implementation if needed, but only grouped bars are used in original for yearByYear)
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

      const monthlySavingsStandard = ${Math.round(ctx.statsStandard.oszczednoscMiesieczna)};
      const monthlySavingsPlus = ${Math.round(ctx.statsPlus.oszczednoscMiesieczna)};
      const annualSavingsStandard = monthlySavingsStandard * 12;
      const annualSavingsPlus = monthlySavingsPlus * 12;
      const tenYearSavingsStandard = annualSavingsStandard * 10;
      const tenYearSavingsPlus = annualSavingsPlus * 10;
      const yearByYearLabels = Array.from({ length: 25 }, (_, i) => String(i + 1));
      const yearByYearStandard = Array.from({ length: 25 }, (_, i) => (i + 1) * annualSavingsStandard);
      const yearByYearPlus = Array.from({ length: 25 }, (_, i) => (i + 1) * annualSavingsPlus);

      window.addEventListener('load', () => {
        const tenYearCurrentCost = ${Math.round(ctx.statsSelected.totalCostStandard)} * 12 * 10;
        const tenYearStandardCost = ${Math.round(ctx.statsStandard.totalCostModel)} * 12 * 10;
        const tenYearPlusCost = ${Math.round(ctx.statsPlus.totalCostModel)} * 12 * 10;

        const comparisonCanvas = document.getElementById('tenYearComparison');
        if (comparisonCanvas) drawCostComparison(comparisonCanvas, [
          { label: 'Koszty zatrudnienia', value: tenYearCurrentCost, color: '#EF4444' },
          { label: 'Suma opłat Eliton Prime™', value: tenYearStandardCost, color: '#64748B' },
          { label: 'Suma opłat Eliton Prime Plus™', value: tenYearPlusCost, color: '#16A34A' },
        ]);

        const comparisonMonthCanvas = document.getElementById('tenYearComparisonMonth');
        if (comparisonMonthCanvas) drawCostComparison(comparisonMonthCanvas, [
          { label: 'Koszty zatrudnienia', value: tenYearCurrentCost, color: '#EF4444' },
          { label: 'Suma opłat Eliton Prime™', value: tenYearStandardCost, color: '#64748B' },
          { label: 'Suma opłat Eliton Prime Plus™', value: tenYearPlusCost, color: '#16A34A' },
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
}

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

  const employeeCount = tempPracownicy.length;
  const isPlus = tempProwizja === 26;
  const monthlyFeesAfterWages = Math.max(0, base.standard.kosztPracodawcy - base.standard.brutto);
  const annualFeesAfterWages = monthlyFeesAfterWages * 12;
  const avgPerEmployee = employeeCount > 0 ? statsSelected.oszczednoscMiesieczna / employeeCount : 0;
  const threeYearEffect = statsSelected.oszczednoscRoczna * 3;

  const validUntil = safeText(meta?.validUntil ? formatDate(meta.validUntil) : undefined);
  const offerNumber = safeText(meta?.offerNumber);
  const date = new Date().toLocaleDateString('pl-PL');

  const contracts = buildContractTotals(tempPracownicy, details);
  const monthSimulation = buildMonthSimulation(statsSelected.oszczednoscMiesieczna);
  const annualScenarios = buildAnnualScenarios(statsSelected.oszczednoscRoczna);
  const payrollRows = buildPayrollRows(tempPracownicy, details);

  const advisor = {
    name: safeText(meta?.advisorName),
    phone: safeText(meta?.advisorPhone),
    email: safeText(meta?.advisorEmail),
  };

  const decisionMaker = (tempFirma?.kontakty || []).find((contact: any) =>
    contact?.is_decision_maker || contact?.isDecisionMaker
  );
  
  const preparedFor = decisionMaker?.name || tempFirma.osobaKontaktowa;
  const firmaWithPreparedFor = { ...tempFirma, preparedFor };

  const ctx: RenderContext = {
    meta,
    firma: firmaWithPreparedFor,
    advisor,
    date,
    statsSelected,
    statsStandard,
    statsPlus,
    base,
    employeeCount,
    isPlus,
    prowizjaProc: tempProwizja,
    contracts,
    monthlyFeesAfterWages,
    annualFeesAfterWages,
    threeYearEffect,
    avgPerEmployee,
    monthSimulation,
    annualScenarios,
    payrollRows,
    validUntil,
    offerNumber
  };

  return generateFullHtml(ctx);
};

export const offerPdfGenerator = {
  generateOfferPDF: (item: ZapisanaKalkulacja, meta?: OfferPdfMeta) => {
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
