/**
 * Adapter: ZapisanaKalkulacja + OfferPdfMeta → EBS HTML string.
 *
 * Zachowuje tę samą sygnaturę co `buildOfferPdfHtml` ze starego generatora,
 * dzięki czemu można podmienić import w `useCalculatorStore.ts` bez zmiany
 * call-site'ów (print preview, html2canvas → CRM, załączniki email).
 */

import type { ZapisanaKalkulacja } from '../../models/history';
import { obliczWariantPodzial, obliczWariantStandard } from '../../tax-engine';
import { renderEbsOfferHtml } from './offerTemplate';
import type { EbsOfferData } from './types';

export interface BuildEbsHtmlMeta {
  offerNumber?: string;
  validUntil?: string;
  advisorName?: string;
  advisorEmail?: string;
  advisorPhone?: string;
  hasExternalAccounting?: boolean;
}

export function buildEbsOfferHtml(item: ZapisanaKalkulacja, meta?: BuildEbsHtmlMeta): string {
  const firma = item.dane.firma;
  const pracownicy = item.dane.pracownicy;
  const config = item.dane.config;
  const prowizjaProc = item.dane.prowizjaProc || 22;

  const details = pracownicy.map((p) => {
    const standard = obliczWariantStandard(p, firma.stawkaWypadkowa, config);
    const podzial = obliczWariantPodzial(p, firma.stawkaWypadkowa, p.nettoZasadnicza, config);
    return { standard, podzial };
  });

  const sumaKosztStandard = details.reduce((acc, w) => acc + w.standard.kosztPracodawcy, 0);
  const sumaKosztSplit = details.reduce((acc, w) => acc + w.podzial.kosztPracodawcy, 0);
  const sumaNettoSwiadczen = details.reduce((acc, w) => acc + w.podzial.swiadczenie.netto, 0);

  const prowizja = sumaNettoSwiadczen * (prowizjaProc / 100);
  const oszczednoscBrutto = sumaKosztStandard - sumaKosztSplit;
  const oszczednoscNetto = oszczednoscBrutto - prowizja;
  const oszczednoscRoczna = oszczednoscNetto * 12;
  const sredniaOszczednoscNaEtat = pracownicy.length > 0 ? oszczednoscNetto / pracownicy.length : 0;

  const hasExternalAccounting = meta?.hasExternalAccounting ?? (prowizjaProc >= 22);

  const data: EbsOfferData = {
    firma: {
      nazwa: firma.nazwa || 'Klient',
      nip: firma.nip,
      adres: firma.adres,
      miasto: firma.miasto,
      kodPocztowy: firma.kodPocztowy,
      osobaKontaktowa: firma.osobaKontaktowa,
      email: firma.email,
      telefon: firma.telefon,
      okres: firma.okres,
    },
    pracownicyCount: pracownicy.length,
    provisionPct: prowizjaProc,
    hasExternalAccounting,
    podsumowanie: {
      sumaKosztStandard,
      sumaKosztSplit,
      oszczednoscBrutto,
      oszczednoscNetto,
      oszczednoscRoczna,
      prowizja,
      sredniaOszczednoscNaEtat,
    },
    advisor: {
      name: meta?.advisorName || 'Doradca Stratton Prime',
      email: meta?.advisorEmail,
      phone: meta?.advisorPhone,
    },
    offerNumber: meta?.offerNumber,
    validUntil: meta?.validUntil,
  };

  return renderEbsOfferHtml(data);
}
