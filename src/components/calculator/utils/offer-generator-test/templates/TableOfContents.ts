import { CONTENT_PAGE_COUNT, header, footerHtml } from '../common';
import { RenderContext } from '../types';

export const TableOfContents = (ctx: RenderContext) => {
  const { meta, date, firma, advisor } = ctx;
  const includeTOC = meta?.includeTOC ?? true;
  if (!includeTOC) return '';

  const tocItems = [
    'Ilustracja finansowa oszczędności',
    'Wizualizacja opłat i wynagrodzeń',
    'Scenariusze rocznych oszczędności',
    'Symulacja miesiąc do miesiąca',
    'Tabela listy płac (10 pracowników)',
    'Gwarancje / korzyści / konstrukcja prawna',
    'Harmonogram wdrożenia i warunki',
    'Firmy podobne i kontakt',
  ];

  return `
    <div class="page">
      ${header('Spis treści', date, firma, advisor)}
      <div class="page-body page-pad">
        <div class="section-title">Spis treści</div>
        <div class="toc-list">
          ${tocItems.map((item, index) => `
            <div class="toc-item"><span>${index + 1}. ${item}</span><span>${index + 1}/${CONTENT_PAGE_COUNT}</span></div>
          `).join('')}
        </div>
        ${footerHtml(0, meta)}
      </div>
    </div>
  `;
};
