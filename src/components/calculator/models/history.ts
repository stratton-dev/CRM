import { Config, Firma } from './company';
import { Pracownik } from './employee';

export interface ZapisanaKalkulacja {
  id: string;
  dataUtworzenia: string;
  nazwaFirmy: string;
  liczbaPracownikow: number;
  oszczednoscRoczna: number;
  dane: {
    firma: Firma;
    pracownicy: Pracownik[];
    config: Config;
    prowizjaProc: number;
  };
}
