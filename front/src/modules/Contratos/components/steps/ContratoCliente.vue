<script setup>
/**
 * Etapa 2 — Seleção de Cliente (PF/PJ).
 * Permite buscar clientes existentes ou cadastrar novos via modal glassmorphic.
 */
import { inject } from 'vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import DynamicSelect from '@/components/ui/DynamicSelect.vue'

const store = inject('contratoStore')
</script>

<template>
  <div class="space-y-6 max-w-2xl mx-auto overflow-visible pb-4">
    <div class="bg-white border border-border rounded-2xl p-6 shadow-sm space-y-6">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-6 h-6 rounded-md bg-primary/10 flex items-center justify-center text-primary">
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
        </div>
        <h3 class="text-sm font-bold text-text-primary uppercase tracking-wider">Identificação do Cliente</h3>
      </div>

      <!-- Segmented Control para Tipo de Cliente -->
      <div class="p-1 bg-surface border border-border rounded-xl flex items-center gap-1 w-full max-w-md mx-auto relative shadow-inner">
        <button 
          type="button"
          class="flex-1 py-2 text-xs font-bold uppercase tracking-wider rounded-lg transition-all z-10"
          :class="store.cliente.value.tipo_cliente === 'pj' ? 'text-primary' : 'text-text-tertiary hover:text-text-secondary'"
          @click="store.cliente.value.tipo_cliente = 'pj'; store.aoAlterarTipoCliente()"
        >
          Pessoa Jurídica
        </button>
        <button 
          type="button"
          class="flex-1 py-2 text-xs font-bold uppercase tracking-wider rounded-lg transition-all z-10"
          :class="store.cliente.value.tipo_cliente === 'pf' ? 'text-primary' : 'text-text-tertiary hover:text-text-secondary'"
          @click="store.cliente.value.tipo_cliente = 'pf'; store.aoAlterarTipoCliente()"
        >
          Pessoa Física
        </button>
        <!-- Background Pill animado -->
        <div 
          class="absolute top-1 bottom-1 w-[calc(50%-4px)] bg-white rounded-lg shadow-sm border border-border/50 transition-all duration-300 ease-[cubic-bezier(0.4,0,0.2,1)]"
          :class="store.cliente.value.tipo_cliente === 'pj' ? 'left-1' : 'left-[calc(50%+2px)]'"
        ></div>
      </div>

      <div class="pt-4 border-t border-border/50">
        <DynamicSelect
          v-model="store.cliente.value.cliente_id"
          :search-service="store.buscarClientes"
          :label="store.cliente.value.tipo_cliente === 'pf' ? 'Buscar Pessoa Física*' : 'Buscar Pessoa Jurídica*'"
          :label-key="store.chaveNomeCliente.value"
          :initial-label="store.clienteLabel.value"
          placeholder="Comece a digitar o nome, CPF ou CNPJ..."
          required
          @select="store.aoSelecionarCliente"
          class="w-full"
        >
          <template #option-detail="{ option }">
            <span class="text-[10px] font-bold text-text-tertiary uppercase bg-surface px-1.5 py-0.5 rounded ml-2">{{ option.cpf || option.cnpj }}</span>
          </template>
        </DynamicSelect>

        <div class="flex items-center justify-center gap-2 mt-4">
          <span class="text-[11px] text-text-tertiary">ou</span>
        </div>

        <button type="button" @click="store.abrirModalCliente" class="mt-4 w-full py-3 border-2 border-dashed border-border rounded-xl text-xs font-bold uppercase tracking-wider text-primary hover:border-primary hover:bg-primary/5 transition-all flex items-center justify-center gap-2 group">
          <div class="w-5 h-5 rounded-full bg-primary/10 flex items-center justify-center group-hover:bg-primary/20 transition-colors">
            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
          </div>
          Cadastrar novo(a) {{ store.cliente.value.tipo_cliente === 'pf' ? 'Pessoa Física' : 'Pessoa Jurídica' }}
        </button>
      </div>
    </div>

    <!-- Modal Cadastro Rápido (Glassmorphism) -->
    <Teleport to="body">
      <transition name="modal-fade">
        <div v-if="store.mostrarModalCliente.value" class="fixed inset-0 z-[999] flex items-center justify-center p-4">
          <div class="absolute inset-0 bg-black/40 backdrop-blur-sm transition-opacity" @click="store.mostrarModalCliente.value = false" />
          
          <div class="relative bg-white/95 backdrop-blur-xl rounded-3xl shadow-2xl border border-white/40 w-full max-w-4xl max-h-[90vh] flex flex-col transform transition-all overflow-hidden ring-1 ring-black/5">
            <div class="flex items-center justify-between px-8 py-5 border-b border-border/50 bg-white/50 flex-shrink-0">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary shadow-inner">
                  <svg v-if="store.tipoPessoaModal.value === 'pf'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                  <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                </div>
                <div>
                  <h3 class="text-lg font-bold text-text-primary leading-tight">Cadastrar {{ store.tipoPessoaModal.value === 'pf' ? 'Pessoa Física' : 'Pessoa Jurídica' }}</h3>
                  <p class="text-[11px] font-medium text-text-tertiary">Preencha os dados básicos para vincular ao contrato.</p>
                </div>
              </div>
              <button type="button" @click="store.mostrarModalCliente.value = false" class="p-2 text-text-tertiary hover:text-text-primary hover:bg-black/5 transition-colors rounded-full">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
            
            <div class="px-8 py-6 overflow-y-auto custom-scrollbar flex-1 space-y-8 bg-surface/30">
              <template v-if="store.tipoPessoaModal.value === 'pf'">
                <section class="bg-white p-5 rounded-2xl border border-border/50 shadow-sm">
                  <h4 class="text-xs font-bold text-primary uppercase tracking-wider mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
                    Dados Pessoais
                  </h4>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Input v-model="store.modalClienteFormulario.value.nome_completo" label="Nome Completo*" class="md:col-span-2" />
                    <Input v-model="store.modalClienteFormulario.value.cpf" label="CPF*" mask="cpf" />
                    <Input v-model="store.modalClienteFormulario.value.rg" label="RG*" />
                    <Input v-model="store.modalClienteFormulario.value.orgao_emissor_rg" label="Órgão Emissor*" />
                    <Input v-model="store.modalClienteFormulario.value.email" label="E-mail*" type="email" />
                    <Input v-model="store.modalClienteFormulario.value.rede_social" label="Rede Social" />
                    <Input v-model="store.modalClienteFormulario.value.telefone" label="Telefone*" mask="celular" />
                    <Input v-model="store.modalClienteFormulario.value.estado_civil" label="Estado Civil*" />
                    <Input v-model="store.modalClienteFormulario.value.regime_partilha" label="Regime de Partilha*" />
                  </div>
                </section>
                
                <section class="bg-white p-5 rounded-2xl border border-border/50 shadow-sm">
                  <h4 class="text-xs font-bold text-primary uppercase tracking-wider mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Endereço
                  </h4>
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Input 
                      v-model="store.modalClienteFormulario.value.cep" 
                      label="CEP*" 
                      mask="cep" 
                      :show-search="true"
                      :loading="store.carregandoBuscaModal.value"
                      @search="store.handleSearchCEPModal"
                    />
                    <Input v-model="store.modalClienteFormulario.value.estado" label="Estado*" />
                    <Input v-model="store.modalClienteFormulario.value.cidade" label="Cidade*" />
                    <Input v-model="store.modalClienteFormulario.value.bairro" label="Bairro*" />
                    <Input v-model="store.modalClienteFormulario.value.endereco" label="Endereço*" class="md:col-span-2" />
                    <Input v-model="store.modalClienteFormulario.value.numero" label="Número*" />
                    <Input v-model="store.modalClienteFormulario.value.complemento" label="Complemento" class="md:col-span-2" />
                  </div>
                </section>
                
                <section class="bg-white p-5 rounded-2xl border border-border/50 shadow-sm">
                  <h4 class="text-xs font-bold text-primary uppercase tracking-wider mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Financeiro & Pix
                  </h4>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Input v-model="store.modalClienteFormulario.value.renda_mensal" label="Renda Mensal" mask="real" />
                    <Input v-model="store.modalClienteFormulario.value.limite_credito" label="Limite de Crédito" mask="real" />
                    <Input v-model="store.modalClienteFormulario.value.chave_pix" label="Chave PIX" class="md:col-span-2" />
                    <Input v-model="store.modalClienteFormulario.value.banco" label="Banco" />
                    <Input v-model="store.modalClienteFormulario.value.agencia" label="Agência" />
                    <Input v-model="store.modalClienteFormulario.value.conta" label="Conta" />
                    <Input v-model="store.modalClienteFormulario.value.observacao" label="Observação" class="md:col-span-2" />
                  </div>
                </section>
              </template>
              
              <template v-else>
                <section class="bg-white p-5 rounded-2xl border border-border/50 shadow-sm">
                  <h4 class="text-xs font-bold text-primary uppercase tracking-wider mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    Dados da Empresa
                  </h4>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Input v-model="store.modalClienteFormulario.value.razao_social" label="Razão Social*" class="md:col-span-2" />
                    <Input v-model="store.modalClienteFormulario.value.nome_fantasia" label="Nome Fantasia*" class="md:col-span-2" />
                    <Input 
                      v-model="store.modalClienteFormulario.value.cnpj" 
                      label="CNPJ*" 
                      mask="cnpj" 
                      :show-search="true"
                      :loading="store.carregandoBuscaModal.value"
                      @search="store.handleSearchCNPJModal"
                    />
                    <Input v-model="store.modalClienteFormulario.value.email" label="E-mail*" type="email" />
                    <Input v-model="store.modalClienteFormulario.value.rede_social" label="Rede Social" />
                    <Input v-model="store.modalClienteFormulario.value.telefone" label="Telefone*" mask="telefone" />
                  </div>
                </section>
                
                <section class="bg-white p-5 rounded-2xl border border-border/50 shadow-sm">
                  <h4 class="text-xs font-bold text-primary uppercase tracking-wider mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Endereço
                  </h4>
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Input 
                      v-model="store.modalClienteFormulario.value.cep" 
                      label="CEP*" 
                      mask="cep" 
                      :show-search="true"
                      :loading="store.carregandoBuscaModal.value"
                      @search="store.handleSearchCEPModal"
                    />
                    <Input v-model="store.modalClienteFormulario.value.estado" label="Estado*" />
                    <Input v-model="store.modalClienteFormulario.value.cidade" label="Cidade*" />
                    <Input v-model="store.modalClienteFormulario.value.bairro" label="Bairro*" />
                    <Input v-model="store.modalClienteFormulario.value.endereco" label="Endereço*" class="md:col-span-2" />
                    <Input v-model="store.modalClienteFormulario.value.numero" label="Número*" />
                    <Input v-model="store.modalClienteFormulario.value.complemento" label="Complemento" class="md:col-span-2" />
                  </div>
                </section>
                
                <section class="bg-white p-5 rounded-2xl border border-border/50 shadow-sm">
                  <h4 class="text-xs font-bold text-primary uppercase tracking-wider mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Financeiro & Pix
                  </h4>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Input v-model="store.modalClienteFormulario.value.limite_credito" label="Limite de Crédito" mask="real" />
                    <Input v-model="store.modalClienteFormulario.value.chave_pix" label="Chave PIX" class="md:col-span-2" />
                    <Input v-model="store.modalClienteFormulario.value.banco" label="Banco" />
                    <Input v-model="store.modalClienteFormulario.value.agencia" label="Agência" />
                    <Input v-model="store.modalClienteFormulario.value.conta" label="Conta" />
                    <Input v-model="store.modalClienteFormulario.value.observacao" label="Observação" class="md:col-span-2" />
                  </div>
                </section>
              </template>
            </div>
            
            <div class="flex justify-end gap-3 px-8 py-5 border-t border-border/50 bg-white/50 flex-shrink-0">
              <Button variant="ghost" type="button" @click="store.mostrarModalCliente.value = false" class="px-6 rounded-xl hover:bg-black/5">Cancelar</Button>
              <Button variant="primary" type="button" :loading="store.modalClienteCarregando.value" @click="store.salvarModalCliente" class="px-8 rounded-xl shadow-md">
                Salvar Cadastro
              </Button>
            </div>
          </div>
        </div>
      </transition>
    </Teleport>
  </div>
</template>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
  transform: scale(0.95) translateY(10px);
}
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
</style>
