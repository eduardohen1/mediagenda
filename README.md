# 🩺 MediAgenda

Sistema web para gerenciamento e agendamento de consultas médicas desenvolvido em PHP durante as aulas de Programação II.

---

# 🚀 Sobre o Projeto

O **MediAgenda** é uma aplicação desenvolvida para auxiliar no gerenciamento de consultas médicas, permitindo:

- 🔐 Login seguro com senha criptografada
- 👤 Cadastro de usuários por código convite
- 🛠 Administração de usuários (cadastro, edição, exclusão e filtros)
- 👥 Controle de perfis de acesso (Administrador e Usuário)
- 📅 Cadastro e gerenciamento de agendamentos
- 👨‍⚕️ Cadastro de médicos
- 🏥 Cadastro de especialidades
- ❌ Cancelamento de consultas
- 📊 Dashboard com calendário mensal

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
├── cadastro_usuarios.php
├── admin_usuarios.php
├── config_usuarios.php
├── cancelar_agendamento.php
├── init.sql
└── README.md
```

---

# 🗄️ Banco de Dados

O sistema utiliza MySQL/MariaDB.

O arquivo:

```text
init.sql
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
init.sql
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

Abra o projeto em um servidor PHP e localhost. Você será redirecionado para:

```text
localhost/login.php
```

Durante a criação do banco, foi incluído manualmente um usuário Administrador:

```text
Login: admin
Senha: admin123
```

---

# 🧠 Funcionalidades Futuras

- 📱 Responsividade mobile
- 🔔 Notificações de consultas
- 📈 Relatórios
- ☁️ Publicação em nuvem

---

# 💻 Tecnologias Utilizadas

| Tecnologia    | Finalidade       |
| ------------- | ---------------- |
| PHP           | Back-end         |
| MySQL/MariaDB | Banco de dados   |
| Bootstrap     | Interface        |
| JavaScript    | Interatividade   |
| SweetAlert2   | Alertas modernos |
| Git/GitHub    | Versionamento    |

---

# 🔒 Segurança

O sistema possui:

- autenticação de usuários por login e senha;
- senhas armazenadas com hash seguro utilizando password_hash();
- alteração de senha pelo próprio usuário;
- controle de acesso por perfil (Administrador e Usuário);
- restrição de acesso à área administrativa;
- bloqueio de edição de agendamentos com data anterior ao dia atual.

---

# 👨‍💻 Integrantes do Grupo

- Allan Luiz Filipe Oliveira – 241216821
- André Felipe Andrade Oliveira – 251210008
- Gabriel Henrique da Fraga Santos – 2412112804
- Isaac Oliveira Ferreira de Sousa – 2412113877
- Vítor Hugo Moreira – 241211248
- Walkíria Aparecida de Souza – 241210014

---

# 📚 Objetivo Acadêmico

Este projeto possui finalidade educacional e foi desenvolvido como atividade prática da disciplina de Programação II.