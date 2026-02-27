import { OfferPdfMeta } from '../types';

export const CoverPage = (firma: any, date: string, meta?: OfferPdfMeta) => {
  const includeCover = meta?.includeCover ?? true;
  if (!includeCover) return '';

  return `
    <div class="page">
      <div class="cover">
        <img class="cover-watermark" src="/logo.svg" alt="" />
        <div>
          <div class="cover-title">STRATTON PRIME</div>
          <div class="cover-sub">Oferta Eliton Prime<sup>TM</sup></div>
        </div>
        <div>
          <div style="font-size: 12px; text-transform: uppercase; opacity: 0.7;">Przygotowano dla:</div>
          <div style="font-size: 24px; font-weight: 600;">${firma.nazwa}</div>
          <div style="font-size: 14px; opacity: 0.8;">NIP: ${firma.nip}</div>
        </div>
        <div style="font-size: 10px; opacity: 0.6;">${date}</div>
      </div>
    </div>
  `;
};
