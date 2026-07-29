<script setup>
/**
 * Tela de Status de Execução de um Contrato.
 * Exibe parâmetros, cronograma de pagamentos e ações financeiras.
 */
import Button from '@/components/ui/Button.vue'
import Modal from '@/components/ui/Modal.vue'
import { useContratoStatus } from '../composables/useContratoStatus'

const {
  carregando, contrato, garantias, parcelasLancadas,
  modalExclusao, modalLancamento, modalFeedback,
  mensagemFeedback, tipoFeedback, processandoLancamento, processandoCRDC,
  formatarMoeda, formatarData, valorSolicitadoFormatado, taxaJurosFormatada, applyMask,
  simulacao, excluirContrato, lancarFinanceiro, enviarCRDC, jaLancado, roteador
} = useContratoStatus()
</script>

<template>
  <div class="p-6 max-w-7xl mx-auto space-y-6">
    <header class="flex justify-between items-center mb-8">
      <div>
        <div class="flex items-center gap-2 text-text-tertiary text-xs font-bold uppercase tracking-widest mb-1">
          <router-link to="/contratos" class="hover:text-primary transition-colors">Contratos</router-link>
          <span>&gt;</span>
          <span>{{ contrato?.numero_contrato || contrato?.id }}</span>
          <span>&gt;</span>
          <span class="text-text-primary">Status de Execução</span>
        </div>
        <h1 class="text-2xl font-bold text-text-primary">Contrato {{ contrato?.numero_contrato || 'Status de Execução' }}</h1>
      </div>
      <div class="flex gap-2">
        <Button variant="primary" outline @click="enviarCRDC" :disabled="carregando || processandoCRDC">
          {{ processandoCRDC ? 'Enviando...' : 'Lançar no CRDC' }}
        </Button>
        <Button variant="primary" @click="modalLancamento = true" :disabled="carregando || processandoLancamento || jaLancado">
          {{ processandoLancamento ? 'Lançando...' : (jaLancado ? 'Já Lançado no Financeiro' : 'Lançar Financeiro') }}
        </Button>
        <Button variant="danger" outline @click="modalExclusao = true" :disabled="carregando">Excluir Contrato</Button>
        <Button variant="primary" @click="roteador.push('/contratos')" :disabled="carregando">Voltar</Button>
      </div>
    </header>

    <!-- Loading -->
    <div v-if="carregando" class="flex items-center justify-center py-20">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
    </div>

    <div v-else-if="contrato" class="space-y-6">
      <!-- Parâmetros da Operação -->
      <section class="bg-surface border border-border rounded-3xl p-6 shadow-sm shadow-black/5">
        <h2 class="text-lg font-bold text-text-primary mb-6 flex items-center gap-2">
          <svg class="w-5 h-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          Parâmetros da Operação Inicial: {{ contrato.numero_contrato || contrato.id }}
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-y-6 gap-x-12">
          <div class="space-y-4">
            <div>
              <p class="text-[11px] font-bold text-text-tertiary uppercase tracking-wider">Cliente</p>
              <p class="text-sm font-semibold text-text-primary">{{ contrato.cliente_nome }}</p>
            </div>
            <div>
              <p class="text-[11px] font-bold text-text-tertiary uppercase tracking-wider">CET ao ano</p>
              <p class="text-sm font-semibold text-text-primary">{{ applyMask('porcentagem', simulacao?.cet.ano) }}</p>
            </div>
            <div>
              <p class="text-[11px] font-bold text-text-tertiary uppercase tracking-wider">CET ao mês</p>
              <p class="text-sm font-semibold text-text-primary">{{ applyMask('porcentagem', simulacao?.cet.mes) }}</p>
            </div>
            <div>
              <p class="text-[11px] font-bold text-text-tertiary uppercase tracking-wider">Tipo de operação</p>
              <p class="text-sm text-text-secondary">{{ contrato.tipo_operacao }}</p>
            </div>
            <div>
              <p class="text-[11px] font-bold text-text-tertiary uppercase tracking-wider">Valor solicitado</p>
              <p class="text-sm font-bold text-primary">{{ valorSolicitadoFormatado }}</p>
            </div>
            <div>
              <p class="text-[11px] font-bold text-text-tertiary uppercase tracking-wider">Quantidade de parcelas</p>
              <p class="text-sm text-text-secondary">{{ contrato.quantidade_parcelas }}</p>
            </div>
            <div>
              <p class="text-[11px] font-bold text-text-tertiary uppercase tracking-wider">Taxa de juros mensal</p>
              <p class="text-sm text-text-secondary">{{ taxaJurosFormatada }}</p>
            </div>
          </div>
          <div class="space-y-4">
            <div>
              <p class="text-[11px] font-bold text-text-tertiary uppercase tracking-wider">Valor total do contrato</p>
              <p class="text-sm font-bold text-emerald-600">{{ formatarMoeda(simulacao?.totalAReceber) }}</p>
            </div>
            <div>
              <p class="text-[11px] font-bold text-text-tertiary uppercase tracking-wider">Taxa no período (Mensal)</p>
              <p class="text-sm text-text-secondary">{{ contrato.taxa_juros ? applyMask('porcentagem', contrato.taxa_juros) : '-' }}</p>
            </div>
            <div>
              <p class="text-[11px] font-bold text-text-tertiary uppercase tracking-wider">Data da assinatura</p>
              <p class="text-sm text-text-secondary">{{ formatarData(contrato.data_assinatura) }}</p>
            </div>
            <div>
              <p class="text-[11px] font-bold text-text-tertiary uppercase tracking-wider">Data da primeira parcela</p>
              <p class="text-sm text-text-secondary">{{ formatarData(contrato.data_primeira_parcela) }}</p>
            </div>
            <div>
              <p class="text-[11px] font-bold text-text-tertiary uppercase tracking-wider">Tipo de amortização</p>
              <p class="text-sm text-text-secondary">{{ contrato.modelo_amortizacao }}</p>
            </div>
            <div>
              <p class="text-[11px] font-bold text-text-tertiary uppercase tracking-wider">Garantias</p>
              <p class="text-sm text-text-secondary">{{ garantias.length ? garantias.map(g => g.tipo_garantia).join(', ') : 'Nenhuma' }}</p>
            </div>
          </div>
          <div class="space-y-4">
            <div>
              <p class="text-[11px] font-bold text-text-tertiary uppercase tracking-wider">Status</p>
              <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold uppercase"
                :class="contrato.status === 'Ativo' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                {{ contrato.status }}
              </span>
            </div>
            <div>
              <p class="text-[11px] font-bold text-text-tertiary uppercase tracking-wider">IOF Calculado</p>
              <p class="text-sm text-text-secondary">{{ contrato.calcular_iof ? 'Sim' : 'Não' }}</p>
            </div>
            <div>
              <p class="text-[11px] font-bold text-text-tertiary uppercase tracking-wider">Risco Inadimplência</p>
              <p class="text-sm text-text-secondary">{{ contrato.risco_inadimplencia }}</p>
            </div>
          </div>
        </div>
      </section>

      <!-- Garantias Vinculadas -->
      <section v-if="garantias && garantias.length" class="bg-surface border border-border rounded-3xl overflow-hidden shadow-sm shadow-black/5">
        <div class="px-6 py-4 border-b border-border bg-surface/50">
          <h3 class="font-bold text-text-primary flex items-center gap-2">
            <svg class="w-4 h-4 text-text-tertiary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            Garantias Utilizadas
          </h3>
        </div>
        <div class="divide-y divide-border">
          <details v-for="(g, i) in garantias" :key="g.id || i" class="group">
            <summary class="px-6 py-4 cursor-pointer hover:bg-surface/50 transition-colors flex items-center justify-between list-none">
              <div class="flex items-center gap-3">
                 <span class="text-sm font-bold text-text-primary">{{ g.tipo_garantia }}</span>
                 <span v-if="g.pessoa_nome_garantia" class="text-xs text-text-secondary border-l border-border pl-3">{{ g.pessoa_nome_garantia }}</span>
              </div>
              <svg class="w-4 h-4 text-text-tertiary group-open:rotate-180 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </summary>
            <div class="px-6 py-4 pt-0 bg-surface/30">
               <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm mt-3">
                  <template v-if="g.tipo_garantia === 'Imóvel'">
                     <div><span class="text-text-tertiary font-bold text-[10px] uppercase block mb-1">Tabelionato</span><p class="font-medium">{{ g.tabelionato || '-' }}</p></div>
                     <div><span class="text-text-tertiary font-bold text-[10px] uppercase block mb-1">Matrícula</span><p class="font-medium">{{ g.numero_matricula || '-' }}</p></div>
                     <div><span class="text-text-tertiary font-bold text-[10px] uppercase block mb-1">Proprietário</span><p class="font-medium">{{ g.proprietario || '-' }}</p></div>
                     <div><span class="text-text-tertiary font-bold text-[10px] uppercase block mb-1">Valor de Avaliação</span><p class="font-medium">{{ g.valor_avaliacao ? formatarMoeda(g.valor_avaliacao) : '-' }}</p></div>
                     <div class="md:col-span-2"><span class="text-text-tertiary font-bold text-[10px] uppercase block mb-1">Descrição</span><p class="font-medium">{{ g.descricao || '-' }}</p></div>
                  </template>
                  <template v-else-if="g.tipo_garantia === 'Veículo'">
                     <div><span class="text-text-tertiary font-bold text-[10px] uppercase block mb-1">Fabricante / Modelo</span><p class="font-medium">{{ g.fabricante }} / {{ g.modelo }}</p></div>
                     <div><span class="text-text-tertiary font-bold text-[10px] uppercase block mb-1">Ano</span><p class="font-medium">{{ g.ano_fabricacao || '-' }}</p></div>
                     <div><span class="text-text-tertiary font-bold text-[10px] uppercase block mb-1">Placa</span><p class="font-medium">{{ g.placa || '-' }}</p></div>
                     <div><span class="text-text-tertiary font-bold text-[10px] uppercase block mb-1">Chassi</span><p class="font-medium">{{ g.chassi || '-' }}</p></div>
                     <div><span class="text-text-tertiary font-bold text-[10px] uppercase block mb-1">Valor de Avaliação</span><p class="font-medium">{{ g.valor_avaliacao ? formatarMoeda(g.valor_avaliacao) : '-' }}</p></div>
                  </template>
                  <template v-else-if="g.tipo_garantia === 'Recebível'">
                     <div><span class="text-text-tertiary font-bold text-[10px] uppercase block mb-1">Tipo Recebível</span><p class="font-medium">{{ g.tipo_recebivel || '-' }}</p></div>
                     <div><span class="text-text-tertiary font-bold text-[10px] uppercase block mb-1">Identificação</span><p class="font-medium">{{ g.numero_identificacao || '-' }}</p></div>
                     <div><span class="text-text-tertiary font-bold text-[10px] uppercase block mb-1">Sacado</span><p class="font-medium">{{ g.sacado || '-' }}</p></div>
                     <div><span class="text-text-tertiary font-bold text-[10px] uppercase block mb-1">Vencimento</span><p class="font-medium">{{ g.data_vencimento_recebivel ? formatarData(g.data_vencimento_recebivel) : '-' }}</p></div>
                     <div><span class="text-text-tertiary font-bold text-[10px] uppercase block mb-1">Valor</span><p class="font-medium">{{ g.valor_avaliacao ? formatarMoeda(g.valor_avaliacao) : '-' }}</p></div>
                  </template>
                  <template v-else-if="g.tipo_garantia === 'Avalista' || g.tipo_garantia === 'Devedor solidário'">
                     <div><span class="text-text-tertiary font-bold text-[10px] uppercase block mb-1">Nome</span><p class="font-medium">{{ g.pessoa_nome_garantia || '-' }}</p></div>
                     <div><span class="text-text-tertiary font-bold text-[10px] uppercase block mb-1">Documento</span><p class="font-medium">{{ g.cpf || g.cnpj || '-' }}</p></div>
                     <div><span class="text-text-tertiary font-bold text-[10px] uppercase block mb-1">Telefone</span><p class="font-medium">{{ g.telefone || '-' }}</p></div>
                     <div><span class="text-text-tertiary font-bold text-[10px] uppercase block mb-1">Email</span><p class="font-medium">{{ g.email || '-' }}</p></div>
                     <div class="md:col-span-2"><span class="text-text-tertiary font-bold text-[10px] uppercase block mb-1">Endereço</span><p class="font-medium">{{ [g.endereco, g.numero, g.bairro, g.cidade, g.estado].filter(Boolean).join(', ') || '-' }}</p></div>
                  </template>
                  <template v-else>
                     <div class="md:col-span-2"><span class="text-text-tertiary font-bold text-[10px] uppercase block mb-1">Descrição</span><p class="font-medium">{{ g.descricao || '-' }}</p></div>
                     <div><span class="text-text-tertiary font-bold text-[10px] uppercase block mb-1">Valor de avaliação</span><p class="font-medium">{{ g.valor_avaliacao ? formatarMoeda(g.valor_avaliacao) : '-' }}</p></div>
                     <div><span class="text-text-tertiary font-bold text-[10px] uppercase block mb-1">Nº de série</span><p class="font-medium">{{ g.numero_serie || '-' }}</p></div>
                  </template>
               </div>
            </div>
          </details>
        </div>
      </section>

      <!-- Cronograma de Pagamentos -->
      <section class="bg-surface border border-border rounded-3xl overflow-hidden shadow-sm shadow-black/5">
        <div class="px-6 py-4 border-b border-border bg-surface/50">
          <h3 class="font-bold text-text-primary flex items-center gap-2">
            <svg class="w-4 h-4 text-text-tertiary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            Cronograma de Pagamentos
          </h3>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-background text-[10px] font-bold text-text-tertiary uppercase tracking-widest">
                <th class="px-6 py-3 border-b border-border">Número</th>
                <th class="px-6 py-3 border-b border-border">Vencimento</th>
                <th class="px-6 py-3 border-b border-border">Valor Parcela</th>
                <th class="px-6 py-3 border-b border-border">Juros</th>
                <th class="px-6 py-3 border-b border-border">Amortização</th>
                <th class="px-6 py-3 border-b border-border">Saldo Devedor</th>
                <th class="px-6 py-3 border-b border-border">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border">
              <tr v-for="p in (parcelasLancadas.length > 0 ? parcelasLancadas : simulacao?.parcelas)" :key="p.num || p.parcela_numero" class="hover:bg-surface/50 transition-colors">
                <td class="px-6 py-4 text-xs font-bold text-text-tertiary">{{ p.num || p.numero_parcela || p.parcela_numero }}</td>
                <td class="px-6 py-4 text-sm text-text-secondary">{{ p.vencimento || (p.data_vencimento ? formatarData(p.data_vencimento) : '-') }}</td>
                <td class="px-6 py-4 text-sm font-bold text-text-primary">{{ formatarMoeda(p.parcela || p.valor_parcela || p.valor_recebido) }}</td>
                <td class="px-6 py-4 text-sm text-text-tertiary">{{ p.juros ? formatarMoeda(p.juros) : (p.valor_juros ? formatarMoeda(p.valor_juros) : '-') }}</td>
                <td class="px-6 py-4 text-sm text-text-tertiary">{{ p.amortizacao ? formatarMoeda(p.amortizacao) : (p.valor_amortizacao ? formatarMoeda(p.valor_amortizacao) : '-') }}</td>
                <td class="px-6 py-4 text-sm text-text-tertiary">{{ p.saldo ? formatarMoeda(p.saldo) : (p.saldo_devedor ? formatarMoeda(p.saldo_devedor) : '-') }}</td>
                <td class="px-6 py-4">
                  <span class="px-2 py-0.5 rounded-full border text-[9px] font-bold uppercase"
                    :class="{
                      'bg-emerald-50 text-emerald-600 border-emerald-100': p.status_financeiro === 'Recebido' || p.status_financeiro === 'Pago',
                      'bg-orange-50 text-orange-600 border-orange-100': p.status_financeiro === 'Pendente' || !p.status_financeiro,
                      'bg-rose-50 text-rose-600 border-rose-100': p.status_financeiro === 'Atrasado',
                      'bg-blue-50 text-blue-600 border-blue-100': p.status_financeiro === 'Lançada',
                      'bg-gray-50 text-text-tertiary border-border': p.status_financeiro === 'Cancelado' || p.status_financeiro === 'Simulação'
                    }"
                  >{{ p.status_financeiro || 'Simulação' }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <div v-else class="text-center py-20 bg-surface border border-border border-dashed rounded-3xl">
      <p class="text-text-tertiary">Contrato não encontrado.</p>
    </div>

    <!-- Modal Lançar Financeiro -->
    <Modal :is-open="modalLancamento" title="Lançar no Financeiro" max-width="max-w-md" @close="modalLancamento = false">
      <div class="space-y-4">
        <p class="text-text-secondary text-sm">Deseja lançar as parcelas deste contrato como <strong>Receitas</strong> no financeiro? Esta operação só pode ser realizada uma vez.</p>
        <div class="flex justify-end gap-3 pt-4">
          <Button variant="ghost" @click="modalLancamento = false">Cancelar</Button>
          <Button variant="primary" @click="lancarFinanceiro" :loading="processandoLancamento">Confirmar Lançamento</Button>
        </div>
      </div>
    </Modal>

    <!-- Modal Excluir -->
    <Modal :is-open="modalExclusao" title="Confirmar Exclusão" max-width="max-w-md" @close="modalExclusao = false">
      <div class="space-y-4">
        <p class="text-text-secondary text-sm">Tem certeza que deseja excluir o contrato <strong>{{ contrato?.numero_contrato }}</strong>? Esta ação é irreversível.</p>
        <div class="flex justify-end gap-3 pt-4">
          <Button variant="ghost" @click="modalExclusao = false">Cancelar</Button>
          <Button variant="danger" @click="excluirContrato">Sim, Excluir</Button>
        </div>
      </div>
    </Modal>

    <!-- Modal Resultado -->
    <Modal :is-open="modalFeedback" :title="tipoFeedback === 'success' ? 'Sucesso' : 'Atenção'" max-width="max-w-md" @close="modalFeedback = false">
      <div class="space-y-4">
        <div class="flex items-center gap-3" :class="tipoFeedback === 'success' ? 'text-emerald-600' : 'text-rose-600'">
          <svg v-if="tipoFeedback === 'success'" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
          <svg v-else class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
          <p class="text-sm font-semibold">{{ mensagemFeedback }}</p>
        </div>
        <div class="flex justify-end pt-4">
          <Button variant="primary" @click="modalFeedback = false">Ok</Button>
        </div>
      </div>
    </Modal>
  </div>
</template>
