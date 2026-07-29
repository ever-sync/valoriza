<?php
require 'vendor/autoload.php';
use App\Models\BaseModel;

class MigrationRunner
{
    public function run()
    {
        echo "=== Executando Migrations ===\n\n";
        
        $this->tabelaProrrogacoes();
        $this->removerColunasIOF();
        $this->adicionarLimiteProrrogacoes();
        $this->atualizarTabelaGarantias();
        $this->adicionarCampoUsosCarencia();
        $this->adicionarRedeSocial();
        
        echo "\n=== Migrations Concluídas ===\n";
    }
    
    private function tabelaProrrogacoes()
    {
        $m = new class extends BaseModel {
            protected string $table = 'tbl_receitas_prorrogacoes';
            protected string $alias = 'prr';
            
            public function run() {
                $sql = "CREATE TABLE IF NOT EXISTS `tbl_receitas_prorrogacoes` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `receita_id` INT NOT NULL,
                    `contrato_id` INT NULL DEFAULT NULL,
                    `empresa_id` INT NOT NULL,
                    `numero_contrato` VARCHAR(50) NULL DEFAULT NULL,
                    `parcela_numero` INT NULL DEFAULT NULL,
                    `total_parcelas` INT NULL DEFAULT NULL,
                    `cliente_nome` VARCHAR(255) NULL DEFAULT NULL,
                    `cliente_documento` VARCHAR(30) NULL DEFAULT NULL,
                    `data_vencimento_anterior` DATE NOT NULL,
                    `data_vencimento_nova` DATE NOT NULL,
                    `valor_anterior` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    `valor_recebido` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    `desconto` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    `juros_atualizacao` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    `juros_mora` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    `multa_atraso` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    `valor_devido` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    `valor_novo` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    `valor_pago` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    `novo_saldo` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    `justificativa` TEXT NULL,
                    `cadastrado_por` INT NULL DEFAULT NULL,
                    `data_cadastro` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    `status_sistema` VARCHAR(20) DEFAULT 'incluido',
                    INDEX `idx_receita_id` (`receita_id`),
                    INDEX `idx_contrato_id` (`contrato_id`),
                    INDEX `idx_empresa_id` (`empresa_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
                
                try {
                    $this->query($sql);
                    echo "[OK] Tabela tbl_receitas_prorrogacoes criada/atualizada!\n";
                } catch (\Exception $e) {
                    if (str_contains($e->getMessage(), 'already exists')) {
                        echo "[SKIP] Tabela tbl_receitas_prorrogacoes já existe.\n";
                    } else {
                        echo "[ERRO] " . $e->getMessage() . "\n";
                    }
                }
            }
        };
        $m->run();
    }
    
    private function removerColunasIOF()
    {
        $m = new class extends BaseModel {
            protected string $table = 'tbl_configuracoes_contratos';
            
            public function run() {
                echo "\n[INFO] Removendo colunas de IOF obsoletas em tbl_configuracoes_contratos...\n";
                
                $columns = ['taxa_iof_diario', 'taxa_iof_adicional'];
                
                foreach ($columns as $col) {
                    try {
                        $this->query("ALTER TABLE {$this->table} DROP COLUMN `$col` ");
                        echo "[OK] Coluna $col removida com sucesso!\n";
                    } catch (\Exception $e) {
                        if (str_contains(strtolower($e->getMessage()), "can't drop") || str_contains(strtolower($e->getMessage()), "check that column/key exists")) {
                            echo "[SKIP] Coluna $col já foi removida ou não existe.\n";
                        } else {
                            echo "[AVISO] " . $e->getMessage() . "\n";
                        }
                    }
                }
            }
        };
        $m->run();
    }
    
    private function adicionarLimiteProrrogacoes()
    {
        $m = new class extends BaseModel {
            protected string $table = 'tbl_configuracoes_contratos';
            protected string $alias = 'cfg';
            
            public function run() {
                echo "\n[INFO] Adicionando limite de prorrogações...\n";
                
                try {
                    $this->query("ALTER TABLE {$this->table} ADD COLUMN IF NOT EXISTS `limite_prorrogacoes` INT NULL DEFAULT 12 COMMENT 'Limite máximo de prorrogações por parcela' AFTER `taxa_iof_adicional`");
                    echo "[OK] Campo limite_prorrogacoes adicionado!\n";
                } catch (\Exception $e) {
                    if (str_contains(strtolower($e->getMessage()), 'duplicate column')) {
                        echo "[SKIP] Campo limite_prorrogacoes já existe.\n";
                    } else {
                        echo "[AVISO] " . $e->getMessage() . "\n";
                    }
                }
                
                $this->query("UPDATE {$this->table} SET limite_prorrogacoes = 12 WHERE limite_prorrogacoes IS NULL");
            }
        };
        $m->run();
    }
    
    private function atualizarTabelaGarantias()
    {
        $m = new class extends BaseModel {
            protected string $table = 'tbl_contratos_garantias';
            protected string $alias = 'ctg';
            
            public function run() {
                echo "\n[INFO] Atualizando tbl_contratos_garantias com campos de vínculo com pessoas...\n";
                
                $alterations = [
                    "ADD COLUMN IF NOT EXISTS `tipo_pessoa_garantia` VARCHAR(2) NULL DEFAULT 'pf' COMMENT 'pj ou pf' AFTER `tipo_garantia`",
                    "ADD COLUMN IF NOT EXISTS `pessoa_id_garantia` INT NULL DEFAULT NULL COMMENT 'ID da pessoa física ou jurídica cadastrada' AFTER `tipo_pessoa_garantia`",
                ];
                
                foreach ($alterations as $sql) {
                    try {
                        $this->query("ALTER TABLE {$this->table} {$sql}");
                        echo "[OK] Campo adicionado com sucesso!\n";
                    } catch (\Exception $e) {
                        if (str_contains(strtolower($e->getMessage()), 'duplicate column')) {
                            echo "[SKIP] Campo já existe.\n";
                        } else {
                            echo "[AVISO] " . $e->getMessage() . "\n";
                        }
                    }
                }
            }
        };
        $m->run();
    }
    /**
     * Adiciona campo usos_carencia em tbl_contratos para rastrear
     * quantas vezes a carência de principal foi utilizada.
     */
    private function adicionarCampoUsosCarencia()
    {
        $m = new class extends BaseModel {
            protected string $table = 'tbl_contratos';
            protected string $alias = 'ctr';
            
            public function run() {
                echo "\n[INFO] Adicionando campo usos_carencia em tbl_contratos...\n";
                
                try {
                    $this->query("ALTER TABLE {$this->table} ADD COLUMN IF NOT EXISTS `usos_carencia` INT NOT NULL DEFAULT 0 COMMENT 'Quantidade de vezes que a carência de principal foi utilizada' AFTER `limite_carencia`");
                    echo "[OK] Campo usos_carencia adicionado!\n";
                } catch (\Exception $e) {
                    if (str_contains(strtolower($e->getMessage()), 'duplicate column')) {
                        echo "[SKIP] Campo usos_carencia já existe.\n";
                    } else {
                        echo "[AVISO] " . $e->getMessage() . "\n";
                    }
                }
            }
        };
        $m->run();
    }
    /**
     * Adiciona o campo rede_social em tbl_pessoas_fisicas e tbl_pessoas_juridicas.
     */
    private function adicionarRedeSocial()
    {
        $tables = ['tbl_pessoas_fisicas', 'tbl_pessoas_juridicas'];
        
        foreach ($tables as $table) {
            $m = new class($table) extends BaseModel {
                protected string $table;
                public function __construct($table) { 
                    parent::__construct();
                    $this->table = $table; 
                }
                
                public function run() {
                    echo "\n[INFO] Adicionando campo rede_social em {$this->table}...\n";
                    try {
                        $this->query("ALTER TABLE `{$this->table}` ADD COLUMN IF NOT EXISTS `rede_social` VARCHAR(255) NULL DEFAULT NULL AFTER `email` ");
                        echo "[OK] Campo rede_social adicionado em {$this->table}!\n";
                    } catch (\Exception $e) {
                        if (str_contains(strtolower($e->getMessage()), 'duplicate column')) {
                            echo "[SKIP] Campo rede_social já existe em {$this->table}.\n";
                        } else {
                            echo "[AVISO] " . $e->getMessage() . "\n";
                        }
                    }
                }
            };
            $m->run();
        }
    }
}

(new MigrationRunner())->run();
