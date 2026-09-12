<?php
declare(strict_types=1);
/* TEMP one-time import: trade accounts + logins (Stage 1+3). Passwords are
   pre-hashed (bcrypt) — no plaintext here. Idempotent: skips accounts that
   already exist by code or name. git rm after running. */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors','1'); error_reporting(E_ALL);
$pdo = db(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$DATA = array (
  0 => 
  array (
    'company' => 'Just Blinds Wigan',
    'contact' => 'Nathan Moss',
    'email' => 'justblindswigan@gmail.com',
    'phone' => '01942863990',
    'address1' => 'Unit 13 Rosebridge Court , Wigan ,  , 01942863990',
    'postcode' => 'WN1 3DP',
    'account_ref' => 'JBW001',
    'username' => 'JBW001',
    'hash' => '$2y$10$SJDzyLaDr6u1hdZ9gn1MPe18LqWkQeMGBR3vDTgysyW2d4IJv6NbG',
  ),
  1 => 
  array (
    'company' => 'Grimsargh Blinds',
    'contact' => 'Nicky Hough',
    'email' => 'info@grimsarghblinds.co.uk',
    'phone' => '01772 963060',
    'address1' => '40 Carbis Avenue , Grimsargh , Preston ,  , 01772 963060',
    'postcode' => 'PR2 5LU',
    'account_ref' => 'GRB001',
    'username' => 'GRB001',
    'hash' => '$2y$10$bfpvhSSAda2beh62Sp32..SzYCBdBL3Sna1yuQoivOfXdnzK0TzpK',
  ),
  2 => 
  array (
    'company' => 'Daventry Blinds',
    'contact' => 'Gary Birch',
    'email' => 'daventryblinds@hotmail.com',
    'phone' => '01327 878433',
    'address1' => '67 High Street , Daventry , Northants ,  , 01327 878433',
    'postcode' => 'NN11 4BQ',
    'account_ref' => 'DAV001',
    'username' => 'DAV001',
    'hash' => '$2y$10$UDHHHgiLwLvJ6XqSyjhiHOxQR8jKI8LQtwBYsXob92un75fq2Ps9.',
  ),
  3 => 
  array (
    'company' => 'Vesta Blinds',
    'contact' => 'James Rowan',
    'email' => 'info@vestablinds.com',
    'phone' => '01623325054',
    'address1' => '8 Wollaton Close , Mansfield ,  , 01623325054',
    'postcode' => 'NG18 3GD',
    'account_ref' => 'VESTA001',
    'username' => 'VESTA001',
    'hash' => '$2y$10$.WrgNSDCTLdemW48h95X4OnXPTX8J/v5PIj5L.L4FJV4DMICctcLi',
  ),
  4 => 
  array (
    'company' => 'All Blinds 4 U',
    'contact' => 'George Lowe',
    'email' => 'allblinds4u@outlook.com',
    'phone' => '02477671624',
    'address1' => '23 Copsewood Avenue , Nuneaton ,  , 02477671624',
    'postcode' => 'CV11 4TQ',
    'account_ref' => 'AB4U001',
    'username' => 'AB4U001',
    'hash' => '$2y$10$Cv7iUgn5dXqxYOkytO9kMeXgoft7BqGoXzsa1iIJjg6KKmS40UkQG',
  ),
  5 => 
  array (
    'company' => 'Just Blinds Lancashire',
    'contact' => 'Amy Winstanley',
    'email' => 'justblindslancashire@gmail.com',
    'phone' => '01925 830999',
    'address1' => 'Unit 13 Rosebridge Court , Ince , Wigan ,  , 01925 830999',
    'postcode' => 'WN1 3DP',
    'account_ref' => 'JBL001',
    'username' => 'JBL001',
    'hash' => '$2y$10$m3sUiInOuxiHMjKY7cdILu6/yBGzUxtkkRU6vYzI/7DzwUZ7QdvGG',
  ),
  6 => 
  array (
    'company' => 'Just Blinds North',
    'contact' => 'Pual Holland',
    'email' => 'Justblindsuknorth@gmail.com',
    'phone' => '01204 531431',
    'address1' => 'Unit 13 Rosebridge Court , Ince , Wigan ,  , 01204 531431',
    'postcode' => 'WN1 3DP',
    'account_ref' => 'JBN001',
    'username' => 'JBN001',
    'hash' => '$2y$10$IxVRxTOmljaKNIWD6ZzGJO2oAYKSThfUR7LDO3QHPyC9vAWTGoTRW',
  ),
  7 => 
  array (
    'company' => 'Blind Trader',
    'contact' => 'Blind Trader',
    'email' => 'accounts@carpettraderbicester.co.uk',
    'phone' => '01869321777',
    'address1' => 'Unit 1, Chaucer Business Park, Granville Way , Bicester , Oxfordshire ,  , 01869321777',
    'postcode' => 'OX26 4JT',
    'account_ref' => 'BT001',
    'username' => 'BT001',
    'hash' => '$2y$10$5.JPwZu/xvMos4AOKuF0buKNWgQDK.cd9Fd1lXfUOZ6jIzklhMXze',
  ),
  8 => 
  array (
    'company' => 'Prestige Blinds',
    'contact' => 'Dave Saxton',
    'email' => 'prestige_blinds@hotmail.com',
    'phone' => '01623430170',
    'address1' => '30 Redcliffe Street , Sutton-in-ashfield ,  , 01623430170',
    'postcode' => 'NG17 4ET',
    'account_ref' => 'PRES001',
    'username' => 'PRES001',
    'hash' => '$2y$10$vARNo5sZfovSFS3C.Y5CxeKOgogDj34Ss.eyCeNPT7GQxOc7wZf2y',
  ),
  9 => 
  array (
    'company' => 'Blinds4Less',
    'contact' => 'Tony Oakes',
    'email' => 'blindsfourless@gmail.com',
    'phone' => '07498227184',
    'address1' => '8 The Osiers , Elford , Tamworth , West Midlands ,  , 07498227184',
    'postcode' => 'B79 9DG',
    'account_ref' => 'B4L001',
    'username' => 'B4L001',
    'hash' => '$2y$10$3gzDe5ZnLccg9tSWtUuOrui9b4cXI4H.zbHoz3vFiGgslt69uGnN6',
  ),
  10 => 
  array (
    'company' => 'Just Blinds Manchester',
    'contact' => 'Rebecca Owen',
    'email' => 'manchesterjustblinds@gmail.com',
    'phone' => '0161 444 3667',
    'address1' => '',
    'postcode' => 'WN1 3DP',
    'account_ref' => 'JBM001',
    'username' => 'JBM001',
    'hash' => '$2y$10$Ti2EBmJh7e7cuu.z7ahDAeHaY2o/3SR7rkR9y/JivbHlQZyTRveYG',
  ),
  11 => 
  array (
    'company' => 'Spencer Interiors Ltd',
    'contact' => 'Paul Spencer',
    'email' => 'spencerinteriors@live.co.uk',
    'phone' => '02476314555',
    'address1' => '4-6 Leicester Street , Bedworth , Westmidlands ,  , 02476314555',
    'postcode' => 'CV12 8SY',
    'account_ref' => 'PSI001',
    'username' => 'PSI001',
    'hash' => '$2y$10$rAt0vwd6oKLftt57Z6d9WeluDaPLuNb5dTzjk.aDQ.1QIYv5hrJpK',
  ),
  12 => 
  array (
    'company' => 'Express Blinds',
    'contact' => 'John Hancox',
    'email' => 'bedworthblinds@gmail.com',
    'phone' => '',
    'address1' => '22 Silver Birch Avenue , Bedworth',
    'postcode' => 'CV12 0AZ',
    'account_ref' => 'EXP001',
    'username' => 'EXP001',
    'hash' => '$2y$10$XvvpG8ZUbmSYo3geGdg72.HjAAhBKdPUmzK1BaN/gtn439xr0D1Z2',
  ),
  13 => 
  array (
    'company' => 'Brixham Blinds',
    'contact' => 'Zak Mangera',
    'email' => 'brixhamblinds@outlook.com',
    'phone' => '02476 666686',
    'address1' => '21 Brixham Drive , Coventry ,  , 02476 666686',
    'postcode' => 'CV2 3LA',
    'account_ref' => 'BRIX001',
    'username' => 'BRIX001',
    'hash' => '$2y$10$7SWvrvKNp4NztUsxIHhSQuLEApiDB69wYmtM2mRkdame9fcX6n4Em',
  ),
  14 => 
  array (
    'company' => 'VM Blinds Limited',
    'contact' => 'Vickie Morrow',
    'email' => 'info@vmblinds.com',
    'phone' => '01604 945995',
    'address1' => '28 Somerset Drive , Duston , Northampton ,  , 01604 945995',
    'postcode' => 'NN5 6FA',
    'account_ref' => 'VMB001',
    'username' => 'VMB001',
    'hash' => '$2y$10$AVTJ3DB0BkfxF8pMHlsb7eSJcPSW3len0i5QFrHX73UyXum8fcSNO',
  ),
  15 => 
  array (
    'company' => 'Forte Flooring Ltd',
    'contact' => '',
    'email' => 'accounts@fortecf.co.uk',
    'phone' => '01234 602 049',
    'address1' => 'Unit 18 Wilstead Industrial Park , Kenneth Way , Wilstead , Bedford ,  , 01234 602 049',
    'postcode' => 'MK45 3PD',
    'account_ref' => 'FORT001',
    'username' => 'FORT001',
    'hash' => '$2y$10$BtHC2qDmVmyFGhl5JETdt.3skx6ozIkbd/c.bp.YPU9z1OBvOR57e',
  ),
  16 => 
  array (
    'company' => 'Simply Blinds York',
    'contact' => 'Neil Sanderson',
    'email' => 'Simplyblindsyork@hotmail.com',
    'phone' => '',
    'address1' => '132 Danebury Drive , Comb , York',
    'postcode' => 'YO26 5EB',
    'account_ref' => 'SBY002',
    'username' => 'SBY002',
    'hash' => '$2y$10$a3D1fDmi2vheAgw.AUEqPOttGpcwIv0eQJB4L8syllbhz/PA8YAF2',
  ),
  17 => 
  array (
    'company' => 'Xquisite Blinds Limited',
    'contact' => 'Ahmed Alimohamed',
    'email' => 'sales@xquisiteblinds.com',
    'phone' => '',
    'address1' => '19 Broad Avenue , Leicester',
    'postcode' => 'LE5 4PT',
    'account_ref' => 'XB001',
    'username' => 'XB001',
    'hash' => '$2y$10$FJflN6oVnbRTYVS7n0upd.Y.VY8huCPmQK4TX3dwaypMAU3ydHz2S',
  ),
  18 => 
  array (
    'company' => 'Paulls Blinds',
    'contact' => 'Paul Lucas',
    'email' => 'paullsblinds@outlook.com',
    'phone' => '07772727452',
    'address1' => '9 York Close , Leicester ,  , 07772727452',
    'postcode' => 'LE2 9UE',
    'account_ref' => 'PAU001',
    'username' => 'PAU001',
    'hash' => '$2y$10$cysXZTft3JOY6dBkk4os7u0463EFJYHHRWiqEDZt.8izvKVT7mU.e',
  ),
  19 => 
  array (
    'company' => 'Peaky Blinds Swindon',
    'contact' => 'James Higgins',
    'email' => 'Info@peakyblindsswindon.co.uk',
    'phone' => '',
    'address1' => '103 May Close , Swindon',
    'postcode' => 'SN2 1XA',
    'account_ref' => 'PBS001',
    'username' => 'PBS001',
    'hash' => '$2y$10$MQKrofLwJmtSMyV03PyTceRkL3rQkzkNZKarOiEVTO/XFPj0Or7lS',
  ),
  20 => 
  array (
    'company' => 'Heyford Installations',
    'contact' => 'Martin McLnerney',
    'email' => 'heyfordinstallationsltd@gmail.com',
    'phone' => '',
    'address1' => '33 Wellington Road , Bicester',
    'postcode' => 'OX25 5AL',
    'account_ref' => 'HAY001',
    'username' => 'HAY001',
    'hash' => '$2y$10$OmpLjvxOuc7Fq058B2730uWV0QKiwxVdVEbiwiMhr2FKcZneMV8Ci',
  ),
  21 => 
  array (
    'company' => 'Right Choice Blinds Limited',
    'contact' => 'Marcus Barnes',
    'email' => 'marcusbarnes43@gmail.com',
    'phone' => '',
    'address1' => '81 , Seven Wells Crescent , East Calder , West Lothian',
    'postcode' => 'EH53 0GT',
    'account_ref' => 'RCB001',
    'username' => 'RCB001',
    'hash' => '$2y$10$0P8aXcWbOBrOe83nylca9.HHBwpaakY/vQAaEkH5sqpFSyeyEDcje',
  ),
  22 => 
  array (
    'company' => 'Leamington Blinds',
    'contact' => 'Jamie Phillips',
    'email' => 'sales@leamingtonblinds.co.uk',
    'phone' => '01926 839689',
    'address1' => 'Clifford House , Montague Road , Warwick ,  , 01926 839689',
    'postcode' => 'CV34 5LW',
    'account_ref' => 'LB001',
    'username' => 'LB001',
    'hash' => '$2y$10$sbTBLtNsCoEDeF7C0kb4Iuos0dJc3PGeVTNXYe.c3XZE0.L.56MI6',
  ),
  23 => 
  array (
    'company' => 'Tailored Shutters & Blinds',
    'contact' => 'Scott Mitchell',
    'email' => 'Sales@tailoredshuttersblinds.co.uk',
    'phone' => '01937 326036',
    'address1' => 'Unit 3 The Old Timber Yard , York Road , Wetherby ,  , 01937 326036',
    'postcode' => 'LS22 5EF',
    'account_ref' => 'TSB001',
    'username' => 'TSB001',
    'hash' => '$2y$10$1SLWfx6lbjzeHFi/tlXJB.w70cRLQxsq3G1WwcHumZqYBHTVMFLaO',
  ),
  24 => 
  array (
    'company' => 'Sterling Blinds Ltd',
    'contact' => 'Shafraz Rafaideen',
    'email' => 'info@sterling-blinds.co.uk',
    'phone' => '',
    'address1' => '',
    'postcode' => '',
    'account_ref' => 'SBL001',
    'username' => 'SBL001',
    'hash' => '$2y$10$cYOeOFutAplkA1BhCFyK3e7HdkB8zOj0Zog9w/tk0rgQNwniDLrha',
  ),
  25 => 
  array (
    'company' => 'Maher Blinds',
    'contact' => 'Andrew Maher',
    'email' => 'maher.blinds@gmail.com',
    'phone' => '',
    'address1' => '66 Doncaster Road , Conisbrough , Doncaster',
    'postcode' => 'DN12 3AG',
    'account_ref' => 'MB001',
    'username' => 'MB001',
    'hash' => '$2y$10$XieWbADowOc9J4TC11PorOw3u48PHj3HPB1.riRrlroAnOpGpGD0.',
  ),
  26 => 
  array (
    'company' => 'Mercia Blinds UK',
    'contact' => 'Kenneth Hipkiss',
    'email' => 'info@merciablinds.com',
    'phone' => '',
    'address1' => '',
    'postcode' => '',
    'account_ref' => 'MBU001',
    'username' => 'MBU001',
    'hash' => '$2y$10$jI5M4WIKaOXNVefI3sEhrOmb3vyl/MpU0dn.rsFsaR8/JUh8qQrEa',
  ),
  27 => 
  array (
    'company' => 'Red Rose Blinds Ltd',
    'contact' => 'Adam Nelson',
    'email' => 'sales@redroseblinds.co.uk',
    'phone' => '01772 655666',
    'address1' => 'Unit 17 Rough Hey Road , Grimsargh , Preston , Lancashire ,  , 01772 655666',
    'postcode' => 'PR2 5AR',
    'account_ref' => 'REDR001',
    'username' => 'REDR001',
    'hash' => '$2y$10$mC7Q5rQ63hIXPJw4H6v2jORZC/Lfz5IWXY9J5RBKroBKxdiBm06HO',
  ),
  28 => 
  array (
    'company' => 'Globe Blinds Ltd',
    'contact' => 'Carly Story',
    'email' => 'carly@globeblinds.com',
    'phone' => '',
    'address1' => '1 Model Cottage , Main Street, Slipton , Kettering , Northamptonshire',
    'postcode' => 'NN14 3AS',
    'account_ref' => 'GOB001',
    'username' => 'GOB001',
    'hash' => '$2y$10$echPf0xSz73Zv9aB99yqH.LmMmu8tqlnmVcWX8KRMaC2YXxaOt3RO',
  ),
  29 => 
  array (
    'company' => 'D & D Blinds',
    'contact' => 'Dany Reid',
    'email' => 'danddblinds@hotmail.com',
    'phone' => '07821538962',
    'address1' => '',
    'postcode' => '',
    'account_ref' => 'DDB001',
    'username' => 'DDB001',
    'hash' => '$2y$10$ByTKFIqHyzTMA2cQngD..uxMMu4IWlko/tUs5RIYGFVqmwsdJICBW',
  ),
  30 => 
  array (
    'company' => 'Perth Blinds',
    'contact' => 'Mike Thomas',
    'email' => 'perthblinds22@gmail.com',
    'phone' => '',
    'address1' => 'Bellour Farm , Methven , Perth',
    'postcode' => 'PH1 3RB',
    'account_ref' => 'PB001',
    'username' => 'PB001',
    'hash' => '$2y$10$pIB2pMG6DhoJmchDGdapuub5TVbUH3ZtRzRtqgihLnyaEKTkMm/e.',
  ),
  31 => 
  array (
    'company' => 'Falcon Blinds',
    'contact' => 'Darren Campling',
    'email' => 'falconblinds23@gmail.com',
    'phone' => '',
    'address1' => '93 Stone Brig Lane , Rohtwell , Leeds',
    'postcode' => 'LS26 0UD',
    'account_ref' => 'FB001',
    'username' => 'FB001',
    'hash' => '$2y$10$7/4CHBLayqjKBNx4qyeaAus3uVRYyrzKxgXl.Qk4BryJCtkwk9QaO',
  ),
  32 => 
  array (
    'company' => 'Robinson Blinds',
    'contact' => 'Craig Robinson',
    'email' => 'craigrobinson22@live.co.uk',
    'phone' => '01733345517',
    'address1' => '',
    'postcode' => '',
    'account_ref' => 'RB001',
    'username' => 'RB001',
    'hash' => '$2y$10$vfvtVLjbVZ3lViZEdy9dNemZ8a19GMjOi1VfvlqahtaxAoYWBedRG',
  ),
  33 => 
  array (
    'company' => 'Trade Blinds And Shutters Derby',
    'contact' => 'Timothy Jones',
    'email' => 'tjones3862@aol.com',
    'phone' => '',
    'address1' => '50 Carol Crescent , Chaddesden , Derby , Derbyshire',
    'postcode' => 'DE21 6PQ',
    'account_ref' => 'TBASD001',
    'username' => 'TBASD001',
    'hash' => '$2y$10$/u6AYk7V3F5z0awAlkVWuO0.VSS3hk2kdxOT1xtUHhfbuwj9kmN/e',
  ),
  34 => 
  array (
    'company' => 'Shop Just Blinds',
    'contact' => 'Nicky Eileen',
    'email' => 'sales@just-blinds.co.uk',
    'phone' => '08007839510',
    'address1' => 'Unit 11 Rosebridge Court , Higher Ince , Wigan ,  , 08007839510',
    'postcode' => 'WN1 3DP',
    'account_ref' => 'SJB001',
    'username' => 'SJB001',
    'hash' => '$2y$10$MbqZ5snzxERJQqH.D/PnM.Yivh6jk34Ogp4rY2RdJZ36nIc7PIz5.',
  ),
  35 => 
  array (
    'company' => 'Sagars Interiors',
    'contact' => 'Mike Brown',
    'email' => 'mike@sagarsinteriors.com',
    'phone' => '01926 856946',
    'address1' => '88 Warwick Road , Kenilworth ,  , 01926 856946',
    'postcode' => 'CV8 1HL',
    'account_ref' => 'SAGA001',
    'username' => 'SAGA001',
    'hash' => '$2y$10$k6FI3955jO4nXSA7QpPi.uJz04pwfNCMNitH.wY.3gZNemOrb7U0a',
  ),
  36 => 
  array (
    'company' => 'BCS Blinds',
    'contact' => 'Keith Payne',
    'email' => 'kpayne609@gmail.com',
    'phone' => '',
    'address1' => '100 St Giles Road , Coventry',
    'postcode' => 'CV7 9HA',
    'account_ref' => 'BCS001',
    'username' => 'BCS001',
    'hash' => '$2y$10$gkzAf/soJReDFSWcs86GFuJRMly2wwpuMhET3uP264OXywbFrDIvu',
  ),
  37 => 
  array (
    'company' => 'South Wales Blinds',
    'contact' => 'Lee Hawkes',
    'email' => 'info@southwalesblinds.com',
    'phone' => '03301335678',
    'address1' => 'The Old Hall Pentre Street , Glynneath , Neath ,  , 03301335678',
    'postcode' => 'SA11 5AH',
    'account_ref' => 'SWB001',
    'username' => 'SWB001',
    'hash' => '$2y$10$4Cp5vKxE8ULwgnsYBiuOyOvUEEvI4Et7apVP2FBVPF.gOMmv7S2um',
  ),
  38 => 
  array (
    'company' => 'Blind Corner',
    'contact' => 'Richard Tomalin',
    'email' => 'mail@blindcorner.co.uk',
    'phone' => '01604671189',
    'address1' => '',
    'postcode' => 'NN3 6AQ',
    'account_ref' => 'BLIN001',
    'username' => 'BLIN001',
    'hash' => '$2y$10$vNg5CCHpmlNEwcCqTO3NZem/MGtH9LdODjFl5NqcdaEBlQwEboS1C',
  ),
  39 => 
  array (
    'company' => 'Carlton Blinds Ltd',
    'contact' => 'Tim Lovelock',
    'email' => 'admin@carlton-blinds.co.uk',
    'phone' => '01407740340',
    'address1' => 'Unit 39a , Penrhos Industrial Estate , Holyhead , Anglesey ,  , 01407740340',
    'postcode' => 'LL65 2FD',
    'account_ref' => 'CAR001',
    'username' => 'CAR001',
    'hash' => '$2y$10$KgZHoCQWHgwy7VJrNZk/Q./yj3txeiN9uxJrI0qQpLnzuNeLGgyCG',
  ),
  40 => 
  array (
    'company' => 'Evereadyblinds And Shutters',
    'contact' => 'Jaiveer Sidhu',
    'email' => 'evereadyblinds@outlook.com',
    'phone' => '',
    'address1' => '77 Collingwood Road , Uxbridge , Hounslow Middlesex',
    'postcode' => 'UB8 3EL',
    'account_ref' => 'EBS001',
    'username' => 'EBS001',
    'hash' => '$2y$10$Km5u/yOEQHKCO.Jmwg.Ey.g.gTtxDEPNf46YqXMxs61CsZkrFtqza',
  ),
  41 => 
  array (
    'company' => 'Tay Forth Blinds',
    'contact' => 'Stewart Bell',
    'email' => 'sales@tayforthblinds.com',
    'phone' => '',
    'address1' => '28 Docherty Gardens , Glenrothes',
    'postcode' => 'KY7 5GA',
    'account_ref' => 'TFB001',
    'username' => 'TFB001',
    'hash' => '$2y$10$ELjHc5o0wymerf4Alrc6i./AiaZKHPEKyDtkBl7EfiwQuRHjXGvKe',
  ),
  42 => 
  array (
    'company' => 'Superior Blinds & Curtains',
    'contact' => 'Sandra',
    'email' => 'designs@superior-blinds.co.uk',
    'phone' => '01527 854990',
    'address1' => '25 High Street , Studley , Warwickshire ,  , 01527 854990',
    'postcode' => 'B80 7HN',
    'account_ref' => 'SUP001',
    'username' => 'SUP001',
    'hash' => '$2y$10$C1gl0CdCypyxKwpHDbl2SeCpalC7yNLKhFZz4KazD4tl.F/wNktGm',
  ),
  43 => 
  array (
    'company' => 'Sue Cardy Ltd',
    'contact' => 'Sue Cardy',
    'email' => 'info@suecardy.com',
    'phone' => '',
    'address1' => '',
    'postcode' => 'NN6 0BT',
    'account_ref' => 'SCL001',
    'username' => 'SCL001',
    'hash' => '$2y$10$tnIR467uxFwU9LlUza07cOrpnFlgCz7rZHf/o9D.FczhmsOhAozB2',
  ),
  44 => 
  array (
    'company' => 'Fabulous Blinds Northampton Ltd',
    'contact' => 'Renata Zdanowicz',
    'email' => 'renata.zdano@gmail.com',
    'phone' => '01604 600667',
    'address1' => '',
    'postcode' => '',
    'account_ref' => 'FNB001',
    'username' => 'FNB001',
    'hash' => '$2y$10$07f/DNHenZkSl1yKOOEe5ONw6w/xXl9OP7f0hTlYH6pqdRLLqj.jm',
  ),
  45 => 
  array (
    'company' => 'Acorn Blinds',
    'contact' => 'Bill Blazeby',
    'email' => 'billblazeby@yahoo.com',
    'phone' => '',
    'address1' => '',
    'postcode' => 'SG17 5LX',
    'account_ref' => 'ACORN001',
    'username' => 'ACORN001',
    'hash' => '$2y$10$34HxMxUeUjRvv4O3Xf4EnuN3.zPZ2eodTOH8OQ/Z/jZJoeOYe3vIC',
  ),
  46 => 
  array (
    'company' => 'Realm Blinds',
    'contact' => 'Russel Ulrich',
    'email' => 'hello@realmblinds.co.uk',
    'phone' => '',
    'address1' => '9 Broom Grove , Wrexham',
    'postcode' => 'LL13 9DL',
    'account_ref' => 'RB002',
    'username' => 'RB002',
    'hash' => '$2y$10$NW6SvYlBS5CBiwU2Hf1R3O1n.7J3Nnz2CErr1anMkbCTn7ndMnnyy',
  ),
  47 => 
  array (
    'company' => 'Noorblindz And Carpets',
    'contact' => 'Kasir Iqbal',
    'email' => 'Kasir786@googlemail.com',
    'phone' => '',
    'address1' => '',
    'postcode' => '',
    'account_ref' => 'NAC001',
    'username' => 'NAC001',
    'hash' => '$2y$10$4EV0FuGAWLjQUURBIY3mXeOlQj/tFSVBpag1fLw.mpDH29Jce/mdC',
  ),
  48 => 
  array (
    'company' => 'Abbey Blinds And Curtains',
    'contact' => 'Gary Buckley',
    'email' => 'abbeyblinds@yahoo.com',
    'phone' => '02476 711234',
    'address1' => '',
    'postcode' => 'CV5 9AF',
    'account_ref' => 'ABAC001',
    'username' => 'ABAC001',
    'hash' => '$2y$10$JDj19JgNQNaYtJgThS5Xwu6OWLKwuBKZ5pBB7ZwF5qclQAakoHSKi',
  ),
);

$dry = (($_GET['go'] ?? '') !== '1');
if ($dry) echo "DRY RUN — add ?go=1 to write. This shows what WOULD be created.\n\n";

$created = 0; $skipped = []; $failed = [];
foreach ($DATA as $a) {
    // Skip if an account already exists by code or exact name (e.g. a test tenant).
    $chk = $pdo->prepare("SELECT id, company_name FROM clients WHERE (account_ref IS NOT NULL AND account_ref = ?) OR LOWER(company_name) = LOWER(?) LIMIT 1");
    $chk->execute([$a['account_ref'], $a['company']]);
    if ($ex = $chk->fetch(PDO::FETCH_ASSOC)) { $skipped[] = $a['company'] . " -> already #{$ex['id']} ({$ex['company_name']})"; continue; }
    // Skip if the login email/username is already taken.
    $eu = $pdo->prepare("SELECT 1 FROM client_users WHERE (email IS NOT NULL AND email = ?) OR (username IS NOT NULL AND username = ?) LIMIT 1");
    $eu->execute([$a['email'] ?: '\0', $a['username']]);
    if ($eu->fetchColumn()) { $skipped[] = $a['company'] . " -> login email/username already in use"; continue; }

    if ($dry) { echo "  would create: {$a['company']}  (code {$a['username']}, {$a['email']})\n"; $created++; continue; }

    try {
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO clients (company_name, contact_name, email, phone, address1, postcode, account_ref, active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)")
            ->execute([$a['company'], $a['contact'] ?: null, $a['email'] ?: null, $a['phone'] ?: null, $a['address1'] ?: null, $a['postcode'] ?: null, $a['account_ref'] ?: null]);
        $cid = (int) $pdo->lastInsertId();
        try { $pdo->prepare("INSERT INTO client_settings (client_id) VALUES (?)")->execute([$cid]); } catch (Throwable $e) { /* table/route varies */ }
        if ($a['hash'] !== '') {
            $pdo->prepare("INSERT INTO client_users (client_id, email, username, full_name, password_hash, role, active, is_super_admin, email_verified_at) VALUES (?, ?, ?, ?, ?, 'admin', 1, 0, NOW())")
                ->execute([$cid, $a['email'] ?: null, $a['username'], $a['contact'] ?: $a['company'], $a['hash']]);
        }
        $pdo->commit();
        $created++;
        echo "  + {$a['company']}  (#{$cid}, {$a['username']})\n";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $failed[] = $a['company'] . ": " . $e->getMessage();
    }
}

echo "\n" . ($dry ? "WOULD create" : "Created") . ": {$created}\n";
if ($skipped) { echo "Skipped (already exist): " . count($skipped) . "\n"; foreach ($skipped as $s) echo "  - {$s}\n"; }
if ($failed)  { echo "FAILED: " . count($failed) . "\n"; foreach ($failed as $s) echo "  ! {$s}\n"; }
