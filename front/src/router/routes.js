const routes = [
    {
        path: '/login',
        name: 'login',
        component: () => import('../views/LoginView.vue'),
        meta: { requiresAuth: false, template: false }
    },
    {
        path: '/',
        name: 'home',
        component: () => import('../views/HomeView.vue'),
        meta: { requiresAuth: true, template: true }
    },
    {
        path: '/fluxo-caixa',
        name: 'fluxo-caixa',
        component: () => import('../modules/FluxoCaixa/views/FluxoCaixaView.vue'),
        meta: { requiresAuth: true, template: true }
    },

    {
        path: '/relatorios',
        name: 'relatorios',
        component: () => import('../modules/Relatorios/views/RelatoriosView.vue'),
        meta: { requiresAuth: true, template: true }
    },
    {
        path: '/pessoas-juridicas',
        name: 'pessoas-juridicas',
        component: () => import('../modules/PessoasJuridicas/views/PessoasJuridicasList.vue'),
        meta: { requiresAuth: true, template: true }
    },
    {
        path: '/pessoas-fisicas',
        name: 'pessoas-fisicas',
        component: () => import('../modules/PessoasFisicas/views/PessoasFisicasList.vue'),
        meta: { requiresAuth: true, template: true }
    },
    {
        path: '/bancos',
        name: 'bancos',
        component: () => import('../modules/Bancos/views/BancosList.vue'),
        meta: { requiresAuth: true, template: true }
    },
    {
        path: '/usuarios',
        name: 'usuarios',
        component: () => import('../modules/Usuarios/views/UsuariosList.vue'),
        meta: { requiresAuth: true, template: true }
    },
    {
        path: '/configuracoes',
        name: 'configuracoes',
        component: () => import('../modules/Configuracoes/views/ConfiguracoesView.vue'),
        meta: { requiresAuth: true, template: true }
    },
    {
        path: '/despesas',
        name: 'despesas',
        component: () => import('../modules/Despesas/views/DespesasList.vue'),
        meta: { requiresAuth: true, template: true }
    },
    {
        path: '/receitas',
        name: 'receitas',
        component: () => import('../modules/Receitas/views/ReceitasList.vue'),
        meta: { requiresAuth: true, template: true }
    },
    {
        path: '/contratos',
        name: 'contratos',
        component: () => import('../modules/Contratos/views/ContratosList.vue'),
        meta: { requiresAuth: true, template: true }
    },
    // Rota de visualização de status - alterada para evitar conflito com /:module/novo
    {
        path: '/contratos/status/:id',
        name: 'contrato-status',
        component: () => import('../modules/Contratos/views/ContratoStatus.vue'),
        meta: { requiresAuth: true, template: true }
    },
    // Rotas Genéricas de Formulário (Criação e Edição) - Ficam no final para não "roubar" rotas específicas
    {
        path: '/:module/novo',
        name: 'module-new',
        component: () => import('../views/ModuleFormView.vue'),
        meta: { requiresAuth: true, template: true }
    },
    {
        path: '/:module/editar/:id',
        name: 'module-edit',
        component: () => import('../views/ModuleFormView.vue'),
        meta: { requiresAuth: true, template: true }
    }
];

export default routes;
