<?php

namespace App\Services\ConfiguracoesContratos;

use App\Models\ConfiguracoesContratos\ConfiguracoesContratosModel;
use App\Services\BaseService;
use stdClass;

class ConfiguracoesContratosService extends BaseService
{
    protected string $model = ConfiguracoesContratosModel::class;

    protected array $camposGlobais = [
        'taxa_juros_minima_sem_garantia:nullable,string',
        'taxa_juros_avalista:nullable,string',
        'taxa_juros_imovel:nullable,string',
        'taxa_juros_veiculo:nullable,string',
        'taxa_juros_outras_garantias:nullable,string',
        'taxa_iof_diario:nullable,string',
        'taxa_iof_adicional:nullable,string',

        'qtd_minima_parcelas:nullable,int',
        'qtd_maxima_parcelas:nullable,int',

        'tipo_registro_crdc:nullable,string',
        'crdc_usuario:nullable,string',
        'crdc_senha:nullable,string',
        'crdc_cnpj:nullable,string',

        'dias_notificacao_vencimento:nullable,string',
        'notificar_vencimento_email:nullable,int',
        'notificar_vencimento_whatsapp:nullable,int',
        'exibir_notificacoes_vencimento:nullable,int',

        'frequencia_notificacao_atraso:nullable,string',
        'notificar_atraso_email:nullable,int',
        'notificar_atraso_whatsapp:nullable,int',
        'exibir_notificacoes_atraso:nullable,int',
        'copiar_avalistas_atraso:nullable,string',
    ];

    private function realizar(array $dados, $contexto = 'inserir'): array
    {
        // Remove campos do sistema que o frontend pode enviar de volta (empresa_id, data_cadastro, etc.)
        $this->removerCamposNaoPermitidos($this->camposGlobais, $dados);
        $this->validarCampos($this->camposGlobais, $dados);
        return $this->normalizarCampos($dados);
    }

    protected function prepareWrite(array $dados, $contexto = 'inserir'): stdClass
    {
        $dadosTratados = $this->realizar($dados, $contexto);

        $parseDecimal = fn($valor): ?float => isset($valor) && $valor !== ''
            ? (float) str_replace(',', '.', preg_replace('/[^0-9,.]/', '', (string) $valor))
            : null;

        $map = fn($v) => [
            'taxa_juros_minima_sem_garantia' => $parseDecimal($v['taxa_juros_minima_sem_garantia'] ?? null),
            'taxa_juros_avalista'            => $parseDecimal($v['taxa_juros_avalista'] ?? null),
            'taxa_juros_imovel'              => $parseDecimal($v['taxa_juros_imovel'] ?? null),
            'taxa_juros_veiculo'             => $parseDecimal($v['taxa_juros_veiculo'] ?? null),
            'taxa_juros_outras_garantias'    => $parseDecimal($v['taxa_juros_outras_garantias'] ?? null),
            'taxa_iof_diario'                => $parseDecimal($v['taxa_iof_diario'] ?? null),
            'taxa_iof_adicional'             => $parseDecimal($v['taxa_iof_adicional'] ?? null),

            'qtd_minima_parcelas'            => $this->sanitizer()->int($v['qtd_minima_parcelas'] ?? 1),
            'qtd_maxima_parcelas'            => $this->sanitizer()->int($v['qtd_maxima_parcelas'] ?? 120),

            'tipo_registro_crdc'             => $this->sanitizer()->string($v['tipo_registro_crdc'] ?? 'integracao_esc'),
            'crdc_usuario'                   => $this->sanitizer()->string($v['crdc_usuario'] ?? ''),
            'crdc_senha'                     => $this->sanitizer()->string($v['crdc_senha'] ?? ''),
            'crdc_cnpj'                      => $this->sanitizer()->string($v['crdc_cnpj'] ?? ''),

            'dias_notificacao_vencimento'    => $this->sanitizer()->string($v['dias_notificacao_vencimento'] ?? ''),
            'notificar_vencimento_email'     => $this->sanitizer()->int($v['notificar_vencimento_email'] ?? 1),
            'notificar_vencimento_whatsapp'  => $this->sanitizer()->int($v['notificar_vencimento_whatsapp'] ?? 1),
            'exibir_notificacoes_vencimento' => $this->sanitizer()->int($v['exibir_notificacoes_vencimento'] ?? 1),

            'frequencia_notificacao_atraso'  => $this->sanitizer()->string($v['frequencia_notificacao_atraso'] ?? ''),
            'notificar_atraso_email'         => $this->sanitizer()->int($v['notificar_atraso_email'] ?? 1),
            'notificar_atraso_whatsapp'      => $this->sanitizer()->int($v['notificar_atraso_whatsapp'] ?? 1),
            'exibir_notificacoes_atraso'     => $this->sanitizer()->int($v['exibir_notificacoes_atraso'] ?? 1),
            'copiar_avalistas_atraso'        => $this->sanitizer()->string($v['copiar_avalistas_atraso'] ?? '0'),
        ];

        $dto = new stdClass();
        $dto->inserir   = array_map(fn($v) => ['cadastrado_por' => $this->getUsuarioId(), ...$map($v)], $dadosTratados);
        $dto->atualizar = array_map(fn($v) => ['atualizado_por' => $this->getUsuarioId(), ...$map($v)], $dadosTratados);

        return $dto;
    }
}
