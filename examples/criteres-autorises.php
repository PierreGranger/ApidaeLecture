<?php

use PierreGranger\ApidaeLecture ;

require_once(realpath(dirname(__FILE__.'')).'/../vendor/autoload.php') ;
require_once(realpath(dirname(__FILE__.'')).'/../config.inc.php') ;

$ApidaeLecture = new ApidaeLecture(array_merge($configApidaeLecture, ['debug' => true])) ;

$res = $ApidaeLecture->call('referentiel/elements-reference/autorises', [
    'type'=>'FETE_ET_MANIFESTATION',
    'field' => 'descriptionTarif.modesPaiement', // Quels modes de paiement sont autorisés pour les fêtes et manifestations de type "Culture" et de catégorie "Chasse aux oeufs" ?
    'contexte' => [
        [
            'field' => 'informationsFeteEtManifestation.typesManifestation',
            'elementReference' => [
                'elementReferenceType' => 'FeteEtManifestationType',
                'id' => 1958, // Culture
            ],
        ],
        [
            'field' => 'informationsFeteEtManifestation.categories',
            'elementReference' => [
                'elementReferenceType' => 'FeteEtManifestationCategorie',
                'id' => 4641, // Chasse aux oeufs
            ],
        ],
    ]
]) ;

echo implode("\n", cleanResult($res)) ;

function cleanResult(mixed $data) {
    return array_map(function($item) {
        return @$item->id.' '.@$item->libelleFr ;
    }, $data);
}