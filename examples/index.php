<?php

    use PierreGranger\ApidaeLecture ;

    require_once(realpath(dirname(__FILE__.'')).'/../vendor/autoload.php') ;
    require_once(realpath(dirname(__FILE__.'')).'/../config.inc.php') ;
    
    $ApidaeLecture = new ApidaeLecture(array_merge($configApidaeLecture,Array(
        'debug' => true
    ))) ;
    
    function showResult($body) {
        echo '<h3>Résultat</h3>' ;
        echo '<pre style="max-height:200px;font-size:.8em;background:#dff0d8;color:#468847;overflow:scroll;">' ;
            echo $body ;
        echo '</pre>' ;
    }

    $examples = Array(
        Array(
            'method' => 'objet-touristique/get-by-id',
            'params' => Array('id'=>5679881)
        ),
        Array(
            'method' => 'objet-touristique/get-by-identifier',
            'params' => Array('identifier'=>'SITRA2_HOT_5679881')
        ),
        Array(
            'method' => 'recherche/list-identifiants',
            'params' => Array('selectionIds'=>[97646]),
        ),
        Array(
            'method' => 'recherche/list-objets-touristiques',
            'params' => Array('selectionIds'=>[97646])
        ),
        Array(
            'method' => 'agenda/simple/list-identifiants',
            'params' => Array('selectionIds'=>[97646])
        ),
        Array(
            'method' => 'agenda/simple/list-objets-touristiques',
            'params' => Array('selectionIds'=>[97646])
        ),
        Array(
            'method' => 'agenda/detaille/list-identifiants',
            'params' => Array('selectionIds'=>[97646])
        ),
        Array(
            'method' => 'agenda/detaille/list-objets-touristiques',
            'params' => Array('selectionIds'=>[97646])
        ),
        Array(
            'method' => 'referentiel/communes',
            'params' => Array('communeIds'=>[1236,1237,1238])
        ),
        Array(
            'method' => 'referentiel/communes',
            'params' => Array('codesInsee'=>['03001','03002','03003'])
        ),
        Array(
            'method' => 'referentiel/elements-reference',
            'params' => Array('elementReferenceIds'=>[2281,4708])
        ),
        Array(
            'method' => 'referentiel/criteres-internes'
        ),
        Array(
            'method' => 'referentiel/selections'
        ),
        Array(
            'method' => 'referentiel/selections-par-objet',
            'params' => Array('referenceIds'=>[5622211])
        ),
        
        Array(
            'method' => 'utilisateur/get-by-id',
            'params' => Array('id'=>14015)
        )
    ) ;

    foreach ( $examples as $example )
    {
        echo '<hr /><h1>$ApidaeLecture->get(\''.$example['method'].'\',$params)</h1>' ;
        if ( isset($example['params']) && is_array($example['params']) )
            echo '<h3>$params</h3><pre>'.print_r($example['params'],true).'</pre>' ;
        try {
            $result = $ApidaeLecture->call($example['method'],@$example['params']) ;
            showResult($result) ;
        } catch ( Exception $e ) { echo $ApidaeLecture->showException($e) ; }
    }
