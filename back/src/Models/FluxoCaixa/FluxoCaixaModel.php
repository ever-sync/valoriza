<?php

namespace App\Models\FluxoCaixa;

use App\Models\BaseModel;

class FluxoCaixaModel extends BaseModel
{
    protected string $table = 'tbl_receitas';

    public function getFluxoProjetado(int $empresaId, int $dias = 30): array
    {
        $fluxo = [];
        for ($i = 0; $i < $dias; $i++) {
            $data = date('Y-m-d', strtotime("+$i days"));
            
            // Receitas para o dia
            $sqlRec = "SELECT SUM(valor_recebido) as total FROM tbl_receitas 
                       WHERE data_vencimento = ? AND empresa_id = ? AND status_sistema = 'incluido'";
            $resRec = $this->query($sqlRec, [$data, $empresaId]);
            $receita = (float)($resRec[0]['total'] ?? 0);

            // Despesas para o dia
            $sqlDesp = "SELECT SUM(valor_pago) as total FROM tbl_despesas 
                        WHERE data_vencimento = ? AND empresa_id = ? AND status_sistema = 'incluido'";
            $resDesp = $this->query($sqlDesp, [$data, $empresaId]);
            $despesa = (float)($resDesp[0]['total'] ?? 0);

            $fluxo[] = [
                'data' => $data,
                'receita' => $receita,
                'despesa' => $despesa,
                'saldo' => $receita - $despesa
            ];
        }

        return $fluxo;
    }

    public function getFluxoPeriodo(int $empresaId, string $inicio, string $fim): array
    {
        // Totais de Receitas (Somente Recebidas ou Parciais)
        $sqlRec = "SELECT SUM(valor_recebido) as total FROM tbl_receitas 
                   WHERE data_vencimento BETWEEN ? AND ? AND empresa_id = ? 
                   AND status_sistema = 'incluido' 
                   AND status IN ('Recebido', 'Parcial')";
        $resRec = $this->query($sqlRec, [$inicio, $fim, $empresaId]);
        $totalEntradas = (float)($resRec[0]['total'] ?? 0);

        // Totais de Despesas (Somente Pagas ou Parciais)
        $sqlDesp = "SELECT SUM(valor_pago) as total FROM tbl_despesas 
                    WHERE data_vencimento BETWEEN ? AND ? AND empresa_id = ? 
                    AND status_sistema = 'incluido'
                    AND status IN ('Pago', 'Parcial')";
        $resDesp = $this->query($sqlDesp, [$inicio, $fim, $empresaId]);
        $totalSaidas = (float)($resDesp[0]['total'] ?? 0);

        // Lista de Registros Combinados (Filtrados por status de liquidação)
        $sqlList = "
            (SELECT 
                'receita' as tipo, 
                rec.valor_recebido as valor, 
                rec.data_vencimento as data, 
                rec.status, 
                rec.descricao,
                (CASE 
                    WHEN rec.tipo_pagador = 'manual' THEN rec.nome_pagador_manual 
                    WHEN rec.tipo_pessoa = 'pf' THEN pf.nome_completo 
                    WHEN rec.tipo_pessoa = 'pj' THEN COALESCE(pj.nome_fantasia, pj.razao_social)
                    ELSE 'Desconhecido' 
                END) as pessoa
             FROM tbl_receitas rec
             LEFT JOIN tbl_pessoas_fisicas pf ON pf.id = rec.pagador_id AND rec.tipo_pessoa = 'pf'
             LEFT JOIN tbl_pessoas_juridicas pj ON pj.id = rec.pagador_id AND rec.tipo_pessoa = 'pj'
             WHERE rec.empresa_id = ? AND rec.status_sistema = 'incluido' 
               AND rec.data_vencimento BETWEEN ? AND ?
               AND rec.status IN ('Recebido', 'Parcial'))
            
            UNION ALL
            
            (SELECT 
                'despesa' as tipo, 
                des.valor_pago as valor, 
                des.data_vencimento as data, 
                des.status, 
                des.descricao,
                (CASE 
                    WHEN des.tipo_favorecido = 'manual' THEN des.nome_favorecido_manual 
                    WHEN des.tipo_pessoa = 'pf' THEN pfd.nome_completo 
                    WHEN des.tipo_pessoa = 'pj' THEN COALESCE(pjd.nome_fantasia, pjd.razao_social)
                    ELSE 'Desconhecido' 
                END) as pessoa
             FROM tbl_despesas des
             LEFT JOIN tbl_pessoas_fisicas pfd ON pfd.id = des.favorecido_id AND des.tipo_pessoa = 'pf'
             LEFT JOIN tbl_pessoas_juridicas pjd ON pjd.id = des.favorecido_id AND des.tipo_pessoa = 'pj'
             WHERE des.empresa_id = ? AND des.status_sistema = 'incluido' 
               AND des.data_vencimento BETWEEN ? AND ?
               AND des.status IN ('Pago', 'Parcial'))
            
            ORDER BY data ASC
        ";
        $registros = $this->query($sqlList, [$empresaId, $inicio, $fim, $empresaId, $inicio, $fim]);

        return [
            'totais' => [
                'entradas' => $totalEntradas,
                'saidas' => $totalSaidas,
                'saldo' => $totalEntradas - $totalSaidas
            ],
            'registros' => $registros
        ];
    }
}
