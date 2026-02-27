import { RenderContext } from '../types';
import { formatPLN, safeText } from '../common';

export const CoverPage = (ctx: RenderContext) => {
  const { firma, date, meta, statsSelected, advisor } = ctx;
  const includeCover = meta?.includeCover ?? true;
  if (!includeCover) return '';

  const yearlySavings = formatPLN(statsSelected.oszczednoscRoczna);
  const clientLogoUrl = meta?.clientLogoUrl; // Assuming this might exist or we use placeholder logic

  return `
    <div class="page cover-page">
      <div class="cover-content">
        <!-- Top Header Area -->
        <div class="cover-header">
          <div class="cover-brand">
            <img src="/logo_paper_navy.png" alt="Stratton Prime" class="brand-logo" />
          </div>
          <div class="cover-client-logo">
            <div class="client-label">STRATEGIC PARTNER</div>
            <div class="client-name">${firma.nazwa}</div>
          </div>
        </div>

        <!-- Hero Section -->
        <div class="cover-hero">
          <div class="hero-title">
            STRATEGICZNY PLAN<br/>
            OPTYMALIZACJI<br/>
            KOSZTÓW PŁACOWYCH
          </div>
          <div class="hero-hook">
            Uwolnienie <span class="highlight-number">${yearlySavings}</span> rocznego kapitału<br/>
            i wzrost wynagrodzeń netto w modelu Eliton Prime™
          </div>
        </div>

        <!-- Personalization Block -->
        <div class="cover-footer-area">
          <div class="personalization-grid">
            <div class="pers-col">
              <div class="pers-label">PRZYGOTOWANO DLA:</div>
              <div class="pers-value">Zarządu ${firma.nazwa}</div>
            </div>
            <div class="pers-col">
              <div class="pers-label">OPRACOWAŁ:</div>
              <div class="pers-value">${advisor.name || 'Doradca'}, Senior Solution Architect | Stratton Prime</div>
            </div>
          </div>

          <div class="cover-bottom-bar">
            <div class="cover-date">Gdańsk, luty 2026</div>
            <div class="cover-ref">Ref: SP/2026/${safeText(firma.nip).replace(/\D/g, '').slice(0, 4)}</div>
          </div>
        </div>
      </div>
      <div class="cover-accent-bg"></div>
    </div>
  `;
};
