# Valoriza

Aplicação de gestão financeira composta por:

- `front/`: frontend Vue 3 + Vite.
- `back-js/`: API Node.js + TypeScript + Fastify (runtime recomendado).

Os arquivos de configuração com credenciais e chaves são mantidos fora do repositório.

## Execução

Frontend:

```sh
cd front
npm install
npm run dev
```

API Node.js (recomendada):

```sh
cd back-js
cp .env.example .env
# configure SUPABASE_URL, SUPABASE_SERVICE_ROLE_KEY e JWT_SECRET
npm install
npm run dev
```

O `SUPABASE_SERVICE_ROLE_KEY` deve permanecer somente no servidor.

O schema inicial do Valoriza está em `supabase/migrations/20260729012000_initial_schema.sql` e foi aplicado ao projeto Supabase vinculado.
