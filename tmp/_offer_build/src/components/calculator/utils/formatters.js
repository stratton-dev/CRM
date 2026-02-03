"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.formatNIP = exports.formatPLN = void 0;
const formatPLN = (value) => {
    if (value === undefined || value === null || isNaN(value))
        return '0,00 zł';
    return new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(value);
};
exports.formatPLN = formatPLN;
const formatNIP = (value) => {
    const digits = value.replace(/\D/g, '');
    if (!digits)
        return '';
    if (digits.length <= 3)
        return digits;
    if (digits.length <= 6)
        return `${digits.slice(0, 3)}-${digits.slice(3)}`;
    if (digits.length <= 8)
        return `${digits.slice(0, 3)}-${digits.slice(3, 6)}-${digits.slice(6)}`;
    return `${digits.slice(0, 3)}-${digits.slice(3, 6)}-${digits.slice(6, 8)}-${digits.slice(8, 10)}`;
};
exports.formatNIP = formatNIP;
