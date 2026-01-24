import { Config } from '../models/company';
import { Pracownik } from '../models/employee';
import { obliczWiek } from './dates';

export interface ImportRow {
  id: number;
  isValid: boolean;
  errors: string[];
  data: Pracownik;
  raw: any;
}

const normalizeDate = (val: any): string => {
  if (!val) return '';
  if (val instanceof Date) return val.toISOString().split('T')[0];
  if (typeof val === 'number') {
    const date = new Date(Math.round((val - 25569) * 86400 * 1000));
    return date.toISOString().split('T')[0];
  }
  let str = String(val).trim();
  if (str.includes('.')) {
    const parts = str.split('.');
    if (parts.length === 3) return `${parts[2]}-${parts[1]}-${parts[0]}`;
  }
  if (str.match(/^\d{2}-\d{2}-\d{4}/)) {
    const parts = str.split('-');
    return `${parts[2]}-${parts[1]}-${parts[0]}`;
  }
  if (str.match(/^\d{4}-\d{2}-\d{2}/)) return str.substring(0, 10);
  return '';
};

const normalizeCurrency = (val: any): number => {
  if (val === undefined || val === null || val === '') return 0;
  if (typeof val === 'number') return val;

  let str = String(val).trim();
  str = str.replace(/[^\d.,\s-]/g, '');
  str = str.replace(/\s/g, '').replace(/\u00A0/g, '');

  if (str.includes(',') && str.includes('.')) {
    if (str.lastIndexOf(',') > str.lastIndexOf('.')) {
      str = str.replace(/\./g, '').replace(',', '.');
    } else {
      str = str.replace(/,/g, '');
    }
  } else if (str.includes(',')) {
    str = str.replace(',', '.');
  }

  const res = parseFloat(str);
  return Number.isNaN(res) ? 0 : Math.round(res * 100) / 100;
};

export const parseExcelData = (rows: any[], config: Config): ImportRow[] => {
  if (rows.length < 3) return [];
  const dataRows = rows.slice(2);

  return dataRows
    .map((row, idx) => {
      if (!row || row.length === 0) return null;

      const colA_Name = row[0];
      const colB_Date = row[1];
      const colC_Sex = row[2];
      const colD_Type = row[3];
      const colE_Zus = row[4];
      const colF_Netto = row[5];
      const colG_Kup = row[6];
      const colH_Pit2 = row[7];
      const colI_Ulga = row[8];
      const colJ_Pit = row[9];

      if (!colA_Name && !colF_Netto) return null;

      const errors: string[] = [];

      let imie = 'Pracownik';
      let nazwisko = `${idx + 1}`;
      const rawName = String(colA_Name || '').trim();
      if (rawName) {
        const parts = rawName.split(' ');
        if (parts.length >= 2) {
          imie = parts[0];
          nazwisko = parts.slice(1).join(' ');
        } else {
          imie = rawName;
          nazwisko = '';
        }
      }

      let dataUrodzenia = '1990-01-01';
      let isAgeInferred = false;
      if (colB_Date) {
        dataUrodzenia = normalizeDate(colB_Date);
        if (!dataUrodzenia || dataUrodzenia.length < 10) {
          dataUrodzenia = '1990-01-01';
          isAgeInferred = true;
        }
      } else {
        isAgeInferred = true;
      }
      const wiek = obliczWiek(dataUrodzenia);

      let plec: 'K' | 'M' = 'M';
      const rawSex = String(colC_Sex || '').toUpperCase();
      if (rawSex.includes('K')) plec = 'K';

      let typUmowy: 'UOP' | 'UZ' = 'UOP';
      const rawType = String(colD_Type || '').toUpperCase();
      if (rawType.includes('ZLEC') || rawType.includes('UZ')) {
        typUmowy = 'UZ';
      }

      let trybSkladek = 'PELNE';
      let choroboweAktywne = true;
      const rawZus = String(colE_Zus || '').toUpperCase();

      if (rawZus.includes('BEZ DOBROWOLNEJ CHOROBOWEJ') || rawZus === 'BEZ CHOROBOWEJ') {
        trybSkladek = 'BEZ_CHOROBOWEJ';
        choroboweAktywne = false;
      } else if (rawZus.includes('STUDENT') || rawZus.includes('UCZEN') || rawZus.includes('BEZ ZUS')) {
        trybSkladek = 'STUDENT_UZ';
        choroboweAktywne = false;
      } else if (rawZus.includes('INNY TYTUL') || rawZus.includes('TYLKO ZDROWOTNA')) {
        trybSkladek = 'INNY_TYTUL';
        choroboweAktywne = false;
      } else if (rawZus.includes('EMERYT') || rawZus.includes('RENCISTA')) {
        trybSkladek = 'EMERYT_RENCISTA';
        choroboweAktywne = true;
      } else if (rawZus.includes('PELNE SKLADKI')) {
        trybSkladek = 'PELNE';
        choroboweAktywne = true;
      }

      if (typUmowy === 'UOP') {
        trybSkladek = 'PELNE';
        choroboweAktywne = true;
      }

      const netto = normalizeCurrency(colF_Netto);
      if (netto <= 0) errors.push('Netto <= 0');

      let kupTyp = 'STANDARD';
      const rawKup = String(colG_Kup || '').toUpperCase();
      if (typUmowy === 'UOP') {
        if (rawKup.includes('300') || rawKup.includes('PODWY')) kupTyp = 'PODWYZSZONE';
        else kupTyp = 'STANDARD';
      } else {
        const valKup = parseFloat(rawKup);
        if (rawKup.includes('50') || rawKup.includes('AUTOR') || valKup === 0.5) {
          kupTyp = 'PROC_50';
        } else {
          kupTyp = 'PROC_20';
        }
      }

      let pit2 = '300';
      const valPit2 = parseFloat(String(colH_Pit2));
      if (!Number.isNaN(valPit2)) pit2 = String(valPit2);

      let ulgaMlodych = wiek < 26;
      const rawUlga = String(colI_Ulga || '').toUpperCase();
      if (rawUlga === 'TAK' || rawUlga === 'YES') ulgaMlodych = true;
      if (rawUlga === 'NIE' || rawUlga === 'NO') ulgaMlodych = false;

      let pitMode: 'AUTO' | 'FLAT_0' | 'FLAT_12' | 'FLAT_32' = 'AUTO';
      const rawPit = String(colJ_Pit || '').toUpperCase().trim();
      if (rawPit.includes('32')) pitMode = 'FLAT_32';
      else if (rawPit.includes('12')) pitMode = 'FLAT_12';
      else if (rawPit === '0' || rawPit === '0%' || rawPit.includes('ZWOL') || rawPit.includes('NIE')) pitMode = 'FLAT_0';

      if (ulgaMlodych) {
        pitMode = 'FLAT_0';
        pit2 = '0';
      }

      const nettoZasadnicza = typUmowy === 'UZ' ? config.minimalnaKwotaUZ.zasadniczaNetto : config.placaMinimalna.netto;

      const pracownik: Pracownik = {
        id: Date.now() + idx,
        imie,
        nazwisko,
        dataUrodzenia,
        plec,
        typUmowy,
        trybSkladek,
        choroboweAktywne,
        pit2,
        ulgaMlodych,
        kupTyp,
        nettoDocelowe: netto,
        nettoZasadnicza,
        pitMode,
        skladkaFP: !isAgeInferred,
        skladkaFGSP: !isAgeInferred,
      };

      if (!imie) errors.push('Brak imienia');
      if (!nazwisko) errors.push('Brak nazwiska');

      return {
        id: idx,
        isValid: errors.length === 0,
        errors,
        data: pracownik,
        raw: {
          imie,
          nazwisko,
          dataUrodzenia,
          plec,
          typUmowy,
          trybSkladek,
          choroboweAktywne,
          netto,
          kupTyp,
          pit2,
          ulgaMlodych,
          pitMode,
          wiek,
        },
      } as ImportRow;
    })
    .filter(Boolean) as ImportRow[];
};
