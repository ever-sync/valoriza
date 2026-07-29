<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import PageHeader from '@/components/ui/PageHeader.vue'
import DataTable from '@/components/ui/DataTable.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import DataFilter from '@/components/ui/DataFilter.vue'
import { useLista } from '@/composables/useLista'
import * as service from '../services/contratos.service.js'

const router = useRouter()

const filterSchema = [
  {
    key: 'cliente',
    label: 'Cliente',
    type: 'text'
  },
  {
    key: 'numero_contrato',
    label: 'Nº Contrato',
    type: 'text'
  },
  {
    key: 'tipo_operacao',
    label: 'Tipo',
    type: 'select',
    options: [
      { label: 'Empréstimo', value: 'Empréstimo' },
      { label: 'Financiamento', value: 'Financiamento' },
      { label: 'Desconto de Recebíveis', value: 'Desconto de Recebíveis' }
    ]
  },
  {
    key: 'data_assinatura',
    label: 'Data',
    type: 'date'
  },
  {
    key: 'valor_solicitado',
    label: 'Valor',
    type: 'number'
  },
  {
    key: 'status',
    label: 'Status',
    type: 'select',
    options: [
      { label: 'Ativo', value: 'Ativo' },
      { label: 'Quitado', value: 'Quitado' },
      { label: 'Inadimplente', value: 'Inadimplente' },
      { label: 'Cancelado', value: 'Cancelado' },
    ]
  }
]

const tableHeader = "Código {numero_contrato}, Data {data_assinatura}, Cliente {cliente_nome}, Tipo {tipo_operacao}, Valor {valor_solicitado}, Parcelas {quantidade_parcelas}, Status {status}"

const mapearParametrosContratos = (params) => ({
  pagina_atual: params.pagina,
  por_pagina: params.porPagina,
  ordena: `${params.ordenacaoColuna}_${params.ordenacaoDirecao}`,
  search: params.search || '',
  status: params.status || '',
  tipo_operacao: params.tipo_operacao || '',
  numero_contrato_like: params.numero_contrato ? `%${params.numero_contrato}%` : '',
  cliente_nome_like: params.cliente ? `%${params.cliente}%` : '',
  data_assinatura: params.data_assinatura || '',
  valor_solicitado: params.valor_solicitado || ''
})

const {
  carregando,
  itens: data,
  meta,
  filtros,
  modalExclusao,
  buscar,
  aplicarFiltros,
  limparFiltros,
  confirmarExclusao,
  fecharModalExclusao,
  excluirItem
} = useLista({
  serviceBuscar: (params) => service.getContratos(params, { showLoading: false }),
  serviceExcluir: service.deleteContrato,
  filtrosIniciais: { search: '', cliente: '', status: '', tipo_operacao: '', numero_contrato: '', data_assinatura: '', valor_solicitado: '' },
  ordenacaoInicial: { coluna: 'id', direcao: 'desc' },
  mapearParametros: mapearParametrosContratos
})

const handleAdd = () => { router.push('/contratos/novo') }

const handleDelete = (row) => {
  confirmarExclusao(row, 'Tem certeza que deseja excluir este contrato? Esta ação não pode ser desfeita.')
}

const formatarMoeda = (valor) => {
  return Number(valor || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

onMounted(buscar)
</script>

<template>
  <div class="p-4 md:p-8 h-full flex flex-col gap-6 overflow-y-auto custom-scrollbar">
    <PageHeader title="Contratos">
      <template #actions>
        <Button variant="primary" @click="handleAdd">Novo Contrato</Button>
      </template>
    </PageHeader>



    <div class="flex-1 flex flex-col gap-8">
      <div class="flex items-center justify-between">
        <DataFilter
          v-model="filtros"
          :schema="filterSchema"
          @apply="() => aplicarFiltros(filtros)"
          @clear="limparFiltros"
          placeholder="Pesquisar por cliente..."
        />
      </div>

      <div class="flex-1">
        <DataTable
          :thead="tableHeader"
          :data="data"
          :current-page="meta.pagina"
          :per-page="meta.porPagina"
          :total-items="meta.total"
          :loading="carregando"
          @update:page="val => { meta.pagina = val; buscar() }"
          @update:perPage="val => { meta.porPagina = val; meta.pagina = 1; buscar() }"
          @delete="handleDelete"
          @row-click="row => $router.push(`/contratos/status/${row.id}`)"
        >
          <template #col-numero_contrato="{ value }">
            <span class="text-sm font-bold text-primary">{{ value || '-' }}</span>
          </template>
          <template #actions="{ row }">
            <div class="flex items-center gap-1">
              <button
                @click.stop="$router.push(`/contratos/status/${row.id}`)"
                class="p-2 text-primary hover:bg-primary/10 rounded-xl transition-all"
                title="Ver Status da Execução"
              >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
              </button>
            </div>
          </template>
          <template #col-data_assinatura="{ value }">
            <span class="text-sm font-bold text-text-primary">
              {{ value ? new Date(value + 'T00:00:00').toLocaleDateString('pt-BR') : '-' }}
            </span>
          </template>

          <template #col-cliente_nome="{ value }">
            <span class="text-sm text-text-primary font-semibold">{{ value || 'Não informado' }}</span>
          </template>

          <template #col-valor_solicitado="{ value }">
            <span class="text-sm font-black text-text-primary">
              {{ Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) }}
            </span>
          </template>

          <template #col-quantidade_parcelas="{ value, row }">
            <span class="text-sm text-text-secondary">{{ value }}x / {{ row.periodo_amortizacao }}</span>
          </template>

          <template #col-status="{ value }">
            <div
              class="px-3 py-1 rounded-xl text-[10px] font-black uppercase tracking-tighter w-fit border shadow-sm"
              :class="{
                'bg-emerald-50 text-emerald-600 border-emerald-100': value === 'Quitado',
                'bg-blue-50 text-blue-600 border-blue-100': value === 'Ativo',
                'bg-rose-50 text-rose-600 border-rose-100': value === 'Inadimplente',
                'bg-gray-50 text-gray-500 border-gray-100': value === 'Cancelado',
              }"
            >{{ value }}</div>
          </template>
        </DataTable>
      </div>
    </div>

    <ConfirmModal
      :isOpen="modalExclusao.visivel"
      title="Excluir Contrato"
      :message="modalExclusao.mensagem"
      :detail="modalExclusao.itemExcluir?.cliente_nome"
      confirm-label="Sim, Excluir"
      type="danger"
      :loading="modalExclusao.carregando"
      @confirm="excluirItem"
      @cancel="fecharModalExclusao"
    />
  </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
</style>
