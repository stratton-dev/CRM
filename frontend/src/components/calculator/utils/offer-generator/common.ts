import { OfferPdfMeta } from './types';

export const CONTENT_PAGE_COUNT = 8;

export const formatDate = (value?: string) => {
  if (!value) {
    return new Date().toLocaleDateString('pl-PL');
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return value;
  }
  return date.toLocaleDateString('pl-PL');
};

export const safeText = (value?: string) => value && value.trim().length > 0 ? value : '-';

export const abbreviateCompanyName = (name: string) => {
  if (!name) return '-';
  return name.replace(/Spółka z ograniczoną odpowiedzialnością/gui, 'SP. Z O.O.').replace(/Spółka Akcyjna/gui, 'S.A.');
};

export const formatPLN = (value: number) => {
  return new Intl.NumberFormat('pl-PL', {
    style: 'currency',
    currency: 'PLN',
    maximumFractionDigits: 0,
  }).format(value);
};

export const header = (title: string, date: string, firma: any, advisor: any) => `
    <div class="page-header">
      <div class="header-grid">
        <div>
          <div class="header-label">Tytuł sekcji</div>
          <div class="header-value" style="font-size: 11pt; line-height: 1.2; margin-bottom: 4px;">${title}</div>
          <div class="header-sub">Data wykonania: ${date}</div>
        </div>
        <div>
          <div class="header-label">Przygotowano dla</div>
          <div class="header-value">${safeText(firma.preparedFor)}</div>
          <div class="header-sub" style="line-height: 1.2;">${abbreviateCompanyName(firma.nazwa)}</div>
          <div class="header-sub">NIP: ${firma.nip}</div>
        </div>
        <div>
          <div class="header-label">Opracowanie</div>
          <div class="header-value">DZIAŁ ANALIZ FINANSOWYCH</div>
          ${(advisor.phone && advisor.phone !== '-') ? `<div class="header-sub">${advisor.phone}</div>` : ''}
        </div>
        <div class="header-logo">
          <img src="/logo_paper_navy.png" alt="Stratton Prime" />
        </div>
      </div>
    </div>
  `;

export const footerHtml = (pageIndex: number, meta?: OfferPdfMeta) => {
  const footerLogoUrl = meta?.footerLogoUrl || '/logo_paper.png';
  const offerNumber = safeText(meta?.offerNumber);
  const documentLayout = meta?.documentLayout || 'vertical';

  const isFullFooter = pageIndex === CONTENT_PAGE_COUNT;

  if (isFullFooter) {
    return `
      <div class="footer footer-${documentLayout} footer-full">
        <div class="footer-left">
          <img src="${footerLogoUrl}" alt="Stratton Prime" />
        </div>
        <div class="footer-middle">
          <div class="footer-line footer-line-1">Stratton Prime Sp. z o.o. Oddział w Polsce</div>
          <div class="footer-line footer-line-2">ul. Nowy Świat 42/44, 80-299 Gdańsk <span class="separator">|</span> KRS: 0001169520 <span class="separator">|</span> NIP: 5842867357</div>
          <div class="footer-line footer-line-2">Infolinia: +48 730 268 668 <span class="separator">|</span> biuro@stratton-prime.pl</div>
        </div>
        <div class="footer-right">
          <div class="footer-meta">Nr kalkulacji: ${offerNumber}</div>
          <div class="footer-meta">${pageIndex > 0 ? `Strona ${pageIndex}/${CONTENT_PAGE_COUNT}` : ''}</div>
        </div>
      </div>
    `;
  }

  return `
    <div class="footer footer-${documentLayout} footer-minimal">
      <div class="footer-left">
        <img src="${footerLogoUrl}" class="footer-logo-small" alt="Stratton Prime" />
        <div class="footer-secret" style="color: #64748b; font-weight: 500;">
          <span>Stratton Prime Sp. z o.o.</span>
          <span class="separator">|</span>
          <span>ul. Nowy Świat 42/44 Gdańsk</span>
          <span class="separator">|</span>
          <span>NIP: 5842867357</span>
          <span class="separator">|</span>
          <span>www.stratton-prime.pl</span>
        </div>
      </div>
      <div class="footer-right">
        <div class="footer-meta">Nr kalkulacji: ${offerNumber}</div>
        <div class="footer-meta">${pageIndex > 0 ? `Strona ${pageIndex}/${CONTENT_PAGE_COUNT}` : ''}</div>
      </div>
    </div>
  `;
};

export const splitProvision = (prowizja: number, isPlusVariant: boolean) => {
  if (!isPlusVariant) {
    return { fee: prowizja, raise: 0, admin: 0 };
  }
  return {
    fee: prowizja * (20 / 26),
    raise: prowizja * (4 / 26),
    admin: prowizja * (2 / 26),
  };
};
