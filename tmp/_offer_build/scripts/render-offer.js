"use strict";
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
const node_fs_1 = __importDefault(require("node:fs"));
const node_path_1 = __importDefault(require("node:path"));
const offerPdfGenerator_js_1 = require("../src/components/calculator/utils/offerPdfGenerator.js");
const constants_js_1 = require("../src/components/calculator/tax-engine/constants.js");
const sampleEmployees = Array.from({ length: 10 }).map((_, index) => {
    const typUmowy = (index < 7 ? 'UOP' : 'UZ');
    return {
        id: index + 1,
        imie: 'Pracownik',
        nazwisko: `${index + 1}`,
        dataUrodzenia: '1990-01-01',
        plec: 'M',
        typUmowy,
        trybSkladek: 'PELNE',
        choroboweAktywne: true,
        pit2: '300',
        ulgaMlodych: false,
        kupTyp: typUmowy === 'UZ' ? 'PROC_20' : 'STANDARD',
        nettoDocelowe: 5200,
        nettoZasadnicza: typUmowy === 'UZ' ? constants_js_1.DEFAULT_CONFIG.minimalnaKwotaUZ.zasadniczaNetto : constants_js_1.DEFAULT_CONFIG.placaMinimalna.netto,
        pitMode: 'AUTO',
        skladkaFP: true,
        skladkaFGSP: true,
    };
});
const sample = {
    id: 'sample-1',
    dataUtworzenia: new Date().toISOString(),
    nazwaFirmy: 'Przykładowa Sp. z o.o.',
    liczbaPracownikow: sampleEmployees.length,
    oszczednoscRoczna: 0,
    dane: {
        firma: {
            nazwa: 'Przykładowa Sp. z o.o.',
            nip: '1234567890',
            adres: 'ul. Przykładowa 1',
            kodPocztowy: '00-000',
            miasto: 'Warszawa',
            email: 'biuro@przyklad.pl',
            telefon: '+48 500 000 000',
            osobaKontaktowa: 'Jan Kowalski',
            branza: 'Usługi',
            benefity: 'Pakiet medyczny',
            udzialWProjekcie: '100% pracowników',
            oszczednosciPrzeszle: 'brak danych',
            oszczednosciAktualne: 'brak danych',
            inwestycjePlanowane: 'Automatyzacja procesów',
            kwotaOszczednosciDeklarowana: '200 000 zł',
            zadluzenia: 'brak',
            ryczaltVat: 'VAT',
            kontakty: [],
            kontaktIds: [],
            okres: new Date().toISOString().slice(0, 7),
            stawkaWypadkowa: 1.67,
        },
        pracownicy: sampleEmployees,
        config: constants_js_1.DEFAULT_CONFIG,
        prowizjaProc: 28,
    },
};
const html = (0, offerPdfGenerator_js_1.buildOfferPdfHtml)(sample, {
    offerNumber: 'SP/2026/1234567890/001',
    validUntil: new Date(Date.now() + 14 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10),
    advisorName: 'Opiekun Stratton',
    advisorEmail: 'opiekun@stratton.pl',
    advisorPhone: '+48 500 111 222',
    includeCover: true,
    includeTOC: true,
    standardRate: 28,
    plusRate: 26,
});
const outDir = process.argv[2] ? node_path_1.default.resolve(process.argv[2]) : node_path_1.default.resolve(process.cwd(), 'crm', 'tmp');
node_fs_1.default.mkdirSync(outDir, { recursive: true });
const outFile = node_path_1.default.join(outDir, 'offer-render.html');
node_fs_1.default.writeFileSync(outFile, html, 'utf8');
// eslint-disable-next-line no-console
console.log(outFile);
