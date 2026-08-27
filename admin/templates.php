<?php
require __DIR__.'/../config.php';
require_login('admin');
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="template-soal-examflow.csv"');
$out=fopen('php://output','w');
fwrite($out,"\xEF\xBB\xBF");
fputcsv($out,['question','type','A','B','C','D','correct_option','points']);
fputcsv($out,['2 + 2 = ?','mcq','3','4','5','6','B','1']);
fputcsv($out,['Jelaskan fotosintesis','essay','','','','','','5']);
fclose($out);
exit;
