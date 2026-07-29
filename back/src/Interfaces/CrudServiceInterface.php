<?php

namespace App\Interfaces;

interface CrudServiceInterface
{
    public function cadastrar(array $dados): int;
    public function consultar(array $filtros, string $ordenacao, array $paginacao): array;
    public function atualizar(array $dados, int $id): bool;
    public function excluir(int $id): bool;
}
