import ExcelJS from 'exceljs';
import { Firma } from '../models/company';
import { GlobalneWyniki } from '../models/calculation';

interface ReportData {
  firma: Firma;
  wyniki: GlobalneWyniki;
  prowizjaProc: number;
}

interface ColumnDef {
  header: string;
  key: string;
  width: number;
  style?: {
    numFmt?: string;
    font?: any;
    alignment?: any;
    fill?: any;
  };
}

const round = (value: number) => Math.round(value * 100) / 100;

const saveWorkbook = async (workbook: any, fileName: string) => {
  const buffer = await workbook.xlsx.writeBuffer();
  const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
  const url = window.URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = fileName.endsWith('.xlsx') ? fileName : `${fileName}.xlsx`;
  anchor.click();
  window.URL.revokeObjectURL(url);
};

const applyHeaderStyle = (worksheet: any, rowIndex: number) => {
  const headerRow = worksheet.getRow(rowIndex);
  headerRow.eachCell((cell: any) => {
    cell.font = { name: 'Calibri', size: 10, bold: true, color: { argb: 'FF0F172A' } };
    cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFE2E8F0' } };
    cell.alignment = { vertical: 'middle', horizontal: 'center', wrapText: true };
    cell.border = {
      top: { style: 'thin', color: { argb: 'FFCBD5E1' } },
      left: { style: 'thin', color: { argb: 'FFCBD5E1' } },
      bottom: { style: 'thin', color: { argb: 'FFCBD5E1' } },
      right: { style: 'thin', color: { argb: 'FFCBD5E1' } },
    };
  });
  headerRow.height = 28;
};

const applyColumnStyles = (row: any, columns: ColumnDef[]) => {
  columns.forEach((col, index) => {
    if (!col.style) return;
    const cell = row.getCell(index + 1);
    if (col.style.numFmt) cell.numFmt = col.style.numFmt;
    if (col.style.font) cell.font = col.style.font;
    if (col.style.alignment) cell.alignment = col.style.alignment;
    if (col.style.fill) cell.fill = col.style.fill;
  });
};

export const excelGenerator = {
  generateManagementReport: async (
    { firma, wyniki, prowizjaProc }: ReportData,
    options?: { returnBuffer?: boolean }
  ) => {
    const workbook = new ExcelJS.Workbook();
    const styles = {
      headerFill: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF1F5F9' } } as ExcelJS.Fill,
      headerFont: { bold: true, size: 10, color: { argb: 'FF334155' } },
      currency: '#,##0.00 "zł"',
      inputFill: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFFF000' } } as ExcelJS.Fill,
    };

    const wsSummary = workbook.addWorksheet('Podsumowanie Menadżerskie', { views: [{ showGridLines: false }] });

    wsSummary.mergeCells('B2:E2');
    const titleCell = wsSummary.getCell('B2');
    titleCell.value = `RAPORT OPTYMALIZACJI KOSZTÓW: ${firma.nazwa?.toUpperCase() || 'FIRMA'}`;
    titleCell.font = { size: 16, bold: true, color: { argb: 'FF0F172A' } };

    wsSummary.getCell('B3').value = `Data symulacji: ${new Date().toLocaleDateString('pl-PL')}`;
    wsSummary.getCell('B3').font = { color: { argb: 'FF64748B' } };

    const kpiRow = 5;
    const totalStandardCost = wyniki.szczegoly.reduce((acc, w) => acc + round(w.standard.kosztPracodawcy), 0);

    wsSummary.getCell(`B${kpiRow}`).value = 'Aktualny Koszt (Msc)';
    wsSummary.getCell(`B${kpiRow + 1}`).value = totalStandardCost;
    wsSummary.getCell(`B${kpiRow + 1}`).numFmt = styles.currency;
    wsSummary.getCell(`B${kpiRow + 1}`).font = { size: 14, color: { argb: 'FF64748B' } };

    wsSummary.getCell(`C${kpiRow}`).value = 'Nowy Koszt (Msc)';
    wsSummary.getCell(`C${kpiRow + 1}`).value = { formula: 'D14' };
    wsSummary.getCell(`C${kpiRow + 1}`).numFmt = styles.currency;
    wsSummary.getCell(`C${kpiRow + 1}`).font = { size: 14, color: { argb: 'FF0F172A' }, bold: true };

    wsSummary.getCell(`D${kpiRow}`).value = 'Miesięczna Oszczędność';
    wsSummary.getCell(`D${kpiRow + 1}`).value = { formula: `B${kpiRow + 1}-C${kpiRow + 1}` };
    wsSummary.getCell(`D${kpiRow + 1}`).numFmt = styles.currency;
    wsSummary.getCell(`D${kpiRow + 1}`).font = { size: 14, color: { argb: 'FF059669' }, bold: true };
    wsSummary.getCell(`D${kpiRow + 1}`).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFECFDF5' } };

    wsSummary.getCell(`E${kpiRow}`).value = 'Roczna Oszczędność';
    wsSummary.getCell(`E${kpiRow + 1}`).value = { formula: `D${kpiRow + 1}*12` };
    wsSummary.getCell(`E${kpiRow + 1}`).numFmt = styles.currency;
    wsSummary.getCell(`E${kpiRow + 1}`).font = { size: 14, color: { argb: 'FF059669' }, bold: true };

    const tableRow = 9;
    const headers = ['Kategoria', 'Model Standard (As-Is)', 'Model Eliton (To-Be)', 'Różnica'];
    headers.forEach((header, index) => {
      const cell = wsSummary.getCell(tableRow, 2 + index);
      cell.value = header;
      cell.fill = styles.headerFill;
      cell.font = styles.headerFont;
      cell.border = { bottom: { style: 'thick', color: { argb: 'FF334155' } } };
    });

    const addDashboardRow = (
      label: string,
      standardValue: number | null,
      targetValueOrFormula: any,
      rowIndex: number,
      isTotal = false,
      isDynamic = false,
    ) => {
      wsSummary.getCell(`B${rowIndex}`).value = label;
      wsSummary.getCell(`C${rowIndex}`).value = standardValue ?? 0;
      wsSummary.getCell(`D${rowIndex}`).value = targetValueOrFormula;
      wsSummary.getCell(`E${rowIndex}`).value = { formula: `C${rowIndex}-D${rowIndex}` };

      ['C', 'D', 'E'].forEach((col) => {
        wsSummary.getCell(`${col}${rowIndex}`).numFmt = styles.currency;
        if (isTotal) wsSummary.getCell(`${col}${rowIndex}`).font = { bold: true };
      });

      if (isDynamic) {
        wsSummary.getCell(`B${rowIndex}`).font = { color: { argb: 'FFD97706' }, italic: true };
        wsSummary.getCell(`D${rowIndex}`).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFFFBEB' } };
      }
    };

    const statsStandard = {
      brutto: wyniki.szczegoly.reduce((acc, w) => acc + round(w.standard.brutto), 0),
      zus: wyniki.szczegoly.reduce((acc, w) => acc + round(w.standard.zusPracodawca.suma), 0),
    };

    const statsStratton = {
      brutto: wyniki.szczegoly.reduce((acc, w) => {
        const isStudent = w.pracownik.trybSkladek === 'STUDENT_UZ';
        return acc + round(isStudent ? w.standard.brutto : w.podzial.pit.lacznyPrzychod);
      }, 0),
      zus: wyniki.szczegoly.reduce((acc, w) => {
        const isStudent = w.pracownik.trybSkladek === 'STUDENT_UZ';
        return acc + round(isStudent ? w.standard.zusPracodawca.suma : w.podzial.zasadnicza.zusPracodawca.suma);
      }, 0),
      prowizja: round(
        wyniki.szczegoly.reduce((acc, w) => {
          const isStudent = w.pracownik.trybSkladek === 'STUDENT_UZ';
          if (isStudent) return acc;
          return acc + round(w.podzial.swiadczenie.brutto);
        }, 0) * (prowizjaProc / 100),
      ),
    };

    addDashboardRow('Wynagrodzenia Brutto', statsStandard.brutto, statsStratton.brutto, tableRow + 1);
    addDashboardRow('ZUS Pracodawcy', statsStandard.zus, statsStratton.zus, tableRow + 2);
    addDashboardRow('Koszt Operacyjny (Prowizja)', 0, statsStratton.prowizja, tableRow + 3);
    addDashboardRow('Budżet na dodatkowe podwyżki', 0, { formula: "'Kalkulator Podwyżek'!$M$2" }, tableRow + 4, false, true);

    wsSummary.getCell(`B${tableRow + 5}`).value = 'CAŁKOWITY KOSZT';
    wsSummary.getCell(`B${tableRow + 5}`).font = { bold: true };
    wsSummary.getCell(`C${tableRow + 5}`).value = { formula: `SUM(C${tableRow + 1}:C${tableRow + 4})` };
    wsSummary.getCell(`D${tableRow + 5}`).value = { formula: `SUM(D${tableRow + 1}:D${tableRow + 4})` };
    wsSummary.getCell(`E${tableRow + 5}`).value = { formula: `C${tableRow + 5}-D${tableRow + 5}` };

    ['C', 'D', 'E'].forEach((col) => {
      wsSummary.getCell(`${col}${tableRow + 5}`).numFmt = styles.currency;
      wsSummary.getCell(`${col}${tableRow + 5}`).font = { bold: true, size: 12 };
      wsSummary.getCell(`${col}${tableRow + 5}`).border = { top: { style: 'double' } };
    });
    wsSummary.getCell(`E${tableRow + 5}`).font = { color: { argb: 'FF059669' }, bold: true, size: 12 };

    wsSummary.getColumn('B').width = 35;
    wsSummary.getColumn('C').width = 25;
    wsSummary.getColumn('D').width = 25;
    wsSummary.getColumn('E').width = 25;

    const wsDetails = workbook.addWorksheet('Kalkulator Podwyżek');
    const isPlusVariant = prowizjaProc === 26;
    const dataStartRow = 5;
    const dataEndRow = dataStartRow + wyniki.szczegoly.length - 1;

    wsDetails.mergeCells('B2:F2');
    wsDetails.getCell('B2').value = 'SYMULACJA PODZIAŁU NADWYŻKI I PODWYŻEK';
    wsDetails.getCell('B2').font = { bold: true, size: 14, color: { argb: 'FF1E40AF' } };

    wsDetails.getCell('M1').value = 'SUMA PODWYŻEK (TECH)';
    wsDetails.getCell('M2').value = { formula: `SUM(H${dataStartRow}:H${dataEndRow})` };
    wsDetails.getCell('M2').font = { color: { argb: 'FFFFFFFF' } };

    wsDetails.getCell('I2').value = 'Dodatkowa podwyżka od pracodawcy (% od podstawy ZUS):';
    wsDetails.getCell('I2').font = { bold: true };
    wsDetails.getCell('I2').alignment = { horizontal: 'right' };

    const inputCell = wsDetails.getCell('K2');
    inputCell.value = 0;
    inputCell.numFmt = '0.00%';
    inputCell.fill = styles.inputFill;
    inputCell.font = { bold: true, color: { argb: 'FF000000' } };
    inputCell.border = { top: { style: 'medium' }, left: { style: 'medium' }, bottom: { style: 'medium' }, right: { style: 'medium' } };
    inputCell.alignment = { horizontal: 'center' };

    wsDetails.getCell('L2').value = '⬅ Wpisz % tutaj';
    wsDetails.getCell('L2').font = { italic: true, color: { argb: 'FF64748B' } };

    const simHeaderRowIdx = 4;
    const simHeaders = [
      'LP',
      'Imię i Nazwisko',
      'Obecne\nNetto',
      'Nowa Baza\n(ZUS)',
      'Świadczenie\n(Benefit)',
      isPlusVariant ? 'Podwyżka Systemowa\n(Stratton 4%)' : 'Podwyżka Systemowa\n(Brak)',
      'Bonus Administracyjny\n(2% - Budżet Firmy)',
      'Podwyżka Dodatkowa\n(Od Pracodawcy)',
      'NOWE ŁĄCZNE\nNETTO PRACOWNIKA',
      'ZMIANA\n(ZYSK PRACOWNIKA)',
    ];

    simHeaders.forEach((header, index) => {
      const colLetter = String.fromCharCode(65 + index);
      const cell = wsDetails.getCell(`${colLetter}${simHeaderRowIdx}`);
      cell.value = header;
      cell.style = {
        font: { bold: true, color: { argb: 'FFFFFFFF' }, size: 10 },
        fill: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF0F172A' } },
        alignment: { horizontal: 'center', vertical: 'middle', wrapText: true },
        border: { bottom: { style: 'medium' } },
      };
    });
    wsDetails.getRow(simHeaderRowIdx).height = 50;

    wyniki.szczegoly.forEach((w, index) => {
      const rowIndex = dataStartRow + index;
      const isStudent = w.pracownik.trybSkladek === 'STUDENT_UZ';

      wsDetails.getCell(`A${rowIndex}`).value = index + 1;
      wsDetails.getCell(`B${rowIndex}`).value = `${w.pracownik.imie} ${w.pracownik.nazwisko}${isStudent ? ' (Student)' : ''}`;
      wsDetails.getCell(`C${rowIndex}`).value = w.standard.netto;

      if (isStudent) {
        wsDetails.getCell(`D${rowIndex}`).value = w.standard.netto;
        wsDetails.getCell(`E${rowIndex}`).value = 0;
        wsDetails.getCell(`F${rowIndex}`).value = 0;
        wsDetails.getCell(`G${rowIndex}`).value = 0;
        wsDetails.getCell(`H${rowIndex}`).value = 0;

        for (let colCode = 65; colCode <= 74; colCode++) {
          wsDetails.getCell(`${String.fromCharCode(colCode)}${rowIndex}`).fill = {
            type: 'pattern',
            pattern: 'solid',
            fgColor: { argb: 'FFF1F5F9' },
          };
        }
      } else {
        wsDetails.getCell(`D${rowIndex}`).value = w.podzial.zasadnicza.nettoGotowka;
        wsDetails.getCell(`E${rowIndex}`).value = w.podzial.swiadczenie.netto;

        let systemRaise = 0;
        let adminBonus = 0;
        if (isPlusVariant) {
          const swiadczenieBrutto = w.podzial.swiadczenie.brutto;
          systemRaise = swiadczenieBrutto * 0.04;
          adminBonus = swiadczenieBrutto * 0.02;
        }

        wsDetails.getCell(`F${rowIndex}`).value = systemRaise;
        wsDetails.getCell(`F${rowIndex}`).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFECFDF5' } };

        wsDetails.getCell(`G${rowIndex}`).value = adminBonus;
        wsDetails.getCell(`G${rowIndex}`).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFEFF6FF' } };

        wsDetails.getCell(`H${rowIndex}`).value = { formula: `D${rowIndex}*$K$2` };
        wsDetails.getCell(`H${rowIndex}`).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFFFBEB' } };
      }

      wsDetails.getCell(`I${rowIndex}`).value = { formula: `D${rowIndex}+E${rowIndex}+F${rowIndex}+H${rowIndex}` };
      wsDetails.getCell(`I${rowIndex}`).font = { bold: true };
      if (!isStudent) wsDetails.getCell(`I${rowIndex}`).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFECFDF5' } };

      wsDetails.getCell(`J${rowIndex}`).value = { formula: `I${rowIndex}-C${rowIndex}` };
      wsDetails.getCell(`J${rowIndex}`).font = { bold: true, color: { argb: 'FF059669' } };

      for (let col = 3; col <= 10; col++) {
        wsDetails.getCell(rowIndex, col).numFmt = '#,##0.00';
        wsDetails.getCell(rowIndex, col).border = { bottom: { style: 'thin', color: { argb: 'FFE2E8F0' } } };
      }
    });

    wsDetails.getColumn('A').width = 5;
    wsDetails.getColumn('B').width = 25;
    wsDetails.getColumn('C').width = 13;
    wsDetails.getColumn('D').width = 13;
    wsDetails.getColumn('E').width = 13;
    wsDetails.getColumn('F').width = 15;
    wsDetails.getColumn('G').width = 15;
    wsDetails.getColumn('H').width = 16;
    wsDetails.getColumn('I').width = 18;
    wsDetails.getColumn('J').width = 14;

    const summaryStartCol = 'L';
    const summaryValueCol = 'M';
    const sumStartRow = 5;

    wsDetails.mergeCells(`${summaryStartCol}${sumStartRow}:${summaryValueCol}${sumStartRow}`);
    const sumTitle = wsDetails.getCell(`${summaryStartCol}${sumStartRow}`);
    sumTitle.value = 'PODSUMOWANIE BUDŻETU (MIESIĘCZNIE)';
    sumTitle.font = { bold: true, color: { argb: 'FFFFFFFF' }, size: 10 };
    sumTitle.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF334155' } };
    sumTitle.alignment = { horizontal: 'center', vertical: 'middle' };

    wsDetails.getCell(`${summaryStartCol}${sumStartRow + 1}`).value = isPlusVariant
      ? 'Podwyżki Systemowe (Stratton 4%)'
      : 'Podwyżki Systemowe (Brak)';
    wsDetails.getCell(`${summaryStartCol}${sumStartRow + 1}`).font = { size: 10 };
    wsDetails.getCell(`${summaryValueCol}${sumStartRow + 1}`).value = { formula: `SUM(F${dataStartRow}:F${dataEndRow})` };
    wsDetails.getCell(`${summaryValueCol}${sumStartRow + 1}`).numFmt = styles.currency;

    wsDetails.getCell(`${summaryStartCol}${sumStartRow + 2}`).value = 'Budżet Administracyjny (2%)';
    wsDetails.getCell(`${summaryStartCol}${sumStartRow + 2}`).font = { size: 10 };
    wsDetails.getCell(`${summaryValueCol}${sumStartRow + 2}`).value = { formula: `SUM(G${dataStartRow}:G${dataEndRow})` };
    wsDetails.getCell(`${summaryValueCol}${sumStartRow + 2}`).numFmt = styles.currency;

    wsDetails.getCell(`${summaryStartCol}${sumStartRow + 3}`).value = 'Dodatkowa Podwyżka (Pracodawca)';
    wsDetails.getCell(`${summaryStartCol}${sumStartRow + 3}`).font = { size: 10, bold: true, color: { argb: 'FFD97706' } };
    wsDetails.getCell(`${summaryValueCol}${sumStartRow + 3}`).value = { formula: `SUM(H${dataStartRow}:H${dataEndRow})` };
    wsDetails.getCell(`${summaryValueCol}${sumStartRow + 3}`).numFmt = styles.currency;
    wsDetails.getCell(`${summaryValueCol}${sumStartRow + 3}`).font = { bold: true };
    wsDetails.getCell(`${summaryValueCol}${sumStartRow + 3}`).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFFFBEB' } };

    wsDetails.getCell(`${summaryStartCol}${sumStartRow + 4}`).value = 'ŁĄCZNA PULA NA PODWYŻKI';
    wsDetails.getCell(`${summaryStartCol}${sumStartRow + 4}`).font = { bold: true };
    wsDetails.getCell(`${summaryStartCol}${sumStartRow + 4}`).border = { top: { style: 'double' } };
    wsDetails.getCell(`${summaryValueCol}${sumStartRow + 4}`).value = {
      formula: `SUM(${summaryValueCol}${sumStartRow + 1}:${summaryValueCol}${sumStartRow + 3})`,
    };
    wsDetails.getCell(`${summaryValueCol}${sumStartRow + 4}`).numFmt = styles.currency;
    wsDetails.getCell(`${summaryValueCol}${sumStartRow + 4}`).font = { bold: true, size: 12, color: { argb: 'FF059669' } };
    wsDetails.getCell(`${summaryValueCol}${sumStartRow + 4}`).border = { top: { style: 'double' } };

    wsDetails.getColumn('L').width = 35;
    wsDetails.getColumn('M').width = 20;

    const fileName = `Raport_${firma.nazwa || 'Firma'}`;
    if (options?.returnBuffer) {
      const buffer = await workbook.xlsx.writeBuffer();
      return { buffer, fileName };
    }
    await saveWorkbook(workbook, fileName);
  },

  generateDetailedReport: async ({ firma, wyniki }: ReportData) => {
    const workbook = new ExcelJS.Workbook();

    const currency = '#,##0.00';

    const standardColumns: ColumnDef[] = [
      { header: 'LP', key: 'lp', width: 5 },
      { header: 'Pracownik', key: 'name', width: 24 },
      { header: 'Umowa', key: 'type', width: 10 },
      { header: 'Netto', key: 'netto', width: 13, style: { numFmt: currency } },
      { header: 'Brutto', key: 'brutto', width: 13, style: { numFmt: currency } },
      { header: 'Koszt', key: 'koszt', width: 15, style: { numFmt: currency, font: { bold: true } } },
      { header: 'Podst. ZUS', key: 'podstZus', width: 13, style: { numFmt: currency } },
      { header: 'Emeryt. (P)', key: 'emerytalnaPrac', width: 12, style: { numFmt: currency } },
      { header: 'Rentowa (P)', key: 'rentowaPrac', width: 12, style: { numFmt: currency } },
      { header: 'Chorobowa', key: 'chorobowaPrac', width: 12, style: { numFmt: currency } },
      { header: 'ZUS Prac.', key: 'zusPrac', width: 12, style: { numFmt: currency } },
      { header: 'Podst. Zdr.', key: 'podstZdrow', width: 13, style: { numFmt: currency } },
      { header: 'Skł. Zdr.', key: 'zdrowotna', width: 12, style: { numFmt: currency } },
      { header: 'KUP', key: 'kup', width: 10, style: { numFmt: currency } },
      { header: 'Podst. PIT', key: 'podstPit', width: 13, style: { numFmt: currency } },
      { header: 'Stawka', key: 'stawkaPit', width: 8, style: { alignment: { horizontal: 'center' } } },
      { header: 'Zaliczka PIT', key: 'pit', width: 12, style: { numFmt: currency } },
      { header: 'Emeryt. (F)', key: 'emerytalnaFirma', width: 12, style: { numFmt: currency } },
      { header: 'Rentowa (F)', key: 'rentowaFirma', width: 12, style: { numFmt: currency } },
      { header: 'Wypadk. (F)', key: 'wypadkowaFirma', width: 12, style: { numFmt: currency } },
      { header: 'FP', key: 'fp', width: 10, style: { numFmt: currency } },
      { header: 'FGŚP', key: 'fgsp', width: 10, style: { numFmt: currency } },
      { header: 'ZUS Firmy', key: 'zusFirma', width: 13, style: { numFmt: currency } },
      { header: 'SUMA SKŁADEK', key: 'sumaSkladek', width: 16, style: { numFmt: currency, font: { bold: true } } },
    ];

    const splitColumns: ColumnDef[] = [
      { header: 'LP', key: 'lp', width: 5 },
      { header: 'Pracownik', key: 'name', width: 24 },
      { header: 'Typ umowy', key: 'type', width: 10 },
      { header: 'Brutto łączne', key: 'bruttoLaczne', width: 13, style: { numFmt: currency } },
      { header: 'Netto zasad.', key: 'nettoZasadnicze', width: 13, style: { numFmt: currency } },
      { header: 'Brutto zasad.', key: 'bruttoZasadnicze', width: 13, style: { numFmt: currency } },
      { header: 'Świadcz. netto', key: 'swiadczenieNetto', width: 13, style: { numFmt: currency, font: { bold: true } } },
      { header: 'Dodatek', key: 'dodatek', width: 12, style: { numFmt: currency } },
      { header: 'Potrącenie', key: 'potracenie', width: 10, style: { numFmt: currency } },
      { header: 'Świadcz. brutto', key: 'swiadczenieBrutto', width: 13, style: { numFmt: currency } },
      { header: 'Zaliczka (Św.)', key: 'swiadczenieZaliczka', width: 12, style: { numFmt: currency } },
      { header: 'Gotówka', key: 'doWyplatyGotowka', width: 13, style: { numFmt: currency } },
      { header: 'Świadczenie', key: 'doWyplatySwiadczenie', width: 13, style: { numFmt: currency } },
      { header: 'RAZEM', key: 'doWyplatyRazem', width: 15, style: { numFmt: currency, font: { bold: true } } },
      { header: 'KOSZT CAŁK.', key: 'koszt', width: 15, style: { numFmt: currency, font: { bold: true } } },
      { header: 'Podst. ZUS', key: 'podstZus', width: 13, style: { numFmt: currency } },
      { header: 'Emerytalna', key: 'zusE', width: 12, style: { numFmt: currency } },
      { header: 'Rentowa', key: 'zusR', width: 12, style: { numFmt: currency } },
      { header: 'Chorobowa', key: 'zusC', width: 12, style: { numFmt: currency } },
      { header: 'Suma ZUS', key: 'zusSuma', width: 12, style: { numFmt: currency } },
      { header: 'Podst. Zdr.', key: 'podstZdr', width: 13, style: { numFmt: currency } },
      { header: 'Skł. Zdr.', key: 'sklZdr', width: 12, style: { numFmt: currency } },
      { header: 'KUP', key: 'kup', width: 10, style: { numFmt: currency } },
      { header: 'Podst. PIT', key: 'podstPit', width: 13, style: { numFmt: currency } },
      { header: 'Stawka', key: 'stawkaPit', width: 8, style: { alignment: { horizontal: 'center' } } },
      { header: 'Zal. baz.', key: 'pitZasadnicza', width: 12, style: { numFmt: currency } },
      { header: 'PIT całk.', key: 'pitCalk', width: 12, style: { numFmt: currency, font: { bold: true } } },
      { header: 'Emeryt. (F)', key: 'firmaE', width: 12, style: { numFmt: currency } },
      { header: 'Rentowa (F)', key: 'firmaR', width: 12, style: { numFmt: currency } },
      { header: 'Wypadk. (F)', key: 'firmaW', width: 12, style: { numFmt: currency } },
      { header: 'FP', key: 'firmaFP', width: 10, style: { numFmt: currency } },
      { header: 'FGŚP', key: 'firmaFGSP', width: 10, style: { numFmt: currency } },
      { header: 'ZUS Firmy', key: 'zusFirma', width: 13, style: { numFmt: currency } },
      { header: 'SUMA SKŁADEK', key: 'sumaSkladek', width: 16, style: { numFmt: currency, font: { bold: true } } },
    ];

    const wsStandard = workbook.addWorksheet('Standard (As-Is)');
    wsStandard.columns = standardColumns;
    applyHeaderStyle(wsStandard, 1);

    wyniki.szczegoly.forEach((w, index) => {
      const sumaSkladek = w.standard.zusPracownik.suma + w.standard.zusPracodawca.suma + w.standard.zdrowotna;
      const row = wsStandard.addRow({
        lp: index + 1,
        name: `${w.pracownik.imie} ${w.pracownik.nazwisko}`,
        type: w.pracownik.typUmowy,
        netto: w.standard.netto,
        brutto: w.standard.brutto,
        koszt: w.standard.kosztPracodawcy,
        podstZus: w.standard.podstawaZus,
        emerytalnaPrac: w.standard.zusPracownik.emerytalna,
        rentowaPrac: w.standard.zusPracownik.rentowa,
        chorobowaPrac: w.standard.zusPracownik.chorobowa,
        zusPrac: w.standard.zusPracownik.suma,
        podstZdrow: w.standard.podstawaZdrowotna,
        zdrowotna: w.standard.zdrowotna,
        kup: w.standard.kup,
        podstPit: w.standard.podstawaPit,
        stawkaPit: w.standard.stawkaPit,
        pit: w.standard.pit,
        emerytalnaFirma: w.standard.zusPracodawca.emerytalna,
        rentowaFirma: w.standard.zusPracodawca.rentowa,
        wypadkowaFirma: w.standard.zusPracodawca.wypadkowa,
        fp: w.standard.zusPracodawca.fp,
        fgsp: w.standard.zusPracodawca.fgsp,
        zusFirma: w.standard.zusPracodawca.suma,
        sumaSkladek,
      });
      applyColumnStyles(row, standardColumns);
    });

    const wsSplit = workbook.addWorksheet('Podział (To-Be)');
    wsSplit.columns = splitColumns;
    applyHeaderStyle(wsSplit, 1);

    wyniki.szczegoly.forEach((w, index) => {
      const sumaSkladek = w.podzial.zasadnicza.zusPracownik.suma + w.podzial.zasadnicza.zusPracodawca.suma + w.podzial.zasadnicza.zdrowotna;
      const row = wsSplit.addRow({
        lp: index + 1,
        name: `${w.pracownik.imie} ${w.pracownik.nazwisko}`,
        type: w.pracownik.typUmowy,
        bruttoLaczne: w.podzial.pit.lacznyPrzychod,
        nettoZasadnicze: w.podzial.zasadnicza.nettoGotowka,
        bruttoZasadnicze: w.podzial.zasadnicza.brutto,
        swiadczenieNetto: w.podzial.swiadczenie.netto,
        dodatek: 0,
        potracenie: 0,
        swiadczenieBrutto: w.podzial.swiadczenie.brutto,
        swiadczenieZaliczka: w.podzial.swiadczenie.zaliczka,
        doWyplatyGotowka: w.podzial.doWyplatyGotowka,
        doWyplatySwiadczenie: w.podzial.doWyplatySwiadczenie,
        doWyplatyRazem: w.podzial.doWyplaty,
        koszt: w.podzial.kosztPracodawcy,
        podstZus: w.podzial.zasadnicza.podstawaZus,
        zusE: w.podzial.zasadnicza.zusPracownik.emerytalna,
        zusR: w.podzial.zasadnicza.zusPracownik.rentowa,
        zusC: w.podzial.zasadnicza.zusPracownik.chorobowa,
        zusSuma: w.podzial.zasadnicza.zusPracownik.suma,
        podstZdr: w.podzial.zasadnicza.podstawaZdrowotna,
        sklZdr: w.podzial.zasadnicza.zdrowotna,
        kup: w.podzial.pit.kup,
        podstPit: w.podzial.pit.podstawa,
        stawkaPit: w.podzial.pit.stawka,
        pitZasadnicza: w.podzial.pit.kwotaOdZasadniczej,
        pitCalk: w.podzial.pit.kwota,
        firmaE: w.podzial.zasadnicza.zusPracodawca.emerytalna,
        firmaR: w.podzial.zasadnicza.zusPracodawca.rentowa,
        firmaW: w.podzial.zasadnicza.zusPracodawca.wypadkowa,
        firmaFP: w.podzial.zasadnicza.zusPracodawca.fp,
        firmaFGSP: w.podzial.zasadnicza.zusPracodawca.fgsp,
        zusFirma: w.podzial.zasadnicza.zusPracodawca.suma,
        sumaSkladek,
      });
      applyColumnStyles(row, splitColumns);
    });

    await saveWorkbook(workbook, `Raport_Szczegolowy_${firma.nazwa || 'Firma'}`);
  },

  generateImportTemplate: async (rowsToGenerate: number = 10) => {
    const workbook = new ExcelJS.Workbook();
    const worksheet = workbook.addWorksheet('Szablon Import');

    worksheet.columns = [
      { key: 'A', width: 25 },
      { key: 'B', width: 15 },
      { key: 'C', width: 15 },
      { key: 'D', width: 25 },
      { key: 'E', width: 45 },
      { key: 'F', width: 18 },
      { key: 'G', width: 20 },
      { key: 'H', width: 25 },
      { key: 'I', width: 30 },
      { key: 'J', width: 15 },
      { key: 'K', width: 25 },
    ];

    const headerRow = worksheet.getRow(1);
    headerRow.height = 45;
    headerRow.values = [
      'Imię i nazwisko',
      'Data urodzenia',
      'Płeć K/M',
      'Rodzaj umowy',
      'Składki ZUS',
      'Wynagrodzenie NETTO',
      'KUP',
      'Kwota zmniejszająca podatek',
      'Ulga < 26 lat',
      'Zaliczka PIT',
      'Wynagrodzenie na rękę',
    ];
    headerRow.eachCell((cell: any) => {
      cell.font = { bold: true, color: { argb: 'FFFFFFFF' }, size: 10, name: 'Calibri' };
      cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF0F172A' } };
      cell.alignment = { vertical: 'middle', horizontal: 'center', wrapText: true };
      cell.border = { top: { style: 'thin' }, left: { style: 'thin' }, bottom: { style: 'thin' }, right: { style: 'thin' } };
    });

    const exampleRow = worksheet.getRow(2);
    exampleRow.height = 30;
    exampleRow.values = ['Jan Kowalski', 'DD.MM.RRRR', 'Wybierz', 'Wybierz', 'Wybierz', 5000, 'AUTO (formuła)', '300 / 150 / 100 / 0', 'TAK / NIE', 'AUTO (formuła)', 'Info'];
    exampleRow.eachCell((cell: any) => {
      cell.font = { color: { argb: 'FF000000' }, size: 10, name: 'Calibri' };
      cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFEF3C7' } };
      cell.alignment = { vertical: 'middle', horizontal: 'center' };
      cell.border = { top: { style: 'thin' }, left: { style: 'thin' }, bottom: { style: 'thin' }, right: { style: 'thin' } };
    });

    const startRow = 3;
    const plecList = '"M,K"';
    const umowaList = '"Umowa o pracę,Umowa zlecenie"';
    const zusList = '"Pełne składki,Pełne składki z dobrowolną chorobową,Bez dobrowolnej chorobowej,Student/uczeń do 26 (bez ZUS),Inny tytuł (tylko zdrowotna),Emeryt/rencista"';
    const ulgaList = '"TAK,NIE"';

    for (let i = 0; i < rowsToGenerate; i++) {
      const rowIndex = startRow + i;
      const row = worksheet.getRow(rowIndex);

      row.eachCell({ includeEmpty: true }, (cell: any, colNumber: number) => {
        cell.border = { top: { style: 'thin' }, left: { style: 'thin' }, bottom: { style: 'thin' }, right: { style: 'thin' } };
        cell.alignment = { vertical: 'middle', horizontal: 'center' };
        if (colNumber === 1 || colNumber === 5) cell.alignment = { vertical: 'middle', horizontal: 'left' };
      });

      worksheet.getCell(`C${rowIndex}`).dataValidation = { type: 'list', allowBlank: true, formulae: [plecList] };
      worksheet.getCell(`D${rowIndex}`).dataValidation = { type: 'list', allowBlank: true, formulae: [umowaList] };
      worksheet.getCell(`E${rowIndex}`).dataValidation = { type: 'list', allowBlank: true, formulae: [zusList] };
      worksheet.getCell(`I${rowIndex}`).dataValidation = { type: 'list', allowBlank: true, formulae: [ulgaList] };

      const colD = `D${rowIndex}`;
      const colI = `I${rowIndex}`;
      const cellG = worksheet.getCell(`G${rowIndex}`);
      cellG.value = { formula: `IF(${colD}=\"Umowa zlecenie\",\"20%\",\"250\")` };
      cellG.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFE0F2FE' } };
      cellG.font = { color: { argb: 'FF0284C7' }, bold: true };

      const cellJ = worksheet.getCell(`J${rowIndex}`);
      cellJ.value = { formula: `IF(${colI}=\"TAK\",0,0.12)` };
      cellJ.numFmt = '0%';
      cellJ.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFE0F2FE' } };
      cellJ.font = { color: { argb: 'FF0284C7' }, bold: true };
    }

    await saveWorkbook(workbook, 'Szablon_Import_Pracownikow');
  },
};
