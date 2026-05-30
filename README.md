# 🩺 MediAgenda

Sistema web para gerenciamento e agendamento de consultas médicas desenvolvido em PHP durante as aulas de Programação Web.

---

# 🚀 Sobre o Projeto

O **MediAgenda** é uma aplicação desenvolvida para auxiliar no gerenciamento de consultas médicas, permitindo:

- 🔐 Login de usuários
- 📅 Visualização de agenda mensal
- 🩺 Cadastro e gerenciamento de agendamentos
- 👨‍⚕️ Cadastro de médicos
- 🏥 Cadastro de especialidades
- ❌ Cancelamento de consultas
- 📊 Dashboard moderno com calendário

O projeto foi desenvolvido utilizando conceitos de:

- PHP
- MySQL / MariaDB
- HTML5
- CSS3
- Bootstrap
- JavaScript
- SweetAlert2
- Git e GitHub

---

# 📁 Estrutura do Projeto

```text
mediagenda/
│
├── login.php
├── principal.php
├── redirect.php
├── logout.php
├── conexao.php
├── cadastro_agendas.php
├── cadastro_medicos.php
├── cadastro_especialidades.php
├── cancelar_agendamento.php
├── script.sql
└── README.md
```

---

# 🗄️ Banco de Dados

O sistema utiliza MySQL/MariaDB.

O arquivo:

```text
script.sql
```

contém:

- criação do banco;
- tabelas;
- relacionamentos;
- views utilizadas pelo sistema.

---

# ⚙️ Como Executar

## 1️⃣ Criar o banco de dados

Execute o arquivo:

```sql
script.sql
```

no MySQL ou MariaDB.

---

## 2️⃣ Configurar a conexão

No arquivo:

```php
conexao.php
```

configure:

- servidor;
- usuário;
- senha;
- banco de dados.

---

## 3️⃣ Executar o projeto

Abra o projeto em um servidor PHP e acesse:

```text
login.php
```

---

# 👨‍💻 Integrantes do Grupo

- Allan Luiz Filipe Oliveira – 241216821
- André Felipe Andrade Oliveira – 251210008
- Gabriel Henrique da Fraga Santos – 2412112804
- Isaac Oliveira Ferreira de Sousa – 2412113877
- Turiê Hermano de Oliveira – 2412113550
- Vítor Hugo Moreira – 241211248
- Walkíria Aparecida de Souza – 241210014

---

# 📚 Objetivo Acadêmico

Este projeto possui finalidade educacional e foi desenvolvido como atividade prática da disciplina de Programação Web.

---

# 🧠 Funcionalidades Futuras

- 📱 Responsividade mobile
- 🔔 Notificações de consultas
- 📈 Relatórios
- 👤 Controle de perfis de acesso
- ☁️ Publicação em nuvem

---

# 💻 Tecnologias Utilizadas

| Tecnologia | Finalidade |
|---|---|
| PHP | Back-end |
| MySQL/MariaDB | Banco de dados |
| Bootstrap | Interface |
| JavaScript | Interatividade |
| SweetAlert2 | Alertas modernos |
| Git/GitHub | Versionamento |

---

# 📌 Observação

Projeto desenvolvido para fins acadêmicos e aprendizado de desenvolvimento web com PHP e banco de dados relacional.
