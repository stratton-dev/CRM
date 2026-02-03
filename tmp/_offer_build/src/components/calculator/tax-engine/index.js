"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.obliczWariantPodzial = exports.obliczWariantStandard = exports.znajdzBruttoDlaNetto = exports.DEFAULT_CONFIG = void 0;
const pit_1 = require("./logic/pit");
const grossUp_1 = require("./logic/grossUp");
Object.defineProperty(exports, "znajdzBruttoDlaNetto", { enumerable: true, get: function () { return grossUp_1.znajdzBruttoDlaNetto; } });
const zus_1 = require("./logic/zus");
var constants_1 = require("./constants");
Object.defineProperty(exports, "DEFAULT_CONFIG", { enumerable: true, get: function () { return constants_1.DEFAULT_CONFIG; } });
const obliczWariantStandard = (pracownik, stawkaWypadkowa, config) => {
    const params = {
        typUmowy: pracownik.typUmowy,
        trybSkladek: pracownik.trybSkladek,
        choroboweAktywne: pracownik.choroboweAktywne,
        pit2: pracownik.pit2,
        ulgaMlodych: pracownik.ulgaMlodych,
        kupTyp: pracownik.kupTyp,
        pitMode: pracownik.pitMode || 'AUTO',
    };
    const wynik = (0, grossUp_1.znajdzBruttoDlaNetto)(pracownik.nettoDocelowe, params, config);
    const zusPracodawca = (0, zus_1.obliczZusPracodawca)(wynik.brutto, pracownik.typUmowy, pracownik.trybSkladek, stawkaWypadkowa, pracownik.skladkaFP, pracownik.skladkaFGSP, config);
    return {
        ...wynik,
        zusPracodawca,
        kosztPracodawcy: wynik.brutto + zusPracodawca.suma,
    };
};
exports.obliczWariantStandard = obliczWariantStandard;
const obliczWariantPodzial = (pracownik, stawkaWypadkowa, nettoZasadnicza, config) => {
    const paramsZasadnicza = {
        typUmowy: pracownik.typUmowy,
        trybSkladek: pracownik.trybSkladek,
        choroboweAktywne: pracownik.choroboweAktywne,
        pit2: pracownik.pit2,
        ulgaMlodych: pracownik.ulgaMlodych,
        kupTyp: pracownik.kupTyp,
        pitMode: pracownik.pitMode || 'AUTO',
    };
    const wynikZasadnicza = (0, grossUp_1.znajdzBruttoDlaNetto)(nettoZasadnicza, paramsZasadnicza, config);
    const swiadczenieNetto = pracownik.nettoDocelowe - nettoZasadnicza;
    if (swiadczenieNetto <= 0) {
        const zusPracodawca = (0, zus_1.obliczZusPracodawca)(wynikZasadnicza.brutto, pracownik.typUmowy, pracownik.trybSkladek, stawkaWypadkowa, pracownik.skladkaFP, pracownik.skladkaFGSP, config);
        return {
            zasadnicza: { ...wynikZasadnicza, zusPracodawca, nettoGotowka: nettoZasadnicza },
            swiadczenie: { brutto: 0, netto: 0, zaliczka: 0, kup: 0 },
            pit: {
                lacznyPrzychod: wynikZasadnicza.brutto,
                podstawa: wynikZasadnicza.podstawaPit,
                kup: wynikZasadnicza.kup,
                kupOdZasadniczej: wynikZasadnicza.kup,
                kupOdSwiadczenia: 0,
                stawka: wynikZasadnicza.stawkaPit,
                kwota: wynikZasadnicza.pit,
                kwotaOdZasadniczej: wynikZasadnicza.pit,
                kwotaOdSwiadczenia: 0,
            },
            kosztPracodawcy: wynikZasadnicza.brutto + zusPracodawca.suma,
            nettoCalkowite: nettoZasadnicza,
            doWyplatyGotowka: nettoZasadnicza,
            doWyplatySwiadczenie: 0,
            doWyplaty: nettoZasadnicza,
        };
    }
    let stawkaPitDlaSwiadczenia = wynikZasadnicza.stawkaPit / 100;
    if (pracownik.pitMode === 'FLAT_12')
        stawkaPitDlaSwiadczenia = config.pit.prog1Stawka / 100;
    else if (pracownik.pitMode === 'FLAT_32')
        stawkaPitDlaSwiadczenia = config.pit.prog2Stawka / 100;
    else if (pracownik.pitMode === 'FLAT_0')
        stawkaPitDlaSwiadczenia = 0;
    let wspolczynnikKupSwiadczenia = 0;
    if (pracownik.typUmowy === 'UZ') {
        wspolczynnikKupSwiadczenia = (pracownik.kupTyp === 'PROC_50' ? config.pit.uzKupAutorskie : config.pit.uzKupProc) / 100;
    }
    let efektywnaStawkaSwiadczenia = stawkaPitDlaSwiadczenia * (1 - wspolczynnikKupSwiadczenia);
    let swiadczenieBruttoWstepne = swiadczenieNetto / (1 - efektywnaStawkaSwiadczenia);
    let kupOdSwiadczeniaWstepne = 0;
    if (pracownik.typUmowy === 'UZ') {
        kupOdSwiadczeniaWstepne = swiadczenieBruttoWstepne * ((pracownik.kupTyp === 'PROC_50' ? config.pit.uzKupAutorskie : config.pit.uzKupProc) / 100);
    }
    const zaliczkaOdSwiadczenia = Math.round((swiadczenieBruttoWstepne - kupOdSwiadczeniaWstepne) * stawkaPitDlaSwiadczenia);
    const swiadczenieBrutto = swiadczenieNetto + zaliczkaOdSwiadczenia;
    const lacznyPrzychod = wynikZasadnicza.brutto + swiadczenieBrutto;
    let kupCalkowite = wynikZasadnicza.kup;
    if (pracownik.typUmowy === 'UZ') {
        const procKup = pracownik.kupTyp === 'PROC_50' ? config.pit.uzKupAutorskie : config.pit.uzKupProc;
        kupCalkowite = (lacznyPrzychod - wynikZasadnicza.zusPracownik.suma) * (procKup / 100);
    }
    const podstawaPitCalkowita = Math.round(Math.max(0, lacznyPrzychod - wynikZasadnicza.zusPracownik.suma - kupCalkowite));
    const kupOdSwiadczenia = kupCalkowite - wynikZasadnicza.kup;
    let stawkaPitFinal = config.pit.prog1Stawka;
    if (pracownik.pitMode === 'FLAT_32')
        stawkaPitFinal = config.pit.prog2Stawka;
    else if (pracownik.pitMode === 'FLAT_12')
        stawkaPitFinal = config.pit.prog1Stawka;
    else if (pracownik.pitMode === 'FLAT_0')
        stawkaPitFinal = 0;
    else
        stawkaPitFinal = podstawaPitCalkowita > config.pit.prog1Limit ? config.pit.prog2Stawka : config.pit.prog1Stawka;
    const pitCalkowity = (0, pit_1.obliczPit)(podstawaPitCalkowita, pracownik.pit2, pracownik.ulgaMlodych, stawkaPitFinal);
    const kwotaOdZasadniczej = wynikZasadnicza.pit || 0;
    const kwotaOdSwiadczenia = pitCalkowity - kwotaOdZasadniczej;
    const zusPracodawca = (0, zus_1.obliczZusPracodawca)(wynikZasadnicza.brutto, pracownik.typUmowy, pracownik.trybSkladek, stawkaWypadkowa, pracownik.skladkaFP, pracownik.skladkaFGSP, config);
    return {
        zasadnicza: { ...wynikZasadnicza, zusPracodawca, nettoGotowka: nettoZasadnicza },
        swiadczenie: { brutto: swiadczenieBrutto, netto: swiadczenieNetto, zaliczka: kwotaOdSwiadczenia, kup: kupOdSwiadczenia },
        pit: {
            lacznyPrzychod,
            podstawa: podstawaPitCalkowita,
            kup: kupCalkowite,
            kupOdZasadniczej: wynikZasadnicza.kup,
            kupOdSwiadczenia,
            stawka: stawkaPitFinal,
            kwota: pitCalkowity,
            kwotaOdZasadniczej: kwotaOdZasadniczej,
            kwotaOdSwiadczenia: kwotaOdSwiadczenia,
        },
        kosztPracodawcy: wynikZasadnicza.brutto + zusPracodawca.suma + swiadczenieBrutto,
        nettoCalkowite: nettoZasadnicza + swiadczenieNetto,
        doWyplatyGotowka: nettoZasadnicza - 1,
        doWyplatySwiadczenie: swiadczenieNetto,
        doWyplaty: nettoZasadnicza - 1 + swiadczenieNetto,
    };
};
exports.obliczWariantPodzial = obliczWariantPodzial;
