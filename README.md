# Painel Central de Licenças e Assinaturas — RMC Sites

Painel privado para administrar produtos RMC Sites, licenças permanentes e assinaturas. A edição atual mantém o fluxo de ativação permanente da Oficina Pro Local e acrescenta catálogo de produtos e planos, registro de assinaturas sincronizadas e um endpoint autenticado para receber snapshots dos produtos conectados.

## Publicação na HostGator

### Instalação nova

1. Crie o subdomínio e extraia o conteúdo desta pasta na raiz pública.
2. Crie um banco MySQL e importe `database/schema.sql`.
3. Copie `private/license-config.php.example` para uma pasta privada, como `/home1/rona7916/private/rmc-flow-licenses/license-config.php`.
4. Preencha banco, hash de senha de administrador e caminho da chave RSA. Não coloque chaves privadas ou segredos em `public_html`.
5. Configure `subscription_event_secrets` com um segredo aleatório forte para cada produto conectado. Mantenha a mesma cópia apenas na configuração privada do produto de origem.
6. Ajuste `bootstrap.php` para apontar ao arquivo privado real e mantenha HTTPS ativado.

### Atualização de uma instalação existente

1. Faça backup do banco, dos arquivos e da chave RSA.
2. Aplique **uma única vez** `database/upgrade-v0.3.0-products-subscriptions.sql` no banco existente.
3. Copie os novos arquivos do painel e o exemplo atualizado de configuração, preservando o arquivo privado e a chave atuais.
4. Acrescente os segredos HMAC privados apenas depois de configurar o produto de origem para assinar as chamadas de sincronização.

Não importe o `schema.sql` sobre um banco já existente: use o script de atualização.

## Produtos e modelos

A tela `subscriptions.php`, acessível pelo menu do painel, permite cadastrar produtos permanentes ou por assinatura, ativar/desativar produtos, cadastrar planos e consultar assinaturas recebidas dos sistemas integrados. Os planos aceitam periodicidade configurável, valores inicial e de renovação e dias de tolerância.

A instalação inicial cadastra a Oficina Pro Local, o Comércio Local e a Oficina Pro Cloud. Os planos Cloud são semeados com os valores existentes no código: Essencial (R$ 149,90 inicial; R$ 80,00 na renovação) e Profissional (R$ 179,90 inicial; R$ 80,00 na renovação), ciclo anual e tolerância de 30 dias.

O formulário de vendas permite selecionar qualquer produto permanente ativo. A declaração `product_claim` e os recursos JSON do catálogo são incluídos no bloco assinado. A chave antiga em `private_key_path` continua exclusiva da Oficina Pro Local; configure uma chave privada separada em `product_private_key_paths` para cada outro produto que emite licenças. A chave pública correspondente precisa estar incorporada ao aplicativo daquele produto. Produtos por assinatura não recebem bloco permanente; seu status chega pela sincronização autenticada.

## Sincronização segura de assinaturas

O endpoint `POST /api/subscription-events.php` recebe snapshots autenticados de um produto. O sistema de origem deve primeiro validar o pagamento junto ao provedor e depois enviar o estado atual da assinatura ao painel. O endpoint não recebe nem valida notificações originais do Mercado Pago; esse fluxo continua no sistema de origem.

Cabeçalhos obrigatórios:

- `X-RMC-Product-Code`: código do produto cadastrado no painel.
- `X-RMC-Timestamp`: timestamp Unix em segundos; a chamada expira após cinco minutos.
- `X-RMC-Event-Id`: identificador estável do evento, único por produto/provedor.
- `X-RMC-Signature`: HMAC-SHA256 hexadecimal.

Texto assinado, usando quebras LF:

```text
{product_code}
{timestamp}
{event_id}
{sha256_hex_do_corpo_bruto}
```

Exemplo de corpo:

```json
{
  "event_type": "subscription.snapshot",
  "occurred_at": "2026-11-11T15:00:00Z",
  "subscription": {
    "external_reference": "company:123",
    "customer_name": "Cliente Exemplo",
    "customer_email": "cliente@example.com",
    "customer_document": "00000000000",
    "plan_code": "professional",
    "status": "active",
    "starts_at": "2026-11-11T15:00:00Z",
    "expires_at": "2027-11-11T15:00:00Z",
    "grace_until": "2027-12-11T15:00:00Z",
    "initial_amount": 179.90,
    "renewal_amount": 80.00,
    "currency": "BRL",
    "provider": "mercado_pago",
    "payment_status": "approved",
    "last_payment_reference": "payment-id",
    "last_payment_amount": 179.90,
    "paid_at": "2026-11-11T15:00:00Z"
  }
}
```

O identificador do evento é deduplicado; a referência do pagamento pode aparecer em vários eventos, por exemplo quando o mesmo pagamento muda de pendente para aprovado. O painel armazena o hash do corpo para auditoria, sem guardar o payload bruto do webhook. Snapshots antigos não sobrescrevem estados mais recentes. O sistema de origem deve enviar um novo snapshot após cada confirmação, renovação ou cancelamento.

## Fluxo comercial legado da Oficina Pro Local

1. Cadastre a venda confirmada no painel.
2. O painel gera um código de ativação permanente.
3. Abra a licença, informe o código de solicitação do computador e gere o bloco assinado.
4. Envie o bloco ao cliente para ativação, inclusive em computador sem internet.

A chave privada RSA fica somente na pasta privada. A chave global existente preserva as ativações legadas da Oficina Pro Local. Para cada outro produto permanente, gere um par RSA próprio em ambiente seguro, configure o caminho privado no arquivo privado e inclua somente a chave pública correspondente no aplicativo. Não altere o produto declarado nem o formato das licenças já emitidas.

## Segurança e limites desta versão

- Este painel é privado e deve usar HTTPS, senha forte, backups e permissões restritas.
- Nunca armazene Access Tokens do Mercado Pago ou segredos HMAC no repositório.
- A sincronização central ainda precisa ser habilitada no sistema de origem. Até lá, a tabela de assinaturas permanece vazia.
- A cobrança e o webhook existentes do Oficina Cloud não são alterados por esta atualização; a integração com o endpoint central é uma etapa posterior.
