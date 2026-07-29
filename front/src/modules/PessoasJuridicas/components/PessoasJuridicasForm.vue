<script setup>
import { toRef } from 'vue'
import Input from '@/components/ui/Input.vue'
import { useCrudForm } from '@/composables/useCrudForm'
import { parsearValor, formatarDecimal, desformatarParaBanco } from '@/helpers/formatacao'
import * as service from '../services/pessoasJuridicas.service.js'
import { consultarCEP, consultarCNPJ } from '@/helpers/Integracoes/consulta'
import { ref } from 'vue'

const props = defineProps({ initialData: { type: Object, default: null } })

const emit = defineEmits(['saved', 'cancel'])

const dadosPadrao = {
  razao_social: '',
  nome_fantasia: '',
  cnpj: '',
  email: '',
  rede_social: '',
  telefone: '',
  cep: '',
  estado: '',
  cidade: '',
  bairro: '',
  endereco: '',
  numero: '',
  complemento: '',
  limite_credito: '',
  observacao: '',
  banco: '',
  agencia: '',
  conta: '',
  chave_pix: ''
}

const { formulario, estaEditando, watchingDadosIniciais, extrairPayload } = useCrudForm({
  dadosPadrao
})

watchingDadosIniciais(
  toRef(props, 'initialData'),
  (dados) => {
    const resultado = { ...dados }
    if (dados?.limite_credito) resultado.limite_credito = formatarDecimal(parsearValor(dados.limite_credito))
    return resultado
  }
)

const carregandoCEP = ref(false)
const carregandoCNPJ = ref(false)

const handleSearchCEP = async (cep) => {
  const cepLimpo = String(cep || '').replace(/\D/g, '')
  if (cepLimpo.length !== 8) {
    window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'warning', message: 'CEP incompleto. Digite os 8 dígitos.' } }))
    return
  }
  carregandoCEP.value = true
  try {
    const res = await consultarCEP(cep)
    if (res?.success && res.data) {
      formulario.value.endereco = res.data.endereco
      formulario.value.bairro = res.data.bairro
      formulario.value.cidade = res.data.cidade
      formulario.value.estado = res.data.estado
    }
  } catch (e) { console.error(e) } finally { carregandoCEP.value = false }
}

const handleSearchCNPJ = async (cnpj) => {
  const cnpjLimpo = String(cnpj || '').replace(/\D/g, '')
  if (cnpjLimpo.length !== 14) {
    window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'warning', message: 'CNPJ incompleto. Digite os 14 dígitos.' } }))
    return
  }
  carregandoCNPJ.value = true
  try {
    const res = await consultarCNPJ(cnpj)
    if (res?.success && res.data) {
      const d = res.data
      formulario.value.razao_social = d.razao_social
      formulario.value.nome_fantasia = d.nome_fantasia
      formulario.value.email = d.email
      formulario.value.telefone = d.telefone
      formulario.value.cep = d.cep
      formulario.value.estado = d.estado
      formulario.value.cidade = d.cidade
      formulario.value.bairro = d.bairro
      formulario.value.endereco = d.endereco
      formulario.value.numero = d.numero
      formulario.value.complemento = d.complemento
    }
  } catch (e) { console.error(e) } finally { carregandoCNPJ.value = false }
}

const handleSalvar = async () => {
  const payload = extrairPayload()
  payload.limite_credito = desformatarParaBanco(formulario.value.limite_credito)

  try {
    let resposta
    if (estaEditando.value) {
      resposta = await service.updatePessoaJuridica(formulario.value.id, payload)
    } else {
      resposta = await service.createPessoaJuridica(payload)
    }

    if (resposta?.success) {
      emit('saved')
    } else {
      window.dispatchEvent(new CustomEvent('toast', { 
        detail: { type: 'error', message: resposta?.message || 'Erro ao salvar.' } 
      }))
    }
  } catch (e) {
    console.error(e)
  }
}

defineExpose({
  save: handleSalvar
})
</script>

<template>
  <form @submit.prevent="handleSalvar" class="space-y-6 max-h-[70vh] overflow-y-auto px-1 custom-scrollbar">
    <section>
      <h3 class="text-xs font-bold text-text-tertiary uppercase tracking-widest mb-4 border-b border-border/50 pb-2">Dados Básicos</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <Input label="Razão Social" v-model="formulario.razao_social" required placeholder="Digite a razão social" class="md:col-span-2" />
        <Input label="Nome Fantasia" v-model="formulario.nome_fantasia" required placeholder="Digite o nome fantasia" class="md:col-span-2" />
        <Input 
          label="CNPJ" 
          v-model="formulario.cnpj" 
          required 
          mask="cnpj" 
          placeholder="00.000.000/0000-00" 
          :show-search="true"
          :loading="carregandoCNPJ"
          @search="handleSearchCNPJ"
        />
        <Input label="E-mail" type="email" v-model="formulario.email" required placeholder="email@exemplo.com.br" />
        <Input label="Rede Social" v-model="formulario.rede_social" placeholder="@empresa ou link" />
        <Input label="Telefone" v-model="formulario.telefone" mask="telefone" placeholder="(00) 0000-0000" />
      </div>
    </section>

    <section>
      <h3 class="text-xs font-bold text-text-tertiary uppercase tracking-widest mb-4 border-b border-border/50 pb-2">Endereço</h3>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Input 
          label="CEP" 
          v-model="formulario.cep" 
          mask="cep" 
          placeholder="00000-000" 
          :show-search="true"
          :loading="carregandoCEP"
          @search="handleSearchCEP"
        />
        <Input label="Estado" v-model="formulario.estado" placeholder="UF" />
        <Input label="Cidade" v-model="formulario.cidade" placeholder="Nome da cidade" />
        <Input label="Bairro" v-model="formulario.bairro" placeholder="Digite o bairro" />
        <Input label="Endereço" v-model="formulario.endereco" placeholder="Rua, Av, etc" class="md:col-span-2" />
        <Input label="Número" v-model="formulario.numero" placeholder="Nº" />
        <Input label="Complemento" v-model="formulario.complemento" placeholder="Apto, Sala, etc" class="md:col-span-2" />
      </div>
    </section>

    <section>
      <h3 class="text-xs font-bold text-text-tertiary uppercase tracking-widest mb-4 border-b border-border/50 pb-2">Financeiro & Pix</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <Input label="Limite de Crédito" v-model="formulario.limite_credito" mask="real" placeholder="R$ 0,00" />
        <Input label="Chave PIX" v-model="formulario.chave_pix" placeholder="CPF, E-mail, Celular ou Chave Aleatória" />
        <Input label="Banco" v-model="formulario.banco" placeholder="Nome do Banco" />
        <Input label="Agência" v-model="formulario.agencia" placeholder="0000" />
        <Input label="Conta" v-model="formulario.conta" placeholder="00000-0" />
        <Input label="Observação" v-model="formulario.observacao" placeholder="Informações adicionais" class="md:col-span-2" />
      </div>
    </section>
  </form>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
</style>
