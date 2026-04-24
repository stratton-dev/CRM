import { header, footerHtml, safeText } from '../common';
import { RenderContext } from '../types';

export const ContactPage = (ctx: RenderContext) => {
  const { advisor, date, meta } = ctx;
  const pageIndex = 8;

  return `
    <div class="page">
      ${header('POTWIERDZENIE I KONTAKT', date, ctx.firma, advisor)}
      
      <div class="page-body page-pad" style="font-family: 'DM Sans', sans-serif;">
        
        <!-- SECTION 1: SOCIAL PROOF -->
        <div style="margin-bottom: 40px;">
            <h1 style="font-size: 24px; font-weight: 700; color: #0B1020; margin-bottom: 24px; letter-spacing: -0.5px;">
                Zaufali nam liderzy swoich branż
            </h1>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                <!-- Card 1: Production -->
                <div style="background-color: white; border: 1px solid #E2E8F0; border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                    <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748B; margin-bottom: 8px; font-weight: 700;">Branża Produkcyjna</div>
                    <div style="font-size: 13px; color: #0F172A; font-weight: 600; margin-bottom: 4px;">120 pracowników</div>
                    <div style="font-size: 12px; color: #475569; line-height: 1.5;">
                        "Odzyskany kapitał: <strong>1.2 mln PLN / rok.</strong> Czas wdrożenia: 4 tygodnie."
                    </div>
                </div>

                <!-- Card 2: Logistics -->
                <div style="background-color: white; border: 1px solid #E2E8F0; border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                    <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748B; margin-bottom: 8px; font-weight: 700;">Branża Logistyczna</div>
                    <div style="font-size: 13px; color: #0F172A; font-weight: 600; margin-bottom: 4px;">50 pracowników</div>
                    <div style="font-size: 12px; color: #475569; line-height: 1.5;">
                        "Wzrost netto pracowników o <strong>11%</strong>. Pełna akceptacja zespołu."
                    </div>
                </div>

                <!-- Card 3: IT -->
                <div style="background-color: white; border: 1px solid #E2E8F0; border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                    <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748B; margin-bottom: 8px; font-weight: 700;">Branża IT/Services</div>
                    <div style="font-size: 13px; color: #0F172A; font-weight: 600; margin-bottom: 4px;">80 pracowników</div>
                    <div style="font-size: 12px; color: #475569; line-height: 1.5;">
                        "Stabilizacja kosztów w obliczu zmian podatkowych. Gwarancja bezpieczeństwa ZUS."
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 2: ADVISOR & CTA GRID -->
        <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 32px; margin-bottom: 40px; align-items: stretch;">
            
            <!-- Advisor Card -->
            <div style="background-color: white; border: 1px solid #E2E8F0; border-radius: 12px; padding: 24px; display: flex; flex-direction: column; align-items: center; text-align: center; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);">
                <div style="width: 80px; height: 80px; background-color: #F1F5F9; border-radius: 50%; margin-bottom: 16px; display: flex; align-items: center; justify-content: center; overflow: hidden; color: #94A3B8;">
                    <!-- User Icon Placeholder -->
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <h3 style="font-size: 16px; font-weight: 700; color: #0F172A; margin-bottom: 4px; font-family: 'DM Sans', sans-serif;">${safeText(advisor.name)}</h3>
                <div style="font-size: 12px; color: #64748B; margin-bottom: 16px; font-weight: 500;">Senior Solution Architect</div>
                <p style="font-size: 11px; color: #94A3B8; margin-bottom: 20px; line-height: 1.4; padding: 0 10px;">
                    "Ekspert w zakresie optymalizacji kosztów pracy z wieloletnim doświadczeniem w branży finansowej."
                </p>
                
                <div style="width: 100%; border-top: 1px solid #F1F5F9; padding-top: 16px; display: flex; flex-direction: column; gap: 8px; font-size: 12px;">
                    <div style="display: flex; align-items: center; gap: 8px; color: #475569;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        ${safeText(advisor.phone)}
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; color: #475569;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        ${safeText(advisor.email)}
                    </div>
                </div>
            </div>

            <!-- Final CTA -->
            <div style="background-color: #0B1020; border-radius: 12px; padding: 32px; color: white; display: flex; flex-direction: column; justify-content: center; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
                <h2 style="font-size: 20px; font-weight: 700; margin-bottom: 24px; font-family: 'DM Sans', sans-serif;">
                    Co musisz zrobić w ciągu najbliższych 24h?
                </h2>
                
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <div style="display: flex; align-items: flex-start; gap: 12px;">
                        <div style="min-width: 24px; height: 24px; background-color: #2563EB; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700;">1</div>
                        <div style="font-size: 13px; line-height: 1.5; color: #E2E8F0;">
                            Potwierdź odbiór oferty (odpowiedz na e-mail).
                        </div>
                    </div>
                    <div style="display: flex; align-items: flex-start; gap: 12px;">
                        <div style="min-width: 24px; height: 24px; background-color: #2563EB; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700;">2</div>
                        <div style="font-size: 13px; line-height: 1.5; color: #E2E8F0;">
                            Wyznacz termin 30-minutowego spotkania w celu szczegółowej akceptacji harmonogramu.
                        </div>
                    </div>
                    <div style="display: flex; align-items: flex-start; gap: 12px;">
                        <div style="min-width: 24px; height: 24px; background-color: #10B981; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700;">3</div>
                        <div style="font-size: 13px; line-height: 1.5; color: #E2E8F0; font-weight: 600;">
                            Uruchom proces oszczędności.
                        </div>
                    </div>
                </div>
            </div>

        </div>

      </div>
      
      ${footerHtml(pageIndex, ctx.meta)}
    </div>
  `;
};
