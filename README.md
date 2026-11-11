# Painel de Licenças — RMC Flow Oficina Pro

Painel privado da RMC Sites para cadastrar vendas da Edição Local, gerar códigos de ativação e produzir licenças assinadas para instalações online ou offline.

## Publicação na HostGator

1. Crie um subdomínio, por exemplo `licencas.rmcsites.com.br`.
2. Extraia **o conteúdo desta pasta** diretamente na raiz pública desse subdomínio. Não use uma pasta `dist`.
3. Crie um banco MySQL exclusivo e importe `database/schema.sql` pelo phpMyAdmin.
4. Copie `private/license-config.php.example` para uma pasta privada, por exemplo:
   `/home1/rona7916/private/rmc-flow-licenses/license-config.php`
5. Preencha os dados do banco, gere a senha com `password_hash` e informe o caminho da chave privada RSA.
6. Gere o par de chaves RSA em computador seguro. A chave privada deve ficar apenas na pasta privada; a pública será incorporada ao instalador desktop antes da distribuição.
7. Ajuste `bootstrap.php` com o caminho real do arquivo privado.

## Fluxo comercial

1. Venda confirmada: cadastre nome, e-mail e CPF/CNPJ do comprador.
2. O painel gera um código de ativação permanente.
3. O cliente instala o RMC Flow Oficina Pro e informa o código de solicitação gerado no computador dele.
4. No painel, abra a licença, registre esse código de solicitação e gere a licença assinada.
5. Envie o bloco gerado por e-mail ao cliente. Ele poderá colá-lo no programa mesmo em computador sem internet.

## Segurança

- Este painel é para uso exclusivo da RMC Sites.
- Use HTTPS, senha forte e mantenha a chave privada fora de `public_html`.
- Faça backup do banco e da chave privada em local seguro. A perda da chave impede emitir novas ativações compatíveis.
- Não distribua este pacote com um arquivo `license-config.php` preenchido ou chave privada.
