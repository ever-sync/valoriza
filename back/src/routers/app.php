<?php

use Lumynus\Framework\Route;

$i = ['url' => 'inserir', 'action' => 'inserir'];
$e = ['url' => 'editar/{id}', 'action' => 'editar'];
$b = ['url' => 'buscar?[string *]', 'action' => 'buscar'];
$a = ['url' => 'excluir/{id}', 'action' => 'excluir'];


Route::midd([
    \App\Middlewares\AuthMiddleware::class,
    \App\Middlewares\EntidadeIdDecrypt::class,
    \App\Middlewares\RoleMiddleware::class,
    \App\Middlewares\HoneyPot::class,
], [
    'handle',
    'handle',
    'handle',
    'handle',
], function () use ($i, $e, $b, $a) {


    // Dashboard
    Route::get("dashboard/stats", App\Controllers\Dashboard\DashboardController::class, 'getStats');

    // Fluxo de Caixa
    Route::get("fluxo-caixa/projetado", App\Controllers\FluxoCaixa\FluxoCaixaController::class, 'getFluxoCaixa');
    Route::get("fluxo-caixa/periodo?[string *]", App\Controllers\FluxoCaixa\FluxoCaixaController::class, 'getFluxoPeriodo');

    // Relatórios
    Route::get("relatorios/sumario-contratos", App\Controllers\Relatorio\RelatorioController::class, 'getSumarioContratos');
    Route::get("relatorios/sumario-clientes", App\Controllers\Relatorio\RelatorioController::class, 'getSumarioClientes');
    Route::get("relatorios/contabil-recebimentos?[string *]", App\Controllers\Relatorio\RelatorioController::class, 'getContabilRecebimentos');
    Route::get("relatorios/contabil-pagamentos?[string *]", App\Controllers\Relatorio\RelatorioController::class, 'getContabilPagamentos');

    // Pessoa Juridica
    Route::get("pessoa-juridica/{$b['url']}", App\Controllers\PessoaJuridica\PessoaJuridicaController::class, $b['action']);
    Route::post("pessoa-juridica/{$i['url']}", App\Controllers\PessoaJuridica\PessoaJuridicaController::class, $i['action']);
    Route::put("pessoa-juridica/{$e['url']}", App\Controllers\PessoaJuridica\PessoaJuridicaController::class, $e['action']);
    Route::delete("pessoa-juridica/{$a['url']}", App\Controllers\PessoaJuridica\PessoaJuridicaController::class, $a['action']);

    // Pessoa Fisica
    Route::get("pessoa-fisica/{$b['url']}", App\Controllers\PessoaFisica\PessoaFisicaController::class, $b['action']);
    Route::post("pessoa-fisica/{$i['url']}", App\Controllers\PessoaFisica\PessoaFisicaController::class, $i['action']);
    Route::put("pessoa-fisica/{$e['url']}", App\Controllers\PessoaFisica\PessoaFisicaController::class, $e['action']);
    Route::delete("pessoa-fisica/{$a['url']}", App\Controllers\PessoaFisica\PessoaFisicaController::class, $a['action']);

    // Empresa
    Route::get("empresa/{$b['url']}", App\Controllers\Empresa\EmpresaController::class, $b['action']);
    Route::post("empresa/{$i['url']}", App\Controllers\Empresa\EmpresaController::class, $i['action']);
    Route::put("empresa/{$e['url']}", App\Controllers\Empresa\EmpresaController::class, $e['action']);
    Route::delete("empresa/{$a['url']}", App\Controllers\Empresa\EmpresaController::class, $a['action']);

    // Bancos
    Route::get("banco/{$b['url']}", App\Controllers\Banco\BancoController::class, $b['action']);
    Route::post("banco/{$i['url']}", App\Controllers\Banco\BancoController::class, $i['action']);
    Route::put("banco/{$e['url']}", App\Controllers\Banco\BancoController::class, $e['action']);
    Route::delete("banco/{$a['url']}", App\Controllers\Banco\BancoController::class, $a['action']);

    // Usuario
    Route::get("usuario/{$b['url']}", App\Controllers\Usuario\UsuarioController::class, $b['action']);
    Route::post("usuario/{$i['url']}", App\Controllers\Usuario\UsuarioController::class, $i['action']);
    Route::put("usuario/{$e['url']}", App\Controllers\Usuario\UsuarioController::class, $e['action']);
    Route::delete("usuario/{$a['url']}", App\Controllers\Usuario\UsuarioController::class, $a['action']);

    // Auth (Login)
    Route::post("auth/login", App\Controllers\Auth\AuthController::class, 'login');
    Route::get("auth/me", App\Controllers\Auth\AuthController::class, 'me');
    Route::get("auth/logout", App\Controllers\Auth\AuthController::class, 'logout');
    Route::post("auth/logout", App\Controllers\Auth\AuthController::class, 'logout'); // Em caso de precisar de POST

    // Configuracoes de Contratos
    Route::get("configuracoes-contratos/{$b['url']}", App\Controllers\ConfiguracoesContratos\ConfiguracoesContratosController::class, $b['action']);
    Route::post("configuracoes-contratos/{$i['url']}", App\Controllers\ConfiguracoesContratos\ConfiguracoesContratosController::class, $i['action']);
    Route::put("configuracoes-contratos/{$e['url']}", App\Controllers\ConfiguracoesContratos\ConfiguracoesContratosController::class, $e['action']);
    Route::delete("configuracoes-contratos/{$a['url']}", App\Controllers\ConfiguracoesContratos\ConfiguracoesContratosController::class, $a['action']);

    // Receitas
    Route::get("receita/{$b['url']}", App\Controllers\Receita\ReceitaController::class, $b['action']);
    Route::get("receita/buscar/{id}", App\Controllers\Receita\ReceitaController::class, $b['action']);
    Route::post("receita/{$i['url']}", App\Controllers\Receita\ReceitaController::class, $i['action']);
    Route::put("receita/{$e['url']}", App\Controllers\Receita\ReceitaController::class, $e['action']);
    Route::delete("receita/{$a['url']}", App\Controllers\Receita\ReceitaController::class, $a['action']);
    Route::post("receita/{id}/prorrogar", App\Controllers\Receita\ReceitaController::class, 'prorrogar');
    Route::post("receita/{id}/simular-prorrogacao", App\Controllers\Receita\ReceitaController::class, 'simularProrrogacao');
    Route::get("receita/{id}/prorrogacoes", App\Controllers\Receita\ReceitaController::class, 'consultarProrrogacoes');
    Route::post("receita/{id}/pagar-parcial", App\Controllers\Receita\ReceitaController::class, 'pagarParcial');
    Route::post("receita/{id}/pagar-carencia", App\Controllers\Receita\ReceitaController::class, 'pagarCarencia');
    Route::post("receita/{id}/quitar-integral", App\Controllers\Receita\ReceitaController::class, 'quitarIntegral');
    Route::post("receita/{id}/calcular-encargos", App\Controllers\Receita\ReceitaController::class, 'calcularEncargos');
    Route::get("receita/{id}/contrato", App\Controllers\Receita\ReceitaController::class, 'consultarContrato');

    // Despesas
    Route::get("despesa/{$b['url']}", App\Controllers\Despesa\DespesaController::class, $b['action']);
    Route::get("despesa/buscar/{id}", App\Controllers\Despesa\DespesaController::class, $b['action']);
    Route::post("despesa/{$i['url']}", App\Controllers\Despesa\DespesaController::class, $i['action']);
    Route::put("despesa/{$e['url']}", App\Controllers\Despesa\DespesaController::class, $e['action']);
    Route::delete("despesa/{$a['url']}", App\Controllers\Despesa\DespesaController::class, $a['action']);

    // Contratos
    Route::get("contrato/{$b['url']}", App\Controllers\Contrato\ContratoController::class, $b['action']);
    Route::get("contrato/buscar/{id}", App\Controllers\Contrato\ContratoController::class, $b['action']);
    Route::get("contrato/garantias/{id}", App\Controllers\Contrato\ContratoController::class, 'consultarGarantias');
    Route::post("contrato/{id}/lancar-parcelas", App\Controllers\Contrato\ContratoController::class, 'lancarParcelas');
    Route::get("contrato/{id}/parcelas", App\Controllers\Contrato\ContratoController::class, 'consultarParcelas');
    Route::post("contrato/simular", App\Controllers\Contrato\ContratoController::class, 'simular');
    Route::post("contrato/{id}/crdc", App\Controllers\Contrato\ContratoController::class, 'enviarCRDC');
    Route::post("contrato/{$i['url']}", App\Controllers\Contrato\ContratoController::class, $i['action']);

    Route::put("contrato/{$e['url']}", App\Controllers\Contrato\ContratoController::class, $a['action']);
    Route::delete("contrato/{$a['url']}", App\Controllers\Contrato\ContratoController::class, $e['action']);
    // Consultas Externas
    Route::get("consulta/cep/{cep}", App\Controllers\Integracao\ConsultaController::class, 'consultarCEP');
    Route::get("consulta/cnpj/{cnpj}", App\Controllers\Integracao\ConsultaController::class, 'consultarCNPJ');
});
