<script setup>
import { onMounted, ref } from 'vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import DataTable from '@/components/ui/DataTable.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import DataFilter from '@/components/ui/DataFilter.vue'
import Dropdown from '@/components/ui/Dropdown.vue'
import DropdownItem from '@/components/ui/DropdownItem.vue'
import Tooltip from '@/components/ui/Tooltip.vue'
import Input from '@/components/ui/Input.vue'
import ReceitaRecebimentoModal from '../components/ReceitaRecebimentoModal.vue'
import { useLista } from '@/composables/useLista'
import { useReceita } from '../composables/useReceita'
import {
  getReceitas,
  deleteReceita,
  updateReceita,
  prorrogarReceita,
  getProrrogacoes,
  pagarParcialReceita,
  pagarCarenciaReceita,
  quitarIntegralReceita,
  simularProrrogacaoReceita,
  getContratoByReceita,
  calcularEncargosReceita,
} from '../services/receitas.service.js'
import { getPessoasFisicas } from '@/modules/PessoasFisicas/services/pessoasFisicas.service'
import { getPessoasJuridicas } from '@/modules/PessoasJuridicas/services/pessoasJuridicas.service'
import { getContratos } from '@/modules/Contratos/services/contratos.service'
import { getContrato } from '@/modules/Contratos/services/contratos.service'
import { getBancos } from '@/modules/Bancos/services/bancos.service'
import { useRouter } from 'vue-router'
import { formatarReal, formatarData, desformatarParaBanco } from '@/helpers/formatacao'
import DynamicSelect from '@/components/ui/DynamicSelect.vue'

const router = useRouter()

// Configurar serviços para o composable useReceita
useReceita.configurarServicos({
  getProrrogacoes,
  getContrato,
  simularProrrogacao: simularProrrogacaoReceita,
})

const {
  formulario,
  ehVinculadoContrato,
  formularioProrrogacao,
  mostrarProrrogacao,
  prorrogacaoCarregando,
  prorrogacoes,
  mostrarHistorico,
  podeProrogar,
  contratoVinculado,
  diasAtraso,
  valorOriginal,
  jurosMora,
  multaAtraso,
  taxaJurosMora,
  taxaMulta,
  valorDevido,
  carregandoSimulacaoProrrogacao,
  valorSimulado,
  usosCarenciaRestantes,
  valorMinimoCarencia,
  preencherComDados,
  abrirProrrogacao,
  fecharProrrogacao,
  salvarProrrogacao,
} = useReceita()

// ═══ Recebimento Unificado ═══
const mostrarRecebimento = ref(false)
const recebimentoCarregando = ref(false)
const receitaSelecionada = ref({})
const contratoRecebimento = ref(null)
const encargosRecebimento = ref({ juros_atualizacao: 0, juros_mora: 0, multa: 0, valor_devido: 0 })

const filterSchema = [
  {
    key: 'status',
    label: 'Status',
    type: 'select',
    options: [
      { label: 'Pendente', value: 'Pendente' },
      { label: 'Recebido', value: 'Recebido' },
      { label: 'Recebido parcial', value: 'Recebido parcial' },
      { label: 'Atrasado', value: 'Atrasado' },
      { label: 'Repactuada', value: 'Repactuada' },
      { label: 'Cancelado', value: 'Cancelado' },
    ],
  },
  {
    key: 'pagador_id',
    label: 'Cliente (Pagador)',
    type: 'dynamic-select',
    placeholder: 'Pesquisar cliente...',
    searchService: (p) =>
      getPessoasFisicas({ ...p, nome_completo_like: p.search }, { showLoading: false }),
    labelKey: 'nome_completo',
    valueKey: 'id',
  },
  {
    key: 'contrato_id',
    label: 'Contrato',
    type: 'dynamic-select',
    placeholder: 'Nº do Contrato...',
    searchService: (p) =>
      getContratos({ ...p, numero_contrato_like: p.search }, { showLoading: false }),
    labelKey: 'numero_contrato',
    valueKey: 'id',
  },
  {
    key: 'data_vencimento',
    label: 'Período de Vencimento',
    type: 'date-range',
  },
  {
    key: 'valor_recebido',
    label: 'Valor',
    type: 'text',
    mask: 'real',
    placeholder: 'R$ 0,00',
  },
]

const tableHeader =
  'Descrição {descricao}, Vencimento {data_vencimento}, Pagador {pagador_nome}, Valor {valor_recebido}, Amortização {valor_amortizacao_parcela}, Juros {valor_juros_parcela}, Status {status}'

const {
  carregando,
  itens,
  meta,
  filtros,
  ordenarPorColuna,
  irParaPagina,
  alterarPorPagina,
  buscar,
  aplicarFiltros,
  limparFiltros,
  modalExclusao,
  confirmarExclusao,
  excluirItem,
  fecharModalExclusao,
} = useLista({
  serviceBuscar: getReceitas,
  serviceExcluir: deleteReceita,
  filtrosIniciais: {
    status: '',
    pagador_id: '',
    contrato_id: '',
    data_vencimento_start: '',
    data_vencimento_end: '',
    valor_recebido: '',
  },
  mapearParametros: (params) => {
    const mapped = {
      pagina_atual: params.pagina,
      por_pagina: params.porPagina,
      ordena: `${params.ordenacaoColuna}_${params.ordenacaoDirecao}`,
      status: params.status || '',
      pagador_id: params.pagador_id || '',
      contrato_id: params.contrato_id || '',
    }

    if (params.data_vencimento_start && params.data_vencimento_end) {
      mapped.data_vencimento_between = [params.data_vencimento_start, params.data_vencimento_end]
    }

    if (params.valor_recebido) {
      // Remover formatação R$ e pontos, trocar vírgula por ponto
      const valorLimpo = params.valor_recebido.replace(/[R$\s.]/g, '').replace(',', '.')
      mapped.valor_recebido = parseFloat(valorLimpo)
    }

    return mapped
  },
})

const handleOrdenar = (dadosOrdenacao) => {
  ordenarPorColuna(dadosOrdenacao.column)
}

const handleAdicionar = () => {
  router.push('/receitas/novo')
}

const handleEditar = (linha) => {
  router.push(`/receitas/editar/${linha.id}`)
}

const handleExcluir = (linha) => {
  confirmarExclusao(linha, 'Tem certeza que deseja excluir esta receita?')
}

const handleConfirmarExclusao = async () => {
  const sucesso = await excluirItem()
  if (sucesso) {
    buscar()
  }
}

const handleProrrogar = (row) => {
  preencherComDados(row)
  abrirProrrogacao(getContratoByReceita)
}

/**
 * Abre o modal unificado de recebimento.
 * Carrega contrato vinculado e encargos via backend.
 * Todos os cálculos financeiros (juros mora, multa, juros atualização)
 * são feitos no backend usando as taxas do contrato.
 */
const handleReceber = async (row) => {
  preencherComDados(row)
  receitaSelecionada.value = row
  contratoRecebimento.value = null
  encargosRecebimento.value = {
    juros_atualizacao: 0,
    juros_mora: 0,
    multa: 0,
    valor_devido: parseFloat(row.valor_recebido || 0),
  }

  mostrarRecebimento.value = true

  // Carregar contrato e encargos em paralelo via backend
  const promises = []

  if (row.contrato_id) {
    promises.push(
      getContratoByReceita(row.id).then((resp) => {
        if (resp?.success) {
          const dados = Array.isArray(resp.data) ? resp.data[0] : resp.data?.data || resp.data
          contratoRecebimento.value = dados
        }
      }).catch((e) => console.warn('Contrato não carregado:', e))
    )
  }

  // Sempre calcular encargos via backend (usa juros_mora e multa_moratoria do contrato)
  promises.push(
    calcularEncargosReceita(
      row.id,
      { 
        data_recebimento: new Date().toISOString().split('T')[0],
        valor_original: parseFloat(row.valor_recebido || 0)
      },
      { showLoading: false, showToast: false }
    ).then((resp) => {
      if (resp?.success) {
        const enc = Array.isArray(resp.data) ? resp.data[0] : resp.data
        encargosRecebimento.value = {
          juros_atualizacao: enc.juros_atualizacao || 0,
          juros_mora: enc.juros_mora || 0,
          multa: enc.multa || 0,
          valor_devido: enc.valor_devido || parseFloat(row.valor_recebido || 0),
          dias_atraso: enc.dias_atraso || 0,
          total_encargos: enc.total_encargos || 0,
          taxas: enc.taxas || {},
          total_parcelas: enc.total_parcelas || null,
        }
      }
    }).catch((e) => console.warn('Encargos não calculados:', e))
  )

  await Promise.allSettled(promises)
}

const onProrrogacaoSalva = async () => {
  if (await salvarProrrogacao(prorrogarReceita)) {
    buscar()
  }
}

/**
 * Handler unificado de recebimento.
 * Decide qual API chamar com base no modo selecionado.
 *
 * @param {Object} dados - { modo, data_recebimento, conta_bancaria_destino_id, valor_pago }
 */
const onRecebimentoSalvo = async (dados) => {
  if (!dados.conta_bancaria_destino_id) {
    window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: 'Selecione o banco de destino.' } }))
    return
  }

  recebimentoCarregando.value = true
  try {
    let resposta

    if (dados.modo === 'integral') {
      resposta = await quitarIntegralReceita(formulario.value.id, {
        data_recebimento: dados.data_recebimento,
        conta_bancaria_destino_id: dados.conta_bancaria_destino_id,
      })
    } else if (dados.modo === 'carencia') {
      resposta = await pagarCarenciaReceita(formulario.value.id, {
        valor_pago: dados.valor_pago,
        data_pagamento: dados.data_recebimento,
        conta_bancaria_destino_id: dados.conta_bancaria_destino_id,
      })
    } else if (dados.modo === 'parcial') {
      resposta = await pagarParcialReceita(formulario.value.id, {
        valor_pago: dados.valor_pago,
        data_pagamento: dados.data_recebimento,
        conta_bancaria_destino_id: dados.conta_bancaria_destino_id,
      })
    }

    if (resposta?.success) {
      mostrarRecebimento.value = false
      buscar()
    }
  } catch (erro) {
    console.error(erro)
  } finally {
    recebimentoCarregando.value = false
  }
}

const formatarMoeda = (valor) => formatarReal(parseFloat(valor || 0))

onMounted(() => {
  buscar()
})
</script>

<template>
  <div class="p-4 md:p-8 h-full flex flex-col gap-6">
    <PageHeader title="Receitas">
      <template #actions>
        <Button variant="primary" @click="handleAdicionar"> Nova Receita </Button>
      </template>
    </PageHeader>



    <div class="flex-1 flex flex-col gap-8">
      <div class="flex items-center justify-between">
        <DataFilter
          v-model="filtros"
          :schema="filterSchema"
          @apply="() => aplicarFiltros(filtros)"
          @clear="limparFiltros"
          placeholder="Buscar receitas..."
        />
      </div>

      <DataTable
        :thead="tableHeader"
        :data="itens"
        :current-page="meta.pagina"
        :per-page="meta.porPagina"
        :total-items="meta.total"
        :loading="carregando"
        @update:page="
          (val) => {
            meta.pagina = val
            buscar()
          }
        "
        @update:perPage="
          (val) => {
            meta.porPagina = val
            meta.pagina = 1
            buscar()
          }
        "
        @edit="handleEditar"
        @delete="handleExcluir"
        @sort="handleOrdenar"
      >
        <template #col-data_vencimento="{ value }">
          <Tooltip
            :texto="`Vencimento: ${value ? new Date(value + 'T00:00:00').toLocaleDateString('pt-BR') : '-'}`"
          >
            <span
              class="text-sm text-text-primary cursor-help underline decoration-dashed underline-offset-2 decoration-text-tertiary/50"
            >
              {{ value ? new Date(value + 'T00:00:00').toLocaleDateString('pt-BR') : '-' }}
            </span>
          </Tooltip>
        </template>

        <template #col-descricao="{ value }">
          <span class="text-sm font-semibold text-text-primary">
            {{ value ? value.split(' - Venc:')[0] : '-' }}
          </span>
        </template>

        <template #col-pagador_nome="{ value }">
          <span class="text-sm text-text-secondary">{{ value || '-' }}</span>
        </template>

        <template #col-status="{ value }">
          <span
            class="px-2 py-1 rounded-full text-xs font-bold uppercase"
            :class="{
              'bg-amber-50 text-amber-700 border border-amber-200': value === 'Pendente',
              'bg-emerald-50 text-emerald-700 border border-emerald-200': value === 'Recebido',
              'bg-sky-50 text-sky-700 border border-sky-200': value === 'Recebido parcial',
              'bg-red-50 text-red-700 border border-red-200': value === 'Atrasado',
              'bg-violet-50 text-violet-700 border border-violet-200': value === 'Repactuada',
              'bg-gray-50 text-gray-600 border border-gray-200': value === 'Cancelado',
            }"
          >
            {{ value }}
          </span>
        </template>

        <template #col-valor_recebido="{ value }">
          <span class="font-semibold text-emerald-600">
            {{ Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) }}
          </span>
        </template>

        <template #col-valor_amortizacao_parcela="{ value }">
          <span v-if="value" class="font-semibold text-blue-600 text-sm">
            {{ Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) }}
          </span>
          <span v-else class="text-text-tertiary text-xs">—</span>
        </template>

        <!-- Coluna Juros -->
        <template #col-valor_juros_parcela="{ value }">
          <span v-if="value" class="font-semibold text-amber-600 text-sm">
            {{ Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) }}
          </span>
          <span v-else class="text-text-tertiary text-xs">—</span>
        </template>

        <!-- Custom Actions Dropdown -->
        <template #actions="{ row }">
          <div class="flex items-center gap-1">
            <template
              v-if="
                !['Recebido', 'Cancelado', 'Recebido parcial', 'Repactuada'].includes(row.status)
              "
            >
              <button
                @click="handleEditar(row)"
                class="text-primary hover:bg-primary/10 p-2 rounded-lg transition-colors"
                title="Editar"
              >
                <svg
                  class="w-4 h-4"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  stroke-width="2"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                  />
                </svg>
              </button>

              <Dropdown align="right">
                <template #trigger="{ toggle }">
                  <button
                    @click="toggle"
                    class="text-text-tertiary hover:bg-background p-2 rounded-lg transition-colors"
                  >
                    <svg
                      class="w-4 h-4"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                      stroke-width="2"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"
                      />
                    </svg>
                  </button>
                </template>

                <DropdownItem @click="handleReceber(row)" variant="primary">
                  <template #icon>
                    <svg
                      class="w-4 h-4"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                      stroke-width="2"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"
                      />
                    </svg>
                  </template>
                  Receber Parcela
                </DropdownItem>

                <DropdownItem @click="handleProrrogar(row)" variant="primary">
                  <template #icon>
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                      />
                    </svg>
                  </template>
                  Prorrogar Parcela
                </DropdownItem>

                <DropdownItem @click="handleExcluir(row)" variant="danger">
                  <template #icon>
                    <svg
                      class="w-4 h-4"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                      stroke-width="2"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                      />
                    </svg>
                  </template>
                  Excluir Parcela
                </DropdownItem>
              </Dropdown>
            </template>
            <template v-else>
              <div
                class="flex items-center gap-1.5 px-3 py-1.5 bg-background border border-border rounded-lg text-text-tertiary"
                title="Sem ações disponíveis"
              >
                <svg
                  class="w-4 h-4 opacity-50"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 00-2 2zm10-10V7a4 4 0 00-8 0v4h8z"
                  />
                </svg>
                <span class="text-[10px] font-bold uppercase tracking-tight">Finalizado</span>
              </div>
            </template>
          </div>
        </template>
      </DataTable>
    </div>

    <!-- Modal Prorrogação -->
    <Teleport to="body">
      <div v-if="mostrarProrrogacao" class="fixed inset-0 z-999 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="fecharProrrogacao" />
        <div
          class="relative bg-surface rounded-2xl shadow-2xl border border-border w-full max-w-2xl mx-4 max-h-[90vh] flex flex-col overflow-hidden"
        >
          <!-- Preload Interno (Calculando encargos) -->
          <div
            v-if="carregandoSimulacaoProrrogacao"
            class="absolute inset-0 z-20 bg-surface/60 backdrop-blur-[2px] flex flex-col items-center justify-center transition-all"
          >
            <div class="flex flex-col items-center gap-2">
              <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
              <span
                class="text-[10px] font-bold text-primary uppercase tracking-widest animate-pulse"
                >Calculando encargos...</span
              >
            </div>
          </div>

          <div class="px-6 py-4 border-b border-border flex items-center justify-between">
            <h3 class="text-lg font-bold text-text-primary uppercase tracking-wider">
              Prorrogar Parcela
            </h3>
            <button
              @click="fecharProrrogacao"
              class="p-2 hover:bg-background rounded-lg transition-colors"
            >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M6 18L18 6M6 6l12 12"
                />
              </svg>
            </button>
          </div>

          <div class="p-6 overflow-y-auto space-y-6">
            <div class="grid grid-cols-2 gap-4">
              <div class="p-4 bg-background rounded-xl border border-border">
                <span class="text-[10px] font-bold text-text-tertiary uppercase block"
                  >Valor Original</span
                >
                <span class="text-lg font-bold text-text-primary">{{
                  formatarMoeda(valorOriginal)
                }}</span>
              </div>
              <div class="p-4 bg-background rounded-xl border border-border">
                <span class="text-[10px] font-bold text-text-tertiary uppercase block"
                  >Dias de Extensão</span
                >
                <span class="text-lg font-bold text-amber-600">{{ diasAtraso }}</span>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div class="p-3 bg-amber-50/50 rounded-xl border border-amber-100 text-center">
                <span class="text-[9px] font-bold text-amber-600 uppercase block"
                  >Juros de Mora</span
                >
                <span class="text-xs font-bold text-amber-800">{{ formatarMoeda(jurosMora) }}</span>
                <span class="text-[8px] text-amber-500 block"
                  >Simples (máx {{ taxaJurosMora }}% a.m.)</span
                >
              </div>
              <div class="p-3 bg-red-50/50 rounded-xl border border-red-100 text-center">
                <span class="text-[9px] font-bold text-red-600 uppercase block"
                  >Multa Moratória</span
                >
                <span class="text-xs font-bold text-red-800">{{ formatarMoeda(multaAtraso) }}</span>
                <span class="text-[8px] text-red-500 block">1x (máx {{ taxaMulta }}% CDC)</span>
              </div>
            </div>

            <div
              class="p-4 bg-primary/5 rounded-xl border border-primary/10 flex justify-between items-center"
            >
              <span class="text-xs font-bold text-primary uppercase">Novo Valor da Parcela</span>
              <span class="text-xl font-black text-primary">{{ formatarMoeda(valorDevido) }}</span>
            </div>

            <div class="space-y-4 pt-2">
              <Input
                v-model="formularioProrrogacao.data_vencimento_nova"
                label="Novo Vencimento"
                type="date"
                required
              />
              <div class="flex flex-col gap-1.5">
                <label class="px-1 text-[10px] font-bold text-text-tertiary uppercase"
                  >Justificativa *</label
                >
                <textarea
                  v-model="formularioProrrogacao.justificativa"
                  class="w-full bg-background border border-border rounded-xl p-4 text-sm outline-none focus:border-primary transition-all min-h-[100px]"
                  placeholder="Motivo da prorrogação..."
                ></textarea>
              </div>
            </div>
          </div>

          <div class="p-4 border-t border-border bg-surface flex justify-end gap-3">
            <Button variant="ghost" @click="fecharProrrogacao">Cancelar</Button>
            <Button
              variant="primary"
              :loading="prorrogacaoCarregando"
              :disabled="
                carregandoSimulacaoProrrogacao ||
                !valorSimulado ||
                !formularioProrrogacao.data_vencimento_nova
              "
              @click="onProrrogacaoSalva"
            >
              Confirmar Prorrogação
            </Button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Modal Recebimento Unificado -->
    <ReceitaRecebimentoModal
      :visivel="mostrarRecebimento"
      :receita="receitaSelecionada"
      :contrato="contratoRecebimento"
      :valor-minimo-carencia="valorMinimoCarencia"
      :usos-carencia-restantes="usosCarenciaRestantes"
      :carregando="recebimentoCarregando"
      :encargos="encargosRecebimento"
      @fechar="mostrarRecebimento = false"
      @salvo="onRecebimentoSalvo"
    />

    <ConfirmModal
      :isOpen="modalExclusao.visivel"
      title="Excluir Receita"
      :message="modalExclusao.mensagem"
      :detail="modalExclusao.itemExcluir?.descricao"
      confirm-label="Excluir"
      type="danger"
      :loading="modalExclusao.carregando"
      @confirm="handleConfirmarExclusao"
      @cancel="fecharModalExclusao"
    />
  </div>
</template>
