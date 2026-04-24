import { Config } from '../../models/company';
import { ZusSkladkiPracodawca, ZusSkladkiPracownik } from '../../models/calculation';

const roundCurrency = (val: number) => Math.round(val * 100) / 100;

export const obliczZusPracownik = (
  brutto: number,
  typUmowy: string,
  trybSkladek: string,
  choroboweAktywne: boolean,
  config: Config
): ZusSkladkiPracownik => {
  if (trybSkladek === 'STUDENT_UZ' || trybSkladek === 'INNY_TYTUL') {
    return { emerytalna: 0, rentowa: 0, chorobowa: 0, suma: 0 };
  }

  const stawki = typUmowy === 'UOP' ? config.zus.uop.pracownik : config.zus.uz.pracownik;
  const emerytalna = roundCurrency(brutto * (stawki.emerytalna / 100));
  const rentowa = roundCurrency(brutto * (stawki.rentowa / 100));

  let chorobowa = 0;
  if (typUmowy === 'UOP') {
    chorobowa = roundCurrency(brutto * (stawki.chorobowa / 100));
  } else if (typUmowy === 'UZ') {
    if (choroboweAktywne && trybSkladek !== 'BEZ_CHOROBOWEJ') {
      chorobowa = roundCurrency(brutto * (stawki.chorobowa / 100));
    }
  }

  return { emerytalna, rentowa, chorobowa, suma: roundCurrency(emerytalna + rentowa + chorobowa) };
};

export const obliczZusPracodawca = (
  brutto: number,
  typUmowy: string,
  trybSkladek: string,
  stawkaWypadkowa: number,
  naliczajFP: boolean,
  naliczajFGSP: boolean,
  config: Config
): ZusSkladkiPracodawca => {
  if (trybSkladek === 'STUDENT_UZ' || trybSkladek === 'INNY_TYTUL') {
    return { emerytalna: 0, rentowa: 0, wypadkowa: 0, fp: 0, fgsp: 0, suma: 0 };
  }

  const stawki = typUmowy === 'UOP' ? config.zus.uop.pracodawca : config.zus.uz.pracodawca;
  const emerytalna = roundCurrency(brutto * (stawki.emerytalna / 100));
  const rentowa = roundCurrency(brutto * (stawki.rentowa / 100));
  const wypadkowa = roundCurrency(brutto * (stawkaWypadkowa / 100));

  let fp = 0;
  let fgsp = 0;

  if (naliczajFP) {
    fp = roundCurrency(brutto * (stawki.fp / 100));
  }

  if (naliczajFGSP) {
    fgsp = roundCurrency(brutto * (stawki.fgsp / 100));
  }

  const suma = roundCurrency(emerytalna + rentowa + wypadkowa + fp + fgsp);
  return { emerytalna, rentowa, wypadkowa, fp, fgsp, suma };
};

export const obliczZdrowotna = (podstawa: number, trybSkladek: string, config: Config): number => {
  if (trybSkladek === 'STUDENT_UZ') return 0;
  return roundCurrency(podstawa * (config.zus.zdrowotna / 100));
};
