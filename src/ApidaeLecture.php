<?php

namespace PierreGranger;

use GuzzleHttp\Client;
use GuzzleHttp\UriTemplate\UriTemplate;
use Guzzle\Service\Description\ServiceDescription;
use GuzzleHttp\Exception\RequestException;
use PierreGranger\ApidaeException;

class ApidaeLecture extends ApidaeCore
{

	protected $projet_consultation_projetId = null;
	protected $projet_consultation_apiKey = null;

	/**
	 * Dans la config suivante, les paramètres type_apidae correpondent au type d'appel proposé par l'API :
	 * 	uri	: concerne les url du type /get-by-id/{id}?apiKey=...&projetId=...
	 * 	query : concerne les url du type /list-objets-touristiques/?query={"apiKey":...,"projetId":...}
	 * 
	 * Le but est de permettre au développeur de ne pas du tout se préoccuper du format demandé par l'API.
	 * Il ne doit s'occupe que de passer ses paramètres à la fonction, c'est ensuite cette classe qui les rangera au bon endroit.
	 */

	public static $operations;
	public static $queryOptions;

	private $debugQuery = false;

	public $lastRequest;

	public function __construct(array $params = null)
	{

		parent::__construct($params);

		if (isset($params['projet_consultation_projetId']) && preg_match('#^[0-9]+$#', $params['projet_consultation_projetId']))
			$this->projet_consultation_projetId = $params['projet_consultation_projetId'];
		else
			throw new \Exception('missing projet_consultation_projetId');

		if (isset($params['projet_consultation_apiKey']) && preg_match('#^[a-zA-Z0-9]{1,20}$#', $params['projet_consultation_apiKey']))
			$this->projet_consultation_apiKey = $params['projet_consultation_apiKey'];
		else
			throw new \Exception('missing projet_consultation_apiKey');

		if (isset($params['debugQuery'])) $this->debugQuery = $params['debugQuery'];

		$this->client = new Client(
			['base_uri' => $this->url_api() . 'api/v002/']
		);

		self::$operations = json_decode(file_get_contents(realpath(dirname(__FILE__)) . '/operations.json'), true);
		if (json_last_error() !== JSON_ERROR_NONE) throw new ApidaeException('operations ko', ApidaeException::NO_JSON);
		self::$queryOptions = json_decode(file_get_contents(realpath(dirname(__FILE__)) . '/queryOptions.json'), true);
		if (json_last_error() !== JSON_ERROR_NONE) throw new ApidaeException('queryOptions ko', ApidaeException::NO_JSON);
	}

	/**
	 * Appel d'une des méthodes de l'API de consultation Apidae
	 * La liste des méthodes acceptées ici se trouve dans operations.json
	 * Contrairement au fonctionnement d'Apidae, qui demande selon les cas de mettre les variables à différents endroits 
	 * 	Exemples :
	 * 		/get/1234?apiKey=...&projetId=...
	 * 		/autre/?query={"id":1234,"apiKey":....,"projetId":....}
	 * ici on ne demande pas à l'utilisateur de savoir où se range une variable.
	 * Toutes les variables sont passées dans $params, c'est ensuite la fonction qui s'occupera de placer la variable au bon endroit et au bon format
	 * Dans le query si nécessaire, dans l'uri ou en GET selon les cas
	 * Cette fonction s'occupe aussi de vérifier les paramètres passés, y compris ceux compris dans query (responseFields, dateDebug, radius...)
	 * Elle fournira également, si $this->debug == true, le détail des erreurs s'il y en a pour faciliter le travail du développeur
	 */
	public function call($method, $params = null, $format = 'object')
	{

		if (!is_array($params)) $params = [];

		$auth = ['projetId' => $this->projet_consultation_projetId, 'apiKey' => $this->projet_consultation_apiKey];

		$erreurs = [];

		if (!isset($this::$operations[$method])) {
			throw new ApidaeException('bad method', null, [
				'debug' => $this->debug,
				'details' => 'La méthode utilisée (' . $method . ') ne fait pas partie de la liste ci-dessous',
				'methodes' => array_keys($this::$operations)
			]);
		}

		/**
		 * $operation_desc contient la description en Array de l'opération
		 */
		$operation = $this::$operations[$method];

		$operation_uri = isset($operation['uri']) ? $operation['uri'] : $method;
		$type_apidae = $operation['type_apidae'];

		$traites = [];
		$params_uri = [];
		/**
		 * $apidae_query désigne le $_GET['query'] = {"id":..,"apiKey":..}
		 * ne pas confondre avec $request_query qui sera le paramètre Guzzle
		 */
		$apidae_query = [];
		/**
		 * $request_query correspond au paramètre ['query' => ["apiKey":"...."]]
		 * qui sera passé à Guzzle pour lancer la requête
		 */
		$request_query = [];

		/**
		 * Au final on aura quelque chose du genre :
		 * $request_query = [
		 * 	"query" : "{"id":....,apiKey":...,"projetId":....}"
		 * ]
		 */


		/**
		 * Si on est sur un type "query", tous les paramètres sont passés en json dans query.
		 * Y compris nos apiKey et projetId.
		 * Pour une url souhaitée sous cette forme :
		 * /get/?query={"id":....,"selectionsIds:[.,.,.],"apiKey":....,"projetId":....}
		 * ici on ne traite que les paramètres dans ?query={...}
		 */
		if ($type_apidae == 'query') {
			// On commence par ajouter les apiKey et projetId
			$apidae_query = $auth;

			if ($operation['parameters'] == 'queryOptions') {
				foreach (self::$queryOptions as $param => $desc) {
					$params_traites[] = $param;

					$erreurs_param = [];

					if (isset($desc['required']) && !isset($params[$param]))
						$erreurs_param[] = $param . ' required';

					if (!isset($params[$param])) continue;

					$value = $params[$param];

					if ($desc['type'] == 'array') {
						if (!is_array($value)) $erreurs_param[] = $param . ' must be an array';
						else {
							if ($desc['items']['type'] == 'int' && sizeof(array_filter($value, "is_int")) != sizeof($value))
								$erreurs_param[] = $param . ' must be an array of integers';
							if ($desc['items']['type'] == 'string' && sizeof(array_filter($value, "is_string")) != sizeof($value))
								$erreurs_param[] = $param . ' must be an array of strings';
						}
					}

					if ($desc['type'] == 'int' && !is_int($value)) $erreurs_param[] = $param . ' must be an integer';
					if ($desc['type'] == 'string' && !is_string($value)) $erreurs_param[] = $param . ' must be a string';

					// Vérification du radius
					if ($param == 'radius') {
						if (!$this->isJson($value)) $erreurs_param[] = $param . ' must be a json value (ex:' . $desc['examples'] . ')';
					}

					if (sizeof($erreurs_param) > 0) $erreurs = array_merge($erreurs, $erreurs_param);
					$apidae_query[$param] = $value;
				}
			}
		}

		/**
		 * On regarde tous les paramètres possibles
		 * Si on est sur un type_apidae = query, il n'y a normalement pas de paramètre...
		 * On fait quand même le test au cas où on aurait un jour un appel sous la forme :
		 * /get/{id}?query={"apiKey":...,"projetId":...}&test=2
		 * Ici on ne traite que des paramètres :
		 * 	{id}, parce que location=uri
		 * 	test=2, parce que location=query
		 */
		if (isset($operation['parameters']) && is_array($operation['parameters'])) {
			foreach ($operation['parameters'] as $param => $desc) {
				$params_traites[] = $param;

				if (isset($desc['required']) && !isset($params[$param]))
					$erreurs[] = $param . ' required';

				if (!isset($params[$param])) continue;

				$value = $params[$param];

				if ($desc['type'] == 'int' && !is_int($value))
					$erreurs[] = $param . ' must be an integer';

				if ($desc['type'] == 'string' && !is_string($value))
					$erreurs[] = $param . ' must be a string';

				if ($desc['type'] == 'array') {
					if (!is_array($value)) $erreurs[] = $param . ' must be an array';
					else {
						if ($desc['items']['type'] == 'int' && sizeof(array_filter($value, "is_int")) != sizeof($value))
							$erreurs[] = $param . ' must be an array of integers';
						if ($desc['items']['type'] == 'string' && sizeof(array_filter($value, "is_string")) != sizeof($value))
							$erreurs[] = $param . ' must be an array of strings';
					}
				}

				if ($desc['location'] == 'uri') $params_uri[$param] = $value;
				elseif ($desc['location'] == 'query') $request_query[$param] = $value;
				elseif ($desc['location'] == 'query_apidae') $apidae_query[$param] = $value;
			}
		}

		if ($type_apidae == 'uri') {
			$request_query = array_merge($request_query, $auth);
		}

		if (sizeof($apidae_query) > 0)
			$request_query['query'] = json_encode($apidae_query, true);

		// Remplace les {id} par leurs valeurs dans l'URL
		$relative_uri = UriTemplate::expand($operation_uri, $params_uri);

		$diff = array_diff(array_keys($params), $params_traites);
		if (sizeof($diff) > 0) {
			foreach ($diff as $p) $erreurs[] = 'Paramètre ' . $p . ' inconnu';
		}

		if (sizeof($erreurs) > 0) {
			throw new ApidaeException('errors', ApidaeException::INVALID_PARAMETER, [
				'debug' => $this->debug,
				'errors' => $erreurs
			]);
		}

		try {
			$response = $this->client->get($relative_uri, [
				'query' => $request_query,
				'debug' => $this->debugQuery
			]);
		} catch (RequestException $e) {
			$response = $e->getResponse();
			$request = $e->getRequest();
			$code = $response->getStatusCode();

			if ($code == 404) {
				return false;
			}

			if ($this->isJson($response->getBody())) {
				throw new ApidaeException('API RequestException : ' . $response->getStatusCode(), ApidaeException::INVALID_HTTPCODE, [
					'debug' => $this->debug,
					'response' => $response,
					//'request' => $request->getUri()
				]);
			}
		}

		if ($response->getStatusCode() == 200) {
			$body = $response->getBody();
			if ($this->isJson($body)) {
				if ($format == 'object') return json_decode($body);
				elseif ($format == 'array') return json_decode($body, true);
			}
			return $body;
		} else {
			throw new ApidaeException('API bad_response : ' . $response->getStatusCode(), ApidaeException::INVALID_HTTPCODE, [
				'debug' => $this->debug,
				'request' => $request,
				'response' => $response,
			]);
		}
	}
}
