import { format as formatDateFns, parseISO } from 'date-fns';
import { ptBR } from 'date-fns/locale';

/**
 * Formata um valor em centavos como moeda brasileira (R$).
 * O banco armazena em centavos; apenas na exibição convertemos para reais.
 * @param {number|string} cents - Valor em centavos (ex: 20000 = R$ 200,00)
 * @returns {string} Valor formatado (ex: "R$ 1.234,56")
 */
export function formatCurrency(cents) {
  const numCents = typeof cents === 'string' ? parseInt(cents, 10) : cents;

  if (isNaN(numCents) || numCents == null) {
    return 'R$ 0,00';
  }

  const reais = Number(numCents) / 100;

  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  }).format(reais);
}

/**
 * Formata uma data usando date-fns com locale pt-BR
 * @param {Date|string} date - Data a ser formatada
 * @param {string} formatStr - Formato desejado (ex: 'dd/MM/yyyy', 'dd/MM/yyyy HH:mm')
 * @returns {string} Data formatada
 */
export function formatDate(date, formatStr = 'dd/MM/yyyy') {
  if (!date) {
    return '';
  }

  try {
    const dateObj = typeof date === 'string' ? parseISO(date) : date;
    return formatDateFns(dateObj, formatStr, { locale: ptBR });
  } catch (error) {
    console.error('Erro ao formatar data:', error);
    return '';
  }
}

/**
 * Formata uma data com hora (formato padrão brasileiro)
 * @param {Date|string} date - Data a ser formatada
 * @returns {string} Data formatada (ex: "25/12/2024 14:30")
 */
export function formatDateTime(date) {
  return formatDate(date, 'dd/MM/yyyy HH:mm');
}

/**
 * Formata apenas a hora (HH:mm)
 * @param {Date|string} date - Data a ser formatada
 * @returns {string} Hora formatada (ex: "14:30")
 */
export function formatTime(date) {
  return formatDate(date, 'HH:mm');
}
