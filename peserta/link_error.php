<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($title ?? 'Ujian Online',ENT_QUOTES,'UTF-8')?></title>
<link rel="stylesheet" href="../admin/assets/admin-ui.css">
<style>
body{margin:0;min-height:100vh;display:grid;place-items:center;background:#eef2f7}
.link-error{width:min(560px,calc(100% - 32px));padding:34px;border-radius:18px;background:#fff;border:1px solid #d9e1ec;box-shadow:0 12px 35px rgba(30,41,59,.10)}
.link-error h1{margin:0 0 12px;font-size:26px}.link-error p{color:#64748b;line-height:1.6;margin:0 0 24px}

/* V6.3.58 responsive participant baseline */
img{max-width:100%;height:auto}
@media (max-width:760px){
  body{font-size:16px!important;overflow-x:hidden}
  .wrap{width:100%;max-width:none;padding:14px!important}
  .card{padding:18px 14px!important;margin:12px 0!important;border-radius:14px!important}
  input,select,textarea,button{font-size:16px!important;max-width:100%}
  input,select,textarea{min-height:44px}
  button,.btn{min-height:44px;touch-action:manipulation}
  table{max-width:100%}
}
@media (max-width:480px){
  .wrap{padding:10px!important}
  .card{padding:16px 12px!important}
}

</style>
</head>
<body>
<main class="link-error">
<h1><?=htmlspecialchars($title ?? '',ENT_QUOTES,'UTF-8')?></h1>
<p><?=htmlspecialchars($message ?? '',ENT_QUOTES,'UTF-8')?></p>
<a class="btn btn-primary" href="../login.php">Kembali</a>
</main>
</body>
</html>
