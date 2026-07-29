-- Impede parcelas duplicadas para o mesmo contrato, preservando registros excluídos.
create unique index if not exists uq_contratos_parcelas_contrato_numero
  on public.tbl_contratos_parcelas (contrato_id, numero_parcela)
  where status_sistema <> 'excluido';
