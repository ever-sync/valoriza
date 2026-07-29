<script setup>
import { ref, toRef, computed } from 'vue'
import Input from '@/components/ui/Input.vue'
import DynamicSelect from '@/components/ui/DynamicSelect.vue'
import { useCrudForm } from '@/composables/useCrudForm'
import { parsearValor, formatarDecimal, desformatarParaBanco } from '@/helpers/formatacao'
import { createDespesa, updateDespesa } from '../services/despesas.service'
import { getBancos } from '@/modules/Bancos/services/bancos.service'
import { getPessoasFisicas } from '@/modules/PessoasFisicas/services/pessoasFisicas.service'
import { getPessoasJuridicas } from '@/modules/PessoasJuridicas/services/pessoasJuridicas.service'

const props = defineProps({
  initialData: { type: Object, default: null }
})

const emit = defineEmits(['saved', 'cancel'])

const dadosPadrao = {
  data_pagamento: '',
  data_vencimento: '',
  descricao: '',
  valor_pago: '',
  conta_bancaria_origem_id: '',
  tipo_favorecido: 'cadastrado',
  tipo_pessoa: '',
  favorecido_id: '',
  nome_favorecido_manual: '',
  tipo_comprovante_fiscal: '',
  numero_comprovante: '',
  categoria_id: '',
  forma_pagamento: '',
  status: 'Pendente',
  irrf_retido: '',
  inss_retido: '',
  iss_retido: '',
  outros_impostos_retidos: '',
  observacoes: '',
  despesa_recorrente: 0,
  quantidade_despesas_recorrentes: 2
}

const { formulario, estaEditando, watchingDadosIniciais, extrairPayload } = useCrudForm({ dadosPadrao })

const favLabel = ref('')
const bcoLabel = ref('')

watchingDadosIniciais(
  toRef(props, 'initialData'),
  (dados) => {
    const r = { ...dados }
    if (dados?.valor_pago) r.valor_pago = formatarDecimal(parsearValor(dados.valor_pago))
    if (dados?.irrf_retido) r.irrf_retido = formatarDecimal(parsearValor(dados.irrf_retido))
    if (dados?.inss_retido) r.inss_retido = formatarDecimal(parsearValor(dados.inss_retido))
    if (dados?.iss_retido) r.iss_retido = formatarDecimal(parsearValor(dados.iss_retido))
    if (dados?.outros_impostos_retidos) r.outros_impostos_retidos = formatarDecimal(parsearValor(dados.outros_impostos_retidos))
    if (dados?.favorecido_nome) favLabel.value = dados.favorecido_nome
    if (dados?.conta_nome) bcoLabel.value = dados.conta_nome
    return r
  }
)

const tiposFavorecido = [
  { label: 'Cadastrado', value: 'cadastrado' },
  { label: 'Manual', value: 'manual' }
]

const tiposPessoa = [
  { label: 'Pessoa Física', value: 'pf' },
  { label: 'Pessoa Jurídica', value: 'pj' }
]

const categorias = ['Alimentação', 'Impostos', 'Investimentos', 'Marketing', 'Saúde', 'Tecnologia']
const formasPagamento = ['Boleto', 'Cartão de Crédito', 'Cartão de Débito', 'Dinheiro', 'PIX', 'Transferência']
const statusGerais = ['Pendente', 'Pago', 'Atrasado', 'Cancelado']

const dynamicFavService = computed(() => {
  return formulario.value.tipo_pessoa === 'pf' ? getPessoasFisicas : getPessoasJuridicas
})

const handleTipoPessoaChange = () => {
  formulario.value.favorecido_id = ''
  favLabel.value = ''
}

const handleSalvar = async () => {
  const payload = extrairPayload()
  payload.valor_pago = desformatarParaBanco(formulario.value.valor_pago)
  payload.irrf_retido = desformatarParaBanco(formulario.value.irrf_retido)
  payload.inss_retido = desformatarParaBanco(formulario.value.inss_retido)
  payload.iss_retido = desformatarParaBanco(formulario.value.iss_retido)
  payload.outros_impostos_retidos = desformatarParaBanco(formulario.value.outros_impostos_retidos)

  const resposta = estaEditando.value
    ? await updateDespesa(formulario.value.id, payload)
    : await createDespesa(payload)

  if (resposta?.success) emit('saved')
  else window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: resposta?.message || 'Erro.' } }))
}

defineExpose({ save: handleSalvar })
</script>

<template>
  <form @submit.prevent="handleSalvar" class="space-y-6 max-h-[70vh] overflow-y-auto px-1 custom-scrollbar">
    <!-- Datas -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <Input v-model="formulario.data_vencimento" label="Vencimento" type="date" required />
        <Input v-model="formulario.data_pagamento" label="Pagamento" type="date" />
    </div>

    <!-- Descrição e Valor -->
    <Input v-model="formulario.descricao" label="Descrição" placeholder="Ex: Pagamento Fornecedor XYZ" required />
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <Input v-model="formulario.valor_pago" label="Valor Líquido" mask="real" required />
        <DynamicSelect 
            v-model="formulario.conta_bancaria_origem_id" 
            :search-service="(p) => getBancos({ ...p, banco_like: p.search }, { showLoading: false })"
            label="Conta Origem"
            label-key="banco"
            :initial-label="bcoLabel"
            placeholder="Pesquisar banco..."
            required
        >
            <template #option-detail="{ option }">
                <span class="text-[10px] text-text-tertiary uppercase tracking-tighter">{{ option.agencia }} / {{ option.conta }}</span>
            </template>
        </DynamicSelect>
    </div>

    <!-- Favorecido Dinâmico -->
    <div class="p-4 rounded-2xl bg-background border border-border space-y-4">
        <div class="flex items-center justify-between">
            <label class="px-1 text-xs font-bold uppercase tracking-wider text-text-tertiary">Favorecido (Destino)</label>
            <div class="flex gap-4">
                <label v-for="t in tiposFavorecido" :key="t.value" class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" v-model="formulario.tipo_favorecido" :value="t.value" class="text-primary focus:ring-primary w-4 h-4" />
                    <span class="text-xs font-bold text-text-secondary uppercase">{{ t.label }}</span>
                </label>
            </div>
        </div>

        <div v-if="formulario.tipo_favorecido === 'cadastrado'" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Seletor Tipo Pessoa -->
                <div class="flex flex-col gap-1.5">
                    <label class="px-1 text-[10px] font-bold text-text-tertiary uppercase tracking-widest">Tipo</label>
                    <select v-model="formulario.tipo_pessoa" @change="handleTipoPessoaChange" class="w-full bg-surface border border-border rounded-xl py-2.5 px-4 text-sm outline-none focus:border-primary transition-all">
                        <option value="">Selecione...</option>
                        <option v-for="tp in tiposPessoa" :key="tp.value" :value="tp.value">{{ tp.label }}</option>
                    </select>
                </div>

                <!-- Busca de Favorecido Dinâmica baseada no Tipo -->
                <DynamicSelect 
                    v-if="formulario.tipo_pessoa"
                    v-model="formulario.favorecido_id" 
                    :search-service="(p) => dynamicFavService({ ...p, [formulario.tipo_pessoa === 'pf' ? 'nome_completo_like' : 'razao_social_like']: p.search }, { showLoading: false })"
                    :label="formulario.tipo_pessoa === 'pf' ? 'Buscar Pessoa Física' : 'Buscar Pessoa Jurídica'"
                    :label-key="formulario.tipo_pessoa === 'pf' ? 'nome_completo' : 'razao_social'"
                    :initial-label="favLabel"
                    placeholder="Comece a digitar..."
                    required
                >
                    <template #option-detail="{ option }">
                        <span class="text-[10px] text-text-tertiary uppercase">{{ option.documento_formatado || option.cpf || option.cnpj }}</span>
                    </template>
                </DynamicSelect>
            </div>
        </div>
        <Input v-else v-model="formulario.nome_favorecido_manual" label="Nome do Favorecido" required />
    </div>

    <!-- Classificação -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="px-1 text-xs font-bold uppercase tracking-wider text-text-tertiary">Categoria</label>
            <select v-model="formulario.categoria_id" class="w-full bg-surface border border-border rounded-xl py-2.5 px-4 text-sm outline-none focus:border-primary transition-all">
                <option value="">Selecione...</option>
                <option v-for="(c, i) in categorias" :key="i" :value="i+1">{{ c }}</option>
            </select>
        </div>
        <div>
            <label class="px-1 text-xs font-bold uppercase tracking-wider text-text-tertiary">Forma Pgto</label>
            <select v-model="formulario.forma_pagamento" class="w-full bg-surface border border-border rounded-xl py-2.5 px-4 text-sm outline-none focus:border-primary transition-all">
                <option value="">Selecione...</option>
                <option v-for="f in formasPagamento" :key="f" :value="f">{{ f }}</option>
            </select>
        </div>
        <div>
            <label class="px-1 text-xs font-bold uppercase tracking-wider text-text-tertiary">Status</label>
            <select v-model="formulario.status" class="w-full bg-surface border border-border rounded-xl py-2.5 px-4 text-sm outline-none focus:border-primary transition-all">
                <option v-for="s in statusGerais" :key="s" :value="s">{{ s }}</option>
            </select>
        </div>
    </div>

    <!-- Impostos -->
    <details class="bg-background border border-border rounded-2xl overflow-hidden">
        <summary class="px-4 py-3 font-bold text-xs uppercase tracking-widest text-text-tertiary cursor-pointer hover:bg-surface transition-colors">Visualizar Impostos Retidos</summary>
        <div class="p-4 grid grid-cols-2 gap-4 border-t border-border/10">
            <Input v-model="formulario.irrf_retido" label="IRRF" mask="real" />
            <Input v-model="formulario.inss_retido" label="INSS" mask="real" />
            <Input v-model="formulario.iss_retido" label="ISS" mask="real" />
            <Input v-model="formulario.outros_impostos_retidos" label="Outros" mask="real" />
        </div>
    </details>

    <!-- Recorrência (Somente no Cadastro) -->
    <div v-if="!estaEditando" class="p-4 bg-primary/5 rounded-2xl border border-primary/10 space-y-4">
        <div class="flex items-center gap-3 cursor-pointer" @click="formulario.despesa_recorrente = formulario.despesa_recorrente ? 0 : 1">
            <div class="w-6 h-6 rounded-lg border-2 flex items-center justify-center transition-all" :class="formulario.despesa_recorrente ? 'bg-primary border-primary' : 'bg-white border-border'">
                <svg v-if="formulario.despesa_recorrente" class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <div>
                <span class="text-sm font-bold text-text-primary block">Habilitar Lançamento Recorrente</span>
                <span class="text-[10px] text-text-tertiary uppercase font-medium">As parcelas serão geradas automaticamente na data atual do cadastro</span>
            </div>
        </div>
        <div v-if="formulario.despesa_recorrente" class="flex flex-col gap-2 pt-2 animate-in fade-in slide-in-from-top-2 duration-300">
            <label class="text-xs font-bold text-text-tertiary uppercase tracking-widest px-1">Quantidade de Parcelas (Meses)</label>
            <input v-model="formulario.quantidade_despesas_recorrentes" type="number" min="2" max="60" class="w-full max-w-[200px] px-4 py-2 rounded-xl border border-border text-sm outline-none focus:border-primary transition-all" />
        </div>
    </div>

  </form>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #e2e8f0;
  border-radius: 10px;
}
</style>
