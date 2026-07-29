import _get from "@/helpers/Connections/get";
import _post from "@/helpers/Connections/post";
import _put from "@/helpers/Connections/put";
import _delete from "@/helpers/Connections/delete";
import { BASE_API } from "@/constants/api";

const API_URL = `${BASE_API}/contrato`;

export const getContratos = async (params = {}, options = {}) => {
  const query = new URLSearchParams(params).toString();
  return await _get({ url: `${API_URL}/buscar?${query}`, ...options });
};

export const getContrato = async (id, options = {}) => {
  return await _get({ url: `${API_URL}/buscar?id=${id}`, ...options });
};

export const getContratoGarantias = async (id, options = {}) => {
  return await _get({ url: `${API_URL}/garantias/${id}`, ...options });
};

export const createContrato = async (data) => {
  return await _post({ url: `${API_URL}/inserir`, body: data });
};

export const updateContrato = async (id, data) => {
  return await _put({ url: `${API_URL}/editar/${id}`, body: data });
};

export const deleteContrato = async (id, options = {}) => {
  return await _delete({ url: `${API_URL}/excluir/${id}`, ...options });
};

export const lancarParcelasContrato = async (id, options = {}) => {
  return await _post({ url: `${API_URL}/${id}/lancar-parcelas`, ...options });
};

export const getContratoParcelas = async (id, options = {}) => {
  return await _get({ url: `${API_URL}/${id}/parcelas`, ...options });
};

export const simularContrato = async (data, options = {}) => {
  return await _post({ url: `${API_URL}/simular`, body: data, ...options });
};

export const enviarParaCRDC = async (id, options = {}) => {
  return await _post({ url: `${API_URL}/${id}/crdc`, ...options });
};
