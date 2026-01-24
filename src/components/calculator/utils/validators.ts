export const validateNIP = (nip: string) => {
  const digits = nip.replace(/\D/g, '');
  if (digits.length !== 10) return { valid: false, message: 'NIP musi miec 10 cyfr' };

  const weights = [6, 5, 7, 2, 3, 4, 5, 6, 7];
  let sum = 0;
  for (let i = 0; i < 9; i++) {
    sum += parseInt(digits[i], 10) * weights[i];
  }
  const checkDigit = sum % 11;
  if (checkDigit === 10) return { valid: false, message: 'NIP nieprawidlowy (suma kontrolna = 10)' };
  if (checkDigit !== parseInt(digits[9], 10)) return { valid: false, message: 'NIP nieprawidlowy (bledna suma kontrolna)' };

  return { valid: true, message: 'NIP prawidlowy' };
};
