export interface ContactPerson {
  id: string;
  name: string;
  email?: string;
  phone?: string;
}

export interface Firma {
  nazwa: string;
  nip: string;
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
  prowizja: {
    standard: number;
    plus: number;
  };
  offerValidDays: number;
}
