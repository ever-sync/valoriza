import { BASE_API } from '@/constants/api';

const routerConfig = {
  login: {
    name: "Login",
    path: "login",
    api: `${BASE_API}/login`,
    form: "",
  },
  'pessoas-fisicas': {
    title: 'Pessoa Física',
    listPath: '/pessoas-fisicas',
    getService: () => import('@/modules/PessoasFisicas/services/pessoasFisicas.service.js'),
    fetchMethod: 'getPessoaFisicaById',
    getForm: () => import('@/modules/PessoasFisicas/components/PessoasFisicasForm.vue')
  },
  'pessoas-juridicas': {
    title: 'Pessoa Jurídica',
    listPath: '/pessoas-juridicas',
    getService: () => import('@/modules/PessoasJuridicas/services/pessoasJuridicas.service.js'),
    fetchMethod: 'getPessoaJuridicaById',
    getForm: () => import('@/modules/PessoasJuridicas/components/PessoasJuridicasForm.vue')
  },
  'bancos': {
    title: 'Banco',
    listPath: '/bancos',
    getService: () => import('@/modules/Bancos/services/bancos.service.js'),
    fetchMethod: 'getBancoById',
    getForm: () => import('@/modules/Bancos/components/BancosForm.vue')
  },
  'usuarios': {
    title: 'Usuário',
    listPath: '/usuarios',
    getService: () => import('@/modules/Usuarios/services/usuarios.service.js'),
    fetchMethod: 'getUsuarioById',
    getForm: () => import('@/modules/Usuarios/components/UsuariosForm.vue')
  },
  'despesas': {
    title: 'Despesa',
    listPath: '/despesas',
    getService: () => import('@/modules/Despesas/services/despesas.service.js'),
    fetchMethod: 'getDespesa',
    getForm: () => import('@/modules/Despesas/components/DespesasForm.vue')
  },
  'contratos': {
    title: 'Contrato',
    listPath: '/contratos',
    getService: () => import('@/modules/Contratos/services/contratos.service.js'),
    fetchMethod: 'getContrato',
    getForm: () => import('@/modules/Contratos/components/ContratoForm.vue')
  },
  'receitas': {
    title: 'Receita',
    listPath: '/receitas',
    getService: () => import('@/modules/Receitas/services/receitas.service.js'),
    fetchMethod: 'getReceita',
    getForm: () => import('@/modules/Receitas/components/ReceitasForm.vue')
  },
  'configuracoes': {
    title: 'Configurações de Contrato',
    listPath: '/configuracoes',
    getService: () => import('@/modules/Configuracoes/services/configuracoesContratos.service.js'),
    fetchMethod: 'getConfiguracoes',
    getForm: () => import('@/modules/Configuracoes/components/ConfiguracoesContratosForm.vue')
  }
};

function getModuleByPath(path) {
  return Object.values(routerConfig).find(
    (mod) => mod.path === path
  );
}

function getModule(module) {
  return routerConfig?.[module];
}

export { routerConfig, getModuleByPath, getModule };
