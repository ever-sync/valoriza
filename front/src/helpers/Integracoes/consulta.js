import _get from '@/helpers/Connections/get'
import { BASE_API } from "@/constants/api";

const API_URL = `${BASE_API}/consulta`

/**
 * Busca dados de um CEP.
 */
export const consultarCEP = async (cep) => {
    if (!cep) return null
    const cleanCep = String(cep).replace(/\D/g, '')
    if (cleanCep.length !== 8) return null
    
    return await _get({ url: `${API_URL}/cep/${cleanCep}` })
}

/**
 * Busca dados de um CNPJ.
 */
export const consultarCNPJ = async (cnpj) => {
    if (!cnpj) return null
    const cleanCnpj = String(cnpj).replace(/\D/g, '')
    if (cleanCnpj.length !== 14) return null
    
    return await _get({ url: `${API_URL}/cnpj/${cleanCnpj}` })
}
