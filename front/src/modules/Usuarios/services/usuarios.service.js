import _get from "@/helpers/Connections/get";
import _post from "@/helpers/Connections/post";
import _put from "@/helpers/Connections/put";
import _delete from "@/helpers/Connections/delete";
import { BASE_API } from "@/constants/api";

const API_URL = `${BASE_API}/usuario`;

export const getUsuarios = async (params = {}, options = {}) => {
  const query = new URLSearchParams(params).toString();
  return await _get({ url: `${API_URL}/buscar?${query}`, ...options });
};

export const getUsuarioById = async (id) => {
  return await _get({ url: `${API_URL}/buscar?id=${id}` });
};

export const createUsuario = async (data) => {
  return await _post({ url: `${API_URL}/inserir`, body: data });
};

export const updateUsuario = async (id, data) => {
  return await _put({ url: `${API_URL}/editar/${id}`, body: data });
};

export const deleteUsuario = async (id) => {
  return await _delete({ url: `${API_URL}/excluir/${id}` });
};
