<script setup>
/**
 * Modal unificado de Recebimento de Parcela.
 *
 * Combina "Pagar Total" e "Pagamento Parcial (Carência)" em um
 * único modal informativo com layout premium.
 *
 * Comportamento dos toggles:
 * - Nenhum ativo → Pagamento integral (valor = valor devido, read-only)
 * - "Pagar apenas juros" → Auto-preenche com valorMinimoCarencia
 * - "Recebimento parcial" → Campo editável, mínimo = juros
 *
 * @emits salvo   Após pagamento bem-sucedido
 * @emits fechar  Ao fechar o modal
 */
import { ref, computed, watch } from 'vue'
import Input from '@/components/ui/Input.vue'
import Button from '@/components/ui/Button.vue'
import DynamicSelect from '@/components/ui/DynamicSelect.vue'
import { getBancos } from '@/modules/Bancos/services/bancos.service'
import { calcularEncargosReceita } from '@/modules/Receitas/services/receitas.service.js'
import { parsearValor, formatarDecimal } from '@/helpers/formatacao/valores.js'

const props = defineProps({
  /** Controle de visibilidade do modal */
  visivel: { type: Boolean, default: false },

  /** Dados da receita selecionada */
  receita: { type: Object, default: () => ({}) },

  /** Contrato vinculado (pode ser null para receitas avulsas) */
  contrato: { type: Object, default: null },

  /** Valor mínimo de carência (juros da parcela) */
  valorMinimoCarencia: { type: Number, default: 0 },

  /** Usos de carência restantes */
  usosCarenciaRestantes: { type: [Number, String], default: 0 },

  /** Estado de carregamento */
  carregando: { type: Boolean, default: false },

  /** Dados de encargos calculados pelo backend */
  encargos: {
    type: Object,
    default: () => ({ juros_atualizacao: 0, juros_mora: 0, multa: 0, valor_devido: 0 }),
  },
})

const emit = defineEmits(['fechar', 'salvo'])

// ========================================
// ESTADO LOCAL
// ========================================

/** Modo de pagamento: 'integral', 'carencia' ou 'parcial' */
const modoPagamento = ref('integral')

/** Formulário de recebimento */
const dataRecebimento = ref(new Date().toISOString().split('T')[0])
const valorRecebido = ref('')
const contaBancariaId = ref('')
const bancoLabel = ref('')

/** Estado de processamento */
const processando = ref(false)

/** Encargos atuais calculados no backend */
const encargosAtuais = ref({ ...props.encargos })
const recarregandoEncargos = ref(false)

// ========================================
// COMPUTED
// ========================================

/** Valor original da receita */
const valorOriginal = computed(() => parseFloat(props.receita?.valor_recebido || 0))

/** Valor devido (com encargos) */
const valorDevido = computed(() => {
  const enc = encargosAtuais.value
  if (enc?.valor_devido && enc.valor_devido > 0) return enc.valor_devido
  return valorOriginal.value
})

/** Juros de atualização */
const jurosAtualizacao = computed(() => parseFloat(encargosAtuais.value?.juros_atualizacao || 0))

/** Juros de mora */
const jurosMora = computed(() => parseFloat(encargosAtuais.value?.juros_mora || 0))

/** Multa por atraso */
const multaAtraso = computed(() => parseFloat(encargosAtuais.value?.multa || 0))

/** IOF (se houver) */
const iof = computed(() => parseFloat(encargosAtuais.value?.iof || 0))

/** Valor a receber (valor original da parcela — sem encargos) */
const valorAReceber = computed(() => valorOriginal.value)

/** Dias de atraso (calculado pelo backend) */
const diasAtraso = computed(() => parseInt(encargosAtuais.value?.dias_atraso || 0))

/** É pagamento integral */
const ehIntegral = computed(() => modoPagamento.value === 'integral')

/** É pagamento via carência */
const ehCarencia = computed(() => modoPagamento.value === 'carencia')

/** É pagamento parcial livre */
const ehParcial = computed(() => modoPagamento.value === 'parcial')

/** Valor efetivamente sendo pago (parseado) */
const valorPagoParsed = computed(() => parsearValor(valorRecebido.value))

/** Saldo residual (apenas para parcial/carência) */
const saldoResidual = computed(() => {
  if (ehIntegral.value) return 0
  return Math.max(0, valorOriginal.value - valorPagoParsed.value)
})

/** Campo de valor editável */
const valorEditavel = computed(() => !ehIntegral.value)

/** Pode usar carência */
const podeUsarCarencia = computed(() => {
  if (!props.contrato) return false
  const permitido = parseInt(props.contrato.permitir_carencia) === 1
  if (!permitido) return false
  const limite = parseInt(props.contrato.limite_carencia) || 0
  const usos = parseInt(props.contrato.usos_carencia) || 0
  return limite === 0 || usos < limite
})

/** Texto de usos de carência */
const textoCarencia = computed(() => {
  if (!props.contrato) return ''
  const limite = parseInt(props.contrato.limite_carencia) || 0
  const usos = parseInt(props.contrato.usos_carencia) || 0
  if (limite === 0) return 'Usos ilimitados disponíveis'
  return `${Math.max(0, limite - usos)} de ${limite} usos disponíveis`
})

/** Dados da receita formatados */
const dadosReceita = computed(() => {
  const r = props.receita || {}
  const totalParcelas = r.total_parcelas || encargosAtuais.value?.total_parcelas || '?'
  return {
    contrato: r.numero_contrato || '-',
    parcela: r.parcela_numero
      ? `${String(r.parcela_numero).padStart(2, '0')} de ${totalParcelas}`
      : '-',
    status: r.status || 'Pendente',
    vencimento: r.data_vencimento
      ? new Date(r.data_vencimento + 'T00:00:00').toLocaleDateString('pt-BR')
      : '-',
    pagamento: r.data_recebimento
      ? new Date(r.data_recebimento + 'T00:00:00').toLocaleDateString('pt-BR')
      : 'Pendente',
    cliente: [r.pagador_documento, r.pagador_nome].filter(Boolean).join(' ') || '-',
  }
})

/** Validação do formulário */
const formularioValido = computed(() => {
  if (!contaBancariaId.value) return false
  if (!dataRecebimento.value) return false
  if (ehParcial.value || ehCarencia.value) {
    if (valorPagoParsed.value <= 0) return false
    if (valorPagoParsed.value < props.valorMinimoCarencia) return false
    if (valorPagoParsed.value >= valorOriginal.value) return false
  }
  return true
})

// ========================================
// WATCHERS
// ========================================

/** Recalcular encargos chamando o backend */
async function recalcularEncargos() {
  if (!dataRecebimento.value || !props.visivel || !props.receita?.id) return

  recarregandoEncargos.value = true
  try {
    const resp = await calcularEncargosReceita(
      props.receita.id,
      {
        data_recebimento: dataRecebimento.value,
        valor_original: valorOriginal.value,
      },
      { showLoading: false, showToast: false },
    )
    if (resp?.success) {
      const enc = Array.isArray(resp.data) ? resp.data[0] : resp.data
      encargosAtuais.value = {
        juros_atualizacao: enc.juros_atualizacao || 0,
        juros_mora: enc.juros_mora || 0,
        multa: enc.multa || 0,
        iof: enc.iof || 0,
        valor_devido: enc.valor_devido || valorOriginal.value,
        dias_atraso: enc.dias_atraso || 0,
        total_encargos: enc.total_encargos || 0,
        taxas: enc.taxas || {},
        total_parcelas: enc.total_parcelas || null,
      }

      // Atualizar o input de valor se estiver no modo integral
      if (modoPagamento.value === 'integral') {
        valorRecebido.value = formatarDecimal(encargosAtuais.value.valor_devido)
      }
    }
  } catch (e) {
    console.warn('Falha ao recalcular encargos:', e)
  } finally {
    recarregandoEncargos.value = false
  }
}

/** Ao abrir modal, resetar estado e calcular encargos iniciais */
watch(
  () => props.visivel,
  async (v) => {
    if (v) {
      modoPagamento.value = 'integral'
      dataRecebimento.value = new Date().toISOString().split('T')[0]
      encargosAtuais.value = { ...props.encargos }
      valorRecebido.value = formatarDecimal(valorDevido.value)
      contaBancariaId.value = props.receita?.conta_bancaria_destino_id || ''
      bancoLabel.value = props.receita?.conta_nome || ''
      processando.value = false

      // Disparar cálculo imediato na abertura
      await recalcularEncargos()
    }
  },
)

/** Ao alterar a data de recebimento, recalcular encargos */
watch(dataRecebimento, () => {
  recalcularEncargos()
})

/** Ao trocar modo de pagamento, atualizar valor */
watch(modoPagamento, (modo) => {
  if (modo === 'integral') {
    valorRecebido.value = formatarDecimal(valorDevido.value)
  } else if (modo === 'carencia' || modo === 'parcial') {
    // Auto-preenche com o valor mínimo (juros da parcela) conforme solicitado
    valorRecebido.value = formatarDecimal(props.valorMinimoCarencia)
  }
})

// ========================================
// MÉTODOS
// ========================================

function formatarMoedaLocal(valor) {
  return Number(valor || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function fechar() {
  emit('fechar')
}

function confirmar() {
  emit('salvo', {
    modo: modoPagamento.value,
    data_recebimento: dataRecebimento.value,
    conta_bancaria_destino_id: contaBancariaId.value,
    valor_pago: ehIntegral.value ? null : parsearValor(valorRecebido.value),
  })
}
</script>

<template>
  <Teleport to="body">
    <div v-if="visivel" class="fixed inset-0 z-[999] flex items-center justify-center">
      <!-- Overlay -->
      <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="fechar" />

      <!-- Modal -->
      <div
        class="relative bg-surface rounded-2xl shadow-2xl border border-border w-full max-w-2xl mx-4 max-h-[90vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200"
      >
        <!-- Header -->
        <div
          class="px-6 py-4 border-b border-border flex items-center justify-between bg-gradient-to-r from-primary/5 to-transparent"
        >
          <div class="flex items-center gap-3">
            <div class="p-2 bg-primary/10 rounded-xl">
              <svg
                class="w-5 h-5 text-primary"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"
                />
              </svg>
            </div>
            <h3 class="text-lg font-bold text-text-primary">Receber Parcela</h3>
          </div>
          <button @click="fechar" class="p-2 hover:bg-background rounded-lg transition-colors">
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

        <!-- Body -->
        <div class="p-6 overflow-y-auto space-y-5">
          <!-- ═══ Informações da Parcela ═══ -->
          <div class="grid grid-cols-2 gap-4">
            <!-- Lado Esquerdo: Dados da parcela -->
            <div class="space-y-2 text-sm">
              <p>
                <span class="font-bold text-primary">Contrato:</span> {{ dadosReceita.contrato }}
              </p>
              <p>
                <span class="font-bold text-text-primary">Parcela / Título:</span>
                {{ dadosReceita.parcela }}
              </p>
              <p>
                <span class="font-bold text-text-primary">Valor da Parcela:</span>
                {{ formatarMoedaLocal(valorOriginal) }}
              </p>
              <p>
                <span class="font-bold text-text-primary">Status:</span>
                <span :class="diasAtraso > 0 ? 'text-red-500 font-semibold' : ''">{{
                  dadosReceita.status
                }}</span>
              </p>
              <p>
                <span class="font-bold text-text-primary">Data de Vencimento:</span>
                {{ dadosReceita.vencimento }}
              </p>
              <p v-if="diasAtraso > 0">
                <span class="font-bold text-red-500">Dias em Atraso:</span>
                <span class="text-red-600 font-bold">{{ diasAtraso }} dias</span>
              </p>
            </div>

            <!-- Lado Direito: Encargos -->
            <div class="space-y-2 text-sm">
              <p>
                <span class="font-bold text-primary">Cliente / Sacado:</span>
                {{ dadosReceita.cliente }}
              </p>
              <p>
                <span class="font-bold text-text-primary">Juros de Atualização:</span>
                <span v-if="recarregandoEncargos" class="text-text-tertiary text-xs italic ml-2"
                  >calculando...</span
                >
                <template v-else>
                  <span class="text-amber-600">{{ formatarMoedaLocal(jurosAtualizacao) }}</span>
                  <span
                    v-if="encargosAtuais.taxas?.juros_contrato"
                    class="text-[10px] text-text-tertiary ml-1"
                    >({{ encargosAtuais.taxas.juros_contrato }}% a.m.)</span
                  >
                </template>
              </p>
              <p>
                <span class="font-bold text-text-primary">Juros de Mora:</span>
                <span v-if="recarregandoEncargos" class="text-text-tertiary text-xs italic ml-2"
                  >calculando...</span
                >
                <template v-else>
                  <span class="text-amber-600">{{ formatarMoedaLocal(jurosMora) }}</span>
                  <span
                    v-if="encargosAtuais.taxas?.juros_mora"
                    class="text-[10px] text-text-tertiary ml-1"
                    >({{ encargosAtuais.taxas.juros_mora }}% a.m.)</span
                  >
                </template>
              </p>
              <p>
                <span class="font-bold text-text-primary">Multa Moratória:</span>
                <span v-if="recarregandoEncargos" class="text-text-tertiary text-xs italic ml-2"
                  >calculando...</span
                >
                <template v-else>
                  <span class="text-red-600">{{ formatarMoedaLocal(multaAtraso) }}</span>
                  <span
                    v-if="encargosAtuais.taxas?.multa_moratoria"
                    class="text-[10px] text-text-tertiary ml-1"
                    >({{ encargosAtuais.taxas.multa_moratoria }}%)</span
                  >
                </template>
              </p>
              <p v-if="iof > 0">
                <span class="font-bold text-text-primary">IOF:</span>
                <span v-if="recarregandoEncargos" class="text-text-tertiary text-xs italic ml-2"
                  >calculando...</span
                >
                <template v-else>
                  <span class="text-text-primary">{{ formatarMoedaLocal(iof) }}</span>
                </template>
              </p>
              <p class="pt-1 border-t border-border">
                <span class="font-bold text-text-primary">Valor Devido:</span>
                <span v-if="recarregandoEncargos" class="ml-2 inline-flex items-center">
                  <span class="w-3 h-3 border-2 border-primary/30 border-t-primary rounded-full animate-spin mr-2"></span>
                  <span class="text-xs text-primary animate-pulse font-medium">atualizando...</span>
                </span>
                <span v-else class="text-lg font-black text-primary animate-in fade-in duration-300">{{
                  formatarMoedaLocal(valorDevido)
                }}</span>
              </p>
            </div>
          </div>

          <hr class="border-border" />

          <!-- ═══ Data do Recebimento ═══ -->
          <Input v-model="dataRecebimento" label="Data do Recebimento" type="date" required />

          <!-- ═══ Toggles de Modo ═══ -->
          <div class="space-y-3" v-if="contrato">
            <!-- Toggle: Carência -->
            <div
              class="flex items-center justify-between p-3 rounded-xl border transition-all cursor-pointer"
              :class="
                ehCarencia
                  ? 'bg-sky-50 border-sky-200'
                  : 'bg-background border-border hover:border-primary/30'
              "
              @click="modoPagamento = ehCarencia ? 'integral' : 'carencia'"
            >
              <div class="flex items-center gap-3">
                <div
                  class="relative w-11 h-6 rounded-full transition-colors"
                  :class="ehCarencia ? 'bg-primary' : 'bg-border'"
                >
                  <div
                    class="absolute top-1 w-4 h-4 bg-white rounded-full shadow transition-transform"
                    :class="ehCarencia ? 'translate-x-6' : 'translate-x-1'"
                  />
                </div>
                <span class="text-sm text-text-secondary"
                  >Pagar apenas juros (carência de principal)</span
                >
              </div>
              <span
                v-if="podeUsarCarencia"
                class="text-[10px] text-sky-600 font-semibold uppercase tracking-wide"
              >
                {{ textoCarencia }}
              </span>
              <span v-else class="text-[10px] text-red-500 font-semibold uppercase">
                Carência indisponível
              </span>
            </div>

            <!-- Toggle: Parcial -->
            <div
              class="flex items-center gap-3 p-3 rounded-xl border transition-all cursor-pointer"
              :class="
                ehParcial
                  ? 'bg-amber-50 border-amber-200'
                  : 'bg-background border-border hover:border-primary/30'
              "
              @click="modoPagamento = ehParcial ? 'integral' : 'parcial'"
            >
              <div
                class="relative w-11 h-6 rounded-full transition-colors"
                :class="ehParcial ? 'bg-primary' : 'bg-border'"
              >
                <div
                  class="absolute top-1 w-4 h-4 bg-white rounded-full shadow transition-transform"
                  :class="ehParcial ? 'translate-x-6' : 'translate-x-1'"
                />
              </div>
              <span class="text-sm text-text-secondary">Recebimento parcial</span>
            </div>
          </div>

          <!-- ═══ Valor a Receber ═══ -->
          <Input
            v-model="valorRecebido"
            label="Valor a ser Recebido"
            mask="real"
            :disabled="!valorEditavel"
            required
          />

          <!-- ═══ Conta Corrente ═══ -->
          <DynamicSelect
            v-model="contaBancariaId"
            :search-service="
              (p) => getBancos({ ...p, banco_like: p.search }, { showLoading: false })
            "
            label="Conta Corrente"
            label-key="banco"
            :initial-label="bancoLabel"
            placeholder="Pesquisar banco..."
            required
          >
            <template #option-detail="{ option }">
              <span class="text-[10px] text-text-tertiary uppercase tracking-tighter">
                {{ option.agencia }} / {{ option.conta }}
              </span>
            </template>
          </DynamicSelect>

          <!-- ═══ Saldo Residual (aparece apenas no parcial/carência) ═══ -->
          <div
            v-if="!ehIntegral && saldoResidual > 0"
            class="p-4 bg-amber-50 border border-amber-100 rounded-xl flex items-center justify-between"
          >
            <div class="flex flex-col">
              <span class="text-[10px] font-bold text-amber-700 uppercase">Saldo Residual</span>
              <span class="text-xs text-amber-600 italic">Será lançado como nova parcela</span>
            </div>
            <span class="text-xl font-bold text-amber-800">{{
              formatarMoedaLocal(saldoResidual)
            }}</span>
          </div>

          <!-- ═══ Banner de Quitação (integral) ═══ -->
          <div
            v-if="ehIntegral"
            class="p-4 bg-emerald-50 border border-emerald-100 rounded-xl flex items-center gap-3"
          >
            <div class="p-2 bg-emerald-100 text-emerald-600 rounded-lg">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M5 13l4 4L19 7"
                />
              </svg>
            </div>
            <div>
              <p class="text-sm font-bold text-emerald-800">Pronto para quitar</p>
              <p class="text-xs text-emerald-600">
                A receita será marcada como 'Recebido' e o saldo será atualizado no banco escolhido.
              </p>
            </div>
          </div>

          <!-- ═══ Banner de Carência ═══ -->
          <div
            v-if="ehCarencia"
            class="p-4 bg-sky-50 border border-sky-100 rounded-xl text-xs text-sky-700 flex gap-3"
          >
            <svg
              class="w-5 h-5 flex-shrink-0"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
            <p>
              Ao utilizar a carência, o cliente paga apenas os juros da parcela. O saldo principal
              será repactuado para o próximo vencimento.
            </p>
          </div>
        </div>

        <!-- Footer -->
        <div class="p-4 border-t border-border bg-surface flex justify-end gap-3">
          <Button variant="ghost" @click="fechar">Cancelar</Button>
          <Button
            variant="primary"
            :loading="carregando"
            :disabled="!formularioValido"
            @click="confirmar"
          >
            Receber
          </Button>
        </div>
      </div>
    </div>
  </Teleport>

</template>
