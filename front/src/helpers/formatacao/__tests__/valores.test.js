import { describe, it, expect } from 'vitest'
import {
  formatarReal,
  formatarDecimal,
  parsearValor,
  formatarPorcentagem,
  desformatarParaBanco
} from '../valores.js'

describe('Helpers de Formatação de Valores Monetários (valores.js)', () => {

  describe('formatarReal', () => {
    it('deve formatar número inteiro e decimal para BRL', () => {
      expect(formatarReal(1234.56)).toBe('R$ 1.234,56')
      expect(formatarReal(0)).toBe('R$ 0,00')
      expect(formatarReal(1000000)).toBe('R$ 1.000.000,00')
    })

    it('deve formatar string numérica ISO (do banco)', () => {
      expect(formatarReal('300000.00')).toBe('R$ 300.000,00')
      expect(formatarReal('4.99')).toBe('R$ 4,99')
    })

    it('deve lidar com valores vazios, null ou undefined', () => {
      expect(formatarReal(null)).toBe('R$ 0,00')
      expect(formatarReal(undefined)).toBe('R$ 0,00')
      expect(formatarReal('')).toBe('R$ 0,00')
    })
  })

  describe('formatarDecimal', () => {
    it('deve formatar sem o símbolo R$', () => {
      expect(formatarDecimal(1234.56)).toBe('1.234,56')
      expect(formatarDecimal('500.5')).toBe('500,50')
    })

    it('deve retornar 0,00 para entradas vazias', () => {
      expect(formatarDecimal('')).toBe('0,00')
      expect(formatarDecimal(null)).toBe('0,00')
    })
  })

  describe('parsearValor', () => {
    it('deve converter número nativo sem alterações', () => {
      expect(parsearValor(1500.75)).toBe(1500.75)
      expect(parsearValor(0)).toBe(0)
    })

    it('deve converter string formato banco (com ponto)', () => {
      expect(parsearValor('1500.75')).toBe(1500.75)
    })

    it('deve converter string formatada em BRL (R$ e vírgula)', () => {
      expect(parsearValor('R$ 1.234,56')).toBe(1234.56)
      expect(parsearValor('300.000,00')).toBe(300000)
    })

    it('deve retornar 0 para valores nulos ou vazios', () => {
      expect(parsearValor(null)).toBe(0)
      expect(parsearValor('')).toBe(0)
    })
  })

  describe('formatarPorcentagem', () => {
    it('deve formatar valor percentual com 2 casas decimal ISO', () => {
      expect(formatarPorcentagem('4.00')).toBe('4,00 %')
      expect(formatarPorcentagem('4.99')).toBe('4,99 %')
    })

    it('deve retornar 0,00 % para entradas nulas', () => {
      expect(formatarPorcentagem(null)).toBe('0,00 %')
    })
  })

  describe('desformatarParaBanco', () => {
    it('deve converter "R$ 1.234,56" para "1234.56"', () => {
      expect(desformatarParaBanco('R$ 1.234,56')).toBe('1234.56')
      expect(desformatarParaBanco('300.000,00')).toBe('300000.00')
    })

    it('deve retornar string vazia para entradas falsy', () => {
      expect(desformatarParaBanco('')).toBe('')
      expect(desformatarParaBanco(null)).toBe('')
    })
  })

})
