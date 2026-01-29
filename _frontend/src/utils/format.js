import { format as formatDateFns, parseISO } from 'date-fns';
import { ptBR } from 'date-fns/locale';

/**
 * Formata um valor numérico como moeda brasileira (R$)
 * @param {number|string} value - Valor a ser formatado
 * @returns {string} Valor formatado (ex: "R$ 1.234,56")
 */
export function formatCurrency(value) {
  const numValue = typeof value === 'string' ? parseFloat(value) : value;

  if (isNaN(numValue)) {
    return 'R$ 0,00';
  }

  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  }).format(numValue);
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
