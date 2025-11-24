<?php
session_id('cli');
session_start();
$_SESSION['usuario_id']=1;
$_SESSION['usuario_nome']='Admin';
$_SESSION['usuario_perfil']='Administrador';
$_GET['id']=1;
ob_start();
include __DIR__ . '/ver_qrcode.php';
$data = ob_get_contents();
ob_end_clean();
file_put_contents('qr_test_output.bin', $data);
?>
