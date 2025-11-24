# Ferramentaria (JOMAGA)
Sistema web em PHP para controle do centro de ferramentas da JOMAGA. Permite registrar o acervo, acompanhar empréstimos e devoluções, emitir relatórios, gerar QR Codes e acompanhar indicadores operacionais.

## Funcionalidades principais
- Login com perfis `Administrador`, `Operador` e `Usuário`, controlados em `inc/auth.php`.
- Dashboard com KPIs de ferramentas totais, em uso, alertas de calibração e tendências mensais.
- Cadastro de classes, modelos e ferramentas com controle de quantidade, localização e status.
- Fluxo completo de empréstimos e devoluções, incluindo termo de responsabilidade e alertas de atraso.
- Agenda de calibração com acompanhamento de próximas/vencidas e exportação (`calibracao_export.php`).
- Relatórios em PDF (FPDF) e geração de QR Code para usuários (`vendor/phpqrcode`).
- Histórico/auditoria de ações e registro de movimentações por setor/usuário.

## Requisitos
- PHP 8.x com extensões PDO/MySQL e GD habilitadas.
- MySQL/MariaDB (testado com XAMPP).
- Servidor web apontando para este diretório (ex.: `htdocs/ferramentariaNova` no XAMPP).
- Dependências já versionadas em `vendor/` (FPDF e phpqrcode) — não é necessário Composer.

## Instalação rápida
1. Clone o repositório para o diretório público do servidor web:
   ```bash
   git clone https://github.com/priscilaenorthon-dev/ferramentariaNova.git
   ```
2. Crie o banco e tabelas importando `database/schema.sql` no MySQL:
   ```sql
   SOURCE /c/xampp/htdocs/ferramentariaNova/database/schema.sql;
   ```
3. Ajuste as credenciais de conexão em `inc/db.php` (`$dbHost`, `$dbName`, `$dbUser`, `$dbPass`).
4. Defina a senha do usuário administrador seed (`login = admin`):
   ```bash
   php -r "echo password_hash('sua_senha', PASSWORD_BCRYPT);"
   ```
   Copie o hash gerado e atualize o registro:
   ```sql
   UPDATE usuarios SET senha_hash = '<HASH_GERADO>' WHERE login = 'admin';
   ```
5. Acesse `http://localhost/ferramentariaNova` no navegador e faça login.

## Estrutura do projeto
- `database/schema.sql` – criação do banco e seed do usuário admin.
- `inc/` – conexão, autenticação, layout (header/sidebar/footer) e mocks.
- `assets/` – estilos, ícones e scripts auxiliares.
- Páginas principais: `dashboard.php`, `ferramentas.php`, `emprestimos.php`, `devolucoes.php`, `inventario.php`, `calibracao.php`, `relatorios.php`, `historico.php`, `auditoria.php`, `usuarios.php`, `modelos.php`.
- Utilitários: `relatorio_pdf.php`, `ver_qrcode.php`, arquivos de teste em `test_*.php`.

## Notas de uso
- Perfis limitam o acesso às rotas; páginas checam permissão via `exigirPermissaoPagina`.
- A geração de QR Codes e PDFs requer a extensão GD ativa e permissões de escrita temporária do PHP.
- Para redefinir senhas ou criar usuários, utilize o módulo `Usuários` ou atualize diretamente via SQL usando `password_hash`.
