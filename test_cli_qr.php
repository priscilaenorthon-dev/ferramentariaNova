<?php
session_id('cli');
session_start();
$_SESSION['usuario_id']=1;
$_SESSION['usuario_nome']='Admin';
$_SESSION['usuario_perfil']='Administrador';
$_GET['id']=1;
include __DIR__ . '/ver_qrcode.php';
?>
