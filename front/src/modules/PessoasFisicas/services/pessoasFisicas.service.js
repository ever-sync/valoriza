import _get from '@/helpers/Connections/get'
import _post from '@/helpers/Connections/post'
import _put from '@/helpers/Connections/put'
import _delete from '@/helpers/Connections/delete'
import { BASE_API } from "@/constants/api";

const API_URL = `${BASE_API}/pessoa-fisica`

export const getPessoasFisicas = async (params = {}, options = {}) => {
  const { page, limit, order, direction, ...filters } = params

  const queryParams = new URLSearchParams()
  if (page) queryParams.append('pagina_atual', page)
  if (limit) queryParams.append('por_pagina', limit)
  if (order && direction) queryParams.append('ordena', `${order}_${direction}`)

  Object.keys(filters).forEach(key => {
    if (filters[key]) queryParams.append(key, filters[key])
  })

  const url = `${API_URL}/buscar?${queryParams.toString()}`
  return await _get({ url, ...options })
}

export const getPessoaFisicaById = async (id, options = {}) => {
  return await _get({ url: `${API_URL}/buscar?id=${id}`, ...options })
}

export const createPessoaFisica = async (data) => {
  return await _post({ url: `${API_URL}/inserir`, body: data })
}

export const updatePessoaFisica = async (id, data) => {
  return await _put({ url: `${API_URL}/editar/${id}`, body: data })
}

export const deletePessoaFisica = async (id) => {
  return await _delete({ url: `${API_URL}/excluir/${id}` })
}
