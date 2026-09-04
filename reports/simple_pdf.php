<?php
function pdf_escape($s){$s=str_replace(['\\','(',')'],['\\\\','\\(','\\)'],(string)$s);return $s;}
function simple_pdf_table($title,$columns,$rows){
    $maxCols=max(1,count($columns)); $pages=[]; $lines=[];
    $lines[]=$title; $lines[]=date('d M Y H:i'); $lines[]='';
    $lines[]=implode(' | ',array_map(fn($c)=>ucwords(str_replace('_',' ',$c)),$columns));
    $lines[]=str_repeat('-', min(110, max(40, 18*$maxCols)));
    foreach($rows as $r){$vals=[];foreach($columns as $c){$v=strip_tags((string)($r[$c]??''));$v=preg_replace('/\s+/',' ',$v);$vals[]=(function_exists('mb_substr')?mb_substr($v,0,24):substr($v,0,24));} $lines[]=implode(' | ',$vals);}
    $chunks=array_chunk($lines,48); foreach($chunks as $chunk)$pages[]=$chunk;
    $objects=[]; $objects[1]='<< /Type /Catalog /Pages 2 0 R >>';
    $kids=[]; $obj=3;
    foreach($pages as $page){$pageObj=$obj++;$contentObj=$obj++;$kids[]=$pageObj.' 0 R';$content="BT\n/F1 9 Tf\n40 800 Td\n";foreach($page as $line){$content.='('.pdf_escape($line).') Tj 0 -14 Td\n';}$content.='ET';$objects[$contentObj]='<< /Length '.strlen($content).' >>\nstream\n'.$content.'\nendstream';$objects[$pageObj]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 '.($obj).' 0 R >> >> /Contents '.$contentObj.' 0 R >>';}
    $fontObj=$obj; $objects[$fontObj]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
    $objects[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($kids).' >>';
    ksort($objects); $pdf="%PDF-1.4\n";$offsets=[0=>0];foreach($objects as $n=>$o){$offsets[$n]=strlen($pdf);$pdf.=$n." 0 obj\n".$o."\nendobj\n";}$xref=strlen($pdf);$pdf.='xref\n0 '.(max(array_keys($objects))+1).'\n0000000000 65535 f \n';for($i=1;$i<=max(array_keys($objects));$i++)$pdf.=sprintf('%010d 00000 n \n',$offsets[$i]??0);$pdf.='trailer\n<< /Size '.(max(array_keys($objects))+1).' /Root 1 0 R >>\nstartxref\n'.$xref.'\n%%EOF';header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="report.pdf"');return $pdf;
}
