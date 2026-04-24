const roundTotal = (val: number) => Math.round(val);

export const obliczPit = (podstawa: number, pit2Kwota: string, ulgaMlodych: boolean, stawkaProcentowa: number): number => {
  if (ulgaMlodych) return 0;

  const podstawaZaokraglona = roundTotal(podstawa);
  let pit = podstawaZaokraglona * (stawkaProcentowa / 100);

  const kwotaZmniejszajaca = parseFloat(pit2Kwota) || 0;
  if (kwotaZmniejszajaca > 0) {
    pit = pit - kwotaZmniejszajaca;
  }

  pit = Math.max(0, pit);
  return roundTotal(pit);
};
