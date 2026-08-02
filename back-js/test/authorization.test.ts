import { test, describe } from 'node:test'
import assert from 'node:assert/strict'
import { iniciarApp, usuario } from './helpers/app.js'

const { app, fake, cookieDe, cabecalhosDe } = await iniciarApp({
  tbl_usuarios: [{ id: 10, empresa_id: 1, nome_completo: 'Fulano', email: 'a@b.c', senha: '$2a$12$hash', perfil_acesso: 'operador' }],
  tbl_empresas: [{ id: 1, razao_social: 'Empresa A' }],
  tbl_configuracoes_contratos: [
    { id: 1, empresa_id: 1, taxa_juros_avalista: 2, qtd_maxima_parcelas: 12, crdc_usuario: 'integrador', crdc_senha: 'segredo-crdc' },
  ],
  tbl_bancos: [{ id: 1, empresa_id: 1, banco: 'Banco A' }],
})

// O front envia cookie e Authorization juntos; escritas dependem do cabeçalho.
const admin = await cabecalhosDe(usuario('administrador'))
const contador = await cabecalhosDe(usuario('contador'))
const operador = await cabecalhosDe(usuario('operador'))
const soCookieAdmin = { cookie: await cookieDe(usuario('administrador')) }

type Cabecalhos = Record<string, string>

const chamar = (method: 'GET' | 'POST' | 'PUT' | 'DELETE', url: string, headers: Cabecalhos = {}) =>
  app.inject({ method, url, headers, payload: method === 'GET' || method === 'DELETE' ? undefined : {} })

describe('sessão', () => {
  test('rota protegida sem cookie responde 401', async () => {
    for (const url of ['/usuario/buscar', '/banco/buscar', '/contrato/buscar', '/dashboard/stats']) {
      assert.equal((await chamar('GET', url)).statusCode, 401, url)
    }
  })

  test('cookie com assinatura inválida responde 401', async () => {
    const resposta = await chamar('GET', '/banco/buscar', { cookie: 'valoriza_session=nao.e.um.jwt' })
    assert.equal(resposta.statusCode, 401)
  })

  test('autenticação via cabeçalho Authorization Bearer funciona', async () => {
    const resposta = await app.inject({ method: 'GET', url: '/banco/buscar', headers: { authorization: admin.authorization } })
    assert.equal(resposta.statusCode, 200)
  })

  test('leitura ainda aceita apenas o cookie', async () => {
    assert.equal((await chamar('GET', '/banco/buscar', soCookieAdmin)).statusCode, 200)
  })
})

describe('leitura restrita a administrador', () => {
  test('somente admin lê usuários e empresas', async () => {
    for (const url of ['/usuario/buscar', '/empresa/buscar']) {
      assert.equal((await chamar('GET', url, admin)).statusCode, 200, `admin ${url}`)
      assert.equal((await chamar('GET', url, contador)).statusCode, 403, `contador ${url}`)
      assert.equal((await chamar('GET', url, operador)).statusCode, 403, `operador ${url}`)
    }
  })

  test('configurações de contrato seguem legíveis por qualquer perfil', async () => {
    // Regressão: ContratoForm e useContratoStatus carregam taxas e limites por aqui.
    // Restringir esta leitura a admin quebra a criação de contratos.
    for (const cookie of [admin, contador, operador]) {
      assert.equal((await chamar('GET', '/configuracoes-contratos/buscar', cookie)).statusCode, 200)
    }
  })
})

describe('escrita restrita a administrador', () => {
  test('somente admin escreve em usuário, empresa e configurações', async () => {
    const rotas: Array<['POST' | 'PUT' | 'DELETE', string]> = [
      ['POST', '/usuario/inserir'],
      ['PUT', '/usuario/editar/1'],
      ['DELETE', '/usuario/excluir/1'],
      ['POST', '/configuracoes-contratos/inserir'],
      ['PUT', '/configuracoes-contratos/editar/1'],
    ]
    for (const [metodo, url] of rotas) {
      assert.equal((await chamar(metodo, url, contador)).statusCode, 403, `contador ${metodo} ${url}`)
      assert.equal((await chamar(metodo, url, operador)).statusCode, 403, `operador ${metodo} ${url}`)
      assert.notEqual((await chamar(metodo, url, admin)).statusCode, 403, `admin ${metodo} ${url}`)
    }
  })

  test('recursos operacionais aceitam perfis não-admin', async () => {
    assert.equal((await chamar('GET', '/banco/buscar', operador)).statusCode, 200)
    assert.notEqual((await chamar('POST', '/banco/inserir', operador)).statusCode, 403)
  })
})

describe('campos sensíveis não saem do backend', () => {
  test('listagem de usuários não expõe o hash de senha', async () => {
    const resposta = await chamar('GET', '/usuario/buscar', admin)
    const corpo = resposta.json()
    assert.equal(resposta.statusCode, 200)
    assert.ok(corpo.data.length > 0, 'esperava ao menos uma linha para o teste ter valor')
    for (const linha of corpo.data) assert.ok(!('senha' in linha), 'senha não pode trafegar')
    assert.ok(!resposta.body.includes('$2a$12$hash'))
  })

  test('escrita de usuário também não devolve o hash', async () => {
    for (const [metodo, url] of [['POST', '/usuario/inserir'], ['PUT', '/usuario/editar/1'], ['DELETE', '/usuario/excluir/1']] as const) {
      const resposta = await chamar(metodo, url, admin)
      assert.ok(!resposta.body.includes('senha'), `${metodo} ${url} devolveu senha`)
    }
  })

  test('senha do CRDC nunca é devolvida, nem para admin', async () => {
    for (const cookie of [admin, contador, operador]) {
      const resposta = await chamar('GET', '/configuracoes-contratos/buscar', cookie)
      assert.ok(!resposta.body.includes('segredo-crdc'), 'crdc_senha vazou')
    }
  })

  test('usuário do CRDC só aparece para admin', async () => {
    const comoAdmin = await chamar('GET', '/configuracoes-contratos/buscar', admin)
    assert.ok(comoAdmin.body.includes('integrador'), 'admin precisa enxergar crdc_usuario para editar')

    for (const cookie of [contador, operador]) {
      const resposta = await chamar('GET', '/configuracoes-contratos/buscar', cookie)
      assert.ok(!resposta.body.includes('integrador'), 'crdc_usuario vazou para perfil não-admin')
    }
  })

  test('taxas e limites continuam disponíveis para perfil não-admin', async () => {
    const corpo = (await chamar('GET', '/configuracoes-contratos/buscar', operador)).json()
    assert.equal(corpo.data[0].qtd_maxima_parcelas, 12)
    assert.equal(corpo.data[0].taxa_juros_avalista, 2)
  })
})

describe('campos somente-escrita preservam o valor guardado', () => {
  test('senha em branco não é gravada', async () => {
    fake.limpar()
    await app.inject({ method: 'PUT', url: '/usuario/editar/1', headers: admin, payload: { nome_completo: 'Novo Nome', senha: '   ' } })
    const escrita = fake.consultas.find((c) => c.operacao === 'update')
    assert.ok(escrita, 'esperava um update')
    assert.ok(!('senha' in (escrita.payload as Record<string, unknown>)), 'senha em branco não pode sobrescrever o hash')
  })

  test('senha preenchida é gravada com hash, nunca em texto puro', async () => {
    fake.limpar()
    await app.inject({ method: 'PUT', url: '/usuario/editar/1', headers: admin, payload: { senha: 'NovaSenha123' } })
    const payload = fake.consultas.find((c) => c.operacao === 'update')?.payload as Record<string, unknown>
    assert.notEqual(payload.senha, 'NovaSenha123')
    assert.match(String(payload.senha), /^\$2[aby]\$/)
  })

  test('crdc_senha em branco não apaga a credencial da registradora', async () => {
    // O formulário reenvia o campo vazio porque a leitura deixou de devolvê-lo.
    fake.limpar()
    await app.inject({
      method: 'PUT',
      url: '/configuracoes-contratos/editar/1',
      headers: admin,
      payload: { crdc_usuario: 'integrador', crdc_senha: '', taxa_juros_avalista: 3 },
    })
    const payload = fake.consultas.find((c) => c.operacao === 'update')?.payload as Record<string, unknown>
    assert.ok(!('crdc_senha' in payload), 'string vazia deve significar "manter o valor atual"')
    assert.equal(payload.taxa_juros_avalista, 3, 'os demais campos seguem sendo gravados')
  })
})

describe('CSRF: escritas exigem o cabeçalho Authorization', () => {
  // O cookie de sessão é SameSite=None porque o front roda em outro domínio, então o
  // navegador o anexa em requisições disparadas por sites de terceiros. O cabeçalho
  // Authorization não é anexado automaticamente e não pode ser definido cross-site
  // sem preflight — que o CORS recusa. Por isso ele é o que autoriza a escrita.
  const comoSiteExterno = (url: string, headers: Record<string, string> = {}) =>
    app.inject({ method: 'POST', url, headers: { ...soCookieAdmin, ...headers } })

  test('POST sem corpo e sem content-type não passa só com o cookie', async () => {
    assert.equal((await comoSiteExterno('/contrato/7/crdc')).statusCode, 401)
  })

  test('POST text/plain não passa só com o cookie', async () => {
    // text/plain é "simple request": vai sem preflight a partir de qualquer página.
    const resposta = await app.inject({
      method: 'POST',
      url: '/receita/3/pagar-carencia',
      headers: { ...soCookieAdmin, 'content-type': 'text/plain' },
      payload: '{}',
    })
    assert.equal(resposta.statusCode, 401)
  })

  test('as demais escritas também recusam autenticação por cookie', async () => {
    for (const [metodo, url] of [['POST', '/banco/inserir'], ['PUT', '/banco/editar/1'], ['DELETE', '/banco/excluir/1']] as const) {
      assert.equal((await chamar(metodo, url, soCookieAdmin)).statusCode, 401, `${metodo} ${url}`)
    }
  })

  test('a mesma escrita passa quando o token vai no cabeçalho', async () => {
    assert.notEqual((await chamar('POST', '/banco/inserir', admin)).statusCode, 401)
  })
})

describe('perfis de acesso', () => {
  // Perfil fora da lista canônica gera usuário que loga e não vê nenhum menu, porque
  // todo item de front/src/constants/navigation.js declara `roles`.
  const salvarPerfil = (perfil: unknown) =>
    app.inject({ method: 'POST', url: '/usuario/inserir', headers: admin, payload: { nome_completo: 'X', email: 'x@y.z', senha: 'Senha123', perfil_acesso: perfil } })

  const perfilGravado = () => (fake.consultas.find((c) => c.operacao === 'insert')?.payload as Record<string, unknown>)?.perfil_acesso

  for (const perfil of ['administrador', 'contador', 'operador']) {
    test(`aceita o perfil canônico ${perfil}`, async () => {
      fake.limpar()
      assert.equal((await salvarPerfil(perfil)).statusCode, 201)
      assert.equal(perfilGravado(), perfil)
    })
  }

  test('alias legado "admin" continua virando administrador', async () => {
    fake.limpar()
    assert.equal((await salvarPerfil('admin')).statusCode, 201)
    assert.equal(perfilGravado(), 'administrador')
  })

  test('perfis que nenhuma tela reconhece são recusados', async () => {
    // O formulário antigo gravava estes dois e produzia barra lateral vazia.
    for (const perfil of ['usuario', 'gerente', 'root', '']) {
      fake.limpar()
      const resposta = await salvarPerfil(perfil)
      assert.equal(resposta.statusCode, 400, `perfil ${perfil} deveria ser recusado`)
      assert.equal(fake.consultas.filter((c) => c.operacao === 'insert').length, 0, `perfil ${perfil} não pode ser gravado`)
    }
  })
})
