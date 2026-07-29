<?php

namespace App\Models;

use App\Models\Banco;
use Lumynus\Framework\LumynusContainer;
use Lumynus\Framework\Sessions;

abstract class BaseModel extends Banco
{

    protected string $table;
    protected string $alias = '';
    protected array $columns = ['*'];
    protected array $joins = [];
    protected array $default = [];
    protected bool $withRelationships = false;
    protected bool $multiTenant = true;

    /**
     * Define se o modelo deve registrar automaticamente o ID do usuário que
     * criou (cadastrado_por) ou alterou (atualizado_por) o registro.
     * Quando ativado, os campos são injetados sozinhos no CrudService.
     * @var bool
     */
    protected bool $registrarAutoria = true;

    protected string $primaryKey = 'id';

    public function isMultiTenant(): bool
    {
        return $this->multiTenant;
    }

    public function setMultiTenant(bool $multiTenant): self
    {
        $this->multiTenant = $multiTenant;
        return $this;
    }

    /**
     * Verifica se o registro de autoria está ativado para este modelo.
     * @return bool
     */
    public function deveRegistrarAutoria(): bool
    {
        return $this->registrarAutoria;
    }

    public function getEmpresaId()
    {
        $session = LumynusContainer::resolve(Sessions::class);
        $token = $session->get(\App\Enums\Auth::token->value);
        return $token['id_empresa'] ?? $token['empresa_id'] ?? null;
    }

    public function getUsuarioLogadoId()
    {
        $session = LumynusContainer::resolve(Sessions::class);
        $token = $session->get(\App\Enums\Auth::token->value);
        return $token['id_usuario'] ?? $token['usuario_id'] ?? $token['id'] ?? null;
    }

    private int $totalRegistros = 0;
    private int $totalPaginas = 1;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Consulta registros com filtros, ordenação e paginação
     * @param array $filtros
     * @param string $ordenacao
     * @param int|null $limite
     * @param int|null $offset
     * @return array
     */
    public function consultar(array $filtros = [], string $ordenacao = 'id_desc', array $paginacao = []): array
    {

        $filtros =  $this->normalizarCampos($filtros);

        if ($this->isMultiTenant()) {
            $tabelaOuAlias = !empty($this->alias) ? $this->alias : $this->table;
            $empresaId = $this->getEmpresaId();
            if ($empresaId !== null) {
                $filtros["{$tabelaOuAlias}.empresa_id"] = $empresaId;
            }
        }

        $filtros = $filtros + $this->default;
        $ordenacao = $this->normalizarOrdenacao($ordenacao);
        $colunas = $this->getSelect();
        $paginacaoNormalizada = $this->normalizarPaginacao(
            pagina: $paginacao['pagina_atual'] ?? null,
            itensPorPagina: $paginacao['por_pagina'] ?? null
        );

        $this->totalRegistros = $this->contar($filtros);
        $this->totalPaginas =  ceil($this->totalRegistros / ($paginacao['por_pagina'] ?? 1));

        $joins = implode(' ', $this->joins);

        $dados = $this->select(
            filters: $filtros,
            columns: $colunas,
            joins: $joins,
            orderBy: $ordenacao,
            limit: $paginacaoNormalizada['limit'] ?? null,
            offset: $paginacaoNormalizada['offset'] ?? 0
        );

        if ($this->withRelationships === true) {
            return $this->agruparRelacionamentoRecursivo($dados);
        }
        return $dados;
    }


    /**
     * Gera a lista de colunas para seleção na consulta
     * @return string
     */
    private function getSelect()
    {
        if (empty($this->columns) || in_array('*', $this->columns)) {
            return '*';
        }

        $colunasPermitidas = [];
        foreach ($this->columns as $coluna => $detalhes) {
            if (isset($detalhes['select']) && $detalhes['select'] === true) {
                if ($coluna === $detalhes['db']) {
                    $colunasPermitidas[] = $detalhes['db'];
                }
                $colunasPermitidas[] = "{$detalhes['db']} AS {$coluna}";
            }
        }
        return implode(', ', $colunasPermitidas);
    }

    /**
     * Remove recursivamente as estruturas internas de controle (_map)
     * utilizadas durante o processo de hidratação de relacionamentos.
     *
     * Esse método percorre toda a estrutura hierárquica gerada pelo
     * agrupamento de resultados com JOIN e elimina a chave especial
     * "_map", que é usada apenas como índice auxiliar para evitar
     * duplicações por ID.
     *
     * A limpeza é feita de forma recursiva em todos os níveis
     * aninhados do array, garantindo que o retorno final esteja
     * pronto para serialização (ex: JSON) sem metadados internos.
     *
     * @param array<string, mixed> &$item Estrutura hierárquica
     *                                    a ser normalizada.
     *
     * @return void
     */
    private function limparMapas(array &$item): void
    {
        foreach ($item as $key => &$value) {

            if ($key === '_map') {
                unset($item[$key]);
                continue;
            }

            if (is_array($value)) {

                if (isset($value[0])) {
                    foreach ($value as &$sub) {
                        if (is_array($sub)) {
                            $this->limparMapas($sub);
                        }
                    }
                }
            }
        }
    }

    /**
     * Agrupa resultados de uma consulta com JOIN em uma estrutura hierárquica (1:N).
     *
     * Converte um array de linhas planas (resultado de SELECT com JOIN)
     * em uma estrutura aninhada baseada em prefixos no formato:
     *
     *   relacionamento__campo
     *
     * Exemplo de alias esperado no SELECT:
     *   aulas__id
     *   aulas__titulo
     *
     * Campos sem o separador "__" são considerados pertencentes
     * à entidade principal (pai). Campos com "__" são agrupados
     * dentro de um array com o nome do relacionamento.
     *
     * Estrutura de entrada esperada:
     * [
     *   [
     *     'id' => 1,
     *     'nome' => 'Curso X',
     *     'aulas__id' => 10,
     *     'aulas__titulo' => 'Introdução'
     *   ],
     *   ...
     * ]
     *
     * Estrutura de saída:
     * [
     *   [
     *     'id' => 1,
     *     'nome' => 'Curso X',
     *     'aulas' => [
     *       ['id' => 10, 'titulo' => 'Introdução']
     *     ]
     *   ]
     * ]
     *
     * @param array<int, array<string, mixed>> $rows Resultado bruto da consulta.
     *
     * @return array<int, array<string, mixed>> Estrutura hierárquica agrupada.
     */
    private function agruparRelacionamentoRecursivo(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $primaryKey = $this->primaryKey;

        // Separar campos pai e campos de relação
        $camposPai = [];
        $camposRelacao = [];

        foreach (array_keys($rows[0]) as $campo) {
            if (!str_contains($campo, '__')) {
                $camposPai[] = $campo;
            } else {
                $camposRelacao[$campo] = explode('__', $campo);
            }
        }

        // Pré-analisar a hierarquia para identificar campos de ID por relação
        // Formato: ['modulos' => ['id_campo' => 'modulos__id', 'campos' => [...], 'filhos' => ['aulas' => [...]]]]
        $hierarquia = $this->construirHierarquia($camposRelacao);

        $resultado = [];

        foreach ($rows as $row) {
            $idPai = $row[$primaryKey];

            if (!isset($resultado[$idPai])) {
                $resultado[$idPai] = ['_map' => []];
                foreach ($camposPai as $campo) {
                    $resultado[$idPai][$campo] = $row[$campo];
                }
            }

            // Processar cada relação de nível 1 e seus sub-relacionamentos
            $this->processarRowNaHierarquia(
                $resultado[$idPai],
                $resultado[$idPai]['_map'],
                $hierarquia,
                $row
            );
        }

        foreach ($resultado as &$item) {
            $this->limparMapas($item);
        }

        return array_values($resultado);
    }

    /**
     * Constrói a árvore hierárquica dos campos de relação.
     *
     * A partir dos campos com "__", constrói uma estrutura que identifica
     * para cada nível de relacionamento: qual é o campo de ID,
     * quais são os campos de dados, e quais são os sub-relacionamentos.
     *
     * @param array<string, array<string>> $camposRelacao Mapa de campo => níveis
     * @return array<string, array> Hierarquia de relacionamentos
     */
    private function construirHierarquia(array $camposRelacao): array
    {
        $hierarquia = [];

        foreach ($camposRelacao as $campoOriginal => $niveis) {
            $this->adicionarNaHierarquia($hierarquia, $niveis, $campoOriginal);
        }

        return $hierarquia;
    }

    /**
     * Adiciona um campo na árvore hierárquica de relacionamentos.
     *
     * @param array &$hierarquia Referência à hierarquia sendo construída
     * @param array $niveis Array de níveis do campo (ex: ['modulos', 'aulas', 'titulo'])
     * @param string $campoOriginal Nome original do campo na row (ex: 'modulos__aulas__titulo')
     * @return void
     */
    private function adicionarNaHierarquia(array &$hierarquia, array $niveis, string $campoOriginal): void
    {
        $relacao = $niveis[0];

        if (!isset($hierarquia[$relacao])) {
            $hierarquia[$relacao] = [
                'id_campo' => null,
                'campos' => [],
                'filhos' => []
            ];
        }

        if (count($niveis) === 2) {
            // Campo final: relacao__campo
            $campo = $niveis[1];
            if ($campo === 'id') {
                $hierarquia[$relacao]['id_campo'] = $campoOriginal;
            } else {
                $hierarquia[$relacao]['campos'][$campo] = $campoOriginal;
            }
        } else {
            // Sub-relacionamento: relacao__subrelacao__...
            $subNiveis = array_slice($niveis, 1);
            $this->adicionarNaHierarquia($hierarquia[$relacao]['filhos'], $subNiveis, $campoOriginal);
        }
    }

    /**
     * Processa uma row SQL e insere os dados na hierarquia correta.
     *
     * Para cada relação no nível atual, verifica o ID da entidade nessa row,
     * cria ou recupera a entidade pelo mapa, atribui os campos, e recursivamente
     * processa os sub-relacionamentos.
     *
     * @param array &$base Estrutura base do nível atual
     * @param array &$mapa Mapa de deduplicação do nível atual
     * @param array $hierarquia Definição da hierarquia de relações
     * @param array $row Linha da consulta SQL
     * @return void
     */
    private function processarRowNaHierarquia(
        array &$base,
        array &$mapa,
        array $hierarquia,
        array $row
    ): void {

        foreach ($hierarquia as $relacao => $config) {
            // Inicializar array da relação se não existir
            if (!isset($base[$relacao])) {
                $base[$relacao] = [];
                $mapa[$relacao] = [];
            }

            // Obter o ID da entidade nesta row
            $idCampo = $config['id_campo'];
            $idValor = $idCampo !== null ? $row[$idCampo] : null;

            // Se não tem ID (NULL no JOIN), pular esta relação
            if ($idValor === null) {
                continue;
            }

            // Criar ou recuperar entidade pelo mapa (deduplicação por ID)
            if (!isset($mapa[$relacao][$idValor])) {
                $novoItem = [
                    'id' => $idValor,
                    '_map' => []
                ];
                $base[$relacao][] = $novoItem;
                $idx = array_key_last($base[$relacao]);
                $mapa[$relacao][$idValor] = &$base[$relacao][$idx];
            }

            // Referência ao item correto via mapa (não via array_key_last!)
            $item = &$mapa[$relacao][$idValor];

            // Atribuir campos de dados ao item correto
            foreach ($config['campos'] as $campo => $campoOriginal) {
                $valor = $row[$campoOriginal];
                if ($valor !== null) {
                    $item[$campo] = $valor;
                }
            }

            // Processar sub-relacionamentos recursivamente
            if (!empty($config['filhos'])) {
                $this->processarRowNaHierarquia(
                    $item,
                    $item['_map'],
                    $config['filhos'],
                    $row
                );
            }

            unset($item);
        }
    }

    /**
     * Normaliza os filtros para consulta
     * @param array $filtros
     * @return array
     */
    private function normalizarCampos(array $dados)
    {
        if (empty($dados)) {
            return [];
        }

        $dadosNormalizados = [];
        foreach ($dados as $key => $value) {

            if ($value === null || (is_string($value) && trim($value) === '')) {
                continue;
            }

            // Detect suffixes
            $keyLike = str_contains($key, '_like');
            $keyBetween = str_contains($key, '_between');
            $keyIn = str_contains($key, '_in');

            // Clean key
            if ($keyLike) $keyLimpa = str_replace('_like', '', $key);
            elseif ($keyBetween) $keyLimpa = str_replace('_between', '', $key);
            elseif ($keyIn) $keyLimpa = str_replace('_in', '', $key);
            else $keyLimpa = $key;

            if (isset($this->columns[$keyLimpa])) {
                $colunaDB = $this->columns[$keyLimpa]['db'];

                if ($keyLike) {
                    $dadosNormalizados['LIKE ' . $colunaDB] = $value;
                } elseif ($keyBetween) {
                    $dadosNormalizados['BETWEEN ' . $colunaDB] = $value;
                } elseif ($keyIn) {
                    $dadosNormalizados['IN ' . $colunaDB] = $value;
                } else {
                    $dadosNormalizados[$colunaDB] = $value;
                }
            }
        }
        return $dadosNormalizados;
    }

    /**
     * Conta registros com filtros
     * @param array $filtros
     * @return int
     */
    /**
     * Conta registros com filtros de forma otimizada
     * @param array $filtros
     * @return int
     */
    public function contar(array $filtros = []): int
    {
        $pk = $this->primaryKey;
        $tabelaOuAlias = !empty($this->alias) ? $this->alias : $this->table;
        $primaryKey = "{$tabelaOuAlias}.{$pk}";

        $temJoins = !empty($this->joins);
        $joins = $temJoins ? implode(' ', $this->joins) : '';

        $colunaCount = $temJoins
            ? "COUNT(DISTINCT {$primaryKey}) as total"
            : "COUNT({$primaryKey}) as total";

        if ($this->isMultiTenant()) {
            $tabelaOuAlias = !empty($this->alias) ? $this->alias : $this->table;
            $empresaId = $this->getEmpresaId();
            if ($empresaId !== null) {
                $filtros["{$tabelaOuAlias}.empresa_id"] = $empresaId;
            }
        }

        $resultado = $this->select(
            filters: $filtros,
            columns: $colunaCount,
            joins: $joins
        );

        return (int) ($resultado[0]['total'] ?? 0);
    }

    /**
     * Normaliza os filtros para consulta
     * @param array $filtros
     * @return array
     */
    public function getTotalRegistros(): int
    {
        return $this->totalRegistros;
    }

    /**
     * Normaliza os filtros para consulta
     * @param array $filtros
     * @return array
     */
    public function getTotalPaginas(): int
    {
        return $this->totalPaginas;
    }

    /**
     * Cadastra um novo registro
     * @param array $dados
     * @return int
     */
    public function cadastrar(array $dados): int
    {
        $retorno = 0;

        try {
            $retorno = $this->insertReturnID($dados);
        } catch (\Throwable $th) {
            throw new \App\Exceptions\SystemException($th->getMessage(), 500, true);
        }
        return $retorno !== false ? $retorno : 0;
    }

    /**
     * Atualiza um registro pelo ID
     * @param array $dados
     * @param int $id
     * @return bool
     */
    public function atualizar(array $dados, int $id): bool
    {
        $retorno = false;
        try {
            $this->update($id, $dados);
            $retorno = $this->affectedRows() > 0;
        } catch (\Throwable $th) {
            throw new \App\Exceptions\SystemException($th->getMessage(), 500, true);
        }
        return $retorno;
    }

    /**
     * Exclui um registro pelo ID (soft delete)
     * @param int $id
     * @return bool
     */
    public function excluir(int $id): bool
    {
        $retorno = false;

        try {
            $this->update($id, [
                'status_sistema' => 'excluido'
            ]);
            $retorno = $this->affectedRows() > 0;
        } catch (\Throwable $th) {
            throw new \App\Exceptions\SystemException($th->getMessage(), 500, true);
        }
        return $retorno;
    }

    /**
     * Normaliza os parâmetros de paginação
     * @param int|null $pagina
     * @param int|null $itensPorPagina
     * @return array
     */
    public function normalizarPaginacao(?int $pagina = null, ?int $itensPorPagina = null): array
    {
        if ($pagina === null || $itensPorPagina === null) {
            return [
                'limit' => null,
                'offset' => null
            ];
        }
        $pagina = $pagina < 1 ? 1 : $pagina;
        $itensPorPagina = $itensPorPagina < 1 ? 10 : $itensPorPagina;
        $offset = ($pagina - 1) * $itensPorPagina;
        return [
            'limit' => $itensPorPagina,
            'offset' => $offset
        ];
    }

    /**
     * Normaliza a string de ordenação
     * @param string $ordenacao
     * @return string
     */
    public function normalizarOrdenacao(string $ordenacao): string
    {
        $ordenacao = trim($ordenacao);
        if (!preg_match('/_(asc|desc)$/i', $ordenacao)) {
            return $this->sanitizer()->string($ordenacao) . ' desc';
        }

        $partes = preg_split('/_(?=(asc|desc)$)/i', $ordenacao);
        $campo = $this->sanitizer()->string($partes[0]);
        $campoNormalizado = $this->columns[$campo]['db'] ?? $campo;
        $direcao = strtolower($partes[1]) === 'asc' ? 'asc' : 'desc';
        if ($campo === $this->primaryKey) {
            $campoNormalizado = $this->primaryKey;
        }
        return "{$campoNormalizado} {$direcao}";
    }
}
