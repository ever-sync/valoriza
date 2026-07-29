<script setup>
/**
 * Etapa 3 — Garantias do Contrato.
 * Redesenhado com interface baseada em cartões (cards) premium.
 */
import { inject } from 'vue'
import Input from '@/components/ui/Input.vue'
import DynamicSelect from '@/components/ui/DynamicSelect.vue'

const store = inject('contratoStore')

const iconesGarantia = {
  'Avalista': 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
  'Devedor solidário': 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
  'Imóvel': 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
  'Veículo': 'M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10 M13 8h4.586a1 1 0 01.707.293l2.414 2.414a1 1 0 01.293.707V16h-2',
  'Recebível': 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
  'Outro': 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10'
}

const getIconForGarantia = (tipo) => iconesGarantia[tipo] || iconesGarantia['Outro']

</script>

<template>
  <div class="space-y-6 max-w-2xl mx-auto pb-4">
    <div v-if="store.garantias.value.length === 0" class="flex flex-col items-center justify-center p-10 bg-surface border border-dashed border-border rounded-3xl text-center space-y-4">
      <div class="w-16 h-16 rounded-full bg-primary/5 flex items-center justify-center text-primary">
        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
      </div>
      <div>
        <h4 class="text-sm font-bold text-text-primary">Nenhuma garantia vinculada</h4>
        <p class="text-xs text-text-tertiary mt-1">A operação está configurada como "Sem Garantia".<br>Você pode adicionar avalistas, imóveis, veículos, etc.</p>
      </div>
      <button type="button" @click="store.adicionarGarantia" class="mt-2 px-6 py-2 bg-white border border-primary text-primary text-xs font-bold uppercase tracking-wider rounded-xl hover:bg-primary/5 transition-colors shadow-sm">
        Adicionar Garantia
      </button>
    </div>

    <div v-else class="space-y-6">
      <div
        v-for="(g, i) in store.garantias.value"
        :key="g.id"
        class="bg-white border border-border rounded-2xl shadow-sm overflow-hidden transition-all duration-300"
        :class="g._aberta ? 'ring-1 ring-primary/30 shadow-md' : 'hover:border-gray-300'"
      >
        <!-- Header do Card -->
        <div class="flex items-center justify-between px-5 py-4 cursor-pointer bg-surface/30 hover:bg-surface transition-colors" @click="store.alternarGarantiaAberta(i)">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary flex-shrink-0 shadow-inner">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" :d="getIconForGarantia(g.tipo_garantia)" />
              </svg>
            </div>
            <div>
              <span class="text-sm font-bold text-text-primary block">{{ g.tipo_garantia }}</span>
              <span class="text-[11px] text-text-tertiary font-medium">
                {{ g.pessoa_nome_garantia || g.descricao || g.modelo || g.proprietario || 'Configurar dados...' }}
              </span>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <button type="button" @click.stop="store.removerGarantia(i)" class="p-2 text-text-tertiary hover:text-danger hover:bg-danger/10 rounded-lg transition-colors" title="Remover">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            </button>
            <div class="w-8 h-8 rounded-full flex items-center justify-center bg-white border border-border text-text-secondary transition-transform duration-300" :class="g._aberta ? 'rotate-180 bg-primary/5 border-primary/20 text-primary' : ''">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
          </div>
        </div>

        <!-- Conteúdo do Card -->
        <div v-if="g._aberta" class="p-6 pt-2 space-y-5 border-t border-border/50">
          <div>
            <label class="px-1 text-[11px] font-bold uppercase tracking-wider text-text-tertiary">Alterar tipo de Garantia</label>
            <select v-model="g.tipo_garantia" class="w-full bg-surface hover:bg-surface-hover border border-border rounded-xl py-2.5 px-4 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all mt-1">
              <option v-for="t in store.tiposGarantiaOpcoes" :key="t" :value="t">{{ t }}</option>
            </select>
          </div>

          <!-- Garantia do tipo Pessoa (Avalista / Devedor Solidário) -->
          <template v-if="store.ehPessoa(g)">
            <div class="bg-surface p-4 rounded-2xl border border-border/50">
              <label class="px-1 text-[11px] font-bold uppercase tracking-wider text-text-tertiary block mb-2">Pessoa Vinculada*</label>
              <div class="p-1 bg-white border border-border rounded-xl flex items-center gap-1 w-full max-w-[240px] mb-4 shadow-sm">
                <button 
                  type="button"
                  class="flex-1 py-1.5 text-[10px] font-bold uppercase tracking-wider rounded-lg transition-colors z-10"
                  :class="g.tipo_pessoa_garantia === 'pj' ? 'bg-primary/10 text-primary' : 'text-text-tertiary hover:bg-surface'"
                  @click="g.tipo_pessoa_garantia = 'pj'"
                >Pessoa Jurídica</button>
                <button 
                  type="button"
                  class="flex-1 py-1.5 text-[10px] font-bold uppercase tracking-wider rounded-lg transition-colors z-10"
                  :class="g.tipo_pessoa_garantia === 'pf' ? 'bg-primary/10 text-primary' : 'text-text-tertiary hover:bg-surface'"
                  @click="g.tipo_pessoa_garantia = 'pf'"
                >Pessoa Física</button>
              </div>

              <DynamicSelect
                v-model="g.pessoa_id_garantia"
                :search-service="(p) => store.buscarPessoaGarantia(i, p)"
                :label="g.tipo_pessoa_garantia === 'pf' ? 'Buscar Pessoa Física*' : 'Buscar Pessoa Jurídica*'"
                :label-key="g.tipo_pessoa_garantia === 'pf' ? 'nome_completo' : 'razao_social'"
                :initial-label="g.pessoa_nome_garantia"
                placeholder="Comece a digitar..."
                @select="(pessoa) => store.aoSelecionarPessoaGarantia(i, pessoa)"
              >
                <template #option-detail="{ option }">
                  <span class="text-[10px] font-bold text-text-tertiary uppercase bg-surface px-1.5 py-0.5 rounded ml-2">{{ option.cpf || option.cnpj }}</span>
                </template>
              </DynamicSelect>

              <div v-if="g.pessoa_nome_garantia" class="mt-4 p-4 rounded-xl bg-emerald-50 border border-emerald-200 flex flex-col gap-2 relative shadow-sm">
                <button type="button" @click="g.pessoa_id_garantia = ''; g.pessoa_nome_garantia = '';" class="absolute top-3 right-3 text-danger hover:bg-danger/10 transition-colors bg-white border border-border rounded-full p-1.5">
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center flex-shrink-0 text-emerald-600 border border-emerald-200">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                  </div>
                  <div class="flex flex-col pr-8">
                    <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider mb-0.5">VINCULADO COM SUCESSO</span>
                    <span class="font-bold text-text-primary text-sm">{{ g.pessoa_nome_garantia }}</span>
                    <span class="text-[11px] font-medium text-text-secondary mt-0.5">{{ g.cpf }}</span>
                  </div>
                </div>
              </div>

              <div v-if="!g.pessoa_id_garantia" class="mt-4 flex items-center justify-center border-t border-border/50 pt-4">
                <button type="button" @click="store.abrirModalPessoaGarantia(i)" class="text-xs font-bold text-primary hover:text-primary-hover transition-colors flex items-center gap-1.5">
                  <div class="w-5 h-5 rounded-full bg-primary/10 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"/></svg>
                  </div>
                  Cadastrar Novo(a)
                </button>
              </div>
            </div>
          </template>

          <!-- Garantia de Imóvel -->
          <template v-else-if="store.ehImovel(g)">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Input v-model="g.tabelionato" label="Tabelionato*" />
              <Input v-model="g.numero_matricula" label="Número de Matrícula*" />
              <Input v-model="g.proprietario" label="Proprietário*" />
              <Input v-model="g.valor_avaliacao" label="Valor de avaliação*" mask="real" />
            </div>
            <div>
              <label class="px-1 text-[11px] font-bold uppercase tracking-wider text-text-tertiary">Descrição do Imóvel*</label>
              <textarea v-model="g.descricao" rows="3" class="w-full bg-surface hover:bg-surface-hover border border-border rounded-xl py-2.5 px-4 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 mt-1 resize-y transition-all" placeholder="Endereço, características, etc." />
            </div>
          </template>

          <!-- Garantia de Veículo -->
          <template v-else-if="store.ehVeiculo(g)">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <Input v-model="g.fabricante" label="Fabricante*" placeholder="Ex: Toyota" />
              <Input v-model="g.modelo" label="Modelo*" placeholder="Ex: Corolla" />
              <Input v-model="g.ano_fabricacao" label="Ano Fab.*" placeholder="Ex: 2022" />
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <Input v-model="g.placa" label="Placa*" />
              <Input v-model="g.chassi" label="Chassi*" />
              <Input v-model="g.valor_avaliacao" label="Valor Avaliação*" mask="real" />
            </div>
          </template>

          <!-- Garantia de Recebível -->
          <template v-else-if="store.ehRecebivel(g)">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="px-1 text-[11px] font-bold uppercase tracking-wider text-text-tertiary">Tipo*</label>
                <select v-model="g.tipo_recebivel" class="w-full bg-surface hover:bg-surface-hover border border-border rounded-xl py-2.5 px-4 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 mt-1 transition-all">
                  <option value="">Selecione...</option>
                  <option v-for="t in store.tiposRecebivel" :key="t" :value="t">{{ t }}</option>
                </select>
              </div>
              <Input v-model="g.numero_identificacao" label="Nº Identificação*" />
              <Input v-model="g.valor_avaliacao" label="Valor*" mask="real" />
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Input v-model="g.sacado" label="Sacado (Devedor)*" />
              <Input v-model="g.data_vencimento_recebivel" label="Data de Vencimento*" type="date" />
            </div>
          </template>

          <!-- Outra Garantia -->
          <template v-else>
            <div>
              <label class="px-1 text-[11px] font-bold uppercase tracking-wider text-text-tertiary">Descrição Detalhada*</label>
              <textarea v-model="g.descricao" rows="2" class="w-full bg-surface hover:bg-surface-hover border border-border rounded-xl py-2.5 px-4 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 mt-1 resize-y transition-all" />
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Input v-model="g.numero_serie" label="Número de série (Opcional)" />
              <Input v-model="g.valor_avaliacao" label="Valor de avaliação*" mask="real" />
            </div>
          </template>
        </div>
      </div>

      <!-- Botão Adicionar -->
      <button type="button" @click="store.adicionarGarantia"
        class="w-full py-4 border-2 border-dashed border-border rounded-2xl text-sm text-text-tertiary font-bold hover:border-primary hover:text-primary hover:bg-primary/5 transition-all flex items-center justify-center gap-2 group"
      >
        <div class="w-6 h-6 rounded-full bg-border group-hover:bg-primary/20 flex items-center justify-center transition-colors">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"/></svg>
        </div>
        Adicionar Outra Garantia
      </button>
    </div>
  </div>
</template>
