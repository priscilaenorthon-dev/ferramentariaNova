<?php
$usuariosMock = [
    [
        'id' => 1,
        'nome' => 'Administrador',
        'login' => 'admin',
        'senha' => 'admin123',
        'perfil' => 'Administrador',
        'qr' => 'assets/qrcodes/admin.png',
    ],
    [
        'id' => 2,
        'nome' => 'Operador João',
        'login' => 'operador',
        'senha' => '123',
        'perfil' => 'Operador',
        'qr' => 'assets/qrcodes/operador.png',
    ],
    [
        'id' => 3,
        'nome' => 'Usuário Carlos',
        'login' => 'carlos',
        'senha' => '123',
        'perfil' => 'Usuario',
        'qr' => 'assets/qrcodes/usuario.png',
    ],
];

$ferramentasMock = [
    ['id' => 1, 'codigo' => 'F001', 'descricao' => 'Furadeira 500W', 'classe' => 'Elétrica', 'modelo' => 'Furadeira X', 'status' => 'Disponível', 'localizacao' => 'Almoxarifado 1'],
    ['id' => 2, 'codigo' => 'F002', 'descricao' => 'Paquímetro 150mm', 'classe' => 'Medição', 'modelo' => 'Paquímetro Y', 'status' => 'Emprestada', 'localizacao' => 'Setor Usinagem'],
    ['id' => 3, 'codigo' => 'F003', 'descricao' => 'Chave Inglesa 12\"', 'classe' => 'Mecânica', 'modelo' => 'Chave Ajustável Z', 'status' => 'Em manutenção', 'localizacao' => 'Oficina'],
];

$emprestimosMock = [
    ['id' => 1, 'ferramenta' => 'Paquímetro 150mm', 'usuario' => 'Carlos', 'operador' => 'Operador João', 'data' => '2025-01-10', 'prevista' => '2025-01-15', 'status' => 'Em andamento'],
    ['id' => 2, 'ferramenta' => 'Furadeira 500W', 'usuario' => 'Carlos', 'operador' => 'Operador João', 'data' => '2025-01-05', 'prevista' => '2025-01-07', 'status' => 'Atrasado'],
];

$devolucoesMock = [
    ['id' => 1, 'ferramenta' => 'Chave Inglesa 12\"', 'usuario' => 'João', 'data' => '2025-01-08', 'condicao' => 'Ok'],
];

$calibracoesMock = [
    ['id' => 1, 'ferramenta' => 'Paquímetro 150mm', 'ultima' => '2024-12-01', 'proxima' => '2025-12-01', 'status' => 'Em dia'],
    ['id' => 2, 'ferramenta' => 'Torquímetro', 'ultima' => '2023-10-10', 'proxima' => '2024-10-10', 'status' => 'Vencida'],
];

$relatoriosMock = [
    ['descricao' => 'Ferramentas emprestadas no mês', 'valor' => 25],
    ['descricao' => 'Ferramentas em atraso', 'valor' => 3],
    ['descricao' => 'Ferramentas em calibração', 'valor' => 5],
];

$historicoMock = [
    ['usuario' => 'Carlos', 'ferramenta' => 'Paquímetro 150mm', 'acao' => 'Empréstimo', 'data' => '2025-01-10 09:30'],
    ['usuario' => 'Carlos', 'ferramenta' => 'Paquímetro 150mm', 'acao' => 'Devolução', 'data' => '2025-01-12 16:00'],
];

$auditoriaMock = [
    ['usuario' => 'admin', 'acao' => 'Criou ferramenta F001', 'data' => '2025-01-01 10:00'],
    ['usuario' => 'admin', 'acao' => 'Resetou senha de Carlos', 'data' => '2025-01-05 14:22'],
];

$classesMock = [
    ['id' => 1, 'nome' => 'Elétrica', 'descricao' => 'Ferramentas elétricas em geral'],
    ['id' => 2, 'nome' => 'Medição', 'descricao' => 'Ferramentas de medição'],
    ['id' => 3, 'nome' => 'Mecânica', 'descricao' => 'Ferramentas mecânicas'],
];

$modelosMock = [
    ['id' => 1, 'nome' => 'Furadeira X', 'tipo' => 'Normal'],
    ['id' => 2, 'nome' => 'Paquímetro Y', 'tipo' => 'Calibração'],
    ['id' => 3, 'nome' => 'Torquímetro Z', 'tipo' => 'Calibração'],
];
