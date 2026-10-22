<?php
// The public verification URL used in registration emails forwards to the
// verification page kept with the authentication screens.
$token = $_GET['token'] ?? '';
header('Location: auth/verify.php?token=' . rawurlencode((string) $token), true, 302);
exit;
