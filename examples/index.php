<?php

use PierreGranger\ApidaeLecture ;

require_once(realpath(dirname(__FILE__.'')).'/../vendor/autoload.php') ;
require_once(realpath(dirname(__FILE__.'')).'/../config.inc.php') ;

$id_manif = 5622211 ; // ID d'une manifestation existante, publiée et qui fait partie d'une sélection de votre projet
$identifier_manif = 'SITRA2_EVE_5622211' ; // Idem
$id_selection = 97646 ; // ID d'une sélection de votre projet (si possible de manifestations)

$ApidaeLecture = new ApidaeLecture(array_merge($configApidaeLecture, ['debug' => true])) ;
$php_sapi_name = php_sapi_name() ;

function showResult($body)
{
    global $php_sapi_name ;
    if ($php_sapi_name == 'cli') {
        echo json_encode($body, JSON_PRETTY_PRINT).PHP_EOL ;
    } else {
        echo '<h3>Résultat</h3>' ;
        echo '<pre style="max-height:200px;font-size:.8em;background:#dff0d8;color:#468847;overflow:scroll;">' ;
        var_dump($body) ;
        echo '</pre>' ;
    }
}

$examples = [
    [
        'method' => 'objet-touristique/get-by-id',
        'params' => ['id'=>$id_manif]
    ],
    [
        'method' => 'objet-touristique/get-by-identifier',
        'params' => ['identifier'=>$identifier_manif]
    ],
    [
        'method' => 'recherche/list-identifiants',
        'params' => ['selectionIds'=>[$id_selection]],
    ],
    [
        'method' => 'recherche/list-objets-touristiques',
        'params' => ['selectionIds'=>[$id_selection]]
    ],
    [
        'method' => 'agenda/simple/list-identifiants',
        'params' => ['selectionIds'=>[$id_selection]]
    ],
    [
        'method' => 'agenda/simple/list-objets-touristiques',
        'params' => ['selectionIds'=>[$id_selection]]
    ],
    [
        'method' => 'agenda/detaille/list-identifiants',
        'params' => ['selectionIds'=>[$id_selection]]
    ],
    [
        'method' => 'agenda/detaille/list-objets-touristiques',
        'params' => ['selectionIds'=>[$id_selection]]
    ],
    [
        'method' => 'referentiel/communes',
        'params' => ['communeIds'=>[1236,1237,1238]]
    ],
    [
        'method' => 'referentiel/communes',
        'params' => ['codesInsee'=>['03001','03002','03003']]
    ],
    [
        'method' => 'referentiel/elements-reference',
        'params' => ['elementReferenceIds'=>[2281,4708]]
    ],
    [
        'method' => 'referentiel/criteres-internes'
    ],
    [
        'method' => 'referentiel/selections'
    ],
    [
        'method' => 'referentiel/selections-par-objet',
        'params' => ['referenceIds'=>[$id_manif]]
    ],
    [
        'method' => 'utilisateur/get-by-id',
        'params' => ['id'=>14015]
    ],
    [
        'method' => 'collaborateurs',
        'params' => ['referenceIds'=>[$id_manif]]
    ]
] ;



foreach ($examples as $example) {
    if ($php_sapi_name == 'cli') {
        echo PHP_EOL . '$ApidaeLecture->get('.$example['method'].')'.PHP_EOL ;
        echo json_encode($example['params'], JSON_PRETTY_PRINT) . PHP_EOL ;
    } else {
        echo '<hr /><h1>$ApidaeLecture->get(\''.$example['method'].'\',$params)</h1>' ;
        if (isset($example['params']) && is_array($example['params'])) {
            echo '<h3>$params</h3><pre>'.print_r($example['params'], true).'</pre>' ;
        }
    }
    try {
        $result = $ApidaeLecture->call($example['method'], @$example['params']) ;
        showResult($result) ;
    } catch (Exception $e) {
        echo $ApidaeLecture->showException($e) ;
    }
}
