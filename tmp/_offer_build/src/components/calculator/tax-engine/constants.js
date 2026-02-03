"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.DEFAULT_CONFIG = void 0;
exports.DEFAULT_CONFIG = {
    zus: {
        uop: {
            pracownik: { emerytalna: 9.76, rentowa: 1.5, chorobowa: 2.45 },
            pracodawca: { emerytalna: 9.76, rentowa: 6.5, wypadkowa: 1.67, fp: 2.45, fgsp: 0.1 },
        },
        uz: {
            pracownik: { emerytalna: 9.76, rentowa: 1.5, chorobowa: 2.45 },
            pracodawca: { emerytalna: 9.76, rentowa: 6.5, wypadkowa: 1.67, fp: 2.45, fgsp: 0.1 },
        },
        zdrowotna: 9.0,
    },
    pit: {
        prog1Limit: 120000,
        prog1Stawka: 12,
        prog2Stawka: 32,
        kwotaWolnaRoczna: 30000,
        kwotaZmniejszajacaMies: 300,
        kupStandard: 250,
        kupPodwyzszone: 300,
        uzKupProc: 20,
        uzKupAutorskie: 50,
        ulgaMlodziMaxWiek: 26,
        ulgaMlodziLimitRoczny: 85528,
        fpZwolnienieWiekKobieta: 55,
        fpZwolnienieWiekMezczyzna: 60,
    },
    placaMinimalna: {
        brutto: 4806,
        netto: 3605.85,
    },
    minimalnaKwotaUZ: {
        zasadniczaNetto: 840.0,
    },
    swiadczenie: {
        stawkaPit: 12,
        odplatnosc: 1.0,
    },
    prowizja: {
        standard: 28,
        plus: 26,
    },
};
