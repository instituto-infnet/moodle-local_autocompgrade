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
5. Renomear o arquivo credentials_dist.php para credentials.php e atualizar o token do webservice.

## Usabilidade

Após a instalação do plugin, sempre que uma entrega de tarefa for avaliada ou um
questionário for respondido e a atividade estiver associada a competências, o
conceito de cada estudante nas competências correspondentes dentro do curso será
atualizado automaticamente.

Ao salvar a avaliação da entrega o plugin verifica os seguintes critérios e faz o downgrade da nota para D quando necessário:

    1 - Entrega dos TPs e do AT no prazo certo - não limita a nota, o aluno pode ficar com ND, D, DL ou DML.
    2 - TPs entregues(com atraso ou não) e primeira entrega do AT atrasada  - limita a nota para D.
    3 - Possui alguma entrega de TP atrasada e AT entregue na data correta - limitar nota para D.
    4 - Entrega primeira tentativa do AT no prazo correto e TPs entregues, reprovado em alguma competência(ND), reabre a segunda tentativa de forma automática.
    5 - TPs entregues(com atraso ou não) e entrega do AT em atraso, ficou com ND em alguma competência - não reabre a segunda tentativa.
    6 - Entrega da segunda tentativa do AT - limitar a nota para D.
    7 - Entrega da segunda tentativa do AT, ficou com ND em alguma competência - mantém o ND.
    8 - Aluno possui alguma prorrogação de data em algum TP e fez a entrega dentro desse prazo - não limita a nota, o aluno pode ficar com ND, D, DL ou DML.
    9 - Aluno possui prorrogação de data no AT e fez a entrega dentro desse prazo  - não limita a nota, o aluno pode ficar com ND, D, DL ou DML.

Para forçar a atualização de competências para um estudante e curso específicos,
acessar o menu lateral `Administração do Site > Competências > Avaliação
automática de competências > Forçar avaliação de competências`.

Para verificar possíveis inconsistências que podem interferir com o cálculo de
resultados, acessar o menu lateral `Administração do Site > Competências >
Avaliação automática de competências > Relatório de consistência para avaliação
automática de competências`.
