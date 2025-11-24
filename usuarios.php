<?php
require_once __DIR__ . '/inc/auth.php';
exigirPermissaoPagina('usuarios');
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/vendor/phpqrcode/qrlib.php';

$paginaTitulo = 'Usuários';
$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? 'criar';
    $usuarioId = (int)($_POST['usuario_id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $sobrenome = trim($_POST['sobrenome'] ?? '');
    $login = trim($_POST['login'] ?? '');
    $senha = trim($_POST['senha'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $matricula = trim($_POST['matricula'] ?? '');
    $setor = trim($_POST['setor'] ?? '');
    $perfil = $_POST['perfil'] ?? 'Usuário';

    if ($acao === 'excluir') {
        if ($usuarioId <= 0) {
            $erro = 'Usuário inválido para exclusão.';
        } else {
            try {
                $stmt = $pdo->prepare('DELETE FROM usuarios WHERE id = :id');
                $stmt->execute([':id' => $usuarioId]);
                $mensagem = 'Usuário removido com sucesso.';
            } catch (PDOException $e) {
                $erro = 'Não foi possível excluir o usuário.';
            }
        }
    } else {
        $camposObrigatoriosVazios = ($nome === '' || $sobrenome === '' || $login === '' || $matricula === '' || $setor === '' || $perfil === '');
        $senhaObrigatoria = ($acao === 'criar');

        if ($camposObrigatoriosVazios) {
            $erro = 'Preencha todos os campos obrigatórios.';
        } elseif ($senhaObrigatoria && $senha === '') {
            $erro = 'Informe uma senha para o novo usuário.';
        } elseif ($acao === 'atualizar' && $usuarioId <= 0) {
            $erro = 'Usuário inválido para edição.';
        } else {
            try {
                if ($acao === 'atualizar') {
                    $sql = 'UPDATE usuarios SET nome = :nome, sobrenome = :sobrenome, login = :login, email = :email, matricula = :matricula, setor = :setor, perfil = :perfil';
                    $params = [
                        ':nome' => $nome,
                        ':sobrenome' => $sobrenome,
                        ':login' => $login,
                        ':email' => $email !== '' ? $email : null,
                        ':matricula' => $matricula,
                        ':setor' => $setor,
                        ':perfil' => $perfil,
                        ':id' => $usuarioId,
                    ];
                    if ($senha !== '') {
                        $sql .= ', senha_hash = :senha';
                        $params[':senha'] = password_hash($senha, PASSWORD_DEFAULT);
                    }
                    $sql .= ' WHERE id = :id';
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $mensagem = 'Usuário atualizado com sucesso.';
                } else {
                    $qrToken = gerarQrTokenUnico($pdo);
                    $stmt = $pdo->prepare('INSERT INTO usuarios (nome, sobrenome, login, senha_hash, email, matricula, setor, perfil, qr_token) VALUES (:nome, :sobrenome, :login, :senha, :email, :matricula, :setor, :perfil, :qr)');
                    $stmt->execute([
                        ':nome' => $nome,
                        ':sobrenome' => $sobrenome,
                        ':login' => $login,
                        ':senha' => password_hash($senha, PASSWORD_DEFAULT),
                        ':email' => $email !== '' ? $email : null,
                        ':matricula' => $matricula,
                        ':setor' => $setor,
                        ':perfil' => $perfil,
                        ':qr' => $qrToken,
                    ]);
                    $mensagem = 'Usuário cadastrado com sucesso.';
                }
            } catch (PDOException $e) {
                $erro = 'Não foi possível salvar o usuário. Verifique login/matrícula.';
            }
        }
    }
}

function iniciaisUsuario(string $nome): string
{
    $partes = preg_split('/\s+/', trim($nome));
    $iniciais = '';
    foreach ($partes as $parte) {
        $iniciais .= strtoupper(substr($parte, 0, 1));
        if (strlen($iniciais) >= 2) {
            break;
        }
    }
    return $iniciais ?: 'US';
}

function badgeClass(string $perfil): string
{
    return match ($perfil) {
        'Administrador' => 'badge-admin',
        'Operador' => 'badge-operador',
        default => 'badge-usuario',
    };
}

$usuarios = $pdo->query('SELECT id, nome, sobrenome, email, matricula, setor, perfil, login, qr_token FROM usuarios ORDER BY nome')->fetchAll();

function gerarQrTokenUnico(PDO $pdo): string
{
    do {
        $token = bin2hex(random_bytes(16));
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE qr_token = :token');
        $stmt->execute([':token' => $token]);
    } while ($stmt->fetchColumn() > 0);
    return $token;
}

function garantirQrToken(PDO $pdo, array $usuario): string
{
    if (!empty($usuario['qr_token'])) {
        return $usuario['qr_token'];
    }
    $token = gerarQrTokenUnico($pdo);
    $stmt = $pdo->prepare('UPDATE usuarios SET qr_token = :token WHERE id = :id');
    $stmt->execute([
        ':token' => $token,
        ':id' => $usuario['id'],
    ]);
    return $token;
}

function gerarQrDataUri(PDO $pdo, array $usuario): string
{
    $token = garantirQrToken($pdo, $usuario);
    $payload = json_encode([
        'login' => $usuario['login'],
        'nome' => trim($usuario['nome'] . ' ' . $usuario['sobrenome']),
        'email' => $usuario['email'],
        'matricula' => $usuario['matricula'],
        'setor' => $usuario['setor'],
        'perfil' => $usuario['perfil'],
        'token' => $token,
        'sistema' => 'JOMAGA'
    ], JSON_UNESCAPED_UNICODE);

    ob_start();
    QRcode::png($payload, null, QR_ECLEVEL_M, 3, 1);
    $png = ob_get_clean();
    return 'data:image/png;base64,' . base64_encode($png);
}

include __DIR__ . '/inc/header.php';
include __DIR__ . '/inc/sidebar.php';
?>
<div class="content-area usuarios-page">
    <div class="page-header d-flex justify-content-between align-items-start">
        <div>
            <h1>Usuários</h1>
            <p class="text-muted mb-0">Gerencie os usuários do sistema</p>
        </div>
        <button class="btn btn-primary rounded-pill px-3" id="openModal">+ Novo Usuário</button>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-success rounded-3 py-2 px-3"><?= htmlspecialchars($mensagem); ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div class="alert alert-danger rounded-3 py-2 px-3"><?= htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <div class="users-filters">
        <div class="search-input">
            <span>&#128269;</span>
            <input type="text" id="searchInput" placeholder="Buscar por nome, email ou matrícula...">
        </div>
        <select id="perfilFilter">
            <option value="">Todos os Perfis</option>
            <option value="Administrador">Administrador</option>
            <option value="Operador">Operador</option>
            <option value="Usuário">Usuário</option>
        </select>
    </div>

    <div class="table-card">
        <table class="table mb-0" id="usuariosTable">
            <thead>
            <tr>
                <th>Usuário</th>
                <th>Email</th>
                <th>Matrícula</th>
                <th>Setor</th>
                <th>Perfil</th>
                <th>QR Code</th>
                <th>Ações</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($usuarios)): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">Nenhum usuário cadastrado.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($usuarios as $usuario) :
                    $nomeCompleto = trim($usuario['nome'] . ' ' . $usuario['sobrenome']);
                    $iniciais = iniciaisUsuario($nomeCompleto);
                    $badge = badgeClass($usuario['perfil']);
                    $qrDataUri = gerarQrDataUri($pdo, $usuario);
                    $qrLink = 'ver_qrcode.php?id=' . (int)$usuario['id'];
                    ?>
                    <tr data-nome="<?= htmlspecialchars(strtolower($nomeCompleto)); ?>"
                        data-email="<?= htmlspecialchars(strtolower($usuario['email'] ?? '')); ?>"
                        data-matricula="<?= htmlspecialchars(strtolower($usuario['matricula'])); ?>"
                        data-perfil="<?= htmlspecialchars($usuario['perfil']); ?>">
                        <td>
                            <div class="user-info">
                                <span class="avatar"><?= htmlspecialchars($iniciais); ?></span>
                                <?= htmlspecialchars($nomeCompleto); ?>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($usuario['email'] ?? '-'); ?></td>
                        <td><span class="badge-matricula"><?= htmlspecialchars($usuario['matricula']); ?></span></td>
                        <td><?= htmlspecialchars($usuario['setor']); ?></td>
                        <td><span class="<?= $badge; ?>"><?= htmlspecialchars($usuario['perfil']); ?></span></td>
                        <td>
                            <a href="<?= $qrDataUri; ?>" target="_blank" rel="noopener" download="qr-<?= htmlspecialchars($usuario['login']); ?>.png" title="Ver QR Code em nova aba">
                                <img src="<?= $qrDataUri; ?>" alt="QR Code de <?= htmlspecialchars($nomeCompleto); ?>" width="42" height="42" loading="lazy">
                            </a>
                        </td>
                        <td class="actions">
                            <button type="button"
                                    class="btn-action btn-edit"
                                    data-id="<?= (int)$usuario['id']; ?>"
                                    data-nome="<?= htmlspecialchars($usuario['nome']); ?>"
                                    data-sobrenome="<?= htmlspecialchars($usuario['sobrenome']); ?>"
                                    data-login="<?= htmlspecialchars($usuario['login']); ?>"
                                    data-email="<?= htmlspecialchars($usuario['email'] ?? ''); ?>"
                                    data-matricula="<?= htmlspecialchars($usuario['matricula']); ?>"
                                    data-setor="<?= htmlspecialchars($usuario['setor']); ?>"
                                    data-perfil="<?= htmlspecialchars($usuario['perfil']); ?>"
                                    title="Editar">
                                ✏️
                            </button>
                            <button type="button"
                                    class="btn-action btn-delete"
                                    data-id="<?= (int)$usuario['id']; ?>"
                                    data-nome="<?= htmlspecialchars($nomeCompleto); ?>"
                                    title="Excluir">
                                🗑️
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="modal">
    <div class="modal-card">
        <div class="modal-header">
            <div>
                <h3 id="modalTitle">Criar Novo Usuário</h3>
                <span id="modalSubtitle">Preencha os dados para criar um novo usuário no sistema</span>
            </div>
            <button class="modal-close" id="closeModal">×</button>
        </div>
        <form class="modal-body" id="usuarioForm" method="POST">
            <input type="hidden" name="acao" value="criar" id="usuarioAcao">
            <input type="hidden" name="usuario_id" value="" id="usuarioId">
            <div class="form-row">
                <div class="form-group">
                    <label>Nome *</label>
                    <input type="text" name="nome" id="inputNome" placeholder="Ex: João" required>
                </div>
                <div class="form-group">
                    <label>Sobrenome *</label>
                    <input type="text" name="sobrenome" id="inputSobrenome" placeholder="Ex: Silva" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="login" id="inputLogin" placeholder="Ex: joao.silva" required>
                </div>
                <div class="form-group">
                    <label id="senhaLabel">Senha *</label>
                    <input type="password" name="senha" id="inputSenha" placeholder="********">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="inputEmail" placeholder="Ex: joao.silva@empresa.com">
                </div>
                <div class="form-group">
                    <label>Matrícula *</label>
                    <input type="text" name="matricula" id="inputMatricula" placeholder="Ex: EMP001" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Setor *</label>
                    <input type="text" name="setor" id="inputSetor" placeholder="Ex: Produção" required>
                </div>
                <div class="form-group">
                    <label>Perfil *</label>
                    <select name="perfil" id="inputPerfil" required>
                        <option value="Usuário">Usuário</option>
                        <option value="Operador">Operador</option>
                        <option value="Administrador">Administrador</option>
                    </select>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" id="cancelModal">Cancelar</button>
                <button type="submit" class="btn-modal-primary" id="submitButton">Criar Usuário</button>
            </div>
        </form>
    </div>
</div>

<form method="POST" id="deleteUsuarioForm" style="display:none;">
    <input type="hidden" name="acao" value="excluir">
    <input type="hidden" name="usuario_id" id="deleteUsuarioId">
</form>

<style>
    .usuarios-page { padding: 1.5rem; }
    .users-filters { display: flex; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .search-input { flex: 1; min-width: 260px; position: relative; }
    .search-input span { position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); color: #9CA3AF; }
    .search-input input { width: 100%; border-radius: 0.75rem; border: 1px solid #CBD5F5; padding: 0.6rem 0.6rem 0.6rem 2.4rem; background: #FFF; }
    .users-filters select { border-radius: 0.75rem; border: 1px solid #CBD5F5; padding: 0.6rem 0.9rem; background: #FFF; min-width: 160px; }
    .table-card { background: #FFF; border-radius: 1rem; border: 1px solid #E5E7EB; overflow-x: auto; }
    .table-card table { width: 100%; min-width: 820px; border-collapse: collapse; }
    .table-card th, .table-card td { padding: 0.85rem 1rem; border-bottom: 1px solid #F3F4F6; font-size: 0.95rem; }
    .table-card th { text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; color: #6B7280; background: #F9FAFB; }
    .user-info { display: flex; align-items: center; gap: 0.75rem; }
    .avatar { width: 38px; height: 38px; border-radius: 50%; background: #E5E7EB; display: inline-flex; align-items: center; justify-content: center; font-weight: 600; color: #1F2937; }
    .badge-matricula { padding: 0.2rem 0.65rem; border-radius: 0.75rem; background: #F3F4F6; font-weight: 600; font-size: 0.85rem; }
    .badge-admin {border-radius:999px;padding:0.25rem 0.85rem;background:#1F56D8;color:#FFF;font-weight:600;font-size:0.82rem;}
    .badge-operador {border-radius:999px;padding:0.25rem 0.85rem;background:#DADDE3;color:#111;font-weight:600;font-size:0.82rem;}
    .badge-usuario {border-radius:999px;padding:0.25rem 0.85rem;background:#ECEFF3;color:#111827;font-weight:600;font-size:0.82rem;}
    .actions { display: flex; gap: 0.4rem; }
    .actions .btn-action { border: none; background: transparent; cursor: pointer; font-size: 1.05rem; }
    .modal-overlay { position: fixed; inset:0; background:rgba(15,23,42,0.55); display:none; align-items:center; justify-content:center; padding:1rem; z-index:1000; }
    .modal-overlay.active { display:flex; }
    .modal-card { background:#FFF; border-radius:1rem; width:min(720px,100%); box-shadow:0 40px 90px rgba(15,23,42,0.3); overflow:hidden; }
    .modal-header { padding:1.4rem 1.6rem; border-bottom:1px solid #F0F2F5; display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; }
    .modal-header span { color:#6B7280; }
    .modal-close { border:none; background:transparent; font-size:1.4rem; cursor:pointer; }
    .modal-body { padding:1.5rem; background:#F9FAFB; display:flex; flex-direction:column; gap:1rem; }
    .modal-body .form-group { display:flex; flex-direction:column; gap:0.35rem; }
    .modal-body input, .modal-body select { border:1px solid #CBD5F5; border-radius:0.85rem; padding:0.65rem 0.85rem; background:#FFF; font-size:0.95rem; }
    .form-row { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:0.9rem; }
    .modal-actions { padding:1rem 1.5rem 1.5rem; background:#F9FAFB; display:flex; justify-content:flex-end; gap:0.7rem; }
    .btn-secondary { border:1px solid #E5E7EB; border-radius:0.85rem; padding:0.65rem 1.2rem; background:#FFF; cursor:pointer; }
    .btn-modal-primary { border:none; border-radius:0.85rem; padding:0.65rem 1.3rem; background:#1F56D8; color:#FFF; font-weight:600; cursor:pointer; }
</style>
<script>
    const modal = document.getElementById('modal');
    const openModalBtn = document.getElementById('openModal');
    const closeModalBtn = document.getElementById('closeModal');
    const cancelModalBtn = document.getElementById('cancelModal');
    const searchInput = document.getElementById('searchInput');
    const perfilFilter = document.getElementById('perfilFilter');
    const modalTitle = document.getElementById('modalTitle');
    const modalSubtitle = document.getElementById('modalSubtitle');
    const submitButton = document.getElementById('submitButton');
    const acaoInput = document.getElementById('usuarioAcao');
    const usuarioIdInput = document.getElementById('usuarioId');
    const nomeInput = document.getElementById('inputNome');
    const sobrenomeInput = document.getElementById('inputSobrenome');
    const loginInput = document.getElementById('inputLogin');
    const senhaInput = document.getElementById('inputSenha');
    const senhaLabel = document.getElementById('senhaLabel');
    const emailInput = document.getElementById('inputEmail');
    const matriculaInput = document.getElementById('inputMatricula');
    const setorInput = document.getElementById('inputSetor');
    const perfilInput = document.getElementById('inputPerfil');
    const deleteForm = document.getElementById('deleteUsuarioForm');
    const deleteUsuarioId = document.getElementById('deleteUsuarioId');

    function toggleModal(show) { modal.classList.toggle('active', show); }

    function prepararModalCriar() {
        modalTitle.textContent = 'Criar Novo Usuário';
        modalSubtitle.textContent = 'Preencha os dados para criar um novo usuário no sistema';
        submitButton.textContent = 'Criar Usuário';
        acaoInput.value = 'criar';
        usuarioIdInput.value = '';
        nomeInput.value = '';
        sobrenomeInput.value = '';
        loginInput.value = '';
        senhaInput.value = '';
        senhaInput.required = true;
        senhaLabel.textContent = 'Senha *';
        emailInput.value = '';
        matriculaInput.value = '';
        setorInput.value = '';
        perfilInput.value = 'Usuário';
        toggleModal(true);
        nomeInput.focus();
    }

    function prepararModalEditar(button) {
        modalTitle.textContent = 'Editar Usuário';
        modalSubtitle.textContent = 'Atualize os dados do usuário selecionado';
        submitButton.textContent = 'Salvar Alterações';
        acaoInput.value = 'atualizar';
        usuarioIdInput.value = button.dataset.id;
        nomeInput.value = button.dataset.nome || '';
        sobrenomeInput.value = button.dataset.sobrenome || '';
        loginInput.value = button.dataset.login || '';
        senhaInput.value = '';
        senhaInput.required = false;
        senhaLabel.textContent = 'Senha (preencha para alterar)';
        emailInput.value = button.dataset.email || '';
        matriculaInput.value = button.dataset.matricula || '';
        setorInput.value = button.dataset.setor || '';
        perfilInput.value = button.dataset.perfil || 'Usuário';
        toggleModal(true);
        nomeInput.focus();
    }

    openModalBtn.addEventListener('click', prepararModalCriar);
    closeModalBtn.addEventListener('click', () => toggleModal(false));
    cancelModalBtn.addEventListener('click', () => toggleModal(false));
    modal.addEventListener('click', (event) => { if (event.target === modal) toggleModal(false); });

    document.querySelectorAll('.btn-edit').forEach((button) => {
        button.addEventListener('click', () => prepararModalEditar(button));
    });

    document.querySelectorAll('.btn-delete').forEach((button) => {
        button.addEventListener('click', () => {
            const nome = button.dataset.nome || 'este usuário';
            if (confirm(`Deseja realmente excluir ${nome}?`)) {
                deleteUsuarioId.value = button.dataset.id;
                deleteForm.submit();
            }
        });
    });

    function aplicarFiltros() {
        const termo = (searchInput.value || '').toLowerCase();
        const perfilSelecionado = perfilFilter.value;
        document.querySelectorAll('#usuariosTable tbody tr').forEach((row) => {
            const combinaBusca = row.dataset.nome.includes(termo)
                || row.dataset.email.includes(termo)
                || row.dataset.matricula.includes(termo);
            const combinaPerfil = !perfilSelecionado || row.dataset.perfil === perfilSelecionado;
            row.style.display = (combinaBusca && combinaPerfil) ? '' : 'none';
        });
    }

    searchInput.addEventListener('input', aplicarFiltros);
    perfilFilter.addEventListener('change', aplicarFiltros);
</script>
<?php include __DIR__ . '/inc/footer.php'; ?>
