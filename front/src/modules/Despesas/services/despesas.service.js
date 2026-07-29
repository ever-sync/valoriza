import _get from "@/helpers/Connections/get";
import _post from "@/helpers/Connections/post";
import _put from "@/helpers/Connections/put";
import _delete from "@/helpers/Connections/delete";
import { BASE_API } from "@/constants/api";

const API_URL = `${BASE_API}/despesa`;

/**
 * Busca a listagem de despesas com suporte a filtros e paginação
 */
export const getDespesas = async (params = {}, options = {}) => {
  const query = new URLSearchParams(params).toString();
  return await _get({ url: `${API_URL}/buscar?${query}`, ...options });
};

/**
 * Busca uma única despesa pelo ID
 */
export const getDespesa = async (id, options = {}) => {
  return await _get({ url: `${API_URL}/buscar?id=${id}`, ...options });
};

/**
 * Cadastra uma nova despesa (ou múltiplas se for recorrente)
 */
export const createDespesa = async (data) => {
  return await _post({ url: `${API_URL}/inserir`, body: data });
};

/**
 * Atualiza uma despesa existente
 */
export const updateDespesa = async (id, data) => {
  return await _put({ url: `${API_URL}/editar/${id}`, body: data });
};

/**
 * Exclui logicamente (soft delete) uma despesa
 */
export const deleteDespesa = async (id) => {
  return await _delete({ url: `${API_URL}/excluir/${id}` });
};
