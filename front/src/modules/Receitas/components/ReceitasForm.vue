<script setup>
/**
 * Formulário de Receitas.
 *
 * Regras de negócio implementadas:
 * - Receitas vinculadas a contrato: data_vencimento e valor_recebido bloqueados
 * - Prorrogação: apenas data e justificativa; valor calculado pelo backend
 * - Pagamento parcial: carência de principal com valor mínimo = juros
 */
import { computed, toRefs } from 'vue'
import Input from '@/components/ui/Input.vue'
import DynamicSelect from '@/components/ui/DynamicSelect.vue'
import { useReceita } from '../composables/useReceita'
import { formatarReal, formatarData } from '@/helpers/formatacao'
import { createReceita, updateReceita, prorrogarReceita, getProrrogacoes, pagarParcialReceita, simularProrrogacaoReceita } from '../services/receitas.service'
import { getBancos } from '@/modules/Bancos/services/bancos.service'
import { getPessoasFisicas } from '@/modules/PessoasFisicas/services/pessoasFisicas.service'
import { getPessoasJuridicas as getPessoasJuridicasService } from '@/modules/PessoasJuridicas/services/pessoasJuridicas.service'
import { getContrato } from '@/modules/Contratos/services/contratos.service'

const props = defineProps({
  initialData: { type: Object, default: null }
})

const emit = defineEmits(['saved', 'cancel'])
const propsRef = toRefs(props)

const servicos = { getPessoasFisicas, getPessoasJuridicas: getPessoasJuridicasService, getProrrogacoes, getContrato, simularProrrogacaoReceita }
useReceita.configurarServicos(servicos)

const {
  formulario, carregando, modoEdicao, pagadorLabel, bancoLabel,
  ehVinculadoContrato,
  formularioProrrogacao, mostrarProrrogacao, prorrogacaoCarregando,
  prorrogacoes, mostrarHistorico, podeProrogar, contratoVinculado,
  diasAtraso, valorOriginal, jurosMora, multaAtraso, valorDevido, carregandoSimulacaoProrrogacao,
  formularioPagamentoParcial, mostrarPagamentoParcial, pagamentoParcialCarregando,
  podePagarParcial, usosCarenciaRestantes, valorMinimoCarencia, saldoResidualParcial,
  valorPago, ehPagamentoParcial, valorPagoAbaixoMinimo, saldoResidualFormulario, alertaPagamento,
  limparPagador, abrirProrrogacao, fecharProrrogacao, salvarProrrogacao,
  abrirPagamentoParcial, fecharPagamentoParcial, salvarPagamentoParcial, extrairPayload
} = useReceita({ initialData: propsRef.initialData })

const servicoPagador = computed(() => {
  return formulario.value?.tipo_pessoa === 'pf' ? getPessoasFisicas : getPessoasJuridicasService
})

const dadosSelecao = {
  tiposPagador: [{ label: 'Cadastrado', value: 'cadastrado' }, { label: 'Manual', value: 'manual' }],
  tiposPessoa: [{ label: 'Pessoa Física', value: 'pf' }, { label: 'Pessoa Jurídica', value: 'pj' }],
  categorias: ['Receita de Contrato', 'Aporte Capital', 'Rendimento Aplicação', 'Outras Receitas'],
  formasRecebimento: ['PIX', 'Boleto', 'Transferência', 'Dinheiro', 'Cartão'],
  statusGerais: ['Pendente', 'Recebido', 'Atrasado', 'Cancelado', 'Recebido parcial']
}

const formatarMoeda = (valor) => formatarReal(parseFloat(valor || 0))

const salvar = async () => {
  if (carregando.value) return
  carregando.value = true
  try {
    const payload = extrairPayload()
    const resposta = modoEdicao.value ? await updateReceita(formulario.value.id, payload) : await createReceita(payload)
    if (resposta?.success) emit('saved')
    else window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: resposta?.message || 'Erro ao salvar.' } }))
  } catch (erro) { console.error(erro) }
  finally { carregando.value = false }
}

const handleAbrirProrrogacao = async () => await abrirProrrogacao(getContrato)
const handleSalvarProrrogacao = async () => { if (await salvarProrrogacao(prorrogarReceita)) emit('saved') }

const handleAbrirPagamentoParcial = async () => await abrirPagamentoParcial(getContrato)
const handleSalvarPagamentoParcial = async () => { if (await salvarPagamentoParcial(pagarParcialReceita)) emit('saved') }

defineExpose({ save: salvar })
</script>

<template>
  <form @submit.prevent="salvar" class="space-y-6 max-h-[70vh] overflow-y-auto px-1 custom-scrollbar">

    <!-- Badge de vínculo com contrato -->
    <div v-if="ehVinculadoContrato" class="flex items-center gap-2 px-4 py-2.5 bg-blue-50 border border-blue-200 rounded-xl">
      <svg class="w-4 h-4 text-blue-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
      <span class="text-xs font-bold text-blue-700 uppercase tracking-wider">Vinculada ao contrato — Data de vencimento e valor protegidos</span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <Input v-model="formulario.data_vencimento" label="Vencimento" type="date" required
        :disabled="ehVinculadoContrato" />
      <Input v-model="formulario.data_recebimento" label="Data Recebimento" type="date" />
    </div>

    <Input v-model="formulario.descricao" label="Descrição" placeholder="Ex: Recebimento Parcela X" required />

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <Input v-model="formulario.valor_recebido" label="Valor da Parcela" mask="real" required
        :disabled="ehVinculadoContrato" />
      <DynamicSelect
        v-model="formulario.conta_bancaria_destino_id"
        :search-service="(p) => getBancos({ ...p, banco_like: p.search }, { showLoading: false })"
        label="Conta Destino" label-key="banco" :initial-label="bancoLabel" placeholder="Pesquisar banco...">
        <template #option-detail="{ option }">
          <span class="text-[10px] text-text-tertiary uppercase tracking-tighter">{{ option.agencia }} / {{ option.conta }}</span>
        </template>
      </DynamicSelect>
    </div>

    <!-- Card de Pagamento — Aparece somente para receitas de contrato em modo edição -->
    <div v-if="ehVinculadoContrato && modoEdicao" class="p-4 rounded-2xl bg-background border border-border space-y-4">
      <div class="flex items-center gap-2 mb-1">
        <svg class="w-4 h-4 text-primary flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        <span class="text-xs font-bold uppercase tracking-wider text-text-tertiary">Pagamento</span>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Valor original (somente leitura) -->
        <div class="flex flex-col gap-1">
          <span class="text-[10px] font-bold uppercase text-text-tertiary tracking-wider">Valor Original</span>
          <span class="text-lg font-bold text-text-primary">
            {{ formatarMoeda(valorOriginal) }}
          </span>
        </div>

        <!-- Valor mínimo (juros da parcela) -->
        <div class="flex flex-col gap-1">
          <span class="text-[10px] font-bold uppercase text-text-tertiary tracking-wider">Mínimo (Juros)</span>
          <span class="text-lg font-bold text-amber-600">
            {{ formatarMoeda(valorMinimoCarencia) }}
          </span>
        </div>

        <!-- Campo valor pago -->
        <Input v-model="valorPago" label="Valor Pago" mask="real" placeholder="Informe o valor pago" />
      </div>

      <!-- Alerta dinâmico -->
      <transition name="fade">
        <div v-if="alertaPagamento" class="rounded-xl px-4 py-3 border text-sm flex items-start gap-3"
          :class="{
            'bg-red-50 border-red-200 text-red-800': alertaPagamento.tipo === 'erro',
            'bg-amber-50 border-amber-200 text-amber-800': alertaPagamento.tipo === 'aviso',
            'bg-emerald-50 border-emerald-200 text-emerald-800': alertaPagamento.tipo === 'sucesso'
          }">
          <!-- Ícone -->
          <svg v-if="alertaPagamento.tipo === 'erro'" class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
          <svg v-else-if="alertaPagamento.tipo === 'aviso'" class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          <svg v-else class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>

          <div class="flex flex-col gap-0.5">
            <span class="font-semibold">{{ alertaPagamento.mensagem }}</span>
            <span v-if="alertaPagamento.detalhe" class="text-xs opacity-80">{{ alertaPagamento.detalhe }}</span>
          </div>
        </div>
      </transition>
    </div>

    <div class="p-4 rounded-2xl bg-background border border-border space-y-4">
      <div class="flex items-center justify-between">
        <label class="px-1 text-xs font-bold uppercase tracking-wider text-text-tertiary">Pagador</label>
        <div class="flex gap-4">
          <label v-for="t in dadosSelecao.tiposPagador" :key="t.value" class="flex items-center gap-2 cursor-pointer">
            <input type="radio" v-model="formulario.tipo_pagador" :value="t.value" class="text-primary focus:ring-primary w-4 h-4" />
            <span class="text-xs font-bold text-text-secondary uppercase">{{ t.label }}</span>
          </label>
        </div>
      </div>
      <div v-if="formulario.tipo_pagador === 'cadastrado'" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="flex flex-col gap-1.5">
            <label class="px-1 text-[10px] font-bold text-text-tertiary uppercase tracking-widest">Tipo</label>
            <select v-model="formulario.tipo_pessoa" @change="limparPagador"
              class="w-full bg-surface border border-border rounded-xl py-2.5 px-4 text-sm outline-none focus:border-primary transition-all">
              <option value="">Selecione...</option>
              <option v-for="tp in dadosSelecao.tiposPessoa" :key="tp.value" :value="tp.value">{{ tp.label }}</option>
            </select>
          </div>
          <DynamicSelect
            v-if="formulario.tipo_pessoa"
            v-model="formulario.pagador_id"
            :search-service="(p) => servicoPagador({ ...p, [formulario.tipo_pessoa === 'pf' ? 'nome_completo_like' : 'razao_social_like']: p.search }, { showLoading: false })"
            :label="formulario.tipo_pessoa === 'pf' ? 'Buscar Pessoa Física' : 'Buscar Pessoa Jurídica'"
            :label-key="formulario.tipo_pessoa === 'pf' ? 'nome_completo' : 'razao_social'"
            :initial-label="pagadorLabel" placeholder="Comece a digitar..." required>
            <template #option-detail="{ option }">
              <span class="text-[10px] text-text-tertiary uppercase">{{ option.documento_formatado || option.cpf || option.cnpj }}</span>
            </template>
          </DynamicSelect>
        </div>
      </div>
      <Input v-else v-model="formulario.nome_pagador_manual" label="Nome do Pagador" required />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>
        <label class="px-1 text-xs font-bold uppercase tracking-wider text-text-tertiary">Categoria</label>
        <select v-model="formulario.categoria_id" class="w-full bg-surface border border-border rounded-xl py-2.5 px-4 text-sm outline-none focus:border-primary transition-all">
          <option value="">Selecione...</option>
          <option v-for="(c, i) in dadosSelecao.categorias" :key="i" :value="i+1">{{ c }}</option>
        </select>
      </div>
      <div>
        <label class="px-1 text-xs font-bold uppercase tracking-wider text-text-tertiary">Forma Recebimento</label>
        <select v-model="formulario.forma_recebimento" class="w-full bg-surface border border-border rounded-xl py-2.5 px-4 text-sm outline-none focus:border-primary transition-all">
          <option value="">Selecione...</option>
          <option v-for="f in dadosSelecao.formasRecebimento" :key="f" :value="f">{{ f }}</option>
        </select>
      </div>
      <div>
        <label class="px-1 text-xs font-bold uppercase tracking-wider text-text-tertiary">Status</label>
        <select v-model="formulario.status" class="w-full bg-surface border border-border rounded-xl py-2.5 px-4 text-sm outline-none focus:border-primary transition-all">
          <option v-for="s in dadosSelecao.statusGerais" :key="s" :value="s">{{ s }}</option>
        </select>
      </div>
    </div>

    <details class="bg-background border border-border rounded-2xl overflow-hidden">
      <summary class="px-4 py-3 font-bold text-xs uppercase tracking-widest text-text-tertiary cursor-pointer hover:bg-surface transition-colors">Mais Informações</summary>
      <div class="p-4 space-y-4 border-t border-border/10">
        <div class="grid grid-cols-2 gap-4">
          <Input v-model="formulario.tipo_comprovante_fiscal" label="Tipo Comprovante" />
          <Input v-model="formulario.numero_comprovante" label="Nº Comprovante" />
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="px-1 text-[10px] font-bold text-text-tertiary uppercase tracking-widest">Observações</label>
          <textarea v-model="formulario.observacoes"
            class="w-full bg-surface border border-border rounded-xl py-2.5 px-4 text-sm outline-none focus:border-primary transition-all min-h-[100px]"></textarea>
        </div>
      </div>
    </details>

    <!-- Ações de Prorrogação e Pagamento Parcial -->
    <div v-if="podeProrogar || podePagarParcial" class="flex flex-wrap items-center gap-3">
      <button v-if="podeProrogar" type="button" @click="handleAbrirProrrogacao"
        class="flex items-center gap-2 px-4 py-2.5 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl text-sm font-bold hover:bg-amber-100 transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        Prorrogar Parcela
      </button>
      <button v-if="podePagarParcial" type="button" @click="handleAbrirPagamentoParcial"
        class="flex items-center gap-2 px-4 py-2.5 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm font-bold hover:bg-emerald-100 transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        Pagamento Parcial (Carência)
        <span class="px-1.5 py-0.5 rounded-full bg-emerald-200/60 text-[10px] font-bold">{{ usosCarenciaRestantes }} restantes</span>
      </button>
      <button v-if="prorrogacoes.length > 0" type="button" @click="mostrarHistorico = !mostrarHistorico"
        class="flex items-center gap-1.5 px-3 py-2.5 text-xs font-semibold text-primary hover:text-primary/80 transition-colors">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Histórico ({{ prorrogacoes.length }})
      </button>
    </div>

    <!-- Histórico de Prorrogações -->
    <div v-if="mostrarHistorico && prorrogacoes.length > 0" class="border border-border rounded-2xl overflow-hidden">
      <div class="px-4 py-3 bg-surface border-b border-border">
        <h4 class="text-xs font-bold text-text-tertiary uppercase tracking-wider">Histórico de Prorrogações</h4>
      </div>
      <div class="divide-y divide-border max-h-[300px] overflow-y-auto custom-scrollbar">
        <div v-for="(p, idx) in prorrogacoes" :key="idx" class="px-4 py-3 space-y-1">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-text-primary">#{{ prorrogacoes.length - idx }} — {{ formatarData(p.data_cadastro?.split(' ')[0]) }}</span>
            <span class="text-[10px] font-semibold text-text-tertiary uppercase">{{ formatarData(p.data_vencimento_anterior) }} → {{ formatarData(p.data_vencimento_nova) }}</span>
          </div>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-[11px]">
            <div><span class="text-text-tertiary">Valor anterior:</span> <span class="font-semibold">{{ formatarMoeda(p.valor_anterior) }}</span></div>
            <div><span class="text-text-tertiary">Juros mora:</span> <span class="font-semibold text-amber-600">{{ formatarMoeda(p.juros_mora) }}</span></div>
            <div><span class="text-text-tertiary">Multa:</span> <span class="font-semibold text-red-600">{{ formatarMoeda(p.multa_atraso) }}</span></div>
            <div><span class="text-text-tertiary">Novo valor:</span> <span class="font-semibold text-primary">{{ formatarMoeda(p.valor_novo) }}</span></div>
          </div>
          <p v-if="p.justificativa" class="text-[11px] text-text-tertiary italic">{{ p.justificativa }}</p>
        </div>
      </div>
    </div>
  </form>

  <!-- ====== MODAL PRORROGAÇÃO ====== -->
  <Teleport to="body">
    <div v-if="mostrarProrrogacao" class="fixed inset-0 z-[999] flex items-center justify-center">
      <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="fecharProrrogacao" />
      <div class="relative bg-surface rounded-2xl shadow-2xl border border-border w-full max-w-3xl mx-4 max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between px-6 py-4 border-b border-border flex-shrink-0">
          <h3 class="text-lg font-bold text-text-primary">Prorrogar Parcela</h3>
          <button type="button" @click="fecharProrrogacao" class="p-1.5 text-text-tertiary hover:text-text-primary transition-colors rounded-lg hover:bg-surface-hover">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>

        <div class="p-6 space-y-6 overflow-y-auto flex-1">
          <div class="grid grid-cols-2 gap-6">
            <div class="p-4 bg-surface rounded-xl border border-border">
              <span class="text-[10px] font-bold text-text-tertiary uppercase tracking-wider block mb-1">Valor Original</span>
              <span class="text-lg font-bold text-text-primary">{{ formatarMoeda(valorOriginal) }}</span>
            </div>
            <div class="p-4 bg-surface rounded-xl border border-border">
              <span class="text-[10px] font-bold text-text-tertiary uppercase tracking-wider block mb-1">Dias de Extensão</span>
              <span class="text-lg font-bold text-amber-600">{{ diasAtraso }}</span>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="p-3 bg-amber-50 rounded-xl border border-amber-100">
              <span class="text-[10px] font-bold text-amber-600 uppercase tracking-wider block">Juros de Mora</span>
              <span class="text-sm font-bold text-amber-800">{{ formatarMoeda(jurosMora) }}</span>
              <span class="text-[9px] text-amber-500 block mt-0.5">Pro-rata simples (máx 1% a.m.)</span>
            </div>
            <div class="p-3 bg-red-50 rounded-xl border border-red-100">
              <span class="text-[10px] font-bold text-red-600 uppercase tracking-wider block">Multa Moratória</span>
              <span class="text-sm font-bold text-red-800">{{ formatarMoeda(multaAtraso) }}</span>
              <span class="text-[9px] text-red-500 block mt-0.5">Incide 1x (máx 2% CDC)</span>
            </div>
          </div>

          <div class="p-4 bg-primary/5 rounded-xl border border-primary/10">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-text-tertiary uppercase">Novo Valor da Parcela</span>
              <span class="text-xl font-bold text-primary">{{ formatarMoeda(valorDevido) }}</span>
            </div>
            <p class="text-[10px] text-text-tertiary mt-1">Valor original + encargos. A parcela será atualizada com este valor e a nova data de vencimento.</p>
          </div>

          <div class="space-y-4">
            <Input v-model="formularioProrrogacao.data_vencimento_nova" label="Nova Data de Vencimento" type="date" required />
            <div class="flex flex-col gap-1.5">
              <label class="px-1 text-[10px] font-bold text-text-tertiary uppercase tracking-widest">Justificativa <span class="text-red-500">*</span></label>
              <textarea v-model="formularioProrrogacao.justificativa" required placeholder="Motivo da prorrogação..."
                class="w-full bg-surface border border-border rounded-xl py-2.5 px-4 text-sm outline-none focus:border-primary transition-all min-h-[80px]"></textarea>
            </div>
          </div>
        </div>

        <div class="px-6 py-4 border-t border-border flex justify-end gap-3 flex-shrink-0">
          <button type="button" @click="fecharProrrogacao" class="px-4 py-2 text-sm font-bold text-text-secondary hover:text-text-primary transition-colors">Cancelar</button>
          <button type="button" @click="handleSalvarProrrogacao" :disabled="prorrogacaoCarregando || carregandoSimulacaoProrrogacao"
            class="px-6 py-2 bg-primary text-white text-sm font-bold rounded-xl hover:bg-primary/90 transition-colors disabled:opacity-50">
            {{ prorrogacaoCarregando ? 'Salvando...' : (carregandoSimulacaoProrrogacao ? 'Calculando...' : 'Confirmar Prorrogação') }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- ====== MODAL PAGAMENTO PARCIAL ====== -->
  <Teleport to="body">
    <div v-if="mostrarPagamentoParcial" class="fixed inset-0 z-[999] flex items-center justify-center">
      <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="fecharPagamentoParcial" />
      <div class="relative bg-surface rounded-2xl shadow-2xl border border-border w-full max-w-xl mx-4 max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between px-6 py-4 border-b border-border flex-shrink-0">
          <h3 class="text-lg font-bold text-text-primary">Pagamento Parcial (Carência)</h3>
          <button type="button" @click="fecharPagamentoParcial" class="p-1.5 text-text-tertiary hover:text-text-primary transition-colors rounded-lg hover:bg-surface-hover">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>

        <div class="p-6 space-y-6 overflow-y-auto flex-1">
          <div class="p-4 bg-blue-50 border border-blue-200 rounded-xl text-xs text-blue-700 space-y-1">
            <p class="font-bold">ℹ️ Carência de principal</p>
            <p>O cliente paga um valor menor que o total da parcela (mínimo = juros). O saldo residual gera uma nova parcela com vencimento no próximo período.</p>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="p-4 bg-surface rounded-xl border border-border">
              <span class="text-[10px] font-bold text-text-tertiary uppercase tracking-wider block mb-1">Valor da Parcela</span>
              <span class="text-lg font-bold text-text-primary">{{ formatarMoeda(valorOriginal) }}</span>
            </div>
            <div class="p-4 bg-emerald-50 rounded-xl border border-emerald-100">
              <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider block mb-1">Valor Mínimo (Juros)</span>
              <span class="text-lg font-bold text-emerald-700">{{ formatarMoeda(valorMinimoCarencia) }}</span>
            </div>
          </div>

          <div class="space-y-4">
            <Input v-model="formularioPagamentoParcial.valor_pago" label="Valor Pago" mask="real" required />
            <Input v-model="formularioPagamentoParcial.data_pagamento" label="Data do Pagamento" type="date" required />
          </div>

          <div class="p-4 bg-surface rounded-xl border border-border">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-text-tertiary uppercase">Saldo Residual</span>
              <span class="text-xl font-bold" :class="saldoResidualParcial > 0 ? 'text-amber-600' : 'text-emerald-600'">
                {{ formatarMoeda(saldoResidualParcial) }}
              </span>
            </div>
            <p class="text-[10px] text-text-tertiary mt-1">Este saldo será lançado como uma nova parcela com juros pro-rata.</p>
          </div>

          <div class="flex items-center gap-2 text-[10px] text-text-tertiary">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>Usos de carência restantes: <strong class="text-text-primary">{{ usosCarenciaRestantes }}</strong></span>
          </div>
        </div>

        <div class="px-6 py-4 border-t border-border flex justify-end gap-3 flex-shrink-0">
          <button type="button" @click="fecharPagamentoParcial" class="px-4 py-2 text-sm font-bold text-text-secondary hover:text-text-primary transition-colors">Cancelar</button>
          <button type="button" @click="handleSalvarPagamentoParcial" :disabled="pagamentoParcialCarregando"
            class="px-6 py-2 bg-emerald-600 text-white text-sm font-bold rounded-xl hover:bg-emerald-700 transition-colors disabled:opacity-50">
            {{ pagamentoParcialCarregando ? 'Processando...' : 'Confirmar Pagamento' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
</style>