<?php
require __DIR__ . '/config/database.php';
$db=getDB();
$q = $db->prepare("SELECT id, nom FROM matricules WHERE nom ILIKE :q ORDER BY id DESC LIMIT 20");
$q->execute([':q'=>'%245%']);
print_r($q->fetchAll());
