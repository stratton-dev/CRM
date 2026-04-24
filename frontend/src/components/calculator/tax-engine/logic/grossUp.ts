import { BaseCalculationResult } from '../../models/calculation';
import { Config } from '../../models/company';
import { obliczPit } from './pit';
import { obliczZusPracownik, obliczZdrowotna } from './zus';

const roundCurrency = (val: number) => Math.round(val * 100) / 100;
const roundTotal = (val: number) => Math.round(val);

export const obliczNettoZBrutto = (brutto: number, params: any, config: Config): BaseCalculationResult => {
  const bruttoInput = roundCurrency(brutto);
  const { typUmowy, trybSkladek, choroboweAktywne, pit2, ulgaMlodych, kupTyp, pitMode } = params;

  const zusPracownik = obliczZusPracownik(bruttoInput, typUmowy, trybSkladek, choroboweAktywne, config);
  const podstawaZdrowotna = roundCurrency(bruttoInput - zusPracownik.suma);
  const zdrowotna = obliczZdrowotna(podstawaZdrowotna, trybSkladek, config);

  let kup = config.pit.kupStandard;
  if (kupTyp === 'PODWYZSZONE') kup = config.pit.kupPodwyzszone;
  if (typUmowy === 'UZ') {
    const podstawaKup = roundCurrency(bruttoInput - zusPracownik.suma);
    if (kupTyp === 'PROC_50') {
      kup = roundCurrency(podstawaKup * (config.pit.uzKupAutorskie / 100));
    } else {
      kup = roundCurrency(podstawaKup * (config.pit.uzKupProc / 100));
    }
  }

  const dochod = bruttoInput - zusPracownik.suma - kup;
  const podstawaPitWartosc = Math.max(0, dochod);
  const podstawaPitZaokr = roundTotal(podstawaPitWartosc);

  let stawkaPit = config.pit.prog1Stawka;
  if (pitMode === 'FLAT_12') {
    stawkaPit = config.pit.prog1Stawka;
  } else if (pitMode === 'FLAT_32') {
    stawkaPit = config.pit.prog2Stawka;
  } else if (pitMode === 'FLAT_0') {
    stawkaPit = 0;
  } else {
    stawkaPit = config.pit.prog1Stawka;
  }

  const pit = obliczPit(podstawaPitWartosc, pit2, ulgaMlodych, stawkaPit);
  const netto = roundCurrency(bruttoInput - zusPracownik.suma - zdrowotna - pit);

  return {
    netto,
    brutto: bruttoInput,
    zusPracownik,
    zdrowotna,
    pit,
    kup,
    podstawaPit: podstawaPitZaokr,
    podstawaZdrowotna,
    stawkaPit,
    podstawaZus: bruttoInput,
  };
};

export const znajdzBruttoDlaNetto = (nettoDocelowe: number, params: any, config: Config): BaseCalculationResult => {
  const target = roundCurrency(nettoDocelowe);

  let bruttoMin = target;
  let bruttoMax = target * 2.5;

  for (let i = 0; i < 30; i++) {
    const mid = (bruttoMin + bruttoMax) / 2;
    const res = obliczNettoZBrutto(mid, params, config);
    if (res.netto < target) bruttoMin = mid;
    else bruttoMax = mid;
  }

  const approx = (bruttoMin + bruttoMax) / 2;
  const scanRange = 5.0;
  const start = Math.floor((approx - scanRange) * 100);
  const end = Math.ceil((approx + scanRange) * 100);

  let bestResult: BaseCalculationResult | null = null;
  let minDiff = Number.MAX_VALUE;

  for (let i = start; i <= end; i++) {
    const candidateBrutto = i / 100;
    if (candidateBrutto <= 0) continue;

    const res = obliczNettoZBrutto(candidateBrutto, params, config);
    const diff = Math.abs(res.netto - target);

    if (diff < minDiff) {
      minDiff = diff;
      bestResult = res;
    } else if (diff < 0.005 && minDiff < 0.005) {
      continue;
    }
  }

  if (!bestResult) {
    return obliczNettoZBrutto(roundCurrency(approx), params, config);
  }

  return bestResult;
};
