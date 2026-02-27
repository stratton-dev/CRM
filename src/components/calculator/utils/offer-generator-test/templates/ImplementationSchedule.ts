import { header, footerHtml, safeText } from '../common';
import { RenderContext } from '../types';

export const ImplementationSchedule = (ctx: RenderContext) => {
  const { prowizjaProc, validUntil, meta } = ctx;
  const pageIndex = 7;

  return `
    <div class="page">
      ${header('WDROŻENIE I WARUNKI', ctx.date, ctx.firma, ctx.advisor)}
      
      <div class="page-body page-pad" style="font-family: 'DM Sans', sans-serif;">
        
        <!-- SECTION 1: HEADER -->
        <div style="margin-bottom: 40px;">
            <h1 style="font-size: 24px; font-weight: 700; color: #0B1020; margin-bottom: 4px; letter-spacing: -0.5px; line-height: 1.2;">
                Harmonogram
            </h1>
            <p style="font-size: 13px; color: #64748B; margin: 0; max-width: 600px;">
                Plan działania dla <span style="font-weight: 600; color: #334155;">${ctx.firma.nazwa || 'Twojej Firmy'}</span>. Od analizy do wdrożenia.
            </p>
        </div>

        <!-- SECTION 2: TIMELINE (Stepper) -->
        <div style="position: relative; margin-bottom: 48px; padding-left: 20px;">
            <!-- Vertical Line -->
            <div style="position: absolute; top: 24px; bottom: 24px; left: 34px; width: 2px; background-color: #E2E8F0;"></div>

            <!-- Step 1 -->
            <div style="display: flex; gap: 24px; margin-bottom: 32px; position: relative; z-index: 10;">
                <div style="min-width: 32px; height: 32px; background-color: #EFF6FF; border: 2px solid #2563EB; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #2563EB; font-weight: 700; font-size: 14px;">1</div>
                <div>
                    <h3 style="font-size: 16px; font-weight: 700; color: #0F172A; margin-bottom: 4px; font-family: 'DM Sans', sans-serif;">Warsztat Zerowy <span style="font-weight: 400; color: #64748B; font-size: 14px;">(Tydzień 1)</span></h3>
                    <p style="font-size: 13px; color: #475569; line-height: 1.5; margin-bottom: 4px;">
                        Konfiguracja modelu pod specyfikę K.D.L.
                    </p>
                    <div style="font-size: 11px; color: #94A3B8; font-style: italic;">Zaangażowanie Zarządu: ok. 2h</div>
                </div>
            </div>

            <!-- Step 2 -->
             <div style="display: flex; gap: 24px; margin-bottom: 32px; position: relative; z-index: 10;">
                <div style="min-width: 32px; height: 32px; background-color: #EFF6FF; border: 2px solid #2563EB; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #2563EB; font-weight: 700; font-size: 14px;">2</div>
                <div>
                    <h3 style="font-size: 16px; font-weight: 700; color: #0F172A; margin-bottom: 4px; font-family: 'DM Sans', sans-serif;">Przygotowanie i Komunikacja <span style="font-weight: 400; color: #64748B; font-size: 14px;">(Tydzień 2)</span></h3>
                    <p style="font-size: 13px; color: #475569; line-height: 1.5;">
                        Opracowanie pełnej dokumentacji i wsparcie działu HR w rozmowach z zespołem. Warsztaty dla pracowników.
                    </p>
                </div>
            </div>

            <!-- Step 3 -->
             <div style="display: flex; gap: 24px; margin-bottom: 32px; position: relative; z-index: 10;">
                <div style="min-width: 32px; height: 32px; background-color: #EFF6FF; border: 2px solid #2563EB; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #2563EB; font-weight: 700; font-size: 14px;">3</div>
                <div>
                    <h3 style="font-size: 16px; font-weight: 700; color: #0F172A; margin-bottom: 4px; font-family: 'DM Sans', sans-serif;">Start Operacyjny <span style="font-weight: 400; color: #64748B; font-size: 14px;">(Tydzień 3)</span></h3>
                    <p style="font-size: 13px; color: #475569; line-height: 1.5;">
                        Pierwsze rozliczenie wynagrodzeń w nowym modelu Eliton Prime™. Pełna asysta naszego zespołu.
                    </p>
                </div>
            </div>

            <!-- Step 4 -->
             <div style="display: flex; gap: 24px; position: relative; z-index: 10;">
                <div style="min-width: 32px; height: 32px; background-color: #10B981; border: 2px solid #10B981; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                    <!-- Check Icon -->
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div>
                     <h3 style="font-size: 16px; font-weight: 700; color: #0F172A; margin-bottom: 4px; font-family: 'DM Sans', sans-serif;">Monitoring i Raport <span style="font-weight: 400; color: #64748B; font-size: 14px;">(Tydzień 4+)</span></h3>
                    <p style="font-size: 13px; color: #475569; line-height: 1.5;">
                        Potwierdzenie pierwszych oszczędności na kontach firmy. Cykliczne raportowanie wyników.
                    </p>
                </div>
            </div>
        </div>

        <!-- SECTION 3: COMMERCIAL TERMS -->
        <div style="background-color: #0B1020; border-radius: 12px; padding: 32px; color: white; display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-bottom: 24px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
            
            <!-- Left: Pricing Model -->
            <div>
                 <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: #94A3B8; margin-bottom: 8px; font-weight: 600;">Model Rozliczenia</div>
                 <div style="font-size: 24px; font-weight: 700; color: white; margin-bottom: 8px; font-family: 'DM Sans', sans-serif;">100% Success Fee</div>
                 <p style="font-size: 13px; color: #CBD5E1; line-height: 1.5; margin: 0;">
                    Nasze wynagrodzenie to <strong>${prowizjaProc}%</strong> od realnie wygenerowanych i potwierdzonych oszczędności netto.
                 </p>
            </div>

            <!-- Right: Guarantee -->
            <div style="border-left: 1px solid rgba(255,255,255,0.1); padding-left: 32px;">
                 <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: #94A3B8; margin-bottom: 8px; font-weight: 600;">Gwarancja Bezpieczeństwa</div>
                 <div style="font-size: 18px; font-weight: 700; color: #34D399; margin-bottom: 8px; font-family: 'DM Sans', sans-serif;">
                    Brak oszczędności = brak kosztów
                 </div>
                 <div style="font-size: 12px; color: #94A3B8; margin-top: 12px; display: flex; align-items: center; gap: 6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    Oferta ważna do: ${safeText(validUntil)}
                 </div>
            </div>

        </div>

        <!-- SECTION 4: TRUST ELEMENT -->
        <div style="text-align: center; max-width: 600px; margin: 0 auto;">
           <p style="font-size: 12px; color: #64748B; font-style: italic; line-height: 1.6;">
              "Nie jesteśmy dostawcą oprogramowania. Jesteśmy Twoim partnerem w zarządzaniu kosztami pracy. Nasze wynagrodzenie jest nagrodą za Twój realny zysk."
           </p>
        </div>

      </div>
      
      ${footerHtml(pageIndex, ctx.meta)}
    </div>
  `;
};
