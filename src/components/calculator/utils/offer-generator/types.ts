export type OfferPdfMeta = {
  offerNumber?: string;
  validUntil?: string;
  advisorName?: string;
  advisorEmail?: string;
  advisorPhone?: string;
  includeCover?: boolean;
  includeTOC?: boolean;
  standardRate?: number;
  plusRate?: number;
  footerLine1?: string;
  footerLine2?: string;
  footerLogoUrl?: string;
  documentLayout?: 'horizontal' | 'vertical';
};

export type OfferStats = {
  standard: {
    kosztPracodawcy: number;
    zusPracodawca: number;
    brutto: number;
    netto: number;
    zusPracownik: number;
    pit: number;
  };
  stratton: {
    kosztPracodawcy: number;
    zusPracodawca: number;
    brutto: number;
    netto: number;
    zusPracownik: number;
    pit: number;
    prowizja: number;
  };
  oszczednoscRoczna: number;
  oszczednoscMiesieczna: number;
  prowizja: number;
  totalCostModel: number;
  totalCostStandard: number;
};

export type BaseTotals = {
  sumaKosztStandard: number;
  sumaKosztPodzial: number;
  sumaBruttoSwiadczen: number;
  sumaNettoSwiadczen: number;
  standard: {
    kosztPracodawcy: number;
    zusPracodawca: number;
    brutto: number;
    netto: number;
    zusPracownik: number;
    pit: number;
  };
  stratton: {
    kosztPracodawcy: number;
    zusPracodawca: number;
    brutto: number;
    netto: number;
    zusPracownik: number;
    pit: number;
  };
};

export interface RenderContext {
  meta?: OfferPdfMeta;
  firma: any;
  advisor: {
    name: string;
    email: string;
    phone: string;
  };
  date: string;
  statsSelected: OfferStats;
  statsStandard: OfferStats;
  statsPlus: OfferStats;
  base: BaseTotals;
  employeeCount: number;
  isPlus: boolean;
  prowizjaProc: number;
  contracts: {
    UOP: { count: number; koszt: number };
    UZ: { count: number; koszt: number };
  };
  monthlyFeesAfterWages: number;
  annualFeesAfterWages: number;
  threeYearEffect: number;
  avgPerEmployee: number;
  monthSimulation: any[];
  annualScenarios: any[];
  payrollRows: any[];
  validUntil: string;
  offerNumber: string;
}

export interface PageProps {
  content: string;
  header: string;
  footer: string;
  pageNum: number;
  totalPages: number;
  meta?: OfferPdfMeta;
}
