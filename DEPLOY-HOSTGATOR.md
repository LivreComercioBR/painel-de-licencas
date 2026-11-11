# Publicação do Painel de Licenças na HostGator

## Caminhos desta instalação

- Document root público: `/home1/rona7916/licencas.rmcsites.com.br/`
- Configuração e chaves privadas: `/home1/rona7916/private/rmc-flow-licenses/`
- Configuração privada carregada por `bootstrap.php`: `/home1/rona7916/private/rmc-flow-licenses/license-config.php`

O repositório deve ser clonado em uma pasta separada, fora do document root e da pasta privada, por exemplo `/home1/rona7916/repos/painel-de-licencas`. O arquivo `.cpanel.yml` na raiz do repositório publica somente os arquivos de aplicação necessários: `index.php`, `subscriptions.php`, `bootstrap.php`, `.htaccess`, `api/` e `assets/`. Ele não copia `database/`, `private/`, documentação ou metadados Git.

## Preparação do cPanel

1. Faça uma cópia dos arquivos que estão atualmente em `/home1/rona7916/licencas.rmcsites.com.br/`.
2. No cPanel, abra **Git Version Control** e clone o repositório privado `git@github.com:LivreComercioBR/painel-de-licencas.git` para uma pasta fora do document root.
3. Se o clone pedir autenticação, autorize uma chave SSH do servidor cPanel no repositório GitHub com acesso de leitura. Não coloque senha, chave privada ou configuração do banco no GitHub.
4. Na área de gerenciamento do repositório, abra **Pull or Deploy** e use **Update from Remote** para buscar `main).
5. Confira que o commit contém `.cpanel.yml`; então use **Deploy HEAD Commit**. Isso executa as cópias declaradas no arquivo para o document root configurado.

A sincronização de Git e a publicação são ações distintas. Um commit no GitHub, sozinho, não atualiza o site.

## Banco e configuração privada

- A atualização do banco existente deve ser feita uma única vez pelo script `database/upgrade-v0.3.0-products-subscriptions.sql`, antes da publicação dos novos arquivos.
- Preserve o arquivo `/home1/rona7916/private/rmc-flow-licenses/license-config.php` e todas as chaves privadas que já existem nessa pasta.
- Nunca copie a pasta `private/` do repositório de exemplo para o document root.
- As chaves de assinatura por produto e os segredos HMAC devem ser configurados depois, apenas nos arquivos privados correspondentes.

## Depois da publicação

Acesse `https://licencas.rmcsites.com.br/`, entre no painel e confira o menu e a tela de produtos/assinaturas. Se aparecer erro, não rode a migration novamente; registre a mensagem e verifique os logs do PHP antes de qualquer segunda implantação.
