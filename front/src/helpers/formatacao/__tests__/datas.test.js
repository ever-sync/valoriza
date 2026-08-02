import { describe, it, expect } from 'vitest'
import {
  formatarData,
  formatarDataInput,
  parsearDataInput,
  diferencaDias,
  adicionarDias,
  adicionarMeses,
  isHoje,
  isAtrasado
} from '../datas.js'

describe('Helpers de Formatação de Datas (datas.js)', () => {

  describe('formatarData', () => {
    it('deve formatar data ISO (yyyy-mm-dd) para dd/mm/yyyy', () => {
      expect(formatarData('2026-03-15')).toBe('15/03/2026')
      expect(formatarData('2026-12-31')).toBe('31/12/2026')
    })

    it('deve aceitar objeto Date', () => {
      const d = new Date(2026, 2, 15) // Março é mês index 2
      expect(formatarData(d)).toBe('15/03/2026')
    })

    it('deve retornar "-" se a data for nula ou inválida', () => {
      expect(formatarData(null)).toBe('-')
      expect(formatarData('')).toBe('-')
      expect(formatarData('data-invalida')).toBe('-')
    })
  })

  describe('formatarDataInput', () => {
    it('deve formatar data para yyyy-mm-dd aceitável em input type="date"', () => {
      expect(formatarDataInput('2026-05-10T15:30:00')).toBe('2026-05-10')
      const d = new Date(2026, 4, 10)
      expect(formatarDataInput(d)).toBe('2026-05-10')
    })

    it('deve retornar string vazia para valores inválidos ou vazios', () => {
      expect(formatarDataInput(null)).toBe('')
      expect(formatarDataInput('')).toBe('')
    })
  })

  describe('diferencaDias', () => {
    it('deve calcular a diferença de dias corretamente', () => {
      expect(diferencaDias('2026-01-01', '2026-01-10')).toBe(9)
      expect(diferencaDias('2026-01-10', '2026-01-01')).toBe(-9)
    })
  })

  describe('adicionarDias e adicionarMeses', () => {
    it('deve adicionar dias a uma data base', () => {
      const novaData = adicionarDias('2026-01-15', 5)
      expect(formatarDataInput(novaData)).toBe('2026-01-20')
    })

    it('deve adicionar meses a uma data base', () => {
      const novaData = adicionarMeses('2026-01-15', 2)
      expect(formatarDataInput(novaData)).toBe('2026-03-15')
    })
  })

  describe('isAtrasado', () => {
    it('deve identificar se a data é anterior ao dia atual', () => {
      expect(isAtrasado('2020-01-01')).toBe(true)
      expect(isAtrasado('2099-12-31')).toBe(false)
      expect(isAtrasado(null)).toBe(false)
    })
  })

})
