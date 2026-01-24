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
  generateManagementReport: async ({ firma, wyniki, prowizjaProc }: ReportData) => {
    const workbook = new ExcelJS.Workbook();
    const wsSummary = workbook.addWorksheet('Podsumowanie', { views: [{ showGridLines: false }] });

    wsSummary.columns = [
      { key: 'A', width: 5 },
      { key: 'B', width: 35 },
      { key: 'C', width: 20 },
      { key: 'D', width: 20 },
      { key: 'E', width: 20 },
      { key: 'F', width: 20 },
    ];

    wsSummary.mergeCells('B2:F2');
    const titleCell = wsSummary.getCell('B2');
    titleCell.value = `RAPORT OPTYMALIZACJI: ${firma.nazwa.toUpperCase()}`;
    titleCell.font = { name: 'Calibri', size: 14, bold: true, color: { argb: 'FF0F172A' } };

    wsSummary.mergeCells('B3:F3');
    const dateCell = wsSummary.getCell('B3');
    dateCell.value = `Data symulacji: ${new Date().toLocaleDateString('pl-PL')}`;
    dateCell.font = { name: 'Calibri', size: 10, color: { argb: 'FF64748B' } };

    const stats = wyniki.podsumowanie;
    const isPlus = prowizjaProc === 26;
    const prowizjaTotal = stats.prowizja;
    let feeCost = prowizjaTotal;
    let raiseCost = 0;

    if (isPlus) {
      feeCost = prowizjaTotal * (20 / 26);
      raiseCost = prowizjaTotal * (6 / 26);
    }

    const standardTotal = stats.sumaKosztStandard;
    const elitonTotal = stats.sumaKosztPodzial + prowizjaTotal;
    const savingsMonth = standardTotal - elitonTotal;
    const savingsYear = savingsMonth * 12;

    const kpiLabelsRow = wsSummary.getRow(5);
    kpiLabelsRow.values = ['', 'Aktualny koszt (msc)', 'Nowy koszt (msc)', 'Miesięczna oszczędność', 'Roczna oszczędność'];
    kpiLabelsRow.font = { name: 'Calibri', size: 10, color: { argb: 'FF475569' } };

    const kpiValuesRow = wsSummary.getRow(6);
    kpiValuesRow.values = ['', standardTotal, elitonTotal, savingsMonth, savingsYear];
    kpiValuesRow.font = { name: 'Calibri', size: 12, bold: true };
    [2, 3, 4, 5].forEach((c) => (kpiValuesRow.getCell(c).numFmt = '#,##0.00 zl'));

    const tableHeaderRow = wsSummary.getRow(9);
    tableHeaderRow.values = ['', 'Kategoria', 'Model Standard', 'Model Prime', 'Różnica'];
    ['B9', 'C9', 'D9', 'E9'].forEach((key) => {
      const cell = wsSummary.getCell(key);
      cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFE2E8F0' } };
      cell.font = { name: 'Calibri', size: 10, bold: true, color: { argb: 'FF334155' } };
      cell.border = { bottom: { style: 'thin', color: { argb: 'FF94A3B8' } } };
    });

    const rows = [
      {
        name: 'Wynagrodzenia Brutto',
        std: wyniki.szczegoly.reduce((acc, w) => acc + w.standard.brutto, 0),
        new: wyniki.szczegoly.reduce((acc, w) => acc + w.podzial.pit.lacznyPrzychod, 0),
      },
      {
        name: 'ZUS Pracodawcy',
        std: wyniki.szczegoly.reduce((acc, w) => acc + w.standard.zusPracodawca.suma, 0),
        new: wyniki.szczegoly.reduce((acc, w) => acc + w.podzial.zasadnicza.zusPracodawca.suma, 0),
      },
      { name: 'Koszt operacyjny (prowizja)', std: 0, new: feeCost },
    ];
    if (isPlus) rows.push({ name: 'Budżet na podwyżki', std: 0, new: raiseCost });

    let currentRowIdx = 10;
    rows.forEach((r) => {
      const row = wsSummary.getRow(currentRowIdx);
      const diffVal = r.std - r.new;
      row.getCell(2).value = r.name;
      row.getCell(3).value = r.std;
      row.getCell(4).value = r.new;
      row.getCell(5).value = diffVal;
      [3, 4, 5].forEach((c) => (row.getCell(c).numFmt = '#,##0.00 zl'));
      currentRowIdx++;
    });

    const totalRow = wsSummary.getRow(currentRowIdx);
    totalRow.getCell(2).value = 'CAŁKOWITY KOSZT';
    totalRow.getCell(3).value = standardTotal;
    totalRow.getCell(4).value = elitonTotal;
    totalRow.getCell(5).value = savingsMonth;
    [2, 3, 4, 5].forEach((c) => {
      const cell = totalRow.getCell(c);
      cell.font = { bold: true, size: 11 };
      cell.border = { top: { style: 'double' } };
      if (c > 2) cell.numFmt = '#,##0.00 zl';
    });

    await saveWorkbook(workbook, `Raport_${firma.nazwa || 'Firma'}`);
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
    exampleRow.values = ['Jan Kowalski', 'DD.MM.RRRR', 'Wybierz', 'Wybierz', 'Wybierz', 5000, 'AUTO', '300', 'TAK/NIE', 'AUTO', 'Info'];
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
    }

    await saveWorkbook(workbook, 'Szablon_Import_Pracownikow');
  },
};
