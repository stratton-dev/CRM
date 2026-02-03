"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.obliczPit = void 0;
const roundTotal = (val) => Math.round(val);
const obliczPit = (podstawa, pit2Kwota, ulgaMlodych, stawkaProcentowa) => {
    if (ulgaMlodych)
        return 0;
    const podstawaZaokraglona = roundTotal(podstawa);
    let pit = podstawaZaokraglona * (stawkaProcentowa / 100);
    const kwotaZmniejszajaca = parseFloat(pit2Kwota) || 0;
    if (kwotaZmniejszajaca > 0) {
        pit = pit - kwotaZmniejszajaca;
    }
    pit = Math.max(0, pit);
    return roundTotal(pit);
};
exports.obliczPit = obliczPit;
