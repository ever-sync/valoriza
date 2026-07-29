<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import PageHeader from '@/components/ui/PageHeader.vue'
import DataTable from '@/components/ui/DataTable.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import DataFilter from '@/components/ui/DataFilter.vue'
import { useLista } from '@/composables/useLista'
import * as service from '../services/usuarios.service.js'

const router = useRouter()

const filterSchema = [
  { key: 'nome_completo_like', label: 'Nome', type: 'text', placeholder: 'Ex: João' },
  { key: 'email', label: 'E-mail', type: 'text', placeholder: 'Ex: joao@email.com' }
]

const tableHeader = "Nome {nome_completo}, E-mail {email}, Perfil {perfil_acesso}, Status {ativo}"

const mapearParametrosUsuarios = (params) => ({
  page: params.pagina,
  limit: params.porPagina,
  order: params.ordenacaoColuna,
  direction: params.ordenacaoDirecao,
  nome_completo_like: params.nome_completo_like || '',
  email: params.email || ''
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
  serviceBuscar: (params) => service.getUsuarios(params, { showLoading: false }),
  serviceExcluir: service.deleteUsuario,
  filtrosIniciais: { nome_completo_like: '', email: '' },
  ordenacaoInicial: { coluna: 'id', direcao: 'desc' },
  mapearParametros: mapearParametrosUsuarios
})

const handleAdd = () => { router.push('/usuarios/novo') }

const handleEdit = (row) => { router.push(`/usuarios/editar/${row.id}`) }

const handleDelete = (row) => {
  confirmarExclusao(row, 'Este usuário perderá o acesso ao sistema instantaneamente. Deseja continuar?')
}

onMounted(buscar)
</script>

<template>
  <div class="p-4 md:p-8 h-full flex flex-col gap-6">
    <PageHeader title="Controle de Usuários">
      <template #actions>
        <Button variant="primary" @click="handleAdd">
          Novo Usuário
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
            placeholder="Buscar por nome ou e-mail..."
         />
      </div>

      <DataTable
        :thead="tableHeader"
        :data="data"
        :current-page="meta.pagina"
        :per-page="meta.porPagina"
        :total-items="meta.total"
        :loading="carregando"
        @update:page="val => { meta.pagina = val; buscar() }"
        @update:perPage="val => { meta.porPagina = val; meta.pagina = 1; buscar() }"
        @edit="handleEdit"
        @delete="handleDelete"
      >
        <template #col-nome_completo="{ value }">
          <span class="font-semibold text-text-primary">{{ value || '-' }}</span>
        </template>

        <template #col-email="{ value }">
          <span class="text-sm text-text-secondary">{{ value || '-' }}</span>
        </template>

        <template #col-ativo="{ value }">
          <div v-if="value == 1" class="flex items-center gap-1.5 text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-100 w-fit">
            <span class="text-[10px] font-bold uppercase tracking-wider">Ativo</span>
          </div>
          <div v-else class="flex items-center gap-1.5 text-rose-600 bg-rose-50 px-2 py-0.5 rounded-full border border-rose-100 w-fit">
            <span class="text-[10px] font-bold uppercase tracking-wider">Inativo</span>
          </div>
        </template>

        <template #col-perfil_acesso="{ value }">
           <span class="text-xs font-bold px-2 py-1 rounded-lg bg-background border border-border text-text-primary capitalize">{{ value }}</span>
        </template>
      </DataTable>
    </div>

    <ConfirmModal
      :isOpen="modalExclusao.visivel"
      title="Excluir Usuário"
      :message="modalExclusao.mensagem"
      :detail="modalExclusao.itemExcluir?.nome_completo"
      confirm-label="Excluir"
      type="danger"
      :loading="modalExclusao.carregando"
      @confirm="excluirItem"
      @cancel="fecharModalExclusao"
    />
  </div>
</template>
