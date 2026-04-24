import { header, footerHtml } from '../common';
import { RenderContext } from '../types';

export const LegalAndBenefits = (ctx: RenderContext) => {
  const pageIndex = 6;

  return `
    <div class="page">
      ${header('BEZPIECZEŃSTWO PRAWNE', ctx.date, ctx.firma, ctx.advisor)}
      
      <div class="page-body page-pad" style="font-family: 'DM Sans', sans-serif;">
        
        <!-- SECTION 1: HEADER -->
        <div style="margin-bottom: 40px; text-align: center;">
            <div style="width: 60px; height: 60px; background-color: #0F172A; border-radius: 50%; margin: 0 auto 16px auto; display: flex; align-items: center; justify-content: center; color: #C6A15B;">
                <!-- Scale Icon -->
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/></svg>
            </div>
            <h1 style="font-size: 24px; font-weight: 700; color: #0F172A; margin-bottom: 8px; letter-spacing: -0.5px; line-height: 1.2;">
                Fundamenty Bezpieczeństwa
            </h1>
            <p style="font-size: 13px; color: #64748B; margin: 0; max-width: 500px; margin-left: auto; margin-right: auto;">
                Bezpieczeństwo wdrożenia w <span style="font-weight: 600; color: #334155;">${ctx.firma.nazwa || 'Twojej Firmie'}</span> jest dla nas priorytetem.
            </p>
        </div>

        <!-- SECTION 2: THREE PILLARS -->
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px; margin-bottom: 48px;">
            
            <!-- Pillar 1 -->
            <div style="text-align: center;">
                <div style="width: 48px; height: 48px; background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; margin: 0 auto 16px auto; display: flex; align-items: center; justify-content: center; color: #0F172A;">
                    <!-- Document/Stamp Icon -->
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>
                </div>
                <h3 style="font-size: 16px; font-weight: 700; color: #0F172A; margin-bottom: 8px; font-family: 'DM Sans', sans-serif;">Zgodność z KIS i ZUS</h3>
                <p style="font-size: 12px; color: #475569; line-height: 1.6;">
                    Wykorzystujemy wyłącznie sprawdzone ścieżki interpretacyjne, potwierdzone Indywidualnymi Interpretacjami Podatkowymi oraz orzecznictwem Sądu Najwyższego.
                </p>
            </div>

            <!-- Pillar 2 -->
             <div style="text-align: center;">
                <div style="width: 48px; height: 48px; background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; margin: 0 auto 16px auto; display: flex; align-items: center; justify-content: center; color: #0F172A;">
                    <!-- Shield Success Icon -->
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                </div>
                <h3 style="font-size: 16px; font-weight: 700; color: #0F172A; margin-bottom: 8px; font-family: 'DM Sans', sans-serif;">Gwarancja Sukcesu</h3>
                <p style="font-size: 12px; color: #475569; line-height: 1.6;">
                    Zarabiamy tylko wtedy, gdy Ty oszczędzasz (Success Fee). Nasze wynagrodzenie jest bezpośrednio powiązane z Twoim realnym zyskiem. Zero kosztów stałych.
                </p>
            </div>

            <!-- Pillar 3 -->
             <div style="text-align: center;">
                <div style="width: 48px; height: 48px; background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; margin: 0 auto 16px auto; display: flex; align-items: center; justify-content: center; color: #0F172A;">
                    <!-- Pen/Signature Icon -->
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19l7-7 3 3-7 7-3-3z"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/><path d="M2 2l7.586 7.586"/><circle cx="11" cy="11" r="2"/></svg>
                </div>
                <h3 style="font-size: 16px; font-weight: 700; color: #0F172A; margin-bottom: 8px; font-family: 'DM Sans', sans-serif;">Pełna Obsługa Prawna</h3>
                <p style="font-size: 12px; color: #475569; line-height: 1.6;">
                    W ramach współpracy zapewniamy komplet dokumentacji, regulaminów i wsparcie w komunikacji z organami kontrolnymi.
                </p>
            </div>

        </div>

        <!-- SECTION 3: LEGAL CONSTRUCTION -->
        <div style="background-color: #F8FAFC; border-left: 4px solid #0F172A; padding: 24px; margin-bottom: 40px; border-radius: 0 8px 8px 0;">
            <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: #64748B; margin-bottom: 12px; font-weight: 700;">Konstrukcja Prawna</div>
            <p style="font-family: 'Georgia', serif; font-size: 14px; color: #334155; margin: 0; line-height: 1.6; font-style: italic;">
                "Model opiera się na optymalizacji składników wynagrodzenia zgodnie z Art. 18 ust. 1 ustawy o systemie ubezpieczeń społecznych oraz aktualnymi wytycznymi dotyczącymi ulg i zwolnień (np. ulga B+R, autorskie koszty uzyskania przychodu – zależnie od wyniku audytu)."
            </p>
        </div>

        <!-- SECTION 4: GUARANTEE -->
        <div style="border: 2px solid #E2E8F0; padding: 32px; border-radius: 12px; text-align: center; position: relative; margin-top: auto;">
            <!-- Badge in corner -->
            <div style="position: absolute; top: -16px; right: 24px; background-color: #C6A15B; color: white; padding: 4px 12px; border-radius: 9999px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em;">
                Zero Ryzyka
            </div>
            
            <h3 style="font-size: 18px; font-weight: 700; color: #0F172A; margin-bottom: 12px;">Gwarancja Odpowiedzialności</h3>
            <p style="font-size: 14px; color: #475569; margin: 0; padding: 0 20px;">
                Bierzemy pełną odpowiedzialność za wdrożone procesy. Naszym celem jest stabilizacja kosztów Twojej firmy, a nie ich czasowe omijanie.
            </p>
        </div>

      </div>
      
      ${footerHtml(pageIndex, ctx.meta)}
    </div>
  `;
};
