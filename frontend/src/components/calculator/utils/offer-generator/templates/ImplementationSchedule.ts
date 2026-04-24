import { header, footerHtml, formatPLN } from '../common';
import { RenderContext } from '../types';

export const ImplementationSchedule = (ctx: RenderContext) => {
  const { statsStandard, statsPlus, validUntil, meta } = ctx;
  const pageIndex = 7;

  return `
      <div class="page">
        ${header('Harmonogram wdrożenia i warunki', ctx.date, ctx.firma, ctx.advisor)}
        <div class="page-body page-pad">
          <div class="section-title">Etapy wdrożenia</div>
          <div class="timeline-container">
            <div class="timeline-line"></div>
            <div class="timeline-steps">
              <div class="tl-step">
                <div class="tl-dot-wrapper"><div class="tl-dot"></div></div>
                <span class="tl-week">Tydzień 1</span>
                <div class="tl-title">Analiza</div>
                <div class="tl-desc">Weryfikacja danych kadrowych i przygotowanie procesu.</div>
              </div>
              <div class="tl-step">
                <div class="tl-dot-wrapper"><div class="tl-dot"></div></div>
                <span class="tl-week">Tydzień 2</span>
                <div class="tl-title">Komunikacja</div>
                <div class="tl-desc">Przedstawienie modelu pracownikom i zebranie zgód.</div>
              </div>
              <div class="tl-step">
                <div class="tl-dot-wrapper"><div class="tl-dot"></div></div>
                <span class="tl-week">Tydzień 3</span>
                <div class="tl-title">Wdrożenie</div>
                <div class="tl-desc">Uruchomienie świadczeń i pierwsze rozliczenie.</div>
              </div>
              <div class="tl-step">
                <div class="tl-dot-wrapper"><div class="tl-dot"></div></div>
                <span class="tl-week">Tydzień 4</span>
                <div class="tl-title">Monitoring</div>
                <div class="tl-desc">Raportowanie efektów i optymalizacje.</div>
              </div>
            </div>
          </div>
          <div class="grid-two" style="margin-top: 18px;">
            <div class="info-card">
              <div class="info-label">Cena Eliton Standard</div>
              <div class="info-value">${formatPLN(statsStandard.prowizja)} / mies. (prowizja)</div>
            </div>
            <div class="info-card">
              <div class="info-label">Cena Eliton Plus</div>
              <div class="info-value">${formatPLN(statsPlus.prowizja)} / mies. (prowizja)</div>
            </div>
            <div class="info-card">
              <div class="info-label">Ważność oferty</div>
              <div class="info-value">${validUntil || '-'}</div>
            </div>
            <div class="info-card">
              <div class="info-label">Umowa</div>
              <div class="info-value">Podpisanie umowy następuje po akceptacji oferty.</div>
            </div>
          </div>
          ${footerHtml(pageIndex, meta)}
        </div>
      </div>
  `;
};
