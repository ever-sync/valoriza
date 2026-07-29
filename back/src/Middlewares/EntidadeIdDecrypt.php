<?php

namespace App\Middlewares;

use App\Helpers\EntityIdEncryptorMiddHelper;
use App\Helpers\RespostaHelper;
use Lumynus\Framework\AbstractMiddleware;
use Lumynus\Framework\Config;
use Lumynus\Http\Contracts\Request;
use Lumynus\Http\Contracts\Response;
use App\Helpers\EntidadeIdEncryptHelper;

class EntidadeIdDecrypt extends AbstractMiddleware
{

    use RespostaHelper;
    use EntidadeIdEncryptHelper;

    public function handle(Request $req, Response $res)
    {

        if (Config::modeProduction() === false && $req->getHeaders()['user-agent'] == 'PostmanRuntime/7.51.1') {
            $req->get('empresa_id') && $req->setAttribute('empresa_id', $req->get('empresa_id'));
            $req->get('id') && $req->setAttribute('id', $req->get('id'));
            return true;
        }

        try {

            $dadosQuery = $req->getQueryParams();
            if ($dadosQuery) {
                $dadosDescriptografados = $this->decryptIds($dadosQuery);

                foreach ($dadosDescriptografados as $chave => $valor) {
                    $req->setAttribute($chave, $valor);
                }
            }

            $dadosBody = $req->getParsedBody();
            if ($dadosBody) {
                $dadosDescriptografados = $this->decryptIds($dadosBody);

                foreach ($dadosDescriptografados as $chave => $valor) {
                    $req->setAttribute($chave, $valor);
                }
            }
        } catch (\Throwable $th) {
            return $this->jsonRetornoMensagem(
                $res,
                400,
                Config::modeProduction() === true
                    ? 'Não foi possível processar sua solicitação no momento. Por favor, tente novamente mais tarde'
                    : $th->getMessage(),
                true
            );
        }
    }
}
