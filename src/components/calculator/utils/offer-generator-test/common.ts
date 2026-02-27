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

export const header = (title: string, date: string, firma: any, advisor: any) => {
  // Define steps for progress bar
  const steps = [
    { label: 'PODSUMOWANIE', match: ['PODSUMOWANIE'] },
    { label: 'ANALIZA', match: ['ANALIZA'] },
    { label: 'PŁYNNOŚĆ', match: ['PŁYNNOŚĆ', 'CASHFLOW'] },
    { label: 'PRACOWNIK', match: ['KORZYŚĆ', 'PRACOWNIKA'] },
    { label: 'BEZPIECZEŃSTWO', match: ['BEZPIECZEŃSTWO', 'PRAWNE'] },
    { label: 'WDROŻENIE', match: ['WDROŻENIE'] },
    { label: 'KONTAKT', match: ['KONTAKT'] },
  ];

  // Determine current active step index
  const activeIndex = steps.findIndex(s => s.match.some(m => title.toUpperCase().includes(m)));

  const progressBar = `
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; padding-bottom: 12px; border-bottom: 1px solid #E2E8F0;">
      <div style="display: flex; align-items: center; gap: 8px;">
          <img src="/logo_paper_navy.png" alt="Stratton Prime" style="height: 16px; margin-right: 12px;" />
          ${steps.map((step, index) => {
            const isActive = index === activeIndex;
            const isCompleted = index < activeIndex;
            
            let color = '#CBD5E1'; // Gray
            if (isActive) color = '#0F172A'; // Navy (Current)
            if (isCompleted) color = '#10B981'; // Green (Done)

            const fontWeight = isActive ? '700' : '500';
            
            return `
              <div style="display: flex; align-items: center; gap: 6px;">
                ${isCompleted 
                  ? `<div style="width: 6px; height: 6px; border-radius: 50%; background-color: ${color};"></div>` 
                  : `<div style="width: 6px; height: 6px; border-radius: 50%; background-color: ${color}; ${isActive ? '' : 'opacity: 0.5;'}"></div>`
                }
                <span style="font-size: 9px; color: ${color}; font-weight: ${fontWeight}; letter-spacing: 0.05em; text-transform: uppercase;">
                  ${step.label}
                </span>
                ${index < steps.length - 1 ? `<div style="width: 12px; height: 1px; background-color: #E2E8F0; margin-left: 6px;"></div>` : ''}
              </div>
            `;
          }).join('')}
      </div>
      <div style="font-size: 9px; color: #94A3B8; font-weight: 500;">
          ${date}
      </div>
    </div>
  `;

  return `
    <div class="page-header" style="height: auto; margin-bottom: 0;">
      ${progressBar}
    </div>
  `;
};

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
