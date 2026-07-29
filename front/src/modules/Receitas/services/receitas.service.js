/**
 * Serviço de API para o módulo de Receitas.
 *
 * Centraliza todas as chamadas HTTP relacionadas a receitas financeiras.
 * Inclui CRUD padrão, prorrogação e pagamento parcial.
 */
import _get from "@/helpers/Connections/get";
import _post from "@/helpers/Connections/post";
import _put from "@/helpers/Connections/put";
import _delete from "@/helpers/Connections/delete";
import { BASE_API } from "@/constants/api";

const API_URL = `${BASE_API}/receita`;

/** Buscar lista de receitas com filtros */
export const getReceitas = async (params = {}, options = {}) => {
  const query = new URLSearchParams(params).toString();
  return await _get({ url: `${API_URL}/buscar?${query}`, ...options });
};

/** Buscar receita por ID */
export const getReceita = async (id) => {
  return await _get({ url: `${API_URL}/buscar?id=${id}` });
};

/** Criar nova receita */
export const createReceita = async (data) => {
  return await _post({ url: `${API_URL}/inserir`, body: data });
};

/** Atualizar receita existente */
export const updateReceita = async (id, data) => {
  return await _put({ url: `${API_URL}/editar/${id}`, body: data });
};

/** Excluir receita (soft delete) */
export const deleteReceita = async (id) => {
  return await _delete({ url: `${API_URL}/excluir/${id}` });
};

/**
 * Prorrogar parcela de receita.
 * Payload: { data_vencimento_nova, justificativa }
 * O valor é calculado automaticamente pelo backend.
 */
export const prorrogarReceita = async (id, data) => {
  return await _post({ url: `${API_URL}/${id}/prorrogar`, body: data });
};

/**
 * Simular prorrogação de parcela de receita.
 * Payload: { data_vencimento_nova }
 * O valor é calculado automaticamente pelo backend (prévia).
 */
export const simularProrrogacaoReceita = async (id, data, options = {}) => {
  return await _post({ url: `${API_URL}/${id}/simular-prorrogacao`, body: data, ...options });
};

/** Consultar histórico de prorrogações de uma receita */
export const getProrrogacoes = async (id) => {
  return await _get({ url: `${API_URL}/${id}/prorrogacoes` });
};

/**
 * Registrar abatimento parcial.
 * Payload: { valor_pago, data_pagamento, data_vencimento_nova }
 * Backend abate do valor da parcela atual, mantendo-a aberta, e cria receita de recebimento.
 */
export const pagarParcialReceita = async (id, data) => {
  return await _post({ url: `${API_URL}/${id}/pagar-parcial`, body: data });
};

/**
 * Registrar pagamento de carência de principal.
 * Payload: { valor_pago, data_pagamento }
 * Backend liquida a parcela atual (juros pagos) e repassa o principal pro final do contrato.
 */
export const pagarCarenciaReceita = async (id, data) => {
  return await _post({ url: `${API_URL}/${id}/pagar-carencia`, body: data });
};

/**
 * Registrar pagamento integral (quitação).
 * Payload: { data_recebimento, conta_bancaria_destino_id }
 */
export const quitarIntegralReceita = async (id, data) => {
  return await _post({ url: `${API_URL}/${id}/quitar-integral`, body: data });
};

/**
 * Calcular encargos de uma receita para o modal de recebimento.
 * Payload: { data_recebimento }
 * Retorna: juros_mora, multa, juros_atualizacao, valor_devido, taxas
 */
export const calcularEncargosReceita = async (id, data, options = {}) => {
  return await _post({ url: `${API_URL}/${id}/calcular-encargos`, body: data, ...options });
};

/** Consultar contrato vinculado a uma receita */
export const getContratoByReceita = async (id) => {
  return await _get({ url: `${API_URL}/${id}/contrato` });
};
