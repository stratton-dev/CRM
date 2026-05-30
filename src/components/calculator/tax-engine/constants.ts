import { Config } from '../models/company';

export const DEFAULT_CONFIG: Config = {
  branding: {
    footerLine1: 'STRATTON PRIME TO FIRMA DORADZTWA BIZNESOWEGO ODDZIAŁ W POLSCE',
    footerLine2: 'STRATTON PRIME SP. Z O.O. ODDZIAŁ W POLSCE UL. XXXX 80-999 GDAŃSK, KRS: 000000000, NIP: 9999999999, INFOLINIA: 9999999, E-MAIL: BIURO@STRATTON-PRIME.PL, WWW.STRATTON-PRIME.PL',
    footerLogoUrl: '/logo_paper.png',
  },
  zus: {
    uop: {
      pracownik: { emerytalna: 9.76, rentowa: 1.5, chorobowa: 2.45 },
      pracodawca: { emerytalna: 9.76, rentowa: 6.5, wypadkowa: 1.67, fp: 2.45, fgsp: 0.1 },
    },
    uz: {
      pracownik: { emerytalna: 9.76, rentowa: 1.5, chorobowa: 2.45 },
      pracodawca: { emerytalna: 9.76, rentowa: 6.5, wypadkowa: 1.67, fp: 2.45, fgsp: 0.1 },
    },
    zdrowotna: 9.0,
  },
  pit: {
    prog1Limit: 120000,
    prog1Stawka: 12,
    prog2Stawka: 32,
    kwotaWolnaRoczna: 30000,
    kwotaZmniejszajacaMies: 300,
    kupStandard: 250,
    kupPodwyzszone: 300,
    uzKupProc: 20,
    uzKupAutorskie: 50,
    ulgaMlodziMaxWiek: 26,
    ulgaMlodziLimitRoczny: 85528,
    fpZwolnienieWiekKobieta: 55,
    fpZwolnienieWiekMezczyzna: 60,
  },
  placaMinimalna: {
    brutto: 4806,
    netto: 3605.85,
  },
  minimalnaKwotaUZ: {
    zasadniczaNetto: 840.0,
  },
  swiadczenie: {
    stawkaPit: 12,
    odplatnosc: 1.0,
  },
  // Single Eliton Prime offer (post 2026-05-30). Two paths driven by the
  // 'has_external_accounting' flag on crm_client_profiles:
  //   - external accounting (default): 22% = 20% Stratton + 2% accounting
  //   - own accounting:                 20% = Stratton only
  // The legacy keys (standard/plus) stay populated as aliases so older
  // historical calculations + PDF templates that read them keep working.
  prowizja: {
    standard: 22,    // alias for zewnetrzna
    plus: 20,        // alias for wlasna
    zewnetrzna: 22,
    wlasna: 20,
  },
  offerValidDays: 14,
};
