<?php

namespace App\Models\Relatorio;

use App\Models\BaseModel;

class RelatorioModel extends BaseModel
{
    protected string $table = 'tbl_contratos';

    public function getSumarioContratos(int $empresaId): array
    {
        $hoje = date('Y-m-d');
        $primeiroDiaMes = date('Y-m-01');
        $ultimoDiaMes = date('Y-m-t');

        // 1. Clientes, Prazo Médio, Valor Total e Médio
        $sqlContratos = "
            SELECT 
                COUNT(DISTINCT cliente_id) as num_clientes,
                AVG(quantidade_parcelas) as prazo_medio,
                SUM(valor_solicitado) as total_valor,
                AVG(valor_solicitado) as valor_medio
            FROM tbl_contratos
            WHERE empresa_id = ? AND status_sistema = 'incluido' AND status NOT IN ('Arquivado', 'Quitado', 'Perda')
        ";
        $resContratos = $this->query($sqlContratos, [$empresaId])[0];

        // 2. Financeiro (Receitas Pendentes)
        $sqlFinanceiro = "
            SELECT 
                SUM(rec.valor_recebido) as total_a_receber,
                SUM(ctp.valor_amortizacao) as restante_amortizar,
                SUM(ctp.valor_juros) as restante_juros
            FROM tbl_receitas rec
            LEFT JOIN tbl_contratos_parcelas ctp ON ctp.contrato_id = rec.contrato_id AND ctp.numero_parcela = rec.parcela_numero AND ctp.status_sistema = 'incluido'
            WHERE rec.empresa_id = ? AND rec.status_sistema = 'incluido' AND rec.status != 'Recebido'
        ";
        $resFinanceiro = $this->query($sqlFinanceiro, [$empresaId])[0];

        // 3. Atrasados no Mês Atual
        $sqlAtrasadosMes = "
            SELECT 
                COUNT(*) as qtd,
                SUM(valor_recebido) as valor
            FROM tbl_receitas
            WHERE empresa_id = ? AND status_sistema = 'incluido' AND status != 'Recebido'
            AND data_vencimento BETWEEN ? AND ? AND data_vencimento < ?
        ";
        $resAtrasadosMes = $this->query($sqlAtrasadosMes, [$empresaId, $primeiroDiaMes, $ultimoDiaMes, $hoje])[0];

        // 4. Atrasados Acumulados
        $sqlAtrasadosTotal = "
            SELECT 
                COUNT(*) as qtd,
                SUM(valor_recebido) as valor
            FROM tbl_receitas
            WHERE empresa_id = ? AND status_sistema = 'incluido' AND status != 'Recebido'
            AND data_vencimento < ?
        ";
        $resAtrasadosTotal = $this->query($sqlAtrasadosTotal, [$empresaId, $hoje])[0];

        return [
            'num_clientes' => (int)$resContratos['num_clientes'],
            'prazo_medio' => round((float)$resContratos['prazo_medio'], 1),
            'total_valor' => (float)$resContratos['total_valor'],
            'valor_medio' => (float)$resContratos['valor_medio'],
            'total_a_receber' => (float)$resFinanceiro['total_a_receber'],
            'restante_amortizar' => (float)$resFinanceiro['restante_amortizar'],
            'restante_juros' => (float)$resFinanceiro['restante_juros'],
            'atrasados_mes_qtd' => (int)$resAtrasadosMes['qtd'],
            'atrasos_mes_valor' => (float)$resAtrasadosMes['valor'],
            'atrasados_total_qtd' => (int)$resAtrasadosTotal['qtd'],
            'atrasos_total_valor' => (float)$resAtrasadosTotal['valor'],
        ];
    }

    public function getSumarioClientes(int $empresaId): array
    {
        $sql = "
            SELECT 
                COALESCE(pf.nome_completo, pj.razao_social, rec.nome_pagador_manual) as cliente,
                COUNT(DISTINCT rec.contrato_id) as qtd_contratos,
                SUM(rec.valor_recebido) as total_valor,
                SUM(CASE WHEN rec.status = 'Recebido' THEN rec.valor_recebido ELSE 0 END) as valor_pago,
                SUM(CASE WHEN rec.status != 'Recebido' THEN rec.valor_recebido ELSE 0 END) as valor_pendente
            FROM tbl_receitas rec
            LEFT JOIN tbl_pessoas_fisicas pf ON pf.id = rec.pagador_id AND rec.tipo_pessoa = 'pf'
            LEFT JOIN tbl_pessoas_juridicas pj ON pj.id = rec.pagador_id AND rec.tipo_pessoa = 'pj'
            WHERE rec.empresa_id = ? AND rec.status_sistema = 'incluido'
            GROUP BY cliente
            ORDER BY total_valor DESC
        ";
        return $this->query($sql, [$empresaId]);
    }

    public function getContabilRecebimentos(int $empresaId, string $inicio, string $fim, string $status, string $tipoData, ?int $clienteId = null): array
    {
        $colunaData = ($tipoData === 'pagamento') ? 'data_recebimento' : 'data_vencimento';
        $params = [$inicio, $fim, $empresaId];
        $whereCliente = "";

        if ($clienteId) {
            $whereCliente = " AND rec.pagador_id = ? ";
            $params[] = $clienteId;
        }

        $whereStatus = "";
        if ($status !== 'Todos') {
            $whereStatus = " AND rec.status = ? ";
            $params[] = $status;
        }

        $sql = "
            SELECT 
                rec.id,
                rec.data_vencimento,
                rec.data_recebimento,
                rec.valor_recebido as valor,
                rec.status,
                rec.descricao,
                (CASE 
                    WHEN rec.tipo_pagador = 'manual' THEN rec.nome_pagador_manual 
                    WHEN rec.tipo_pessoa = 'pf' THEN pf.nome_completo 
                    WHEN rec.tipo_pessoa = 'pj' THEN COALESCE(pj.nome_fantasia, pj.razao_social)
                    ELSE 'Desconhecido' 
                END) as cliente
            FROM tbl_receitas rec
            LEFT JOIN tbl_pessoas_fisicas pf ON pf.id = rec.pagador_id AND rec.tipo_pessoa = 'pf'
            LEFT JOIN tbl_pessoas_juridicas pj ON pj.id = rec.pagador_id AND rec.tipo_pessoa = 'pj'
            WHERE rec.$colunaData BETWEEN ? AND ? 
              AND rec.empresa_id = ? 
              AND rec.status_sistema = 'incluido'
              $whereCliente
              $whereStatus
            ORDER BY rec.$colunaData ASC
        ";

        return $this->query($sql, $params);
    }

    public function getContabilPagamentos(int $empresaId, string $inicio, string $fim, string $status, string $tipoData, ?int $favorecidoId = null): array
    {
        $colunaData = ($tipoData === 'pagamento') ? 'data_pagamento' : 'data_vencimento';
        $params = [$inicio, $fim, $empresaId];
        $whereFavorecido = "";

        if ($favorecidoId) {
            $whereFavorecido = " AND des.favorecido_id = ? ";
            $params[] = $favorecidoId;
        }

        $whereStatus = "";
        if ($status !== 'Todos') {
            $whereStatus = " AND des.status = ? ";
            $params[] = $status;
        }

        $sql = "
            SELECT 
                des.id,
                des.data_vencimento,
                des.data_pagamento,
                des.valor_pago as valor,
                des.status,
                des.descricao,
                (CASE 
                    WHEN des.tipo_favorecido = 'manual' THEN des.nome_favorecido_manual 
                    WHEN des.tipo_pessoa = 'pf' THEN pf.nome_completo 
                    WHEN des.tipo_pessoa = 'pj' THEN COALESCE(pj.nome_fantasia, pj.razao_social)
                    ELSE 'Desconhecido' 
                END) as favorecido
            FROM tbl_despesas des
            LEFT JOIN tbl_pessoas_fisicas pf ON pf.id = des.favorecido_id AND des.tipo_pessoa = 'pf'
            LEFT JOIN tbl_pessoas_juridicas pj ON pj.id = des.favorecido_id AND des.tipo_pessoa = 'pj'
            WHERE des.$colunaData BETWEEN ? AND ? 
              AND des.empresa_id = ? 
              AND des.status_sistema = 'incluido'
              $whereFavorecido
              $whereStatus
            ORDER BY des.$colunaData ASC
        ";

        return $this->query($sql, $params);
    }
}
