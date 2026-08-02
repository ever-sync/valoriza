-- Preserva quanto foi originalmente cobrado em cada receita.
--
-- Até aqui o pagamento parcial sobrescrevia valor_recebido com o saldo remanescente,
-- de modo que o valor cobrado se perdia no primeiro pagamento e a receita deixava de
-- ser auditável. valor_original passa a ser imutável após a criação e valor_pago
-- acumula os recebimentos; o saldo continua refletido em valor_recebido.
alter table public.tbl_receitas
  add column if not exists valor_original numeric(15,2),
  add column if not exists valor_pago numeric(15,2) not null default 0;

-- Backfill possível: para receitas sem pagamento parcial anterior, o saldo atual é o
-- próprio valor cobrado. As que já sofreram pagamento parcial antes desta migration
-- não têm como recuperar o valor original — o histórico foi perdido na gravação e
-- elas ficam com o saldo remanescente, que subestima a cobrança.
update public.tbl_receitas
  set valor_original = valor_recebido
  where valor_original is null;
