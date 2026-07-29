<script setup>
import { onMounted } from 'vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import DataTable from '@/components/ui/DataTable.vue'
import Button from '@/components/ui/Button.vue'
import DataFilter from '@/components/ui/DataFilter.vue'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import { useLista } from '@/composables/useLista'
import { getBancos, deleteBanco } from '../services/bancos.service.js'
import { useRouter } from 'vue-router'

const router = useRouter()

const filterSchema = [
  { key: 'banco_like', label: 'Instituição', type: 'text', placeholder: 'Ex: Itaú' },
  { key: 'agencia', label: 'Agência', type: 'text', placeholder: '0001' },
  { key: 'conta', label: 'Conta', type: 'text', placeholder: '12345' }
]

const tableHeader = "Instituição {banco}, Agência {agencia}, Conta {conta}, PIX {chave_pix}, Padrão {padrao}"

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
  serviceBuscar: getBancos,
  serviceExcluir: deleteBanco,
  filtrosIniciais: { banco_like: '', agencia: '', conta: '' }
})

const handleOrdenar = (dadosOrdenacao) => {
  ordenarPorColuna(dadosOrdenacao.column)
}

const handleAdicionar = () => {
  router.push('/bancos/novo')
}

const handleEditar = (linha) => {
  router.push(`/bancos/editar/${linha.id}`)
}

const handleExcluir = (linha) => {
  confirmarExclusao(linha, 'Esta conta bancária será removida permanentemente. Verifique se existem lançamentos vinculados.')
}

const handleConfirmarExclusao = async () => {
  const sucesso = await excluirItem()
  if (sucesso) {
    router.push('/bancos')
  }
}

onMounted(() => {
  buscar()
})
</script>

<template>
  <div class="p-4 md:p-8 h-full flex flex-col gap-6">
    <PageHeader title="Contas Bancárias">
      <template #actions>
        <Button variant="primary" @click="handleAdicionar">
          Cadastrar Banco
        </Button>
      </template>
    </PageHeader>

    <div class="flex-1 flex flex-col gap-4">
      <div class="flex items-center justify-between bg-surface p-4 rounded-2xl border border-border shadow-sm">
        <DataFilter 
          v-model="filtros"
          :schema="filterSchema"
          @apply="() => aplicarFiltros(filtros)"
          @clear="limparFiltros"
          placeholder="Buscar por instituição ou conta..."
        />
      </div>

      <div class="flex-1">
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
          <template #col-banco="{ value }">
            <span class="font-semibold text-text-primary">{{ value || '-' }}</span>
          </template>

          <template #col-padrao="{ value }">
            <div v-if="value == 1" class="flex items-center gap-1.5 text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-100 w-fit">
              <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></div>
              <span class="text-[10px] font-bold uppercase tracking-wider">Padrão</span>
            </div>
            <span v-else class="text-text-secondary text-xs italic">Secundária</span>
          </template>

          <template #col-agencia="{ value }">
            <span class="font-mono text-sm text-text-primary">{{ value }}</span>
          </template>

          <template #col-conta="{ value }">
            <span class="font-mono text-sm font-bold text-text-primary">{{ value }}</span>
          </template>
        </DataTable>
      </div>
    </div>

    <ConfirmModal
      :isOpen="modalExclusao.visivel"
      title="Excluir Banco"
      :message="modalExclusao.mensagem"
      :detail="modalExclusao.itemExcluir?.banco + ' - ' + modalExclusao.itemExcluir?.conta"
      confirm-label="Excluir"
      type="danger"
      :loading="modalExclusao.carregando"
      @confirm="handleConfirmarExclusao"
      @cancel="fecharModalExclusao"
    />
  </div>
</template>