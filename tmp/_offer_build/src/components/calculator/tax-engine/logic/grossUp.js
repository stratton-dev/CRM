"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.znajdzBruttoDlaNetto = exports.obliczNettoZBrutto = void 0;
const pit_1 = require("./pit");
const zus_1 = require("./zus");
const roundCurrency = (val) => Math.round(val * 100) / 100;
const roundTotal = (val) => Math.round(val);
const obliczNettoZBrutto = (brutto, params, config) => {
    const bruttoInput = roundCurrency(brutto);
    const { typUmowy, trybSkladek, choroboweAktywne, pit2, ulgaMlodych, kupTyp, pitMode } = params;
    const zusPracownik = (0, zus_1.obliczZusPracownik)(bruttoInput, typUmowy, trybSkladek, choroboweAktywne, config);
    const podstawaZdrowotna = roundCurrency(bruttoInput - zusPracownik.suma);
    const zdrowotna = (0, zus_1.obliczZdrowotna)(podstawaZdrowotna, trybSkladek, config);
    let kup = config.pit.kupStandard;
    if (kupTyp === 'PODWYZSZONE')
        kup = config.pit.kupPodwyzszone;
    if (typUmowy === 'UZ') {
        const podstawaKup = roundCurrency(bruttoInput - zusPracownik.suma);
        if (kupTyp === 'PROC_50') {
            kup = roundCurrency(podstawaKup * (config.pit.uzKupAutorskie / 100));
        }
        else {
            kup = roundCurrency(podstawaKup * (config.pit.uzKupProc / 100));
        }
    }
    const dochod = bruttoInput - zusPracownik.suma - kup;
    const podstawaPitWartosc = Math.max(0, dochod);
    const podstawaPitZaokr = roundTotal(podstawaPitWartosc);
    let stawkaPit = config.pit.prog1Stawka;
    if (pitMode === 'FLAT_12') {
        stawkaPit = config.pit.prog1Stawka;
    }
    else if (pitMode === 'FLAT_32') {
        stawkaPit = config.pit.prog2Stawka;
    }
    else if (pitMode === 'FLAT_0') {
        stawkaPit = 0;
    }
    else {
        stawkaPit = config.pit.prog1Stawka;
    }
    const pit = (0, pit_1.obliczPit)(podstawaPitWartosc, pit2, ulgaMlodych, stawkaPit);
    const netto = roundCurrency(bruttoInput - zusPracownik.suma - zdrowotna - pit);
    return {
        netto,
        brutto: bruttoInput,
        zusPracownik,
        zdrowotna,
        pit,
        kup,
        podstawaPit: podstawaPitZaokr,
        podstawaZdrowotna,
        stawkaPit,
        podstawaZus: bruttoInput,
    };
};
exports.obliczNettoZBrutto = obliczNettoZBrutto;
const znajdzBruttoDlaNetto = (nettoDocelowe, params, config) => {
    const target = roundCurrency(nettoDocelowe);
    let bruttoMin = target;
    let bruttoMax = target * 2.5;
    for (let i = 0; i < 30; i++) {
        const mid = (bruttoMin + bruttoMax) / 2;
        const res = (0, exports.obliczNettoZBrutto)(mid, params, config);
        if (res.netto < target)
            bruttoMin = mid;
        else
            bruttoMax = mid;
    }
    const approx = (bruttoMin + bruttoMax) / 2;
    const scanRange = 5.0;
    const start = Math.floor((approx - scanRange) * 100);
    const end = Math.ceil((approx + scanRange) * 100);
    let bestResult = null;
    let minDiff = Number.MAX_VALUE;
    for (let i = start; i <= end; i++) {
        const candidateBrutto = i / 100;
        if (candidateBrutto <= 0)
            continue;
        const res = (0, exports.obliczNettoZBrutto)(candidateBrutto, params, config);
        const diff = Math.abs(res.netto - target);
        if (diff < minDiff) {
            minDiff = diff;
            bestResult = res;
        }
        else if (diff < 0.005 && minDiff < 0.005) {
            continue;
        }
    }
    if (!bestResult) {
        return (0, exports.obliczNettoZBrutto)(roundCurrency(approx), params, config);
    }
    return bestResult;
};
exports.znajdzBruttoDlaNetto = znajdzBruttoDlaNetto;
