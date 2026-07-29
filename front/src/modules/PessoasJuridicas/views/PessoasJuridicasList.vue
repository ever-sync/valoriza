<script setup>
import { onMounted } from 'vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import DataTable from '@/components/ui/DataTable.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import DataFilter from '@/components/ui/DataFilter.vue'
import { useLista } from '@/composables/useLista'
import { getPessoasJuridicas, deletePessoaJuridica } from '../services/pessoasJuridicas.service.js'
import { useRouter } from 'vue-router'

const router = useRouter()

const filterSchema = [
  { key: 'razao_social_like', label: 'Razão Social', type: 'text', placeholder: 'Ex: Empresa Ltda' },
  { key: 'nome_fantasia_like', label: 'Nome Fantasia', type: 'text', placeholder: 'Ex: Empresa' },
  { key: 'cnpj', label: 'CNPJ', type: 'mask', mask: 'cnpj', placeholder: '00.000.000/0000-00' },
  { key: 'email_like', label: 'E-mail', type: 'text', placeholder: 'exemplo@email.com' }
]

const tableHeader = "Razão Social {razao_social}, Nome Fantasia {nome_fantasia}, CNPJ {cnpj}, E-mail {email}"

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
  serviceBuscar: getPessoasJuridicas,
  serviceExcluir: deletePessoaJuridica,
  filtrosIniciais: { razao_social_like: '', nome_fantasia_like: '', cnpj: '', email_like: '' }
})

const handleOrdenar = (dadosOrdenacao) => {
  ordenarPorColuna(dadosOrdenacao.column)
}

const handleAdicionar = () => {
  router.push('/pessoas-juridicas/novo')
}

const handleEditar = (linha) => {
  router.push(`/pessoas-juridicas/editar/${linha.id}`)
}

const handleExcluir = (linha) => {
  confirmarExclusao(linha, 'Tem certeza que deseja excluir esta pessoa jurídica?')
}

const handleConfirmarExclusao = async () => {
  const sucesso = await excluirItem()
  if (sucesso) {
    router.push('/pessoas-juridicas')
  }
}

onMounted(() => {
  buscar()
})
</script>

<template>
  <div class="p-4 md:p-8 h-full flex flex-col gap-6">
    <PageHeader title="Pessoas Jurídicas">
      <template #actions>
        <Button variant="primary" @click="handleAdicionar">
          Nova Pessoa Jurídica
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
          placeholder="Buscar empresas..."
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
        <template #col-razao_social="{ value }">
          <span class="font-semibold text-text-primary">{{ value || '-' }}</span>
        </template>

        <template #col-nome_fantasia="{ value }">
          <span class="text-sm text-text-secondary">{{ value || '-' }}</span>
        </template>

        <template #col-cnpj="{ value }">
          <span class="font-mono text-sm">{{ value || '-' }}</span>
        </template>

        <template #col-email="{ value }">
          <span class="text-sm text-text-secondary">{{ value || '-' }}</span>
        </template>
      </DataTable>
    </div>

    <ConfirmModal
      :isOpen="modalExclusao.visivel"
      title="Excluir Pessoa Jurídica"
      :message="modalExclusao.mensagem"
      :detail="modalExclusao.itemExcluir?.razao_social"
      confirm-label="Excluir"
      type="danger"
      :loading="modalExclusao.carregando"
      @confirm="handleConfirmarExclusao"
      @cancel="fecharModalExclusao"
    />
  </div>
</template>