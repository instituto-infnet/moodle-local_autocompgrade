# Avaliação automática de competências

Plugin do Moodle que realiza o cálculo de resultados de cada estudante e
atualiza o conceito de cada competência automaticamente, após a correção de
tarefas ou envio de respostas de questionários.

Contém um relatório de inconsistências, para facilitar a identificação de casos
em que as competências não estejam sendo avaliadas corretamente.

Também possui uma tela para forçar o cálculo manualmente.

## Instalação ou atualização

1. Criar a pasta `local/autocompgrade` dentro da estrutura do Moodle, se não
houver.
2. Copiar todos os arquivos do plugin para dentro desta pasta.
3. Acessar a página principal de administração do Moodle.
4. Conferir o plugin na lista exibida e confirmar a atualização do banco de
dados do Moodle.

## Uso

Após a instalação do plugin, sempre que uma entrega de tarefa for avaliada ou um
questionário for respondido e a atividade estiver associada a competências, o
conceito de cada estudante nas competências correspondentes dentro do curso será
atualizado automaticamente.

Para forçar a atualização de competências para um estudante e curso específicos,
acessar o menu lateral `Administração do Site > Competências > Avaliação
automática de competências > Forçar avaliação de competências`.

Para verificar possíveis inconsistências que podem interferir com o cálculo de
resultados, acessar o menu lateral `Administração do Site > Competências >
Avaliação automática de competências > Relatório de consistência para avaliação
automática de competências`.