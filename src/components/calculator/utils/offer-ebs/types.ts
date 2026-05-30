export interface EbsOfferFirma {
  nazwa: string;
  nip?: string;
  adres?: string;
  miasto?: string;
  kodPocztowy?: string;
  osobaKontaktowa?: string;
  email?: string;
  telefon?: string;
  okres?: string;
}

export interface EbsOfferPodsumowanie {
  sumaKosztStandard: number;
  sumaKosztSplit: number;
  oszczednoscBrutto: number;
  oszczednoscNetto: number;
  oszczednoscRoczna: number;
  prowizja: number;
  sredniaOszczednoscNaEtat: number;
}

export interface EbsOfferAdvisor {
  name: string;
  email?: string;
  phone?: string;
}

export interface EbsOfferData {
  firma: EbsOfferFirma;
  pracownicyCount: number;
  provisionPct: number;
  hasExternalAccounting?: boolean;
  podsumowanie: EbsOfferPodsumowanie;
  advisor: EbsOfferAdvisor;
  logoDataUri?: string;
  offerNumber?: string;
  validUntil?: string;
}
