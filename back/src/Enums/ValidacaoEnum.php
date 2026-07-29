<?php

declare(strict_types=1);

namespace App\Enums;

enum ValidacaoEnum: string
{

    case CAMPOS_OBRIGATORIOS_VAZIOS = 'Existem campos obrigatórios vazios.';
    case CAMPOS_INVALIDOS = 'Existem campos com valores inválidos ou ausentes.';
    case CRITERIO_SENHA_INVALIDO = 'A senha não atendeu aos requisitos. Favor, revisar.';
    case EMAIL_INVALIDO = 'O e-mail informado é inválido.';
    case EMAIL_NAO_ENCONTRADO = 'Não foi possível encontrar o e-mail informado.';
    case EMAIL_NAO_ENVIADO = 'Não foi possível enviar a mensagem. Tente novamente mais tarde.';
    case TELEFONE_INVALIDO = 'O telefone informado é inválido.';
    case TELEFONE_EMAIL_VAZIOS = 'Ao menos um dos dois campos (telefone e email), precisam estar preenchidos.';

    case DATA_FORMATO_INVALIDO = 'Data no formato inválido. Use o formato DD/MM/AAAA.';
    case DATA_NASCIMENTO_INVALIDA = 'Data de nascimento no formato inválido. Use o formato DD/MM/AAAA.';
    case DATA_NASCIMENTO_FUTURA = 'A data de nascimento não pode ser maior que a data atual.';
    case DATA_FUTURA = 'A data informada não pode ser maior que a data atual.';
    case DATA_VENCIMENTO_MENOR_QUE_DATA_ATUAL = 'A data de vencimento não pode ser menor que a data atual.';
    case DATA_LIMITE_PAGAMENTO_MENOR_QUE_DATA_VENCIMENTO = 'A data limite de pagamento não pode ser menor que a data de vencimento.';
    case DATA_PAGAMENTO_MAIOR_QUE_DATA_LIMITE = 'A data de pagamento não pode ser maior que a data limite da parcela.';

    case CNPJ_FORMATO_INVALIDO = 'Cnpj informado é inválido. Favor, revisar.';
    case CEP_INVALIDO = 'CEP informado é inválido. Favor, revisar.';
    case PROCESSO_FALHO = 'Não foi possível processar a solicitação neste momento. Tente mais tarde!';
    case VALOR_QUANTIDADE_INVALIDO = 'O valor informado para a quantidade é inválido.';
    case VALOR_DECIMAL_INVALIDO = 'O valor decimal informado é inválido.';
    case SAIDA_ITEM_SEM_ESTOQUE = 'Não é possível realizar uma saída de estoque para um item que não possui estoque registrado no depósito selecionado.';
    case SAIDA_MAIOR_QUE_ESTOQUE = 'A quantidade de saída é maior que o estoque disponível.';
    case MOVIMENTACAO_TIPO_INVALIDO = 'O tipo de movimentação informado é inválido.';
    case OBSERVACAO_VAZIA = 'A observação não pode ser vazia.';
    case DEPOSITO_NAO_ENCONTRADO = 'Não foi possível encontrar o depósito informado.';
}
