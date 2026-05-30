import type { ZapisanaKalkulacja } from '../../models/history';
import { buildEbsOfferHtml, type BuildEbsHtmlMeta } from './buildEbsHtml';

export { renderEbsOfferHtml } from './offerTemplate';
export { buildEbsOfferHtml } from './buildEbsHtml';
export type { BuildEbsHtmlMeta } from './buildEbsHtml';
export type {
  EbsOfferData,
  EbsOfferFirma,
  EbsOfferPodsumowanie,
  EbsOfferAdvisor,
} from './types';

export const ebsOfferPdfGenerator = {
  generateOfferPDF: (item: ZapisanaKalkulacja, meta?: BuildEbsHtmlMeta) => {
    const htmlContent = buildEbsOfferHtml(item, meta);
    const printWindow = window.open('', '_blank');
    if (printWindow) {
      printWindow.document.open();
      printWindow.document.write(htmlContent);
      printWindow.document.close();
      printWindow.focus();
      setTimeout(() => { printWindow.print(); }, 500);
    }
  },
};
