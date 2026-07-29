# Valoriza

Aplicação de gestão financeira composta por:

- `front/`: frontend Vue 3 + Vite.
- `back/`: API PHP 8.2 sobre Lumynus.

Os arquivos de configuração com credenciais e chaves são mantidos fora do repositório.

## Execução

Frontend:

```sh
cd front
npm install
npm run dev
```

Backend: PHP 8.2+ e extensões `pdo`, `pdo_pgsql` e `mbstring` são necessários. Copie `back/.env.example` para o ambiente do servidor e configure as credenciais do PostgreSQL do Supabase. A API deve ser publicada em um host que execute PHP; o Supabase fornece o banco, não o runtime PHP.

O schema inicial do Valoriza está em `supabase/migrations/20260729012000_initial_schema.sql` e foi aplicado ao projeto Supabase vinculado.
