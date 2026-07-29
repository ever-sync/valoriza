<?php

namespace App\Models\Dashboard;

use App\Models\BaseModel;

class DashboardModel extends BaseModel
{
    protected string $table = 'tbl_receitas'; // Usaremos como base mas faremos queries manuais

    public function getReceitaMes(int $empresaId, string $mesAtual): float
    {
        $sql = "SELECT SUM(valor_recebido) as total FROM tbl_receitas 
                WHERE status IN ('Recebido', 'Parcial') AND data_vencimento LIKE ? AND empresa_id = ? AND status_sistema = 'incluido'";
        $res = $this->query($sql, ["$mesAtual%", $empresaId]);
        return (float)($res[0]['total'] ?? 0);
    }

    public function getReceitasPendentes(int $empresaId, string $hoje): float
    {
        $sql = "SELECT SUM(valor_recebido) as total FROM tbl_receitas 
                WHERE status NOT IN ('Recebido', 'Parcial') AND data_vencimento >= ? AND empresa_id = ? AND status_sistema = 'incluido'";
        $res = $this->query($sql, [$hoje, $empresaId]);
        return (float)($res[0]['total'] ?? 0);
    }

    public function getAtrasos(int $empresaId, string $hoje): float
    {
        $sql = "SELECT SUM(valor_recebido) as total FROM tbl_receitas 
                WHERE status NOT IN ('Recebido', 'Parcial') AND data_vencimento < ? AND empresa_id = ? AND status_sistema = 'incluido'";
        $res = $this->query($sql, [$hoje, $empresaId]);
        return (float)($res[0]['total'] ?? 0);
    }

    public function getNovosClientes(int $empresaId, string $mesAtual): int
    {
        $sqlPF = "SELECT COUNT(*) as total FROM tbl_pessoas_fisicas 
                  WHERE data_cadastro LIKE ? AND empresa_id = ? AND status_sistema = 'incluido'";
        $resPF = $this->query($sqlPF, ["$mesAtual%", $empresaId]);

        $sqlPJ = "SELECT COUNT(*) as total FROM tbl_pessoas_juridicas 
                  WHERE data_cadastro LIKE ? AND empresa_id = ? AND status_sistema = 'incluido'";
        $resPJ = $this->query($sqlPJ, ["$mesAtual%", $empresaId]);

        return (int)($resPF[0]['total'] ?? 0) + (int)($resPJ[0]['total'] ?? 0);
    }

    public function getTransacoesRecentes(int $empresaId): array
    {
        $sql = "
            (SELECT 
                'receita' as tipo, 
                rec.valor_recebido as valor, 
                rec.data_vencimento as data, 
                rec.status, 
                (CASE 
                    WHEN rec.tipo_pagador = 'manual' THEN rec.nome_pagador_manual 
                    WHEN rec.tipo_pessoa = 'pf' THEN pf.nome_completo 
                    WHEN rec.tipo_pessoa = 'pj' THEN COALESCE(pj.nome_fantasia, pj.razao_social)
                    ELSE 'Desconhecido' 
                END) as nome,
                rec.data_cadastro
             FROM tbl_receitas rec
             LEFT JOIN tbl_pessoas_fisicas pf ON pf.id = rec.pagador_id AND rec.tipo_pessoa = 'pf'
             LEFT JOIN tbl_pessoas_juridicas pj ON pj.id = rec.pagador_id AND rec.tipo_pessoa = 'pj'
             WHERE rec.empresa_id = ? AND rec.status_sistema = 'incluido' AND rec.status IN ('Recebido', 'Parcial'))
            
            UNION ALL
            
            (SELECT 
                'despesa' as tipo, 
                des.valor_pago as valor, 
                des.data_vencimento as data, 
                des.status,
                (CASE 
                    WHEN des.tipo_favorecido = 'manual' THEN des.nome_favorecido_manual 
                    WHEN des.tipo_pessoa = 'pf' THEN pfd.nome_completo 
                    WHEN des.tipo_pessoa = 'pj' THEN COALESCE(pjd.nome_fantasia, pjd.razao_social)
                    ELSE 'Desconhecido' 
                END) as nome,
                des.data_cadastro
             FROM tbl_despesas des
             LEFT JOIN tbl_pessoas_fisicas pfd ON pfd.id = des.favorecido_id AND des.tipo_pessoa = 'pf'
             LEFT JOIN tbl_pessoas_juridicas pjd ON pjd.id = des.favorecido_id AND des.tipo_pessoa = 'pj'
             WHERE des.empresa_id = ? AND des.status_sistema = 'incluido' AND des.status IN ('Pago', 'Parcial'))
            
            ORDER BY data_cadastro DESC LIMIT 8
        ";
        return $this->query($sql, [$empresaId, $empresaId]);
    }

    public function getHistoricoMensal(int $empresaId, int $meses = 6): array
    {
        $grafico = [];
        for ($i = $meses - 1; $i >= 0; $i--) {
            $mes = date('Y-m', strtotime("-$i months"));
            $nomeMes = date('M', strtotime("-$i months"));
            
            $total = $this->getReceitaMes($empresaId, $mes);
            $grafico[] = ['mes' => $nomeMes, 'total' => $total];
        }
        return $grafico;
    }
}
