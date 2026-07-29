<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\SystemException;
use App\Helpers\EntidadeIdEncryptHelper;
use App\Helpers\RespostaHelper;
use Lumynus\Framework\AbstractController;
use Lumynus\Http\Contracts\Response;
use Lumynus\Http\Contracts\Request;


abstract class BaseController extends AbstractController
{

    use RespostaHelper;
    use EntidadeIdEncryptHelper;

    /**
     * Serviço principal utilizado pelo controller.
     *
     * @var object|null
     */
    protected ?object $servico = null;

    /**
     * Nome utilizado para compor mensagens de exceção.
     *
     * @var string
     */
    protected string $nomeParaExcecao = 'Registro';

    /**
     * Armazena instâncias de serviços já criadas.
     *
     * A chave do array é o nome da classe do serviço.
     *
     * @var array<string, object>
     */
    private array $instancias = [];


    /**
     * Construtor responsável por definir o serviço principal do controller.
     *
     * O serviço pode ser informado como:
     * - Nome da classe (string)
     * - Instância de objeto
     * - null
     *
     * Caso o serviço já tenha sido instanciado anteriormente,
     * a instância existente será reutilizada.
     *
     * @param object|string|null $servico Nome da classe do serviço ou instância do serviço.
     *
     * @throws \RuntimeException Caso a classe do serviço não exista.
     */
    protected function __construct(object|string|null $servico = null)
    {
        if ($servico) {
            $this->servico = $this->resolverServico($servico);
        }
    }

    /**
     * Define ou recupera um serviço auxiliar para uso no controller.
     *
     * Caso o serviço já tenha sido instanciado anteriormente,
     * a instância armazenada será reutilizada.
     *
     * @param string|object $servicoAuxiliar Nome da classe do serviço ou instância do serviço.
     *
     * @return object
     *
     * @throws \RuntimeException Caso a classe do serviço não exista.
     */
    protected function auxService(string $servicoAuxiliar): object
    {
        return $this->resolverServico($servicoAuxiliar);
    }

    /**
     * Resolve e retorna uma instância de um serviço.
     *
     * Este método atua como um pequeno container de dependências,
     * garantindo que cada serviço seja instanciado apenas uma vez
     * durante o ciclo de vida do controller.
     *
     * Caso o serviço já tenha sido criado anteriormente, a instância
     * armazenada será reutilizada.
     *
     * O serviço pode ser informado de duas formas:
     * - Nome da classe do serviço (string)
     * - Instância de objeto já criada
     *
     * @param string|object $servico Nome da classe do serviço ou instância do serviço.
     *
     * @return object Instância do serviço resolvido.
     *
     * @throws \RuntimeException Caso a classe informada não exista.
     */
    private function resolverServico(string|object $servico): object
    {
        $classe = is_object($servico) ? get_class($servico) : $servico;

        if (!isset($this->instancias[$classe])) {

            if (is_string($servico)) {

                if (!class_exists($servico)) {
                    throw new \RuntimeException("Serviço {$servico} não existe");
                }

                $this->instancias[$classe] = new $servico();
            } else {

                $this->instancias[$classe] = $servico;
            }
        }

        return $this->instancias[$classe];
    }

    /**
     * POST /Controller
     */
    public function inserir(Request $req, Response $res): Response
    {
        return $this->executar($req, $res, function ($dados) {
            return $this->servico->cadastrar($dados);
        });
    }

    /**
     * PUT /Controller/{id}
     */
    public function editar(Request $req, Response $res): Response
    {
        return $this->executar($req, $res, function ($dados, $id) {
            $this->servico->atualizar($dados, (int)$id);
        });
    }

    /**
     * EXCLUIR /Controller/{id}
     */
    public function excluir(Request $req, Response $res): Response
    {
        return $this->executar($req, $res, function () use ($req) {
            $this->servico->excluir((int)$req->getAttribute('id'));
        });
    }

    /**
     * GET /Controller
     */
    public function buscar(Request $req, Response $res): Response
    {
        return $this->executar($req, $res, function ($dados) {
            return $this->servico->consultar($dados);
        });
    }

    /**
     * Executa uma ação específica do controller encapsulando o tratamento
     * padrão de exceções e o formato de resposta HTTP.
     *
     * O callback é responsável por executar a lógica de negócio desejada.
     * Caso o callback retorne algum valor diferente de null, o conteúdo
     * será retornado como resposta JSON. Caso contrário, será retornada
     * apenas uma mensagem de sucesso.
     *
     * Exceções do tipo EmsoftException são capturadas e convertidas em
     * respostas padronizadas de erro.
     *
     * @param Request $req
     *     Objeto de requisição HTTP.
     *
     * @param Response $res
     *     Objeto de resposta HTTP.
     *
     * @param callable(): mixed $callback
     *     Função responsável por executar a lógica específica da ação.
     *     Pode retornar qualquer valor ou null.
     *
     * @return Response
     *     Resposta HTTP padronizada em JSON.
     */
    protected function executar(
        Request $req,
        Response $res,
        callable $callback,
        bool $retornaMensagem = false
    ): Response {
        try {

            $body = $req->getMethod() === 'GET'
                ? $req->getQueryParams()
                : ($req->getParsedBody() ?? []);

            if (is_object($body)) {
                $body = (array) $body;
            }

            $dados = array_merge($body, $req->getAttributes() ?? []);
            $id = $dados['id'] ?? null;

            if ($req->getMethod() === 'POST' || $req->getMethod() === 'PUT' || $req->getMethod() === 'PATCH') {
                unset($dados['id']);
            }

            $resultado = $callback($dados, $id);

            if (is_array($resultado)) {
                $resultado = $this->encryptRecords($resultado);
            }

            if (is_array($resultado) && !$retornaMensagem) {
                // Detecta se o resultado já tem chave 'data' (coleção do CrudService::buscar)
                // ou se é um registro único que precisa ser envolvido em 'data'
                $isColecao = array_key_exists('data', $resultado);
                return $this->jsonRetornoConteudo($res, 200, $resultado, $isColecao);
            }

            return $this->jsonRetornoMensagem($res, 200, gettype($resultado) === 'boolean' ? null : (is_int($resultado) ? "Registro criado com sucesso (ID: {$resultado})" : $resultado));
        } catch (SystemException $e) {
            return $this->jsonRetornoMensagem(
                $res,
                $e->statusCode,
                $e->all($this->nomeParaExcecao),
                true
            );
        } catch (\Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() <= 599 ? $e->getCode() : 400;
            return $this->jsonRetornoMensagem(
                $res,
                $code,
                $e->getMessage(),
                true
            );
        }
    }
}
