export interface ContactPerson {
  id: string;
  name: string;
  email?: string;
  phone?: string;
}

export interface Firma {
  nazwa: string;
  nip: string;
  regon?: string | null;
  krs?: string | null;
  adres?: string;
  kodPocztowy?: string;
  miasto?: string;
  email?: string;
  telefon?: string;
  osobaKontaktowa?: string;
  branza?: string;
  benefity?: string;
  udzialWProjekcie?: string;
  oszczednosciPrzeszle?: string;
  oszczednosciAktualne?: string;
  inwestycjePlanowane?: string;
  kwotaOszczednosciDeklarowana?: string;
  zadluzenia?: string;
  ryczaltVat?: string;
  zusWysokie?: string;          // 'tak' | 'nie' — czy składki ZUS są wysokie
  wdrazaOszczednosci?: string;  // 'tak' | 'nie' — czy firma wdraża oszczędności
  wyzwanieKlienta?: string; // New field for client challenge
  aiDiagnoza?: string; // New field for AI diagnosis
  kontakty?: ContactPerson[];
  kontaktIds?: string[];
  okres: string;
  stawkaWypadkowa: number;
}

export interface Config {
  branding?: {
    footerLine1?: string;
    footerLine2?: string;
    footerLogoUrl?: string;
  };
  zus: {
    uop: {
      pracownik: { emerytalna: number; rentowa: number; chorobowa: number };
      pracodawca: { emerytalna: number; rentowa: number; wypadkowa: number; fp: number; fgsp: number };
    };
    uz: {
      pracownik: { emerytalna: number; rentowa: number; chorobowa: number };
      pracodawca: { emerytalna: number; rentowa: number; wypadkowa: number; fp: number; fgsp: number };
    };
    zdrowotna: number;
  };
  pit: {
    prog1Limit: number;
    prog1Stawka: number;
    prog2Stawka: number;
    kwotaWolnaRoczna: number;
    kwotaZmniejszajacaMies: number;
    kupStandard: number;
    kupPodwyzszone: number;
    uzKupProc: number;
    uzKupAutorskie: number;
    ulgaMlodziMaxWiek: number;
    ulgaMlodziLimitRoczny: number;
    fpZwolnienieWiekKobieta: number;
    fpZwolnienieWiekMezczyzna: number;
  };
  placaMinimalna: {
    brutto: number;
    netto: number;
  };
  minimalnaKwotaUZ: {
    zasadniczaNetto: number;
  };
  swiadczenie: {
    stawkaPit: number;
    odplatnosc: number;
  };
  /**
   * Eliton Prime commission rates (the single Eliton Prime offer, post 2026-05-30):
   * - `zewnetrzna` (default 22%) — client uses an external accounting office.
   *   Breakdown: 20% Stratton + 2% accounting office.
   * - `wlasna` (20%) — client runs their own HR/accounting. Stratton-only.
   *
   * Field names `standard` / `plus` are kept as legacy aliases so existing
   * historical calculations + PDF templates that read them still resolve.
   * `standard` now maps to `zewnetrzna` (22), `plus` maps to `wlasna` (20).
   */
  prowizja: {
    standard: number;
    plus: number;
    zewnetrzna?: number;
    wlasna?: number;
    legalizacja?: number;
  };
  offerValidDays: number;
}
