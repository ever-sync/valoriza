-- DDL: Módulo Contratos
-- Execute no banco de dados antes de usar o módulo

CREATE TABLE IF NOT EXISTS tbl_contratos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  empresa_id INT NOT NULL,

  -- Operação
  tipo_operacao VARCHAR(50) NOT NULL DEFAULT 'Empréstimo',
  valor_solicitado DECIMAL(15,2) NOT NULL,
  periodo_amortizacao VARCHAR(50) NOT NULL,
  modelo_amortizacao VARCHAR(50) NOT NULL,
  taxa_juros DECIMAL(10,6) NOT NULL,
  tipo_taxa VARCHAR(20) NOT NULL DEFAULT 'mensal',
  quantidade_parcelas INT NOT NULL,
  data_assinatura DATE NOT NULL,
  data_primeira_parcela DATE NOT NULL,
  simples_nacional TINYINT(1) NOT NULL DEFAULT 0,
  calcular_iof TINYINT(1) NOT NULL DEFAULT 0,

  -- Cliente
  tipo_cliente VARCHAR(5) NOT NULL DEFAULT 'pj',
  cliente_id INT NULL,

  -- Inadimplência
  juros_mora DECIMAL(10,6) NULL,
  multa_moratoria DECIMAL(10,6) NULL,
  risco_inadimplencia VARCHAR(20) NULL DEFAULT 'Baixo',
  permitir_carencia TINYINT(1) NOT NULL DEFAULT 0,
  limite_carencia INT NULL,                             -- NULL = ilimitado

  -- Conclusão / Assinatura
  tipo_assinatura VARCHAR(60) NULL DEFAULT 'sem_assinatura',
  enviar_registradora TINYINT(1) NOT NULL DEFAULT 0,
  contrato_iniciado TINYINT(1) NOT NULL DEFAULT 0,

  -- Status
  status VARCHAR(30) NOT NULL DEFAULT 'Ativo',

  -- Sistema
  data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
  data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  status_sistema VARCHAR(20) NOT NULL DEFAULT 'incluido',
  cadastrado_por INT NULL,
  atualizado_por INT NULL
);

-- Se a tabela já existir, adicione as colunas manualmente:
-- ALTER TABLE tbl_contratos ADD COLUMN limite_carencia INT NULL AFTER permitir_carencia;
-- ALTER TABLE tbl_contratos ADD COLUMN tipo_assinatura VARCHAR(60) NULL DEFAULT 'sem_assinatura' AFTER limite_carencia;
-- ALTER TABLE tbl_contratos ADD COLUMN enviar_registradora TINYINT(1) NOT NULL DEFAULT 0 AFTER tipo_assinatura;
-- ALTER TABLE tbl_contratos ADD COLUMN contrato_iniciado TINYINT(1) NOT NULL DEFAULT 0 AFTER enviar_registradora;

CREATE TABLE IF NOT EXISTS tbl_contratos_garantias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contrato_id INT NOT NULL,
  empresa_id INT NOT NULL,
  tipo_garantia VARCHAR(50) NOT NULL,
  nome_completo VARCHAR(255) NULL,
  cpf VARCHAR(20) NULL,
  rg VARCHAR(30) NULL,
  orgao_emissor_rg VARCHAR(50) NULL,
  email VARCHAR(255) NULL,
  telefone VARCHAR(30) NULL,
  renda_mensal DECIMAL(15,2) NULL,
  estado_civil VARCHAR(50) NULL,
  regime_bens VARCHAR(50) NULL,
  cep VARCHAR(10) NULL,
  estado VARCHAR(5) NULL,
  cidade VARCHAR(100) NULL,
  bairro VARCHAR(100) NULL,
  endereco VARCHAR(255) NULL,
  numero VARCHAR(20) NULL,
  complemento VARCHAR(100) NULL,
  descricao TEXT NULL,
  numero_serie VARCHAR(100) NULL,
  estado_conservacao VARCHAR(100) NULL,
  localizacao_fisica VARCHAR(255) NULL,
  valor_avaliacao DECIMAL(15,2) NULL,
  status_sistema VARCHAR(20) NOT NULL DEFAULT 'incluido',
  data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (contrato_id) REFERENCES tbl_contratos(id)
);

-- ==============================================================
-- Adicionar campo taxa_iof na tabela de Configurações de Contratos
-- Execute se a tabela tbl_configuracoes_contratos já foi criada:
-- ==============================================================
ALTER TABLE tbl_configuracoes_contratos
  ADD COLUMN taxa_iof DECIMAL(10,6) NULL DEFAULT 3.0000 COMMENT 'Taxa IOF anual configurável (%)' AFTER taxa_juros_outras_garantias;
