# VisionAccess - atualização administrativa

Este pacote foi preparado sobre a estrutura real dos arquivos enviados do projeto.

## O que foi analisado

Foram revisados os arquivos de autenticação, cadastro, login, dashboard, triagem, processamento, resultado, histórico, páginas institucionais, header/footer, CSS/JavaScript, configuração, conexão com banco, dump SQL e a documentação PDF do projeto.

### Pontos encontrados que afetam diretamente o painel administrativo

1. O questionário atual é carregado diretamente das tabelas `pergunta`, `categoria_triagem` e `opcao_resposta`. A única proteção de edição existente é `pergunta.ativo`; não existe versão de questionário.
2. O prompt usado pelo Gemini está fixo dentro de `php/processar_triagem.php`, portanto uma mudança exige alterar código e publicar o arquivo novamente.
3. O código atual envia somente `descricao_texto` para a IA. A documentação do projeto descreve o envio do relato e das respostas do questionário. A nova implementação passa a enviar os dois, de forma estruturada.
4. A pontuação atual compara a soma bruta por categoria. Isso funciona enquanto todas as categorias tiverem a mesma quantidade de perguntas e a mesma escala. Um administrador adicionando perguntas em uma única categoria criaria viés. A nova implementação registra a pontuação máxima e compara o percentual normalizado.
5. O processamento atual abre uma transação no banco antes de chamar a API externa. A nova implementação chama a IA antes da transação de gravação, evitando manter uma transação aberta durante uma chamada de rede.
6. Não existe perfil de administrador na tabela `usuario`. A migração adiciona `perfil` com padrão `usuario` e o backend valida o perfil diretamente no banco em cada página administrativa.
7. A documentação já lista "Página de Administrador" no plano de melhorias, então o módulo é coerente com a evolução prevista do projeto.
8. O dump SQL enviado está inconsistente na definição de `resultado`: o `CREATE TABLE resultado` termina em `resposta_ia`, enquanto os `INSERTs` e o PHP utilizam também `classificacao_interna`, `pontuacao_interna`, `nivel_risco`, `resultado_final`, `descricao_resultado` e `data_resultado`. Se o site atual funciona, a tabela no banco online provavelmente possui esses campos, mas o arquivo exportado não deve ser usado como restauração confiável sem correção.
9. Os arquivos enviados contêm chave de API e credenciais de banco diretamente no código. Troque essas credenciais antes de publicar/compartilhar o repositório e evite versioná-las em Git.

## Estrutura adicionada

```text
main/
  admin.php
  admin_questionario.php
  admin_pergunta.php
  admin_prompt.php

includes/
  admin_auth.php
  csrf.php
  header.php                 (substituir)

php/
  auth.php                   (substituir)
  processar_triagem.php      (substituir)
  admin_questionario_action.php
  admin_prompt_action.php

assets/
  css/style.css              (substituir)
  js/admin.js

database/
  00_preflight.sql
  01_admin_migration.sql
```

Também devem ser substituídos na raiz:

```text
triagem.php
resultado.php
historico.php
como_funciona.php
```

## Fluxo do questionário

- A versão atualmente publicada fica com status `ativo` e não é editada.
- O administrador clica em **Criar nova versão**.
- O sistema clona perguntas e opções da versão ativa para um `rascunho`.
- O administrador pode criar, editar, ordenar, ativar/desativar e excluir perguntas somente no rascunho.
- Ao publicar, o backend valida:
  - pelo menos uma pergunta ativa em cada categoria;
  - pelo menos duas opções ativas por pergunta;
  - pontuação mínima igual a zero;
  - pontuação máxima maior que zero.
- A versão anterior vira `arquivado` e continua disponível para rastreabilidade.
- Cada nova triagem grava `id_questionario_versao`.

Triagens antigas ficam com `id_questionario_versao = NULL`, porque o banco antigo não registrava versão e não é seguro inventar retrospectivamente qual versão foi utilizada.

## Fluxo do prompt

- `prompt_agente` armazena versões imutáveis das instruções administrativas.
- Salvar na tela administrativa cria uma nova versão e a ativa.
- O backend mantém um contrato técnico fixo para o JSON de saída. Assim, o administrador pode ajustar as instruções de análise sem remover campos obrigatórios que `processar_triagem.php` precisa interpretar.
- `resultado.id_prompt`, `prompt_snapshot` e `modelo_ia` registram o contexto usado em novas análises.
- Resultados antigos permanecem com `id_prompt = NULL`, já que a versão exata usada historicamente não pode ser comprovada pelo banco antigo.

## Pontuação normalizada

A tabela `pontuacao_triagem` passa a guardar:

- `pontuacao_total`;
- `pontuacao_maxima`;
- `percentual`.

A categoria predominante é escolhida pelo maior percentual, e não apenas pela soma bruta.

Faixas utilizadas:

- 0%: Sem indício relevante;
- > 0% até 33,33%: Baixo;
- > 33,33% até 66,66%: Moderado;
- > 66,66%: Alto.

Isso mantém o comportamento equivalente à escala atual de três perguntas por categoria (máximo 9 pontos) e permite aumentar/reduzir perguntas sem favorecer categorias com mais itens.

## Ordem de implantação

1. Faça backup do banco e dos arquivos atuais.
2. Rode `database/00_preflight.sql` e confirme especialmente a estrutura real de `resultado`.
3. Se a tabela `resultado` do banco online não tiver os campos utilizados hoje pelo PHP, corrija isso antes da migração.
4. Rode `database/01_admin_migration.sql` **uma única vez**.
5. Promova um usuário existente para administrador:

```sql
UPDATE usuario
SET perfil = 'admin'
WHERE id_usuario = SEU_ID;
```

6. Adicione os arquivos novos e substitua os arquivos marcados acima.
7. Faça logout e login novamente para que `user_role` seja carregado na sessão.
8. Abra `admin.php`, crie um rascunho do questionário e teste sem publicar.
9. Salve uma nova versão de prompt e faça uma triagem de teste.
10. Só depois publique uma nova versão do questionário.

## Compatibilidade com InfinityFree

O módulo não usa Stored Procedures, Functions ou Triggers. Toda a lógica fica em PHP + SQL comum, seguindo a adaptação já feita no projeto para as limitações da hospedagem gratuita.

## Observação sobre PHP

O dump registra o ambiente do phpMyAdmin como PHP 7.2.22, enquanto o `processar_triagem.php` enviado usa `match`, recurso do PHP 8. O PHP do phpMyAdmin não necessariamente é o mesmo do site, mas vale confirmar a versão configurada para a aplicação. O `processar_triagem.php` deste pacote evita `match` e usa construções compatíveis com PHP 7.2+.
