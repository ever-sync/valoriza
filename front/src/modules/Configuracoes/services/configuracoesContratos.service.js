import _get from "@/helpers/Connections/get";
import _post from "@/helpers/Connections/post";
import _put from "@/helpers/Connections/put";
import _delete from "@/helpers/Connections/delete";
import { BASE_API } from "@/constants/api";

const API_URL = `${BASE_API}/configuracoes-contratos`;

export const getConfiguracoes = async (params = {}, options = {}) => {
  const query = new URLSearchParams(params).toString();
  return await _get({ url: `${API_URL}/buscar?${query}`, ...options });
};

export const getConfiguracaoById = async (id, options = {}) => {
  return await _get({ url: `${API_URL}/buscar?id=${id}`, ...options });
};

export const createConfiguracao = async (data) => {
  return await _post({ url: `${API_URL}/inserir`, body: data });
};

export const updateConfiguracao = async (id, data) => {
  return await _put({ url: `${API_URL}/editar/${id}`, body: data });
};

export const deleteConfiguracao = async (id) => {
  return await _delete({ url: `${API_URL}/excluir/${id}` });
};
