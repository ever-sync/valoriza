<script setup>
import { toRef } from 'vue'
import Input from '@/components/ui/Input.vue'
import { useCrudForm } from '@/composables/useCrudForm'
import { parsearValor, formatarDecimal, desformatarParaBanco } from '@/helpers/formatacao'
import * as service from '../services/pessoasFisicas.service.js'
import { consultarCEP } from '@/helpers/Integracoes/consulta'
import { ref } from 'vue'

const props = defineProps({ initialData: { type: Object, default: null } })

const emit = defineEmits(['saved', 'cancel'])

const dadosPadrao = {
  nome_completo: '', cpf: '', rg: '', orgao_emissor_rg: '',
  telefone: '', email: '', rede_social: '', cep: '', estado: '', cidade: '',
  bairro: '', endereco: '', numero: '', complemento: '',
  renda_mensal: '', limite_credito: '', estado_civil: '',
  regime_partilha: '', observacao: '', banco: '', agencia: '', conta: '', chave_pix: ''
}

const { formulario, estaEditando, watchingDadosIniciais, extrairPayload } = useCrudForm({ dadosPadrao })

watchingDadosIniciais(
  toRef(props, 'initialData'),
  (dados) => {
    const r = { ...dados }
    if (dados?.renda_mensal) r.renda_mensal = formatarDecimal(parsearValor(dados.renda_mensal))
    if (dados?.limite_credito) r.limite_credito = formatarDecimal(parsearValor(dados.limite_credito))
    return r
  }
)

const carregandoCEP = ref(false)

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
  } catch (e) {
    console.error(e)
  } finally {
    carregandoCEP.value = false
  }
}

const handleSalvar = async () => {
  const payload = extrairPayload()
  payload.renda_mensal = desformatarParaBanco(formulario.value.renda_mensal)
  payload.limite_credito = desformatarParaBanco(formulario.value.limite_credito)
  try {
    const resposta = estaEditando.value
      ? await service.updatePessoaFisica(formulario.value.id, payload)
      : await service.createPessoaFisica(payload)
    if (resposta?.success) emit('saved')
    else window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: resposta?.message || 'Erro ao salvar.' } }))
  } catch (e) { console.error(e) }
}

defineExpose({ save: handleSalvar })
</script>

<template>
  <form @submit.prevent="handleSalvar" class="space-y-6 max-h-[70vh] overflow-y-auto px-1 custom-scrollbar">
    <section>
      <h3 class="text-xs font-bold text-text-tertiary uppercase tracking-widest mb-4 border-b border-border/50 pb-2">Dados Pessoais</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <Input label="Nome Completo" v-model="formulario.nome_completo" required placeholder="Digite o nome completo" class="md:col-span-2" />
        <Input label="CPF" v-model="formulario.cpf" required mask="cpf" placeholder="000.000.000-00" />
        <Input label="RG" v-model="formulario.rg" required placeholder="Digite o RG" />
        <Input label="Órgão Emissor" v-model="formulario.orgao_emissor_rg" required placeholder="Ex: SSP/SP" />
        <Input label="E-mail" type="email" v-model="formulario.email" required placeholder="email@exemplo.com" />
        <Input label="Rede Social" v-model="formulario.rede_social" placeholder="@usuario ou link" />
        <Input label="Telefone" v-model="formulario.telefone" required mask="celular" placeholder="(00) 00000-0000" />
        <Input label="Estado Civil" v-model="formulario.estado_civil" required placeholder="Ex: Solteiro(a)" />
        <Input label="Regime de Partilha" v-model="formulario.regime_partilha" required placeholder="Ex: Comunhão Parcial" />
      </div>
    </section>

    <section>
      <h3 class="text-xs font-bold text-text-tertiary uppercase tracking-widest mb-4 border-b border-border/50 pb-2">Endereço</h3>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Input 
          label="CEP" 
          v-model="formulario.cep" 
          required 
          mask="cep" 
          placeholder="00000-000" 
          :show-search="true"
          :loading="carregandoCEP"
          @search="handleSearchCEP"
        />
        <Input label="Estado" v-model="formulario.estado" required placeholder="UF" />
        <Input label="Cidade" v-model="formulario.cidade" required placeholder="Nome da cidade" />
        <Input label="Bairro" v-model="formulario.bairro" required placeholder="Digite o bairro" />
        <Input label="Endereço" v-model="formulario.endereco" required placeholder="Rua, Av, etc" class="md:col-span-2" />
        <Input label="Número" v-model="formulario.numero" required placeholder="Nº" />
        <Input label="Complemento" v-model="formulario.complemento" placeholder="Apto, Sala, etc" class="md:col-span-2" />
      </div>
    </section>

    <section>
      <h3 class="text-xs font-bold text-text-tertiary uppercase tracking-widest mb-4 border-b border-border/50 pb-2">Financeiro & Pix</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <Input label="Renda Mensal" v-model="formulario.renda_mensal" mask="real" placeholder="R$ 0,00" />
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
