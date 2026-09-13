<?php
declare(strict_types=1);
/* TEMP: split imported account addresses into address1/address2/town/county/postcode.
   Only accounts WITH a parsed street line (no blanking). Dry-run default; ?go=1. git rm after. */
require __DIR__ . "/bootstrap.php";
require __DIR__ . "/auth/middleware.php";
requireSuperAdmin();
header("Content-Type: text/plain; charset=utf-8");
ini_set("display_errors","1"); error_reporting(E_ALL);
$pdo=db(); $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$P=[
  'JBW001' => ['Unit 13 Rosebridge Court','','Wigan','','WN1 3DP'],
  'GRB001' => ['40 Carbis Avenue','Grimsargh','Preston','','PR2 5LU'],
  'DAV001' => ['67 High Street','','Daventry','Northants','NN11 4BQ'],
  'VESTA001' => ['8 Wollaton Close','','Mansfield','','NG18 3GD'],
  'AB4U001' => ['23 Copsewood Avenue','','Nuneaton','','CV11 4TQ'],
  'JBL001' => ['Unit 13 Rosebridge Court','Ince','Wigan','','WN1 3DP'],
  'JBN001' => ['Unit 13 Rosebridge Court','Ince','Wigan','','WN1 3DP'],
  'BT001' => ['Unit 1','Chaucer Business Park, Granville Way','Bicester','Oxfordshire','OX26 4JT'],
  'PRES001' => ['30 Redcliffe Street','','Sutton-in-ashfield','','NG17 4ET'],
  'B4L001' => ['8 The Osiers','Elford','Tamworth','West Midlands','B79 9DG'],
  'PSI001' => ['4-6 Leicester Street','','Bedworth','Westmidlands','CV12 8SY'],
  'EXP001' => ['22 Silver Birch Avenue','','Bedworth','','CV12 0AZ'],
  'BRIX001' => ['21 Brixham Drive','','Coventry','','CV2 3LA'],
  'VMB001' => ['28 Somerset Drive','Duston','Northampton','','NN5 6FA'],
  'FORT001' => ['Unit 18 Wilstead Industrial Park','Kenneth Way, Wilstead','Bedford','','MK45 3PD'],
  'SBY002' => ['132 Danebury Drive','Comb','York','','YO26 5EB'],
  'XB001' => ['19 Broad Avenue','','Leicester','','LE5 4PT'],
  'PAU001' => ['9 York Close','','Leicester','','LE2 9UE'],
  'PBS001' => ['103 May Close','','Swindon','','SN2 1XA'],
  'HAY001' => ['33 Wellington Road','','Bicester','','OX25 5AL'],
  'RCB001' => ['81','Seven Wells Crescent','East Calder','West Lothian','EH53 0GT'],
  'LB001' => ['Clifford House','Montague Road','Warwick','','CV34 5LW'],
  'TSB001' => ['Unit 3 The Old Timber Yard','York Road','Wetherby','','LS22 5EF'],
  'MB001' => ['66 Doncaster Road','Conisbrough','Doncaster','','DN12 3AG'],
  'REDR001' => ['Unit 17 Rough Hey Road','Grimsargh','Preston','Lancashire','PR2 5AR'],
  'GOB001' => ['1 Model Cottage','Main Street, Slipton','Kettering','Northamptonshire','NN14 3AS'],
  'PB001' => ['Bellour Farm','Methven','Perth','','PH1 3RB'],
  'FB001' => ['93 Stone Brig Lane','Rohtwell','Leeds','','LS26 0UD'],
  'TBASD001' => ['50 Carol Crescent','Chaddesden','Derby','Derbyshire','DE21 6PQ'],
  'SJB001' => ['Unit 11 Rosebridge Court','Higher Ince','Wigan','','WN1 3DP'],
  'SAGA001' => ['88 Warwick Road','','Kenilworth','','CV8 1HL'],
  'BCS001' => ['100 St Giles Road','','Coventry','','CV7 9HA'],
  'SWB001' => ['The Old Hall Pentre Street','Glynneath','Neath','','SA11 5AH'],
  'CAR001' => ['Unit 39a','Penrhos Industrial Estate','Holyhead','Anglesey','LL65 2FD'],
  'EBS001' => ['77 Collingwood Road','','Uxbridge','Hounslow Middlesex','UB8 3EL'],
  'TFB001' => ['28 Docherty Gardens','','Glenrothes','','KY7 5GA'],
  'SUP001' => ['25 High Street','','Studley','Warwickshire','B80 7HN'],
  'RB002' => ['9 Broom Grove','','Wrexham','','LL13 9DL'],
];
$dry=(($_GET["go"]??"")!=="1");
if($dry) echo "DRY RUN — add ?go=1 to write.".chr(10).chr(10);
$upd=$pdo->prepare("UPDATE clients SET address1=?, address2=?, town=?, county=?, postcode=? WHERE account_ref=?");
$n=0;
foreach($P as $ref=>$v){
  [$a1,$a2,$town,$county,$pc]=$v;
  if($dry){ echo "  $ref: $a1 | $a2 | $town | $county | $pc".chr(10); $n++; continue; }
  $upd->execute([$a1?:null,$a2?:null,$town?:null,$county?:null,$pc?:null,$ref]);
  $n+=$upd->rowCount();
}
echo chr(10).($dry?"WOULD update":"Updated")." $n account(s).".chr(10);
