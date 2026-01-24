import { ZapisanaKalkulacja } from '../models/history';
import { formatPLN } from './formatters';
import { obliczWariantPodzial, obliczWariantStandard } from '../tax-engine';

const generateOfferHTML = (firma: any, stats: any, prowizjaProc: number, employeeCount: number) => {
  const { standard, stratton, oszczednoscRoczna, oszczednoscMiesieczna } = stats;
  const date = new Date().toLocaleDateString('pl-PL');

  const isPlus = prowizjaProc === 26;
  const totalProvision = stats.stratton.prowizja;

  let feeAmount = totalProvision;
  let raiseAmount = 0;
  let adminAmount = 0;

  if (isPlus) {
    feeAmount = totalProvision * (20 / 26);
    raiseAmount = totalProvision * (4 / 26);
    adminAmount = totalProvision * (2 / 26);
  }

  const totalCostModel = stats.stratton.kosztPracodawcy + totalProvision;

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

        .split-screen { display: grid; grid-template-columns: 35% 65%; height: 100%; }
        .grid-finance { display: grid; grid-template-columns: 320px 1fr; gap: 40px; padding: 40px 50px; height: 100%; }
        .grid-legal { display: grid; grid-template-columns: 1fr 1fr; gap: 60px; padding: 40px 60px; height: 100%; }

        h1, h2, h3 { font-family: 'Cinzel', serif; color: var(--navy); margin: 0; text-transform: uppercase; letter-spacing: 1px; }
        .text-gold { color: var(--gold); }
        .text-navy { color: var(--navy); }

        .cover-left {
          background: var(--navy);
          color: white;
          padding: 60px;
          display: flex;
          flex-direction: column;
          justify-content: space-between;
          position: relative;
          background-image: radial-gradient(circle at 0% 0%, #1e293b 0%, var(--navy) 60%);
        }
        .cover-right {
          background: white;
          padding: 80px;
          display: flex;
          flex-direction: column;
          justify-content: center;
        }

        .brand-logo { font-size: 28px; font-weight: 700; letter-spacing: 3px; color: var(--gold); border-bottom: 1px solid rgba(198, 161, 91, 0.3); padding-bottom: 20px; display: inline-block; }
        .report-type { font-size: 11px; text-transform: uppercase; letter-spacing: 4px; margin-top: 20px; opacity: 0.7; }

        .hero-title { font-size: 56px; font-weight: 800; color: var(--navy); line-height: 1.1; margin-bottom: 20px; font-family: 'Inter', sans-serif; letter-spacing: -2px; }
        .hero-subtitle { font-size: 22px; color: #64748b; font-weight: 300; }

        .savings-box { margin-top: 60px; padding: 40px; border-left: 6px solid var(--gold); background: #fffbeb; }
        .savings-label { font-size: 13px; text-transform: uppercase; font-weight: 700; color: #92400e; letter-spacing: 1px; }
        .savings-value { font-size: 52px; font-weight: 800; color: var(--navy); margin-top: 5px; letter-spacing: -1px; }

        .page-header { position: absolute; top: 0; left: 0; right: 0; padding: 25px 50px; display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid #e2e8f0; bg: white; z-index: 10; }
        .ph-title { font-size: 20px; font-weight: 700; color: var(--navy); font-family: 'Cinzel', serif; }
        .ph-meta { font-size: 10px; color: #94a3b8; text-align: right; font-family: monospace; }

        .page-body { margin-top: 80px; height: calc(100% - 80px); }

        .kpi-card { background: #f8fafc; padding: 25px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #e2e8f0; position: relative; overflow: hidden; }
        .kpi-label { font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.5px; }
        .kpi-val { font-size: 26px; font-weight: 800; color: var(--navy); margin-top: 5px; }
        .kpi-sub { font-size: 10px; color: #94a3b8; margin-top: 2px; }

        .kpi-card.highlight { background: var(--navy); color: white; border: none; box-shadow: 0 10px 30px -10px rgba(11, 16, 32, 0.5); }
        .kpi-card.highlight .kpi-label { color: rgba(255,255,255,0.6); }
        .kpi-card.highlight .kpi-val { color: var(--gold); }
        .kpi-card.highlight .kpi-sub { color: rgba(255,255,255,0.4); }

        .fin-table { width: 100%; border-collapse: collapse; font-size: 10pt; }
        .fin-table th { text-align: left; padding: 14px 16px; border-bottom: 2px solid var(--navy); color: var(--navy); font-weight: 700; text-transform: uppercase; font-size: 9pt; background: #f1f5f9; }
        .fin-table td { padding: 14px 16px; border-bottom: 1px solid #e2e8f0; color: #334155; }
        .col-highlight { background: rgba(198, 161, 91, 0.08); font-weight: 600; color: var(--navy); }

        .row-total td { font-weight: 800; border-top: 2px solid var(--navy); background: #f8fafc; font-size: 11pt; color: var(--navy); }

        .badge-success { background: #ecfdf5; color: #047857; padding: 3px 8px; border-radius: 4px; font-size: 9pt; font-weight: 700; display: inline-block; }
        .badge-blue { background: #eff6fc; color: #1e3a8a; padding: 3px 8px; border-radius: 4px; font-size: 9pt; font-weight: 700; display: inline-block; }

        .legal-head { font-size: 14pt; font-weight: 700; color: var(--navy); margin-bottom: 25px; border-bottom: 3px solid var(--gold); padding-bottom: 10px; display: inline-block; text-transform: uppercase; letter-spacing: 1.5px; font-family: 'Cinzel', serif; }

        .legal-citation-box { background: #fff; border-left: 5px solid var(--navy); padding: 25px; margin-bottom: 30px; position: relative; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border-radius: 0 8px 8px 0; }
        .legal-citation-header { font-family: 'Cinzel', serif; font-weight: 700; color: var(--navy); font-size: 12pt; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; }
        .legal-citation-text { font-family: 'Inter', sans-serif; font-style: italic; color: #475569; font-size: 10pt; line-height: 1.6; }

        .risk-item { margin-bottom: 35px; display: flex; gap: 25px; align-items: flex-start; }
        .risk-num { font-family: 'Cinzel', serif; font-weight: 700; font-size: 32pt; color: var(--gold); line-height: 0.8; min-width: 50px; text-shadow: 2px 2px 0px rgba(0,0,0,0.05); }
        .risk-content strong { display: block; color: var(--navy); font-size: 12pt; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        .risk-content p { font-size: 10pt; color: #64748b; margin: 0; line-height: 1.5; text-align: justify; }

        .timeline-container { margin-top: 60px; position: relative; padding: 20px 0; }
        .timeline-line { position: absolute; top: 32px; left: 60px; right: 60px; height: 3px; background: #e2e8f0; z-index: 0; }
        .timeline-steps { display: flex; justify-content: space-between; position: relative; z-index: 1; }
        .tl-step { text-align: center; flex: 1; padding: 0 15px; }
        .tl-dot-wrapper { display: flex; justify-content: center; margin-bottom: 20px; }
        .tl-dot { width: 20px; height: 20px; background: var(--navy); border: 4px solid white; border-radius: 50%; box-shadow: 0 0 0 3px var(--navy); transition: all 0.3s; }
        .tl-week { font-size: 10pt; color: var(--gold); font-weight: 700; text-transform: uppercase; margin-bottom: 8px; display: block; letter-spacing: 2px; }
        .tl-title { font-size: 12pt; font-weight: 700; color: var(--navy); margin-bottom: 8px; font-family: 'Cinzel', serif; }
        .tl-desc { font-size: 9pt; color: #64748b; line-height: 1.5; max-width: 200px; margin: 0 auto; }

        .chart-container { margin-top: auto; padding: 20px; background: white; border-radius: 8px; border: 1px solid #e2e8f0; }
        .chart-bar { height: 24px; background: #e2e8f0; border-radius: 4px; overflow: hidden; display: flex; margin-top: 10px; }
        .chart-seg { height: 100%; display: flex; align-items: center; justify-content: center; font-size: 8pt; font-weight: 700; color: white; transition: width 0.5s; }

        .footer { position: absolute; bottom: 20px; left: 50px; right: 50px; border-top: 1px solid #e2e8f0; padding-top: 10px; display: flex; justify-content: space-between; font-size: 8pt; color: #94a3b8; text-transform: uppercase; }
      </style>
    </head>
    <body>

      <div class="page">
        <div class="split-screen">
            <div class="cover-left">
                <div>
                    <div class="brand-logo">STRATTON PRIME</div>
                    <div class="report-type">Kalkulacja Optymalizacji Wynagrodzeń</div>
                </div>

                <div style="margin-bottom: 40px;">
                    <div style="font-size: 12px; opacity: 0.6; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 10px;">Analiza dla firmy:</div>
                    <div style="font-size: 28px; font-weight: 700; line-height: 1.2;">${firma.nazwa}</div>
                    <div style="font-size: 14px; opacity: 0.8; font-family: monospace; margin-top: 8px;">NIP: ${firma.nip}</div>
                </div>

                <div style="font-size: 10px; opacity: 0.5;">
                    &copy; ${new Date().getFullYear()} Stratton Prime Sp. z o.o.<br>
                    Dokument poufny. Przeznaczony wyłącznie dla Zarządu.
                </div>
            </div>

            <div class="cover-right">
                <div style="text-transform: uppercase; color: var(--gold); font-weight: 700; letter-spacing: 2px; font-size: 13px; margin-bottom: 15px;">Memorandum Wdrożeniowe</div>
                <div class="hero-title">Model <span class="text-gold">Eliton Prime™</span></div>
                <div class="hero-subtitle">
                    ${isPlus ? 'Strategia "Win-Win": Oszczędność i Podwyżki' : 'Strategia "Standard": Maksymalizacja Oszczędności'}
                </div>

                <div class="savings-box">
                    <div class="savings-label">Potencjał Oszczędności (Rocznie)</div>
                    <div class="savings-value">${formatPLN(oszczednoscRoczna)}</div>
                    <div style="font-size: 14px; color: #92400e; margin-top: 8px; font-weight: 500;">
                        przy zachowaniu pełnego bezpieczeństwa prawnego
                    </div>
                </div>

                <div style="margin-top: auto; border-top: 1px solid #e2e8f0; padding-top: 20px; display: flex; justify-content: space-between; align-items: flex-end;">
                    <div>
                        <div style="font-size: 11px; text-transform: uppercase; color: #94a3b8; margin-bottom: 5px;">Data sporządzenia:</div>
                        <div style="font-size: 14px; font-weight: 600; color: var(--navy);">${date}</div>
                    </div>
                    <div>
                        <div style="font-size: 11px; text-transform: uppercase; color: #94a3b8; margin-bottom: 5px; text-align: right;">Wersja raportu:</div>
                        <div style="font-size: 14px; font-weight: 600; color: var(--navy); text-align: right;">v2.6.1 (2026 Tax Rules)</div>
                    </div>
                </div>
            </div>
        </div>
      </div>

      <div class="page">
        <div class="page-header">
            <div class="ph-title">Analiza Finansowa <span style="font-weight: 300; color: #94a3b8;">| Symulacja Miesięczna</span></div>
            <div class="ph-meta">Strona 2/4 &bull; Nr Ref: SP/${new Date().getFullYear()}/${firma.nip.substring(0,4)}</div>
        </div>

        <div class="page-body grid-finance">
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <div class="kpi-card">
                    <div class="kpi-label">Koszt Pracodawcy (Standard)</div>
                    <div class="kpi-val text-slate-500 line-through decoration-slate-300 decoration-2 text-[20px]">${formatPLN(standard.kosztPracodawcy)}</div>
                    <div class="kpi-sub">Wariant tradycyjny</div>
                </div>
                <div class="kpi-card highlight">
                    <div class="kpi-label">Koszt Pracodawcy (Eliton Prime)</div>
                    <div class="kpi-val">${formatPLN(stratton.kosztPracodawcy)}</div>
                    <div class="kpi-sub">Nowy model kosztowy</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Oszczędność Miesięczna</div>
                    <div class="kpi-val">${formatPLN(oszczednoscMiesieczna)}</div>
                    <div class="kpi-sub">Netto po prowizji</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Oszczędność Roczna</div>
                    <div class="kpi-val">${formatPLN(oszczednoscRoczna)}</div>
                    <div class="kpi-sub">Prognoza 12 miesięcy</div>
                </div>
            </div>
            <div>
                <table class="fin-table">
                    <thead>
                        <tr>
                            <th>Kategoria</th>
                            <th>Standard</th>
                            <th class="col-highlight">Eliton Prime</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Wynagrodzenie brutto</td>
                            <td>${formatPLN(standard.brutto)}</td>
                            <td class="col-highlight">${formatPLN(stratton.brutto)}</td>
                        </tr>
                        <tr>
                            <td>Wynagrodzenie netto</td>
                            <td>${formatPLN(standard.netto)}</td>
                            <td class="col-highlight">${formatPLN(stratton.netto)}</td>
                        </tr>
                        <tr>
                            <td>ZUS pracodawcy</td>
                            <td>${formatPLN(standard.zusPracodawca)}</td>
                            <td class="col-highlight">${formatPLN(stratton.zusPracodawca)}</td>
                        </tr>
                        <tr>
                            <td>ZUS pracownika</td>
                            <td>${formatPLN(standard.zusPracownik)}</td>
                            <td class="col-highlight">${formatPLN(stratton.zusPracownik)}</td>
                        </tr>
                        <tr>
                            <td>PIT pracownika</td>
                            <td>${formatPLN(standard.pit)}</td>
                            <td class="col-highlight">${formatPLN(stratton.pit)}</td>
                        </tr>
                        <tr>
                            <td>Prowizja Stratton</td>
                            <td>-</td>
                            <td class="col-highlight">${formatPLN(stratton.prowizja)}</td>
                        </tr>
                        <tr>
                            <td><strong>Nowy koszt całkowity</strong></td>
                            <td>-</td>
                            <td class="col-highlight"><strong>${formatPLN(totalCostModel)}</strong></td>
                        </tr>
                        <tr class="row-total">
                            <td>Oszczędność netto</td>
                            <td colspan="2">${formatPLN(oszczednoscMiesieczna)} / mies.</td>
                        </tr>
                    </tbody>
                </table>

                <div style="margin-top: 18px; display: flex; gap: 12px;">
                    <span class="badge-success">${employeeCount} pracowników objętych programem</span>
                    <span class="badge-blue">Model ${isPlus ? 'WIN-WIN' : 'STANDARD'}</span>
                </div>
            </div>
        </div>
      </div>

      <div class="page">
        <div class="page-header">
            <div class="ph-title">Struktura Prowizji i Benefity</div>
            <div class="ph-meta">Strona 3/4 &bull; Eliton Prime™</div>
        </div>
        <div class="page-body grid-legal">
            <div>
                <div class="legal-head">Podział prowizji</div>
                <div class="legal-citation-box">
                    <div class="legal-citation-header">Struktura prowizyjna</div>
                    <div class="legal-citation-text">
                        ${isPlus
                          ? `W modelu Win-Win prowizja jest dzielona na: opłata za usługę (${formatPLN(feeAmount)}),
                             środki na podwyżki (${formatPLN(raiseAmount)}), administracja (${formatPLN(adminAmount)}).`
                          : `W modelu Standard prowizja stanowi całość opłaty za usługę: ${formatPLN(totalProvision)}.`}
                    </div>
                </div>

                <div class="legal-citation-box">
                    <div class="legal-citation-header">Podstawy prawne</div>
                    <div class="legal-citation-text">
                        Rozwiązanie bazuje na obowiązujących przepisach prawa podatkowego oraz
                        interpretacjach organów podatkowych i ZUS.
                    </div>
                </div>
            </div>
            <div>
                <div class="legal-head">Ryzyka minimalizowane</div>
                <div class="risk-item">
                    <div class="risk-num">01</div>
                    <div class="risk-content">
                        <strong>Pełna zgodność prawna</strong>
                        <p>Proces zgodny z aktualnymi przepisami prawa pracy i podatkowego.</p>
                    </div>
                </div>
                <div class="risk-item">
                    <div class="risk-num">02</div>
                    <div class="risk-content">
                        <strong>Transparentność rozliczeń</strong>
                        <p>Każdy pracownik otrzymuje jasne zestawienie świadczeń i wynagrodzeń.</p>
                    </div>
                </div>
                <div class="risk-item">
                    <div class="risk-num">03</div>
                    <div class="risk-content">
                        <strong>Brak ryzyka ZUS</strong>
                        <p>Model oparty o świadczenia niestanowiące podstawy składek.</p>
                    </div>
                </div>
            </div>
        </div>
      </div>

      <div class="page">
        <div class="page-header">
            <div class="ph-title">Plan Wdrożenia</div>
            <div class="ph-meta">Strona 4/4 &bull; Harmonogram</div>
        </div>
        <div class="page-body" style="padding: 40px 60px;">
            <div class="legal-head">Etapy wdrożenia</div>
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

export const offerPdfGenerator = {
  generateOfferPDF: (item: ZapisanaKalkulacja) => {
    const tempPracownicy = item.dane.pracownicy;
    const tempFirma = item.dane.firma;
    const tempConfig = item.dane.config;
    const tempProwizja = item.dane.prowizjaProc || 28;

    const details = tempPracownicy.map((p: any) => {
      const standard = obliczWariantStandard(p, tempFirma.stawkaWypadkowa, tempConfig);
      const podzial = obliczWariantPodzial(p, tempFirma.stawkaWypadkowa, p.nettoZasadnicza, tempConfig);
      return { standard, podzial };
    });

    const sumaKosztStandard = details.reduce((acc: number, w: any) => acc + w.standard.kosztPracodawcy, 0);
    const sumaKosztPodzial = details.reduce((acc: number, w: any) => acc + w.podzial.kosztPracodawcy, 0);
    const sumaBruttoSwiadczen = details.reduce((acc: number, w: any) => acc + w.podzial.swiadczenie.brutto, 0);
    const oszczednoscBrutto = sumaKosztStandard - sumaKosztPodzial;
    const prowizja = sumaBruttoSwiadczen * (tempProwizja / 100);
    const oszczednoscNetto = oszczednoscBrutto - prowizja;

    const stats = {
      standard: {
        kosztPracodawcy: sumaKosztStandard,
        zusPracodawca: details.reduce((acc: number, w: any) => acc + w.standard.zusPracodawca.suma, 0),
        brutto: details.reduce((acc: number, w: any) => acc + w.standard.brutto, 0),
        netto: details.reduce((acc: number, w: any) => acc + w.standard.netto, 0),
        zusPracownik: details.reduce((acc: number, w: any) => acc + w.standard.zusPracownik.suma + w.standard.zdrowotna, 0),
        pit: details.reduce((acc: number, w: any) => acc + w.standard.pit, 0),
      },
      stratton: {
        kosztPracodawcy: sumaKosztPodzial,
        zusPracodawca: details.reduce((acc: number, w: any) => acc + w.podzial.zasadnicza.zusPracodawca.suma, 0),
        brutto: details.reduce((acc: number, w: any) => acc + w.podzial.pit.lacznyPrzychod, 0),
        netto: details.reduce((acc: number, w: any) => acc + w.podzial.doWyplaty, 0),
        zusPracownik: details.reduce((acc: number, w: any) => acc + w.podzial.zasadnicza.zusPracownik.suma + w.podzial.zasadnicza.zdrowotna, 0),
        pit: details.reduce((acc: number, w: any) => acc + w.podzial.pit.kwota, 0),
        prowizja: prowizja,
      },
      oszczednoscRoczna: oszczednoscNetto * 12,
      oszczednoscMiesieczna: oszczednoscNetto,
    };

    const htmlContent = generateOfferHTML(item.dane.firma, stats, tempProwizja, tempPracownicy.length);
    const printWindow = window.open('', '_blank');
    if (printWindow) {
      printWindow.document.write(htmlContent);
      printWindow.document.close();
      printWindow.focus();
      setTimeout(() => { printWindow.print(); }, 500);
    }
  },
};
