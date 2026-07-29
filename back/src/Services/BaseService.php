<?php

namespace App\Services;

use App\Enums\Auth;
use App\Exceptions\SystemException;
use App\Helpers\CrudServiceHelper;
use Lumynus\Framework\LumynusUtilities;
use App\Interfaces\CrudServiceInterface;
use stdClass;

abstract class BaseService implements CrudServiceInterface
{

    use CrudServiceHelper;
    use LumynusUtilities;

    protected string $model = '';
    private ?object $modelInstanc = null;
    private array $modelaux = [];
    private array $servicosaux = [];

    protected ?int $idEditado = null;
    protected ?int $idCriado = null;

    protected array $camposGlobais = [];
    protected array $camposInserir = [];
    protected array $camposAtualizar = [];

    public function __construct()
    {
        date_default_timezone_set('America/Sao_Paulo');
    }

    /**
     * Retorna uma instância do model principal.
     */
    protected function model(): object
    {
        return $this->modelInstanc ??= new $this->model();
    }

    /**
     * Retorna uma instância de um model auxiliar.
     */
    protected function auxModel(string|object $classe): object
    {
        $classe = is_object($classe) ? get_class($classe) : $classe;

        if (!isset($this->modelaux[$classe])) {

            if (is_string($classe)) {

                if (!class_exists($classe)) {
                    throw new \RuntimeException("Model {$classe} não existe");
                }

                $this->modelaux[$classe] = new $classe();
            } else {

                $this->modelaux[$classe] = $classe;
            }
        }

        return $this->modelaux[$classe];
    }

    /**
     * Retorna uma instância de um servico auxiliar.
     */
    protected function auxService(string|object $classe): object
    {
        $classe = is_object($classe) ? get_class($classe) : $classe;

        if (!isset($this->servicosaux[$classe])) {

            if (is_string($classe)) {

                if (!class_exists($classe)) {
                    throw new \RuntimeException("Model {$classe} não existe");
                }

                $this->servicosaux[$classe] = new $classe();
            } else {

                $this->servicosaux[$classe] = $classe;
            }
        }

        return $this->servicosaux[$classe];
    }


    /**
     * Retorna o ID do usuário logado.
     */
    protected function getUsuarioId(): ?int
    {
        return $this->sanitizer()->int($this->sessions()->get(Auth::token->value)['id_usuario'] ?? null);
    }

    /**
     * Retorna o usuário logado.
     */
    protected function getUsuario(): ?array
    {
        return $this->sessions()->get(Auth::token->value) ?? null;
    }

    /**
     * Retorna o tipo do usuário logado. @see PERFIL_ACESSO
     */
    protected function getUsuarioTipo(): ?string
    {
        return $this->sanitizer()->string($this->sessions()->get(Auth::token->value)['perfil_acesso'] ?? null);
    }

    /**
     * Retorna o ID da empresa logada.
     */
    protected function getEmpresaId(): ?int
    {
        return $this->sanitizer()->int($this->sessions()->get(Auth::token->value)['empresa_id'] ?? null);
    }


    /**
     * Cadastra um novo registro
     * @param array $dados
     * @return int
     * @throws SystemException
     */
    public function cadastrar(array $dados): int
    {
        try {
            $dados = $this->prepareWrite($dados, 'inserir')->inserir;
            $dados = $dados[0];
            $dadosTratados = $this->removerCamposVazios($dados);

            if (method_exists($this->model(), 'isMultiTenant') && $this->model()->isMultiTenant()) {
                $empresaId = $this->model()->getEmpresaId();
                if ($empresaId !== null) {
                    $dadosTratados['empresa_id'] = $empresaId;
                }
            }

            if (method_exists($this->model(), 'deveRegistrarAutoria') && $this->model()->deveRegistrarAutoria()) {
                $usuarioId = $this->model()->getUsuarioLogadoId();
                if ($usuarioId !== null) {
                    $dadosTratados['cadastrado_por'] = $usuarioId;
                }
            }

            $retorno = $this->model()->cadastrar($dadosTratados);

            if ($retorno === -1 || $retorno === 0) {
                throw new SystemException('Falha ao inserir registro!');
            }
            return $retorno;
        } catch (SystemException $e) {
            throw $e;
        } catch (\Throwable $th) {
            throw new SystemException($th->getMessage());
        }
    }

    /**
     * Consulta um registro pelo ID
     * @param int $id
     * @return array
     */
    public function consultar(array $filtros = [], string $ordenacao = 'id_desc', array $paginacao = []): array
    {
        // 1. Extrair Ordenação
        if (isset($filtros['ordena'])) {
            $ordenacao = $filtros['ordena'];
            unset($filtros['ordena']);
        } elseif (isset($filtros['ordenacaoColuna'])) {
            $col = $filtros['ordenacaoColuna'];
            $dir = $filtros['ordenacaoDirecao'] ?? 'desc';
            $ordenacao = "{$col}_{$dir}";
            unset($filtros['ordenacaoColuna'], $filtros['ordenacaoDirecao']);
        }

        // 2. Extrair Paginação
        if (isset($filtros['pagina_atual']) || isset($filtros['por_pagina'])) {
            $paginacao = [
                'pagina_atual' => (int) ($filtros['pagina_atual'] ?? 1),
                'por_pagina'   => (int) ($filtros['por_pagina'] ?? 10)
            ];
            unset($filtros['pagina_atual'], $filtros['por_pagina']);
        } elseif (isset($filtros['pagina']) || isset($filtros['porPagina'])) {
            $paginacao = [
                'pagina_atual' => (int) ($filtros['pagina'] ?? 1),
                'por_pagina'   => (int) ($filtros['porPagina'] ?? 10)
            ];
            unset($filtros['pagina'], $filtros['porPagina']);
        }

        // Executar consulta no model
        $dados =  $this->model()->consultar($filtros, $ordenacao, $paginacao);
        $totalRegistros = $this->model()->getTotalRegistros();
        $totalPaginas = $this->model()->getTotalPaginas();

        if (method_exists(static::class, 'transformOutput')) {
            $dados = $this->transformOutput($dados);
        }

        return [
            'data' => $dados,
            'meta' => [
                'total' => $totalRegistros,
                'pagina' => $paginacao['pagina_atual'] ?? 1,
                'por_pagina' => $paginacao['por_pagina'] ?? 10,
                'total_paginas' => $totalPaginas
            ]
        ];
    }

    /**
     * Atualiza um registro existente pelo ID
     * @param array $dados
     * @param int $id
     * @return bool
     * @throws SystemException
     */
    public function atualizar(array $dados, int $id): bool
    {
        try {
            $dados = $this->prepareWrite($dados, 'atualizar')->atualizar;
            $dados = $dados[0];
            $dadosTratados = $this->removerCamposVazios($dados);

            if (method_exists($this->model(), 'isMultiTenant') && $this->model()->isMultiTenant()) {
                $empresaId = $this->model()->getEmpresaId();
                if ($empresaId !== null) {
                    $dadosTratados['empresa_id'] = $empresaId;
                }
            }

            if (method_exists($this->model(), 'deveRegistrarAutoria') && $this->model()->deveRegistrarAutoria()) {
                $usuarioId = $this->model()->getUsuarioLogadoId();
                if ($usuarioId !== null) {
                    $dadosTratados['atualizado_por'] = $usuarioId;
                }
            }

            $retorno = $this->model()->atualizar($dadosTratados, $id);

            if ($retorno === -1) {
                throw new SystemException('Falha ao atualizar registro!');
            }
            return 1;
        } catch (SystemException $e) {
            throw $e;
        } catch (\Throwable $th) {
            throw new SystemException($th->getMessage());
        }
        return false;
    }

    /**
     * Excluir(Soft delete) registro pelo ID
     * @param int $id
     * @return bool
     * @throws SystemException
     */
    public function excluir(int $id): bool
    {
        try {
            $retorno = $this->model()->excluir($id);

            if ($retorno === -1) {
                throw new SystemException('Falha ao excluir registro!');
            }
            return true;
        } catch (SystemException $e) {
            throw $e;
        } catch (\Throwable $th) {
            throw new SystemException($th->getMessage());
        }
    }

    /*
    * Prepara os dados para inserção ou atualização
    * @param array $input
    * @param string $contexto
    * @return stdClass
    */
    protected function prepareWrite(array $input, string $contexto = 'inserir'): stdClass
    {
        $dto = new stdClass();
        return $dto;
    }

    /**
     * Transforma os dados para o formato de saída
     * @param array $dados
     * @return array
     */
    protected function transformOutput(array $dados): array
    {
        return $dados;
    }

    /**
     * Lança uma exceção.
     */
    protected function exception(string $mensagemDetalhada = 'Ocorreu um erro inesperado.', int $codigoErro = 0, bool $autoAnalisar = true): never
    {
        throw new SystemException(
            message: $mensagemDetalhada,
            code: $codigoErro,
            autoAnalisar: $autoAnalisar,
            statusCode: $codigoErro
        );
    }
}
