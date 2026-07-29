<script setup>
import { onMounted } from 'vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import DataTable from '@/components/ui/DataTable.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import DataFilter from '@/components/ui/DataFilter.vue'
import { useLista } from '@/composables/useLista'
import { getDespesas, deleteDespesa } from '../services/despesas.service.js'
import { useRouter } from 'vue-router'

const router = useRouter()

const filterSchema = [
  { 
    key: 'status', 
    label: 'Status', 
    type: 'select', 
    options: [
      { label: 'Pendente', value: 'Pendente' },
      { label: 'Pago', value: 'Pago' },
      { label: 'Atrasado', value: 'Atrasado' },
      { label: 'Cancelado', value: 'Cancelado' }
    ]
  },
  {
    key: 'categoria_id',
    label: 'Categoria',
    type: 'select',
    options: [
      { label: 'Alimentação', value: '1' },
      { label: 'Impostos', value: '2' },
      { label: 'Investimentos', value: '3' },
      { label: 'Marketing', value: '4' },
      { label: 'Saúde', value: '5' },
      { label: 'Tecnologia', value: '6' }
    ]
  },
  {
    key: 'favorecido_nome_like',
    label: 'Favorecido',
    type: 'text',
    placeholder: 'Nome do favorecido...'
  },
  {
    key: 'data_vencimento_between',
    label: 'Vencimento (Período)',
    type: 'date_range'
  },
  {
    key: 'conta_bancaria_origem_id',
    label: 'Conta Bancária',
    type: 'select',
    options: [
      { label: 'Banco do Brasil', value: '1' },
      { label: 'Bradesco', value: '2' },
      { label: 'Itaú', value: '3' },
      { label: 'Santander', value: '4' },
      { label: 'Nubank', value: '5' }
    ]
  }
]

const tableHeader = "Vencimento {data_vencimento}, Descrição {descricao}, Favorecido {favorecido_nome}, Valor {valor_pago}, Status {status}"

const {
  carregando,
  itens,
  meta,
  filtros,
  ordenarPorColuna,
  buscar,
  aplicarFiltros,
  limparFiltros,
  modalExclusao,
  confirmarExclusao,
  excluirItem,
  fecharModalExclusao
} = useLista({
  serviceBuscar: getDespesas,
  serviceExcluir: deleteDespesa,
  filtrosIniciais: { status: '', categoria_id: '', favorecido_nome_like: '', data_vencimento_between: '', conta_bancaria_origem_id: '' },
  mapearParametros: (params) => ({
    pagina_atual: params.pagina,
    por_pagina: params.porPagina,
    ordena: `${params.ordenacaoColuna}_${params.ordenacaoDirecao}`,
    status: params.status || '',
    categoria_id: params.categoria_id || '',
    favorecido_nome_like: params.favorecido_nome_like || '',
    data_vencimento_between: params.data_vencimento_between || '',
    conta_bancaria_origem_id: params.conta_bancaria_origem_id || ''
  })
})

const handleOrdenar = (dadosOrdenacao) => {
  ordenarPorColuna(dadosOrdenacao.column)
}

const handleAdicionar = () => {
  router.push('/despesas/novo')
}

const handleEditar = (linha) => {
  router.push(`/despesas/editar/${linha.id}`)
}

const handleExcluir = (linha) => {
  confirmarExclusao(linha, 'Tem certeza que deseja excluir esta despesa?')
}

const handleConfirmarExclusao = async () => {
  const sucesso = await excluirItem()
  if (sucesso) {
    router.push('/despesas')
  }
}

const formatarMoeda = (valor) => {
  return Number(valor || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

onMounted(() => {
  buscar()
})
</script>

<template>
  <div class="p-4 md:p-8 h-full flex flex-col gap-6">
    <PageHeader title="Despesas">
      <template #actions>
        <Button variant="primary" @click="handleAdicionar">
          Nova Despesa
        </Button>
      </template>
    </PageHeader>



    <div class="flex-1 flex flex-col gap-8">
      <div class="flex items-center justify-between">
        <DataFilter 
          v-model="filtros"
          :schema="filterSchema"
          @apply="() => aplicarFiltros(filtros)"
          @clear="limparFiltros"
          placeholder="Buscar despesas..."
        />
      </div>

      <DataTable
        :thead="tableHeader"
        :data="itens"
        :current-page="meta.pagina"
        :per-page="meta.porPagina"
        :total-items="meta.total"
        :loading="carregando"
        @update:page="val => { meta.pagina = val; buscar() }"
        @update:perPage="val => { meta.porPagina = val; meta.pagina = 1; buscar() }"
        @edit="handleEditar"
        @delete="handleExcluir"
        @sort="handleOrdenar"
      >
        <template #col-data_vencimento="{ value }">
          <span class="text-sm text-text-primary">
            {{ value ? new Date(value + 'T00:00:00').toLocaleDateString('pt-BR') : '-' }}
          </span>
        </template>

        <template #col-favorecido_nome="{ value }">
          <span class="text-sm font-semibold text-text-primary">{{ value || '-' }}</span>
        </template>

        <template #col-status="{ value }">
          <span 
            class="px-2 py-1 rounded-full text-xs font-bold uppercase"
            :class="{
              'bg-amber-50 text-amber-700 border border-amber-200': value === 'Pendente',
              'bg-emerald-50 text-emerald-700 border border-emerald-200': value === 'Pago',
              'bg-red-50 text-red-700 border border-red-200': value === 'Atrasado',
              'bg-gray-50 text-gray-600 border border-gray-200': value === 'Cancelado'
            }"
          >
            {{ value }}
          </span>
        </template>

        <template #col-valor_pago="{ value }">
          <span class="font-semibold text-red-600">
            {{ Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) }}
          </span>
        </template>
      </DataTable>
    </div>

    <ConfirmModal
      :isOpen="modalExclusao.visivel"
      title="Excluir Despesa"
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