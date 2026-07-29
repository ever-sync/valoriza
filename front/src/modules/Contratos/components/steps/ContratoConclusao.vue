<script setup>
/**
 * Etapa 5 — Conclusão do Contrato.
 * Focado na configuração de assinatura eletrônica e declarações finais.
 * O resumo da operação foi removido desta tela pois agora reside na sidebar direita (split-screen).
 */
import { inject } from 'vue'

const store = inject('contratoStore')
</script>

<template>
  <div class="space-y-6 max-w-2xl mx-auto pb-4">
    <div class="text-center space-y-2 mb-8">
      <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center text-primary mx-auto mb-4">
        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
      </div>
      <h2 class="text-xl font-bold text-text-primary">Tudo pronto para finalizar!</h2>
      <p class="text-sm text-text-tertiary">Revise os dados na barra lateral e escolha como o contrato será assinado.</p>
    </div>

    <div class="bg-white border border-border rounded-2xl p-6 shadow-sm">
      <div class="flex items-center gap-2 mb-4">
        <div class="w-6 h-6 rounded-md bg-blue-500/10 flex items-center justify-center text-blue-600">
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
        </div>
        <h3 class="text-sm font-bold text-text-primary uppercase tracking-wider">Assinatura Eletrônica</h3>
      </div>

      <div class="space-y-3">
        <!-- Sem Assinatura -->
        <label 
          class="flex items-start gap-4 p-4 rounded-xl border-2 cursor-pointer transition-all duration-200"
          :class="store.conclusao.value.tipo_assinatura === 'sem_assinatura' ? 'border-primary bg-primary/5' : 'border-border bg-surface hover:border-gray-300'"
        >
          <div class="mt-1 relative flex items-center justify-center w-5 h-5 rounded-full border-2 transition-colors flex-shrink-0" :class="store.conclusao.value.tipo_assinatura === 'sem_assinatura' ? 'border-primary' : 'border-gray-300'">
            <div v-if="store.conclusao.value.tipo_assinatura === 'sem_assinatura'" class="w-2.5 h-2.5 rounded-full bg-primary"></div>
          </div>
          <input type="radio" v-model="store.conclusao.value.tipo_assinatura" value="sem_assinatura" class="hidden" />
          <div>
            <span class="text-sm font-bold text-text-primary block mb-0.5" :class="{ 'text-primary': store.conclusao.value.tipo_assinatura === 'sem_assinatura' }">
              Sem Assinatura Eletrônica integrada
            </span>
            <p class="text-xs text-text-tertiary leading-relaxed">
              Assinatura realizada fisicamente no papel ou por outros meios digitais externos à plataforma ESC. Nenhuma cobrança adicional.
            </p>
          </div>
        </label>

        <!-- Assinatura Avançada -->
        <label 
          class="flex items-start gap-4 p-4 rounded-xl border-2 cursor-pointer transition-all duration-200"
          :class="store.conclusao.value.tipo_assinatura === 'avancada' ? 'border-primary bg-primary/5' : 'border-border bg-surface hover:border-gray-300'"
        >
          <div class="mt-1 relative flex items-center justify-center w-5 h-5 rounded-full border-2 transition-colors flex-shrink-0" :class="store.conclusao.value.tipo_assinatura === 'avancada' ? 'border-primary' : 'border-gray-300'">
            <div v-if="store.conclusao.value.tipo_assinatura === 'avancada'" class="w-2.5 h-2.5 rounded-full bg-primary"></div>
          </div>
          <input type="radio" v-model="store.conclusao.value.tipo_assinatura" value="avancada" class="hidden" />
          <div class="flex-1">
            <span class="text-sm font-bold text-text-primary block mb-0.5" :class="{ 'text-primary': store.conclusao.value.tipo_assinatura === 'avancada' }">
              Assinatura Avançada (Selfie + Documento)
            </span>
            <p class="text-xs text-text-tertiary leading-relaxed mb-2">
              Os signatários receberão um link por SMS/WhatsApp. Serão coletadas a foto do rosto (selfie) e a foto do documento de identidade CNH/RG para validação biométrica.
            </p>
            <div class="inline-flex items-center gap-1.5 px-2 py-1 bg-blue-50 text-blue-700 text-[10px] font-bold uppercase tracking-wider rounded-md">
              <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.069.34-.433.582a2.305 2.305 0 01-.567.267z" /><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.311c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.311c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd" /></svg>
              Custo: 1 crédito por signatário
            </div>
          </div>
        </label>

        <!-- Assinatura Qualificada -->
        <label 
          class="flex items-start gap-4 p-4 rounded-xl border-2 cursor-pointer transition-all duration-200"
          :class="store.conclusao.value.tipo_assinatura === 'qualificada' ? 'border-primary bg-primary/5' : 'border-border bg-surface hover:border-gray-300'"
        >
          <div class="mt-1 relative flex items-center justify-center w-5 h-5 rounded-full border-2 transition-colors flex-shrink-0" :class="store.conclusao.value.tipo_assinatura === 'qualificada' ? 'border-primary' : 'border-gray-300'">
            <div v-if="store.conclusao.value.tipo_assinatura === 'qualificada'" class="w-2.5 h-2.5 rounded-full bg-primary"></div>
          </div>
          <input type="radio" v-model="store.conclusao.value.tipo_assinatura" value="qualificada" class="hidden" />
          <div class="flex-1">
            <span class="text-sm font-bold text-text-primary block mb-0.5" :class="{ 'text-primary': store.conclusao.value.tipo_assinatura === 'qualificada' }">
              Assinatura Qualificada (Certificado e-CPF / e-CNPJ)
            </span>
            <p class="text-xs text-text-tertiary leading-relaxed mb-2">
              Requer uso obrigatório de certificado digital padrão ICP-Brasil (A1 ou A3). Possui a maior validade jurídica existente. Recomendado para operações de alto valor.
            </p>
            <div class="inline-flex items-center gap-1.5 px-2 py-1 bg-blue-50 text-blue-700 text-[10px] font-bold uppercase tracking-wider rounded-md">
              <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.069.34-.433.582a2.305 2.305 0 01-.567.267z" /><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.311c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.311c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd" /></svg>
              Custo: 1 crédito por signatário
            </div>
          </div>
        </label>
      </div>

      <div class="mt-6 border-t border-border/50 pt-5 space-y-4">
        <!-- Enviar CRDC -->
        <label class="flex items-start gap-3 cursor-pointer group bg-surface/50 p-3 rounded-xl hover:bg-surface transition-colors border border-border/50">
          <div class="relative w-11 h-6 rounded-full transition-colors flex-shrink-0 mt-0.5 shadow-inner" :class="store.conclusao.value.enviar_registradora ? 'bg-primary' : 'bg-border'" @click="store.alternarSwitchConclusao('enviar_registradora')">
            <div class="absolute top-1 w-4 h-4 bg-white rounded-full shadow transition-transform" :class="store.conclusao.value.enviar_registradora ? 'translate-x-6' : 'translate-x-1'" />
          </div>
          <div @click="store.alternarSwitchConclusao('enviar_registradora')">
            <span class="text-sm font-bold text-text-primary block">Enviar operação para a Registradora (CRDC)</span>
            <span class="text-[11px] text-text-tertiary leading-tight block mt-0.5">Obrigatório para duplicatas e certos lastros conforme norma do BACEN. A integração fará o registro automático.</span>
          </div>
        </label>

        <!-- Termo Final de Aceite (Obrigatório para iniciar) -->
        <label class="flex items-start gap-3 cursor-pointer group bg-emerald-50/50 border border-emerald-100 p-4 rounded-xl hover:bg-emerald-50 transition-colors mt-2">
          <div 
            class="w-6 h-6 rounded flex items-center justify-center flex-shrink-0 transition-all border-2 mt-0.5 shadow-sm" 
            :class="store.declaracaoAssinada.value ? 'bg-emerald-500 border-emerald-500 text-white' : 'border-emerald-300 bg-white text-transparent group-hover:border-emerald-400'"
            @click="store.alternarDeclaracao"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
          </div>
          <div @click="store.alternarDeclaracao">
            <span class="text-sm font-bold text-emerald-800 block">Confirmação de Liberação do Crédito*</span>
            <span class="text-[11px] text-emerald-600/80 leading-tight block mt-0.5 font-medium">Declaro formalmente que o contrato foi devidamente assinado por todas as partes e o valor líquido foi transferido/pago ao cliente. Autorizo o início das cobranças desta operação.</span>
          </div>
        </label>
      </div>
    </div>

    <!-- Actions Externas (Download PDF) -->
    <div class="flex justify-center mt-6">
      <button type="button" class="flex items-center gap-2 px-6 py-2.5 bg-white border border-border rounded-xl text-xs font-bold text-text-secondary hover:border-primary hover:text-primary transition-all shadow-sm">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Pré-visualizar Minuta do Contrato (PDF)
      </button>
    </div>

    <div class="flex items-start gap-3 p-4 bg-amber-50 border border-amber-200 rounded-2xl text-sm text-amber-800 shadow-sm mt-8">
      <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <div class="space-y-1">
        <p class="font-bold">Atenção: Ação Irreversível</p>
        <p class="text-[11px] font-medium opacity-90 leading-relaxed">
          Ao clicar em <strong>"Iniciar contrato"</strong> (disponível no rodapé após marcar a declaração acima), os boletos serão gerados e os parâmetros financeiros (Taxa, Prazo, IOF, Valor) serão trancados para garantir a integridade da auditoria. 
        </p>
      </div>
    </div>
  </div>
</template>
