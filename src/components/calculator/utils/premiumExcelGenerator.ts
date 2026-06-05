
import type ExcelJS from 'exceljs'; // tylko typy (erased) — runtime ładowany dynamicznie niżej
import { Firma } from '../models/company';
import { GlobalneWyniki } from '../models/calculation';

interface GeneratorOptions {
    firma: Firma;
    wyniki: GlobalneWyniki;
    prowizjaProc: number;
}

// Helper do zaokrąglania
const round = (val: number) => Math.round(val * 100) / 100;

export const generatePremiumExcel = async ({ firma, wyniki, prowizjaProc }: GeneratorOptions) => {
    const ExcelJSRuntime = (await import('exceljs')).default;

    const workbook = new ExcelJSRuntime.Workbook();

    // --- PRZYGOTOWANIE DANYCH ---
    const totalStandardCost = wyniki.szczegoly.reduce((acc, w) => {
        return acc + round(w.standard.kosztPracodawcy);
    }, 0);

    // --- DEFINICJA STYLI ---
    const styles = {
        navyFill: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF0F172A' } } as ExcelJS.Fill,
        navyFont: { color: { argb: 'FFFFFFFF' }, bold: true, size: 10 } as Partial<ExcelJS.Font>,
        headerFont: { bold: true, size: 10, color: { argb: 'FF334155' } } as Partial<ExcelJS.Font>,
        headerFill: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF1F5F9' } } as ExcelJS.Fill,
        currency: '#,##0.00 "zł"',
        // Kolory specjalne
        greenFont: { color: { argb: 'FF059669' }, bold: true },
        greenBg: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFECFDF5' } } as ExcelJS.Fill,
        blueFont: { color: { argb: 'FF1E40AF' }, italic: true },
        blueBg: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFEFF6FF' } } as ExcelJS.Fill, 
        orangeFont: { color: { argb: 'FFD97706' }, italic: true },
        orangeBg: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFFFBEB' } } as ExcelJS.Fill,
        yellowInput: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFFF000' } } as ExcelJS.Fill,
        
        centerAlign: { vertical: 'middle', horizontal: 'center', wrapText: true } as Partial<ExcelJS.Alignment>
    };

    // ==========================================================================================
    // ARKUSZ 1: PODSUMOWANIE (DASHBOARD)
    // ==========================================================================================
    const wsDash = workbook.addWorksheet('Podsumowanie', {
        views: [{ showGridLines: false }]
    });

    // --- NAGŁÓWEK ---
    // B2:F2 merged
    wsDash.mergeCells('B2:F2');
    const titleCell = wsDash.getCell('B2');
    titleCell.value = `Ilustracja finansowa oszczędności po wdrożeniu modelu Eliton Prime PLUS dla firmy : ${firma.nazwa || 'FIRMA'}`;
    titleCell.font = { size: 16, bold: true, color: { argb: 'FF0F172A' } };
    
    // B3 merged or simple cell
    const dateCell = wsDash.getCell('B3');
    dateCell.value = `Data symulacji: ${new Date().toLocaleDateString()}`;
    dateCell.font = { color: { argb: 'FF64748B' }, size: 10 };

    // --- KPI DASHBOARD (Row 5 - Titles, Row 6 - Values) ---
    const kpiRowTitle = 5;
    const kpiRowValue = 6;
    wsDash.getRow(kpiRowTitle).height = 45; // Wrapped text requires height
    wsDash.getRow(kpiRowValue).height = 40;

    // B5: Aktualny koszt miesięczny
    wsDash.getCell(`B${kpiRowTitle}`).value = "Aktualny koszt miesięczny";
    wsDash.getCell(`B${kpiRowTitle}`).alignment = styles.centerAlign;
    wsDash.getCell(`B${kpiRowTitle}`).font = { color: { argb: 'FF64748B' }, size: 9 };

    wsDash.getCell(`B${kpiRowValue}`).value = totalStandardCost;
    wsDash.getCell(`B${kpiRowValue}`).numFmt = styles.currency;
    wsDash.getCell(`B${kpiRowValue}`).alignment = styles.centerAlign;
    wsDash.getCell(`B${kpiRowValue}`).font = { size: 14, color: { argb: 'FF64748B' }, bold: true };

    // C5: Koszt po wdrożeniu ...
    wsDash.getCell(`C${kpiRowTitle}`).value = "Koszt po wdrożeniu modelu\nEliton Prime PLUS (msc)";
    wsDash.getCell(`C${kpiRowTitle}`).alignment = styles.centerAlign;
    wsDash.getCell(`C${kpiRowTitle}`).font = { color: { argb: 'FF64748B' }, size: 9 };

    // C6: Value linked to total from table below
    // Will be filled later with formula pointing to table total

    // D5: Miesięczna oszczędność ...
    wsDash.getCell(`D${kpiRowTitle}`).value = "Miesięczna oszczędność po\npodwyżkach";
    wsDash.getCell(`D${kpiRowTitle}`).alignment = styles.centerAlign;
    wsDash.getCell(`D${kpiRowTitle}`).font = { color: { argb: 'FF64748B' }, size: 9 };

    // D6: Formula B6 - C6
    wsDash.getCell(`D${kpiRowValue}`).value = { formula: `B${kpiRowValue}-C${kpiRowValue}` };
    wsDash.getCell(`D${kpiRowValue}`).numFmt = styles.currency;
    wsDash.getCell(`D${kpiRowValue}`).alignment = styles.centerAlign;
    wsDash.getCell(`D${kpiRowValue}`).font = { size: 14, color: { argb: 'FF059669' }, bold: true }; // Green Text
    wsDash.getCell(`D${kpiRowValue}`).fill = styles.greenBg;

    // E5: Roczna Oszczędność
    wsDash.getCell(`E${kpiRowTitle}`).value = "Roczna Oszczędność";
    wsDash.getCell(`E${kpiRowTitle}`).alignment = styles.centerAlign;
    wsDash.getCell(`E${kpiRowTitle}`).font = { color: { argb: 'FF64748B' }, size: 9 };

    // E6: Formula D6 * 12
    wsDash.getCell(`E${kpiRowValue}`).value = { formula: `D${kpiRowValue}*12` };
    wsDash.getCell(`E${kpiRowValue}`).numFmt = styles.currency;
    wsDash.getCell(`E${kpiRowValue}`).alignment = styles.centerAlign;
    wsDash.getCell(`E${kpiRowValue}`).font = { size: 14, color: { argb: 'FF059669' }, bold: true };


    // --- TABELA PODSUMOWANIA (Row 9) ---
    const tableHeaderRow = 9;
    const headers = ['Kategoria', 'Aktualny system\nwynagradzania', 'Model Eliton Prime PLUS', 'Różnica'];
    
    // Set headers B9:E9
    ['B', 'C', 'D', 'E'].forEach((col, idx) => {
        const cell = wsDash.getCell(`${col}${tableHeaderRow}`);
        cell.value = headers[idx];
        cell.fill = styles.headerFill;
        cell.font = styles.headerFont;
        cell.alignment = styles.centerAlign;
        cell.border = { bottom: { style: 'thick', color: { argb: 'FF334155' } } };
    });

    // Data for Summary Table
    // Calculate totals for "Aktualny System" (Standard)
    const sumStandardBrutto = wyniki.szczegoly.reduce((acc, w) => acc + round(w.standard.brutto), 0);
    const sumStandardZus = wyniki.szczegoly.reduce((acc, w) => acc + round(w.standard.zusPracodawca.suma), 0);

    // Let's iterate rows to build table
    let currentRow = 10;

    // Helper to add row
    const addRow = (label: string, valStd: number, valElitonRef: any, styleOverride: any = {}) => {
        wsDash.getCell(`B${currentRow}`).value = label;
        if(styleOverride.labelFont) wsDash.getCell(`B${currentRow}`).font = styleOverride.labelFont;

        wsDash.getCell(`C${currentRow}`).value = valStd;
        wsDash.getCell(`C${currentRow}`).numFmt = styles.currency;

        wsDash.getCell(`D${currentRow}`).value = valElitonRef;
        wsDash.getCell(`D${currentRow}`).numFmt = styles.currency;
        if(styleOverride.elitonFill) wsDash.getCell(`D${currentRow}`).fill = styleOverride.elitonFill;

        // Difference: Standard - Eliton
        wsDash.getCell(`E${currentRow}`).value = { formula: `C${currentRow}-D${currentRow}` };
        wsDash.getCell(`E${currentRow}`).numFmt = styles.currency;

        // Default borders
        ['B', 'C', 'D', 'E'].forEach(c => {
            wsDash.getCell(`${c}${currentRow}`).border = { bottom: { style: 'thin', color: { argb: 'FFE2E8F0' } } };
        });

        currentRow++;
    };

    // Row 1: Wynagrodzenia Brutto
    // Eliton Brutto = Sum of New Base (Col D) + Benefit (Col E)
    // IMPORTANT FIX: Previously summed 'Netto'. Must be Reduced Gross Base (Col D) + Benefit (Col E) 
    // to equal 'Wynagrodzenie Brutto' concept in new model
    addRow("Wynagrodzenia Brutto", sumStandardBrutto, { formula: "SUM('Kalkulator Podwyżek'!D:D)+SUM('Kalkulator Podwyżek'!E:E)" });

    // Row 2: ZUS Pracodawcy
    const sumElitonZus = wyniki.szczegoly.reduce((acc, w) => {
        const isStudent = w.pracownik.trybSkladek === 'STUDENT_UZ';
        // Use podzial.zasadnicza.zusPracodawca.suma for Eliton model
        if (isStudent) return round(w.standard.zusPracodawca.suma); 
        return acc + round(w.podzial.zasadnicza.zusPracodawca.suma);
    }, 0);
    addRow("ZUS Pracodawcy", sumStandardZus, sumElitonZus);

    // Row 3: Opłata Success Fee
    // Fee = 20% of Benefit Netto (approximated from commission structure)
    const totalBenefitNetto = wyniki.szczegoly.reduce((acc, w) => {
        if (w.pracownik.trybSkladek === 'STUDENT_UZ') return acc;
        return acc + round(w.podzial.swiadczenie.netto);
    }, 0);
    const feeValue = totalBenefitNetto * 0.20; 
    addRow("Opłata Success Fee za obsługę modelu", 0, feeValue);

    // Row 4: Rezerwa Stratton (0%)
    addRow("Rezerwa Stratton (0%)", 0, { formula: "SUM('Kalkulator Podwyżek'!F:F)" }, {
        labelFont: styles.greenFont,
        elitonFill: styles.greenBg
    });

    // Row 5: Bonus dla biura księgowego (2%)
    addRow("Bonus dla biura księgowego (2%)", 0, { formula: "SUM('Kalkulator Podwyżek'!G:G)" }, {
        labelFont: { color: { argb: 'FF1E40AF' }, italic: true },
        elitonFill: styles.blueBg
    });

    // Row 6: Budżet na dodatkowe podwyżki od pracodawcy
    addRow("Budżet na dodatkowe podwyżki od pracodawcy", 0, { formula: "SUM('Kalkulator Podwyżek'!I:I)" }, {
        labelFont: { color: { argb: 'FFD97706' }, italic: true },
        elitonFill: styles.orangeBg
    });

    // Row 7: CAŁKOWITY KOSZT
    const totalRow = currentRow;
    const dashboardLastRow = totalRow - 1;
    wsDash.getCell(`B${totalRow}`).value = "CAŁKOWITY KOSZT";
    wsDash.getCell(`B${totalRow}`).font = { bold: true };
    
    // Sum C10 to lastDataRow
    wsDash.getCell(`C${totalRow}`).value = { formula: `SUM(C10:C${dashboardLastRow})` };
    wsDash.getCell(`C${totalRow}`).font = { bold: true };
    wsDash.getCell(`C${totalRow}`).numFmt = styles.currency;
    wsDash.getCell(`C${totalRow}`).border = { top: { style: 'double' } };

    // Sum D10 to lastDataRow
    wsDash.getCell(`D${totalRow}`).value = { formula: `SUM(D10:D${dashboardLastRow})` };
    wsDash.getCell(`D${totalRow}`).font = { bold: true };
    wsDash.getCell(`D${totalRow}`).numFmt = styles.currency;
    wsDash.getCell(`D${totalRow}`).border = { top: { style: 'double' } };

    // Difference
    wsDash.getCell(`E${totalRow}`).value = { formula: `C${totalRow}-D${totalRow}` };
    wsDash.getCell(`E${totalRow}`).font = { bold: true, color: { argb: 'FF059669' } };
    wsDash.getCell(`E${totalRow}`).numFmt = styles.currency;
    wsDash.getCell(`E${totalRow}`).border = { top: { style: 'double' } };

    // Update KPI "Koszt po wdrożeniu" (C6) to point to Table Total (D[totalRow])
    wsDash.getCell(`C${kpiRowValue}`).value = { formula: `D${totalRow}` };
    wsDash.getCell(`C${kpiRowValue}`).alignment = styles.centerAlign;
    wsDash.getCell(`C${kpiRowValue}`).font = { size: 14, color: { argb: 'FF0F172A' }, bold: true };
    wsDash.getCell(`C${kpiRowValue}`).numFmt = styles.currency;

    // Adjust widths
    wsDash.getColumn('B').width = 45;
    wsDash.getColumn('C').width = 25;
    wsDash.getColumn('D').width = 25;
    wsDash.getColumn('E').width = 25;


    // ==========================================================================================
    // ARKUSZ 2: KALKULATOR PODWYŻEK (INTERAKTYWNY)
    // ==========================================================================================
    const wsSim = workbook.addWorksheet('Kalkulator Podwyżek');
    
    // --- NAGŁÓWEK ---
    // Title B2
    wsSim.mergeCells('B2:F2');
    wsSim.getCell('B2').value = "SYMULACJA PODZIAŁU NADWYŻKI I PODWYŻEK";
    wsSim.getCell('B2').font = { bold: true, size: 12, color: { argb: 'FF1E40AF' } };

    // Input area
    // G2 label
    wsSim.getCell('G2').value = "Dodatkowa podwyżka od pracodawcy dla wszystkich pracowników (% od Obence Netto):";
    wsSim.getCell('G2').font = { bold: true };
    wsSim.getCell('G2').alignment = { horizontal: 'right' };
    wsSim.getColumn('G').width = 40;

    // L2 Input Cell
    const inputCell = wsSim.getCell('L2');
    inputCell.value = 0; 
    inputCell.numFmt = '0.00%';
    inputCell.fill = styles.yellowInput;
    inputCell.border = { top: { style: 'medium' }, left: { style: 'medium' }, bottom: { style: 'medium' }, right: { style: 'medium' } };
    inputCell.alignment = { horizontal: 'center' };
    inputCell.protection = { locked: false }; // Unlock for user edit

    // M2 Arrow/Instructions
    wsSim.getCell('M2').value = "⬅ Wpisz % tutaj";
    wsSim.getCell('M2').font = { italic: true, color: { argb: 'FF64748B' } };


    // --- TABELA DANYCH (Row 4 Header, Row 5+ Data) ---
    const simHeaderRow = 4;
    // Updated Headers to reflect actual content
    const simHeaders = [
        { label: 'LP', col: 'A', width: 5 },
        { label: 'Imię i Nazwisko', col: 'B', width: 25 },
        { label: 'Obecne\nNetto', col: 'C', width: 15 },
        { label: 'Kwota\noZUSowana', col: 'D', width: 18 }, // New Base (Reduced Gross)
        { label: 'Świadczenie\n(Benefit)', col: 'E', width: 15 }, // Benefit Netto (Not taxed)
        { label: 'Rezerwa\n(0%)', col: 'F', width: 15 }, 
        { label: 'Bonus dla działu\nKsięgowo -\nKadrowego', col: 'G', width: 15 },
        { label: 'Oszczędność Firmy\n(Na czysto)', col: 'H', width: 15, color: 'green' },
        { label: 'Podwyżka Dodatkowa\n(Edytowalna)', col: 'I', width: 15 },
        { label: 'NOWE ŁĄCZNE\nNETTO PRACOWNIKA', col: 'J', width: 18 },
        { label: 'ZMIANA\n(ZYSK\nPRACOWNIKA)', col: 'K', width: 15 }
    ];

    simHeaders.forEach(h => {
        const cell = wsSim.getCell(`${h.col}${simHeaderRow}`);
        cell.value = h.label;
        cell.fill = styles.navyFill;
        cell.font = styles.navyFont;
        cell.alignment = styles.centerAlign;
        
        wsSim.getColumn(h.col).width = h.width;
    });
    wsSim.getRow(simHeaderRow).height = 45;

    // Data Rows
    let simRow = 5;
    wyniki.szczegoly.forEach((w, i) => {
        const isStudent = w.pracownik.trybSkladek === 'STUDENT_UZ';
        
        // A - LP
        wsSim.getCell(`A${simRow}`).value = i + 1;
        
        // B - Name
        wsSim.getCell(`B${simRow}`).value = `${w.pracownik.imie} ${w.pracownik.nazwisko}`;

        // C - Current Netto
        wsSim.getCell(`C${simRow}`).value = w.standard.netto;

        if (isStudent) {
            // Student passthrough
            // D - Base (Netto)
            wsSim.getCell(`D${simRow}`).value = w.standard.netto;
            // E - Benefit 0
            wsSim.getCell(`E${simRow}`).value = 0;
            // F - Raise 0
            wsSim.getCell(`F${simRow}`).value = 0;
            // G - Bonus 0
            wsSim.getCell(`G${simRow}`).value = 0;
            // H - Savings 0
            wsSim.getCell(`H${simRow}`).value = 0;
            // I - Extra Raise 0
            wsSim.getCell(`I${simRow}`).value = 0;
            // J - New Total
            wsSim.getCell(`J${simRow}`).value = { formula: `D${simRow}` };
            // K - Change
            wsSim.getCell(`K${simRow}`).value = 0;
            
            // Grey out
            ['A','B','C','D','E','F','G','H','I','J','K'].forEach(c => {
                wsSim.getCell(`${c}${simRow}`).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF1F5F9' } };
            });

        } else {
            // Regular
            // D - New Base (Kwota oZUSowana - Reduced Brutto)
            wsSim.getCell(`D${simRow}`).value = round(w.podzial.zasadnicza.brutto);
            
            // E - Benefit (Swiadczenie netto)
            wsSim.getCell(`E${simRow}`).value = w.podzial.swiadczenie.netto;

            // F - System Raise (4% of Benefit)
            const benefit = w.podzial.swiadczenie.netto;
            wsSim.getCell(`F${simRow}`).value = benefit * 0.04;
            wsSim.getCell(`F${simRow}`).fill = styles.greenBg;

            // G - Admin Bonus (2% of Benefit)
            wsSim.getCell(`G${simRow}`).value = benefit * 0.02;
            wsSim.getCell(`G${simRow}`).fill = styles.blueBg;

            // H - Savings (Calculated clean savings)
            const grossSavings = w.standard.kosztPracodawcy - w.podzial.kosztPracodawcy;
            // Commission
            const fullCommission = benefit * (prowizjaProc / 100);
            
            // Deduct benefit principal and commission from gross payroll savings to get Employer Net Savings
            // Formula Check: Gross Savings must cover Benefit + Commission + Company Savings
            // So: Company Savings = Gross Savings - Benefit - Commission
            const netSavingsBeforeExtra = grossSavings - benefit - fullCommission;
            
            // Formula: NetSavingsBefore - ExtraRaise(Column I)
            wsSim.getCell(`H${simRow}`).value = { formula: `${round(netSavingsBeforeExtra)}-I${simRow}` };
            wsSim.getCell(`H${simRow}`).font = { bold: true, color: { argb: 'FF059669' } }; 
            
            // I - Extra Raise (Formula linked to L2 global input)
            // = C * $L$2
            wsSim.getCell(`I${simRow}`).value = { formula: `C${simRow}*$L$2` };
            wsSim.getCell(`I${simRow}`).fill = styles.orangeBg;

            // J - New Total Netto (Formula-like logic)
            // J = BaseNetto + Benefit + Raise + ExtraRaise
            // We use static value for (BaseNetto+Benefit+Raise) to avoid circular/lookup complexity
            const staticNettoSum = round(w.podzial.zasadnicza.nettoGotowka + benefit + (benefit * 0.04));
            
            wsSim.getCell(`J${simRow}`).value = { formula: `${staticNettoSum}+I${simRow}` };
            wsSim.getCell(`J${simRow}`).font = { bold: true };
            wsSim.getCell(`J${simRow}`).fill = styles.greenBg;

            // K - Change (Gain)
            // J - C
            wsSim.getCell(`K${simRow}`).value = { formula: `J${simRow}-C${simRow}` };
            wsSim.getCell(`K${simRow}`).font = { bold: true, color: { argb: 'FF059669' } };
        }

        // Common formatting
        ['C','D','E','F','G','H','I','J','K'].forEach(col => {
            wsSim.getCell(`${col}${simRow}`).numFmt = styles.currency;
            wsSim.getCell(`${col}${simRow}`).border = { bottom: { style: 'thin', color: { argb: 'FFE2E8F0' } } };
        });

        simRow++;
    });

    // SUM ROW
    const simLastRow = simRow - 1;
    wsSim.getCell(`B${simRow}`).value = "SUMA";
    wsSim.getCell(`B${simRow}`).font = { bold: true };
    wsSim.getCell(`B${simRow}`).alignment = { horizontal: 'right' };

    ['C','D','E','F','G','H','I','J','K'].forEach(col => {
        wsSim.getCell(`${col}${simRow}`).value = { formula: `SUM(${col}5:${col}${simLastRow})` };
        wsSim.getCell(`${col}${simRow}`).font = { bold: true };
        wsSim.getCell(`${col}${simRow}`).numFmt = styles.currency;
        wsSim.getCell(`${col}${simRow}`).border = { top: { style: 'double' } };
    });
    // H and K Green text
    wsSim.getCell(`H${simRow}`).font = { bold: true, color: { argb: 'FF059669' } };
    wsSim.getCell(`K${simRow}`).font = { bold: true, color: { argb: 'FF059669' } };


    // --- PODSUMOWANIE BUDŻETU (Right Side Panel) ---
    // N5 Header
    const budgetStartRow = 5;
    wsSim.mergeCells(`N${budgetStartRow}:O${budgetStartRow}`);
    wsSim.getCell(`N${budgetStartRow}`).value = "PODSUMOWANIE BUDŻETU (MIESIĘCZNIE)";
    wsSim.getCell(`N${budgetStartRow}`).fill = styles.navyFill;
    wsSim.getCell(`N${budgetStartRow}`).font = styles.navyFont;
    wsSim.getCell(`N${budgetStartRow}`).alignment = styles.centerAlign;

    // Row 6: Rezerwa Stratton (0%)
    wsSim.getCell(`N${budgetStartRow+1}`).value = "Rezerwa Stratton (0%)";
    wsSim.getCell(`N${budgetStartRow+1}`).font = { color: { argb: 'FF059669' }, bold: true, size: 9 };
    // Value: Sum of F
    wsSim.getCell(`O${budgetStartRow+1}`).value = { formula: `F${simRow}` }; // Sum Row
    wsSim.getCell(`O${budgetStartRow+1}`).numFmt = styles.currency;

    // Row 7: Dodatkowa Podwyżka (od Pracodawca)
    wsSim.getCell(`N${budgetStartRow+2}`).value = "Dodatkowa Podwyżka (od Pracodawca)";
    wsSim.getCell(`N${budgetStartRow+2}`).font = { color: { argb: 'FFD97706' }, bold: true, size: 9 };
    // Value: Sum of I
    wsSim.getCell(`O${budgetStartRow+2}`).value = { formula: `I${simRow}` }; // Sum Row
    wsSim.getCell(`O${budgetStartRow+2}`).numFmt = styles.currency;
    wsSim.getCell(`O${budgetStartRow+2}`).fill = styles.orangeBg;

    // Row 8: ŁĄCZNA PULA NA PODWYŻKI
    wsSim.getCell(`N${budgetStartRow+3}`).value = "ŁĄCZNA PULA NA PODWYŻKI";
    wsSim.getCell(`N${budgetStartRow+3}`).font = { bold: true };
    wsSim.getCell(`N${budgetStartRow+3}`).border = { top: { style: 'double' } };
    // Value: Sum of O6+O7
    wsSim.getCell(`O${budgetStartRow+3}`).value = { formula: `SUM(O${budgetStartRow+1}:O${budgetStartRow+2})` };
    wsSim.getCell(`O${budgetStartRow+3}`).font = { bold: true, color: { argb: 'FF059669' }, size: 11 };
    wsSim.getCell(`O${budgetStartRow+3}`).numFmt = styles.currency;
    wsSim.getCell(`O${budgetStartRow+3}`).border = { top: { style: 'double' } };

    // Bonus Summary (Row 12 or below)
    const bonusRow = 12;
    wsSim.getCell(`N${bonusRow}`).value = "Bonus dla działu kadrowo księgowego 2%";
    wsSim.getCell(`N${bonusRow}`).font = { color: { argb: 'FF1E40AF' }, bold: true, size: 9 };
    
    wsSim.getCell(`O${bonusRow}`).value = { formula: `G${simRow}` }; // Sum of G
    wsSim.getCell(`O${bonusRow}`).numFmt = styles.currency;
    wsSim.getCell(`O${bonusRow}`).font = { color: { argb: 'FF1E40AF' }, bold: true, size: 11 };

    wsSim.getColumn('N').width = 35;
    wsSim.getColumn('O').width = 20;

    // --- Generate File ---
    const buffer = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    const url = window.URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = `Kalkulator Podwyżek - ${firma.nazwa || 'Firma'}.xlsx`;
    anchor.click();
    window.URL.revokeObjectURL(url);
};
