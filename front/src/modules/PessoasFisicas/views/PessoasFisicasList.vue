<script setup>
import { onMounted } from 'vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import DataTable from '@/components/ui/DataTable.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import DataFilter from '@/components/ui/DataFilter.vue'
import { useLista } from '@/composables/useLista'
import { getPessoasFisicas, deletePessoaFisica } from '../services/pessoasFisicas.service.js'
import { useRouter } from 'vue-router'

const router = useRouter()

const filterSchema = [
  { key: 'nome_completo_like', label: 'Nome Completo', type: 'text', placeholder: 'Ex: João Silva' },
  { key: 'cpf', label: 'CPF', type: 'mask', mask: 'cpf', placeholder: '000.000.000-00' },
  { key: 'email_like', label: 'E-mail', type: 'text', placeholder: 'exemplo@email.com' },
  { key: 'cidade_like', label: 'Cidade', type: 'text', placeholder: 'Ex: São Paulo' }
]

const tableHeader = "Nome Completo {nome_completo}, CPF {cpf}, E-mail {email}, Telefone {telefone}, Cidade {cidade}"

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
  serviceBuscar: getPessoasFisicas,
  serviceExcluir: deletePessoaFisica,
  filtrosIniciais: { nome_completo_like: '', cpf: '', email_like: '', cidade_like: '' }
})

const handleOrdenar = (dadosOrdenacao) => {
  ordenarPorColuna(dadosOrdenacao.column)
}

const handleAdicionar = () => {
  router.push('/pessoas-fisicas/novo')
}

const handleEditar = (linha) => {
  router.push(`/pessoas-fisicas/editar/${linha.id}`)
}

const handleExcluir = (linha) => {
  confirmarExclusao(linha, 'Tem certeza que deseja excluir esta pessoa física?')
}

const handleConfirmarExclusao = async () => {
  const sucesso = await excluirItem()
  if (sucesso) {
    router.push('/pessoas-fisicas')
  }
}

onMounted(() => {
  buscar()
})
</script>

<template>
  <div class="p-4 md:p-8 h-full flex flex-col gap-6">
    <PageHeader title="Pessoas Físicas">
      <template #actions>
        <Button variant="primary" @click="handleAdicionar">
          Nova Pessoa Física
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
          placeholder="Buscar pessoas..."
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
        <template #col-nome_completo="{ value }">
          <span class="font-semibold text-text-primary">{{ value || '-' }}</span>
        </template>

        <template #col-cpf="{ value }">
          <span class="font-mono text-sm">{{ value || '-' }}</span>
        </template>

        <template #col-email="{ value }">
          <span class="text-sm text-text-secondary">{{ value || '-' }}</span>
        </template>

        <template #col-telefone="{ value }">
          <span class="text-sm text-text-secondary">{{ value || '-' }}</span>
        </template>

        <template #col-cidade="{ value }">
          <span class="text-sm text-text-secondary">{{ value || '-' }}</span>
        </template>
      </DataTable>
    </div>

    <ConfirmModal
      :isOpen="modalExclusao.visivel"
      title="Excluir Pessoa Física"
      :message="modalExclusao.mensagem"
      :detail="modalExclusao.itemExcluir?.nome_completo"
      confirm-label="Excluir"
      type="danger"
      :loading="modalExclusao.carregando"
      @confirm="handleConfirmarExclusao"
      @cancel="fecharModalExclusao"
    />
  </div>
</template>