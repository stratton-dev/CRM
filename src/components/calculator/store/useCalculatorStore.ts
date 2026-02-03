import { computed, ref, watch } from 'vue';
import { defineStore } from 'pinia';
import { useToastStore } from '@/stores/toast';
import { useAuthStore } from '@/stores/auth';
import { useSessionStore } from '@/stores/session';
import { api } from '@/api/client';
import { Config, Firma } from '../models/company';
import { GlobalneWyniki } from '../models/calculation';
import { Pracownik } from '../models/employee';
import { ZapisanaKalkulacja } from '../models/history';
import { DEFAULT_CONFIG } from '../tax-engine/constants';
import { obliczWariantPodzial, obliczWariantStandard } from '../tax-engine';
import { excelGenerator } from '../utils/excelGenerator';
import { buildOfferPdfHtml } from '../utils/offerPdfGenerator';
import { offerPdfGenerator } from '../utils/offerPdfGenerator';

interface ComparisonState {
  activeCard: 'STANDARD' | 'PRIME';
  customStandardRate: number;
  customPrimeRate: number;
}

interface CalculatorContext {
  meetingId: string | null;
  clientId: string | null;
  source: 'quick' | 'detailed' | null;
}

export const DEFAULT_FIRMA_STATE: Firma = {
  nazwa: '',
  nip: '',
  adres: '',
  kodPocztowy: '',
  miasto: '',
  email: '',
  telefon: '',
  osobaKontaktowa: '',
  kontakty: [],
  kontaktIds: [],
  okres: new Date().toISOString().slice(0, 7),
  stawkaWypadkowa: 1.67,
};

const normalizeFirma = (input?: Partial<Firma> | null): Firma => {
  const kontakty = Array.isArray(input?.kontakty) ? input?.kontakty : [];
  const kontaktIds = Array.isArray(input?.kontaktIds) ? input?.kontaktIds : [];
  const base: Firma = {
    ...DEFAULT_FIRMA_STATE,
    ...(input || {}),
    kontakty,
    kontaktIds,
  };

  if ((base.kontakty?.length ?? 0) === 0 && (base.osobaKontaktowa || base.email || base.telefon)) {
    const legacyContact = {
      id: 'main',
      name: base.osobaKontaktowa || 'Kontakt',
      email: base.email || '',
      phone: base.telefon || '',
    };
    base.kontakty = [legacyContact];
    base.kontaktIds = (base.kontaktIds?.length ?? 0) ? base.kontaktIds : [legacyContact.id];
  }

  if ((base.kontaktIds?.length ?? 0) === 0 && (base.kontakty?.length ?? 0) > 0) {
    base.kontaktIds = [base.kontakty?.[0].id || 'main'];
  }

  return base;
};

const mergeConfig = (saved?: Config | null): Config => {
  if (!saved) return DEFAULT_CONFIG;
  return {
    ...DEFAULT_CONFIG,
    ...saved,
    zus: {
      ...DEFAULT_CONFIG.zus,
      ...(saved.zus || {}),
      uop: {
        ...DEFAULT_CONFIG.zus.uop,
        ...(saved.zus?.uop || {}),
      },
      uz: {
        ...DEFAULT_CONFIG.zus.uz,
        ...(saved.zus?.uz || {}),
      },
    },
    pit: {
      ...DEFAULT_CONFIG.pit,
      ...(saved.pit || {}),
    },
  };
};

export const useCalculatorStore = defineStore('calculator', () => {
  const toast = useToastStore();
  const auth = useAuthStore();
  const session = useSessionStore();
  const configLoading = ref(false);
  const configError = ref<string | null>(null);

  const firma = ref<Firma>((() => {
    const saved = localStorage.getItem('kalkulator_firma');
    return normalizeFirma(saved ? JSON.parse(saved) : DEFAULT_FIRMA_STATE);
  })());

  const config = ref<Config>((() => {
    const saved = localStorage.getItem('kalkulator_config');
    if (!saved) return DEFAULT_CONFIG;
    try {
      return mergeConfig(JSON.parse(saved));
    } catch (error) {
      console.warn('Failed to parse saved config, falling back to default', error);
      return DEFAULT_CONFIG;
    }
  })());

  const pracownicy = ref<Pracownik[]>((() => {
    const saved = localStorage.getItem('kalkulator_pracownicy');
    return saved ? JSON.parse(saved) : [];
  })());

  const historia = ref<ZapisanaKalkulacja[]>((() => {
    const saved = localStorage.getItem('kalkulator_historia');
    return saved ? JSON.parse(saved) : [];
  })());

  const prowizjaProc = ref(26);
  const comparisonState = ref<ComparisonState>({
    activeCard: 'PRIME',
    customStandardRate: 28,
    customPrimeRate: 26,
  });

  const isCalculating = ref(false);
  const context = ref<CalculatorContext>({ meetingId: null, clientId: null, source: null });

  const wyniki = computed<GlobalneWyniki | null>(() => {
    if (pracownicy.value.length === 0) return null;

    const szczegoly = pracownicy.value.map((p) => {
      const standard = obliczWariantStandard(p, firma.value.stawkaWypadkowa, config.value);
      const podzial = obliczWariantPodzial(p, firma.value.stawkaWypadkowa, p.nettoZasadnicza, config.value);
      return {
        pracownik: p,
        standard,
        podzial,
        oszczednosc: standard.kosztPracodawcy - podzial.kosztPracodawcy,
      };
    });

    const sumaKosztStandard = szczegoly.reduce((acc, w) => acc + w.standard.kosztPracodawcy, 0);
    const sumaKosztPodzial = szczegoly.reduce((acc, w) => acc + w.podzial.kosztPracodawcy, 0);
    const sumaBruttoSwiadczen = szczegoly.reduce((acc, w) => acc + w.podzial.swiadczenie.brutto, 0);
    const oszczednoscBrutto = sumaKosztStandard - sumaKosztPodzial;
    const prowizja = sumaBruttoSwiadczen * (prowizjaProc.value / 100);
    const oszczednoscNetto = oszczednoscBrutto - prowizja;

    return {
      szczegoly,
      podsumowanie: {
        sumaKosztStandard,
        sumaKosztPodzial,
        sumaBruttoSwiadczen,
        oszczednoscBrutto,
        prowizja,
        oszczednoscNetto,
        oszczednoscRoczna: oszczednoscNetto * 12,
        sredniaOszczednoscNaEtat: pracownicy.value.length > 0 ? oszczednoscNetto / pracownicy.value.length : 0,
      },
    };
  });

  const deleteFromHistory = (id: string) => {
    historia.value = historia.value.filter((item) => item.id !== id);
  };

  const setContext = (payload: Partial<CalculatorContext>) => {
    context.value = { ...context.value, ...payload };
  };

  const addEmployee = () => {
    const id = Date.now();
    const employee = {
      id,
      imie: 'Pracownik',
      nazwisko: `#${id.toString().slice(-4)}`,
      dataUrodzenia: '1990-01-01',
      plec: 'M' as const,
      typUmowy: 'UOP' as const,
      trybSkladek: 'PELNE',
      choroboweAktywne: true,
      pit2: '300',
      ulgaMlodych: false,
      kupTyp: 'STANDARD',
      nettoDocelowe: 5000,
      nettoZasadnicza: config.value.placaMinimalna.netto,
      pitMode: 'AUTO' as const,
      skladkaFP: true,
      skladkaFGSP: true,
    };
    pracownicy.value = [...pracownicy.value, employee];
    return employee;
  };

  const updateEmployee = (id: number, patch: Partial<Pracownik>) => {
    pracownicy.value = pracownicy.value.map((item) =>
      item.id === id ? { ...item, ...patch } : item
    );
  };

  const removeEmployee = (id: number) => {
    pracownicy.value = pracownicy.value.filter((item) => item.id !== id);
  };

  const duplicateEmployee = (id: number) => {
    const target = pracownicy.value.find((item) => item.id === id);
    if (!target) return;
    const clone = { ...target, id: Date.now() };
    pracownicy.value = [...pracownicy.value, clone];
  };

  const clearEmployees = () => {
    pracownicy.value = [];
  };

  const resetSession = () => {
    firma.value = normalizeFirma({ ...DEFAULT_FIRMA_STATE, okres: new Date().toISOString().slice(0, 7) });
    pracownicy.value = [];
    prowizjaProc.value = 26;
  };

  const saveToHistory = (): ZapisanaKalkulacja | null => {
    if (pracownicy.value.length === 0) {
      toast.error('Brak pracowników do zapisania.');
      return null;
    }
    if (!wyniki.value) return null;

    const entry: ZapisanaKalkulacja = {
      id: Date.now().toString(),
      dataUtworzenia: new Date().toISOString(),
      nazwaFirmy: firma.value.nazwa || 'Bez nazwy',
      liczbaPracownikow: pracownicy.value.length,
      oszczednoscRoczna: wyniki.value.podsumowanie.oszczednoscRoczna,
      dane: {
        firma: firma.value,
        pracownicy: pracownicy.value,
        config: config.value,
        prowizjaProc: prowizjaProc.value,
      },
    };

    historia.value = [entry, ...historia.value];
    toast.success('Kalkulacja została zapisana.');
    return entry;
  };

  const loadFromHistory = (item: ZapisanaKalkulacja, skipConfirm = false): boolean => {
    if (!skipConfirm && !confirm(`Wczytać kalkulację dla firmy ${item.nazwaFirmy}? Bieżące niezapisane zmiany zostaną utracone.`)) {
      return false;
    }
    firma.value = normalizeFirma(item.dane.firma);
    pracownicy.value = item.dane.pracownicy;
    config.value = mergeConfig(item.dane.config);
    prowizjaProc.value = item.dane.prowizjaProc || 28;
    toast.info(`Wczytano ofertę: ${item.nazwaFirmy}`);
    return true;
  };

  const loadBackup = (data: any, skipConfirm = false): boolean => {
    try {
      if (data && data.dane && data.dane.firma && Array.isArray(data.dane.pracownicy)) {
        if (!skipConfirm && !confirm(`Wczytać kopię zapasową dla firmy ${data.nazwaFirmy || 'Bez nazwy'}?`)) return false;

        firma.value = normalizeFirma(data.dane.firma);
        pracownicy.value = data.dane.pracownicy;
        config.value = mergeConfig(data.dane.config);
        if (data.dane.prowizjaProc) prowizjaProc.value = data.dane.prowizjaProc;

        toast.success('Przywrócono kopię zapasową.');
        return true;
      }

      if (Array.isArray(data) && data.length > 0 && data[0].imie) {
        if (!skipConfirm && !confirm(`Plik wygląda na listę ${data.length} pracowników (bez ustawień firmy). Zaimportować?`)) return false;
        pracownicy.value = data;
        toast.success(`Zaimportowano ${data.length} pracowników z pliku.`);
        return true;
      }

      toast.error('Nieprawidłowy format pliku JSON.');
      return false;
    } catch (error) {
      console.error(error);
      toast.error('Błąd krytyczny podczas przetwarzania pliku.');
      return false;
    }
  };

  const downloadCalculation = (item: ZapisanaKalkulacja) => {
    const dataStr = `data:text/json;charset=utf-8,${encodeURIComponent(JSON.stringify(item, null, 2))}`;
    const downloadAnchorNode = document.createElement('a');
    const fileName = `Kalkulacja_${item.nazwaFirmy.replace(/[^a-z0-9]/gi, '_')}_${item.dataUtworzenia.slice(0, 10)}.json`;

    downloadAnchorNode.setAttribute('href', dataStr);
    downloadAnchorNode.setAttribute('download', fileName);
    document.body.appendChild(downloadAnchorNode);
    downloadAnchorNode.click();
    downloadAnchorNode.remove();
    toast.info('Pobrano plik archiwum JSON.');
  };

  const generateOfferPdf = async (
    item: ZapisanaKalkulacja,
    options?: { documentLayout?: 'horizontal' | 'vertical' },
  ) => {
    let offer: any = null;
    try {
      offer = await ensureOffer(item);
    } catch (error) {
      console.error('ensureOffer failed', error);
    }
    const advisor = session.currentUser;
    const offerNumber = offer?.number || buildFallbackOfferNumber(item);
    const fallbackValidUntil = (() => {
      const date = new Date();
      date.setDate(date.getDate() + getOfferValidDays());
      return date.toISOString().slice(0, 10);
    })();
    const validUntil = offer?.valid_to || offer?.expires_at || fallbackValidUntil;
    persistOfferSnapshot(item, {
      offerNumber,
      validUntil,
      advisorName: advisor?.name || advisor?.email || 'Doradca',
      advisorEmail: advisor?.email || '',
      advisorPhone: advisor?.phone || '',
    });
    offerPdfGenerator.generateOfferPDF(item, {
      offerNumber,
      validUntil,
      advisorName: advisor?.name || advisor?.email || 'Doradca',
      advisorEmail: advisor?.email || '',
      advisorPhone: advisor?.phone || '',
      includeCover: true,
      includeTOC: true,
      standardRate: comparisonState.value.customStandardRate,
      plusRate: comparisonState.value.customPrimeRate,
      footerLine1: config.value.branding?.footerLine1,
      footerLine2: config.value.branding?.footerLine2,
      footerLogoUrl: config.value.branding?.footerLogoUrl,
      documentLayout: options?.documentLayout || 'vertical',
    });

    toast.info('Generowanie PDF...');
  };

  const buildOfferStorageKey = () => {
    if (context.value.meetingId) return `offer_snapshot_meeting_${context.value.meetingId}`;
    if (context.value.clientId) return `offer_snapshot_client_${context.value.clientId}`;
    return null;
  };

  const persistOfferSnapshot = (item: ZapisanaKalkulacja, meta: Record<string, any>) => {
    const key = buildOfferStorageKey();
    if (!key) return;
    localStorage.setItem(key, JSON.stringify({ snapshot: item, meta }));
  };

  const readOfferSnapshot = () => {
    const key = buildOfferStorageKey();
    if (!key) return null;
    const raw = localStorage.getItem(key);
    if (!raw) return null;
    try {
      return JSON.parse(raw);
    } catch {
      return null;
    }
  };

  const buildOfferEmailAttachments = async () => {
    const stored = readOfferSnapshot();
    if (!stored?.snapshot) return null;
    const item: ZapisanaKalkulacja = stored.snapshot;
    const meta = stored.meta || {};

    const details = item.dane.pracownicy.map((p) => {
      const standard = obliczWariantStandard(p, item.dane.firma.stawkaWypadkowa, item.dane.config);
      const podzial = obliczWariantPodzial(p, item.dane.firma.stawkaWypadkowa, p.nettoZasadnicza, item.dane.config);
      return { pracownik: p, standard, podzial, oszczednosc: standard.kosztPracodawcy - podzial.kosztPracodawcy };
    });

    const sumaKosztStandard = details.reduce((acc, w) => acc + w.standard.kosztPracodawcy, 0);
    const sumaKosztPodzial = details.reduce((acc, w) => acc + w.podzial.kosztPracodawcy, 0);
    const sumaBruttoSwiadczen = details.reduce((acc, w) => acc + w.podzial.swiadczenie.brutto, 0);
    const oszczednoscBrutto = sumaKosztStandard - sumaKosztPodzial;
    const prowizja = sumaBruttoSwiadczen * (item.dane.prowizjaProc / 100);
    const oszczednoscNetto = oszczednoscBrutto - prowizja;

    const wynikiSnapshot = {
      szczegoly: details,
      podsumowanie: {
        sumaKosztStandard,
        sumaKosztPodzial,
        sumaBruttoSwiadczen,
        oszczednoscBrutto,
        prowizja,
        oszczednoscNetto,
        oszczednoscRoczna: oszczednoscNetto * 12,
        sredniaOszczednoscNaEtat: details.length > 0 ? oszczednoscNetto / details.length : 0,
      },
    };

    const excelResult: any = await excelGenerator.generateManagementReport(
      { firma: item.dane.firma, wyniki: wynikiSnapshot, prowizjaProc: item.dane.prowizjaProc },
      { returnBuffer: true }
    );

    const excelBuffer = excelResult?.buffer;
    const excelFileName = excelResult?.fileName || `Raport_${item.nazwaFirmy || 'Firma'}`;
    const toBase64 = (buffer: ArrayBuffer) => {
      const bytes = new Uint8Array(buffer);
      let binary = '';
      bytes.forEach((b) => { binary += String.fromCharCode(b); });
      return btoa(binary);
    };
    const excelBase64 = excelBuffer ? toBase64(excelBuffer) : null;

    const offerHtml = buildOfferPdfHtml(item, {
      offerNumber: meta.offerNumber,
      validUntil: meta.validUntil,
      advisorName: meta.advisorName,
      advisorEmail: meta.advisorEmail,
      advisorPhone: meta.advisorPhone,
    });

    return {
      offerHtml,
      offerFileName: `Oferta_${item.nazwaFirmy || 'Firma'}.pdf`,
      excelBase64,
      excelFileName: excelFileName.endsWith('.xlsx') ? excelFileName : `${excelFileName}.xlsx`,
    };
  };

  const buildFallbackOfferNumber = (item: ZapisanaKalkulacja) => {
    const year = new Date().getFullYear();
    const nip = (item.dane.firma.nip || '').replace(/\D+/g, '') || '0000000000';
    return `SP/${year}/${nip}/TMP`;
  };

  const getOfferValidDays = () => {
    const days = Math.round(config.value.offerValidDays ?? 14);
    return Math.min(Math.max(days, 1), 365);
  };

  const ensureOffer = async (item: ZapisanaKalkulacja) => {
    const meetingId = context.value.meetingId;
    const clientId = context.value.clientId;
    const meetingIdNum = meetingId ? Number(meetingId) : null;
    const clientIdNum = clientId ? Number(clientId) : null;

    if (!meetingIdNum && !clientIdNum) return null;

    try {
      if (meetingIdNum) {
        const { data } = await api.get('/v1/offers', {
          params: { meeting_id: meetingIdNum, per_page: 1 },
        });
        const list = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : [];
        if (list.length > 0) return list[0];
      }

      const today = new Date();
      const validFrom = today.toISOString().slice(0, 10);
      const validToDate = new Date(today.getTime() + getOfferValidDays() * 24 * 60 * 60 * 1000);
      const validTo = validToDate.toISOString().slice(0, 10);

      const payload: Record<string, any> = {
        meeting_id: meetingIdNum || undefined,
        company_id: clientIdNum || undefined,
        valid_from: validFrom,
        valid_to: validTo,
        expires_at: validTo,
        commission_percent: prowizjaProc.value / 100,
        stratton_raise_percent: prowizjaProc.value === 26 ? 0.04 : 0,
        currency: 'PLN',
      };

      const { data } = await api.post('/v1/offers', payload);
      return data;
    } catch (error) {
      console.error(error);
      return null;
    }
  };

  const generateExcelReport = async (item: ZapisanaKalkulacja) => {
    const details = item.dane.pracownicy.map((p) => {
      const standard = obliczWariantStandard(p, item.dane.firma.stawkaWypadkowa, item.dane.config);
      const podzial = obliczWariantPodzial(p, item.dane.firma.stawkaWypadkowa, p.nettoZasadnicza, item.dane.config);
      return { pracownik: p, standard, podzial, oszczednosc: standard.kosztPracodawcy - podzial.kosztPracodawcy };
    });

    const sumaKosztStandard = details.reduce((acc, w) => acc + w.standard.kosztPracodawcy, 0);
    const sumaKosztPodzial = details.reduce((acc, w) => acc + w.podzial.kosztPracodawcy, 0);
    const sumaBruttoSwiadczen = details.reduce((acc, w) => acc + w.podzial.swiadczenie.brutto, 0);
    const oszczednoscBrutto = sumaKosztStandard - sumaKosztPodzial;
    const prowizja = sumaBruttoSwiadczen * (item.dane.prowizjaProc / 100);
    const oszczednoscNetto = oszczednoscBrutto - prowizja;

    const wynikiSnapshot = {
      szczegoly: details,
      podsumowanie: {
        sumaKosztStandard,
        sumaKosztPodzial,
        sumaBruttoSwiadczen,
        oszczednoscBrutto,
        prowizja,
        oszczednoscNetto,
        oszczednoscRoczna: oszczednoscNetto * 12,
        sredniaOszczednoscNaEtat: details.length > 0 ? oszczednoscNetto / details.length : 0,
      },
    };

    await excelGenerator.generateManagementReport({
      firma: item.dane.firma,
      wyniki: wynikiSnapshot,
      prowizjaProc: item.dane.prowizjaProc,
    });
    toast.info('Generowanie Excel...');
  };

  const generateDetailedExcelReport = async (item: ZapisanaKalkulacja) => {
    const details = item.dane.pracownicy.map((p) => {
      const standard = obliczWariantStandard(p, item.dane.firma.stawkaWypadkowa, item.dane.config);
      const podzial = obliczWariantPodzial(p, item.dane.firma.stawkaWypadkowa, p.nettoZasadnicza, item.dane.config);
      return { pracownik: p, standard, podzial, oszczednosc: standard.kosztPracodawcy - podzial.kosztPracodawcy };
    });

    const sumaKosztStandard = details.reduce((acc, w) => acc + w.standard.kosztPracodawcy, 0);
    const sumaKosztPodzial = details.reduce((acc, w) => acc + w.podzial.kosztPracodawcy, 0);
    const sumaBruttoSwiadczen = details.reduce((acc, w) => acc + w.podzial.swiadczenie.brutto, 0);
    const oszczednoscBrutto = sumaKosztStandard - sumaKosztPodzial;
    const prowizja = sumaBruttoSwiadczen * (item.dane.prowizjaProc / 100);
    const oszczednoscNetto = oszczednoscBrutto - prowizja;

    const wynikiSnapshot = {
      szczegoly: details,
      podsumowanie: {
        sumaKosztStandard,
        sumaKosztPodzial,
        sumaBruttoSwiadczen,
        oszczednoscBrutto,
        prowizja,
        oszczednoscNetto,
        oszczednoscRoczna: oszczednoscNetto * 12,
        sredniaOszczednoscNaEtat: details.length > 0 ? oszczednoscNetto / details.length : 0,
      },
    };

    await excelGenerator.generateDetailedReport({
      firma: item.dane.firma,
      wyniki: wynikiSnapshot,
      prowizjaProc: item.dane.prowizjaProc,
    });
    toast.info('Generowanie szczegółowego Excel...');
  };

  const generateImportTemplate = async (rows: number) => {
    await excelGenerator.generateImportTemplate(rows);
  };

  const updateMeetingOfferStatus = async (status: 'preparing' | 'generated' | 'sent') => {
    const auth = useAuthStore();
    if (!auth.enabled || !context.value.meetingId) return;
    const payload: Record<string, any> = { offer_status: status };
    if (status !== 'preparing') {
      payload.calculation_shown = true;
    }
    try {
      await api.patch(`/v1/meetings/${context.value.meetingId}`, payload);
    } catch (error) {
      console.error(error);
    }
  };

  const updateClientStatus = async (status: string) => {
    const auth = useAuthStore();
    if (!auth.enabled || !context.value.clientId) return;
    try {
      await api.patch(`/v1/clients/${context.value.clientId}`, { status });
      toast.success('Zaktualizowano status klienta.');
    } catch (error) {
      console.error('Failed to update client status', error);
    }
  };

  const saveCalculationToApi = async () => {
    if (!auth.enabled) return null;
    const meetingId = context.value.meetingId;
    const clientId = context.value.clientId;
    if (!meetingId && !clientId) {
      toast.warning('Brak aktywnego klienta lub spotkania do zapisu.');
      return null;
    }
    if (!wyniki.value) return null;

    const validUntil = new Date();
    validUntil.setDate(validUntil.getDate() + getOfferValidDays());

    const payload = {
      meeting_id: meetingId ? Number(meetingId) : undefined,
      client_id: clientId ? Number(clientId) : undefined,
      employee_count: pracownicy.value.length,
      savings_amount: Math.max(0, Math.round(wyniki.value.podsumowanie.oszczednoscNetto)),
      value_json: {
        firma: firma.value,
        pracownicy: pracownicy.value,
        config: config.value,
        prowizjaProc: prowizjaProc.value,
        wyniki: wyniki.value.podsumowanie,
      },
      valid_until: validUntil.toISOString().slice(0, 10),
      status: 'GENERATED',
    };

    try {
      const { data } = await api.post('/v1/calculations', payload);
      toast.success('Kalkulacja zapisana w CRM.');
      return data;
    } catch (error) {
      console.error(error);
      toast.error('Nie udało się zapisać kalkulacji w CRM.');
      throw error;
    }
  };

  const updateCalculationStatus = async (calcId: string, status: string) => {
    if (!auth.enabled) return;
    try {
      await api.patch(`/v1/calculations/${calcId}`, { status });
    } catch (error) {
      console.error(error);
    }
  };

  const syncHistoryToApiByNip = async () => {
    if (!auth.enabled) {
      toast.warning('Synchronizacja dostępna tylko po zalogowaniu.');
      return;
    }

    const entries = historia.value.filter((item) => (item.dane?.firma?.nip || '').trim().length > 0);
    if (entries.length === 0) {
      toast.info('Brak zapisów z NIP do synchronizacji.');
      return;
    }

    const normalizeNip = (value: string) => value.replace(/\D+/g, '');

    let page = 1;
    const perPage = 200;
    const clients: any[] = [];
    for (;;) {
      const { data } = await api.get('/v1/clients', { params: { per_page: perPage, page } });
      const list = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : [];
      clients.push(...list);
      if (list.length < perPage) break;
      page += 1;
    }

    const clientByNip = new Map<string, any>();
    clients.forEach((client) => {
      const nip = normalizeNip(String(client?.nip || ''));
      if (nip) clientByNip.set(nip, client);
    });

    const calculationsCache = new Map<string, any[]>();
    const loadCalculations = async (clientId: string) => {
      if (calculationsCache.has(clientId)) return calculationsCache.get(clientId) as any[];
      const collected: any[] = [];
      let calcPage = 1;
      for (;;) {
        const { data } = await api.get('/v1/calculations', {
          params: { per_page: perPage, page: calcPage, client_id: clientId },
        });
        const list = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : [];
        collected.push(...list);
        if (list.length < perPage) break;
        calcPage += 1;
      }
      calculationsCache.set(clientId, collected);
      return collected;
    };

    let created = 0;
    let skipped = 0;
    let missingClient = 0;

    for (const entry of entries) {
      const nip = normalizeNip(entry.dane.firma.nip || '');
      const client = nip ? clientByNip.get(nip) : null;
      if (!client) {
        missingClient += 1;
        continue;
      }

      const clientId = String(client.id);
      const existing = await loadCalculations(clientId);
      const expectedSavings = Math.max(0, Math.round(Number(entry.oszczednoscRoczna || 0) / 12));
      const duplicate = existing.some((calc) =>
        Number(calc.employee_count || 0) === Number(entry.liczbaPracownikow || 0)
        && Number(calc.savings_amount || 0) === expectedSavings
      );

      if (duplicate) {
        skipped += 1;
        continue;
      }

      const baseDate = new Date(entry.dataUtworzenia || new Date().toISOString());
      const validUntil = new Date(baseDate.getTime());
      validUntil.setDate(validUntil.getDate() + getOfferValidDays());

      const payload = {
        client_id: Number(clientId),
        employee_count: Number(entry.liczbaPracownikow || 0),
        savings_amount: expectedSavings,
        valid_until: validUntil.toISOString().slice(0, 10),
        status: 'PREPARING',
      };

      try {
        await api.post('/v1/calculations', payload);
        created += 1;
      } catch (error) {
        console.error('syncHistoryToApiByNip failed', error);
      }
    }

    toast.success(`Synchronizacja zakończona: dodano ${created}, pominięto ${skipped}, brak klienta ${missingClient}.`);
  };

  const fetchConfigFromApi = async () => {
    if (!auth.enabled) return;
    configLoading.value = true;
    configError.value = null;
    try {
      const { data } = await api.get('/v1/calculator-configs', {
        params: {
          scope: 'global',
          key: 'crm_calculator',
          per_page: 1,
        },
      });
      const list = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : [];
      const latest = list[0];
      if (latest?.value_json) {
        config.value = mergeConfig(latest.value_json);
      }
    } catch (error: any) {
      configError.value = error?.response?.data?.message || error?.message || 'Nie udało się pobrać konfiguracji kalkulatora.';
    } finally {
      configLoading.value = false;
    }
  };

  const saveConfigToApi = async () => {
    if (!auth.enabled) return;
    configLoading.value = true;
    configError.value = null;
    try {
      await api.post('/v1/calculator-configs', {
        scope: 'global',
        key: 'crm_calculator',
        value_json: config.value,
        is_active: true,
      });
    } catch (error: any) {
      configError.value = error?.response?.data?.message || error?.message || 'Nie udało się zapisać konfiguracji kalkulatora.';
      throw error;
    } finally {
      configLoading.value = false;
    }
  };

  watch(firma, (value) => {
    localStorage.setItem('kalkulator_firma', JSON.stringify(value));
  }, { deep: true });

  watch(config, (value) => {
    if (!auth.enabled) {
      localStorage.setItem('kalkulator_config', JSON.stringify(value));
    }
  }, { deep: true });

  watch(pracownicy, (value) => {
    localStorage.setItem('kalkulator_pracownicy', JSON.stringify(value));
  }, { deep: true });

  watch(historia, (value) => {
    localStorage.setItem('kalkulator_historia', JSON.stringify(value));
  }, { deep: true });

  watch(
    () => auth.isAuthenticated,
    (isAuthed) => {
      if (auth.enabled && isAuthed) fetchConfigFromApi();
    },
    { immediate: true }
  );

  return {
    firma,
    config,
    pracownicy,
    historia,
    configLoading,
    configError,
    prowizjaProc,
    comparisonState,
    isCalculating,
    context,
    wyniki,
    deleteFromHistory,
    setContext,
    addEmployee,
    updateEmployee,
    removeEmployee,
    duplicateEmployee,
    clearEmployees,
    resetSession,
    saveToHistory,
    loadFromHistory,
    loadBackup,
    downloadCalculation,
    generateOfferPdf,
    generateExcelReport,
    generateDetailedExcelReport,
    generateImportTemplate,
    updateMeetingOfferStatus,
    updateClientStatus,
    saveCalculationToApi,
    updateCalculationStatus,
    buildOfferEmailAttachments,
    syncHistoryToApiByNip,
    fetchConfigFromApi,
    saveConfigToApi,
  };
});
