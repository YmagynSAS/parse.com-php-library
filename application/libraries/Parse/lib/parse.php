<?php

class parseRestClient
{

    private $_appid = '';
    private $_masterkey = '';
    private $_restkey = '';
    private $_parseurl = '';

    public $data;
    public $requestUrl = '';
    public $returnData = '';

    public function __construct()
    {
        $ci =& get_instance();
        $ci->config->load('parse');
        $this->_appid = $ci->config->item('app_id');
        $this->_masterkey = $ci->config->item('master_key');
        $this->_restkey = $ci->config->item('rest_key');
        $this->_parseurl = $ci->config->item('parse_url');

        if (empty($this->_appid) || empty($this->_restkey) || empty($this->_masterkey)) {
            $this->throwError('You must set your Application ID, Master Key and REST API Key');
        }

        $version = curl_version();
        $ssl_supported = ($version['features'] & CURL_VERSION_SSL);

        if (!$ssl_supported) {
            $this->throwError('CURL ssl support not found');
        }

    }

    /*
     * All requests go through this function
     *
     *
     */
    public function request($args)
    {
        $isFile = false;
        $c = curl_init();
        curl_setopt($c, CURLOPT_TIMEOUT, 30);
        curl_setopt($c, CURLOPT_USERAGENT, 'parse.com-php-library/2.0');
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLINFO_HEADER_OUT, true);
        if (substr($args['requestUrl'], 0, 5) == 'files') {
            curl_setopt($c, CURLOPT_HTTPHEADER, array(
                'Content-Type: ' . $args['contentType'],
                'X-Parse-Application-Id: ' . $this->_appid,
                'X-Parse-Master-Key: ' . $this->_masterkey
            ));
            $isFile = true;
        } else if (substr($args['requestUrl'], 0, 5) == 'users' && isset($args['sessionToken'])) {
            curl_setopt($c, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'X-Parse-Application-Id: ' . $this->_appid,
                'X-Parse-REST-API-Key: ' . $this->_restkey,
                'X-Parse-Session-Token: ' . $args['sessionToken']
            ));
        } else {
            curl_setopt($c, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'X-Parse-Application-Id: ' . $this->_appid,
                'X-Parse-REST-API-Key: ' . $this->_restkey,
                'X-Parse-Master-Key: ' . $this->_masterkey
            ));
        }
        curl_setopt($c, CURLOPT_CUSTOMREQUEST, $args['method']);
        curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
        $url = $this->_parseurl . $args['requestUrl'];

        if ($args['method'] == 'PUT' || $args['method'] == 'POST') {
            if ($isFile) {
                $postData = $args['data'];
            } else {
                $postData = json_encode($args['data']);
            }

            curl_setopt($c, CURLOPT_POSTFIELDS, $postData);
        }

        if ($args['requestUrl'] == 'login') {
            $urlParams = http_build_query($args['data'], '', '&');
            $url = $url . '?' . $urlParams;
        }
        if (array_key_exists('urlParams', $args)) {
            $urlParams = http_build_query($args['urlParams'], '', '&');
            $url = $url . '?' . $urlParams;
        }

        curl_setopt($c, CURLOPT_URL, $url);

        $unique_key = md5(serialize($url) . serialize($args['method']) . serialize(isset($postData) ? $postData : NULL));
        if (isset($GLOBALS["profiler"][$unique_key])) {
            $response = $GLOBALS["profiler"][$unique_key]["response"];
            $responseCode = $GLOBALS["profiler"][$unique_key]["responseCode"];
        } else {
            $response = curl_exec($c);
            $responseCode = curl_getinfo($c, CURLINFO_HTTP_CODE);
            $curl_infos = curl_getinfo($c);
            $curl_infos["method"] = $args['method'];
            $curl_infos["data"] = isset($postData) ? $postData : NULL;
            $curl_infos["unique_key"] = $unique_key;
            $GLOBALS["profiler"][$unique_key] = [
                "response" => $response,
                "responseCode" => $responseCode,
                "infos" => $curl_infos
            ];
        }


        $expectedCode = array('200');
        if ($args['method'] == 'POST' && substr($args['requestUrl'], 0, 4) != 'push') {
            // checking if it is not cloud code - it returns code 200
            if (substr($args['requestUrl'], 0, 9) != 'functions') {
                $expectedCode = array('200', '201');
            }
        }

        //BELOW HELPS WITH DEBUGGING
        /*
        if(!in_array($responseCode,$expectedCode)){
            //print_r($response);
            //print_r($args);
        }
        */
        return $this->checkResponse($response, $responseCode, $expectedCode);
    }

    public function dataType($type, $params)
    {
        if ($type != '') {
            switch ($type) {
                case 'date':
                    $return = array(
                        "__type" => "Date",
                        "iso" => date("c", strtotime($params))
                    );
                    break;
                case 'bytes':
                    $return = array(
                        "__type" => "Bytes",
                        "base64" => base64_encode($params)
                    );
                    break;
                case 'pointer':
                    $return = array(
                        "__type" => "Pointer",
                        "className" => $params[0],
                        "objectId" => $params[1]
                    );
                    break;
                case 'geopoint':
                    $return = array(
                        "__type" => "GeoPoint",
                        "latitude" => floatval($params[0]),
                        "longitude" => floatval($params[1])
                    );
                    break;
                case 'file':
                    $return = array(
                        "__type" => "File",
                        "name" => $params[0],
                    );
                    break;
                case 'increment':
                    $return = array(
                        "__op" => "Increment",
                        "amount" => $params[0]
                    );
                    break;
                case 'decrement':
                    $return = array(
                        "__op" => "Decrement",
                        "amount" => $params[0]
                    );
                    break;
                default:
                    $return = false;
                    break;
            }

            return $return;
        }
    }

    public function throwError($msg, $code = 0)
    {
        throw new ParseLibraryException($msg, $code);
    }

    private function checkResponse($response, $responseCode, $expectedCode)
    {
        //TODO: Need to also check for response for a correct result from parse.com
        if (!in_array($responseCode, $expectedCode)) {
            log_message('error', $responseCode);
            $error = json_decode($response);
            $this->throwError($error->error, $error->code);
        } else {
            //check for empty return
            if ($response == '{}') {
                return true;
            } else {
                return json_decode($response);
            }
        }
    }
}


class ParseLibraryException extends Exception
{
    private function translateError($code) {
        switch ($code) {
            default:
            case -1:
                $message = "Une erreur inconnue est survenue.";
                break;
            case 1:
                $message = "Une erreur est survenue sur les serveurs Parse.";
                break;
            case 100:
                $message = "Erreur lors de la connection aux serveurs Parse";
                break;
            case 101:
                $message = "L'objet recherché n'existe pas";
                break;
            case 102:
                $message = "You tried to query with a datatype that doesn't support it, like exact matching an array or object";
                break;
            case 103:
                $message = "Le nom de la classe est manquante ou invalide";
                break;
            case 104:
                $message = "Aucun objectId définie";
                break;
            case 105:
                $message = "Clé invalide";
                break;
            case 106:
                $message = "Pointeur invalide";
                break;
            case 107:
                $message = "Le format JSON reçu est invalide";
                break;
            case 108:
                $message = "L'option que vous tenté d'accéder n'est disponible que pour vos phases de test";
                break;
            case 109:
                $message = "Vous devez appeler Parse.initialize avant d'accéder à la librairie Parse";
                break;
            case 111:
                $message = "Type du champ invalide.";
                break;
            case 112:
                $message = "Nom du channel invalide";
                break;
            case 115:
                $message = "Votre push est mal formatté";
                break;
            case 116:
                $message = "Votre objet dépasse la taille maximum autorisé";
                break;
            case 119:
                $message = "Cette opération n'est pas autorisé";
                break;
            case 120:
                $message = "Le résultat n'a pu être trouvé dans le cache";
                break;
            case 121:
                $message = "Une clé invalide a été utilisé dans votre objet";
                break;
            case 122:
                $message = "Le nom de votre fichier est invalide";
                break;
            case 123:
                $message = "ACL invalide";
                break;
            case 124:
                $message = "Temps de réponse dépassé. Cette erreur peut apparaitre quand votre appel demande trop de ressources";
                break;
            case 125:
                $message = "Adresse email invalide";
                break;
            case 137:
                $message = "Votre valeur dans un champ unique est déjà utilisé";
                break;
            case 139:
                $message = "Nom du rôle invalide";
                break;
            case 140:
                $message = "Vous avez dépassé les quotats de votre application";
                break;
            case 141:
                $message = "L'éxecution de votre cloud code a échoué";
                break;
            case 200:
                $message = "Le nom d'utilisateur est absent ou vide";
                break;
            case 201:
                $message = "Le mot de passe est absent ou vide";
                break;
            case 202:
                $message = "Ce nom d'utilisateur est déjà utilisé";
                break;
            case 203:
                $message = "Cette adresse email est déjà utilisé";
                break;
            case 204:
                $message = "L'adresse email est manquante";
                break;
            case 205:
                $message = "L'utilisateur recherché avec l'adresse donné n'a pu être trouvé";
                break;
            case 206:
                $message = "Un utilisateur ne peut être modifier sans une session valide";
                break;
            case 207:
                $message = "Un utilisateur ne peut être crée uniquement via la fonction signup";
                break;
            case 208:
                $message = "Error code indicating that an an account being linked is already linked to another user.";
                break;
            case 250:
                $message = "Error code indicating that a user cannot be linked to an account because that account's id could not be found.";
                break;
            case 251:
                $message = "Error code indicating that a user with a linked (e.g. Facebook) account has an invalid session.";
                break;
            case 252:
                $message = "Error code indicating that a service being linked (e.g. Facebook or Twitter) is unsupported.";
                break;
        }

        return $message;
    }

    public function __construct($message, $code = 0, Exception $previous = null)
    {
        //codes are only set by a parse.com error
        if ($code != 0) {
            $message = "parse.com error: " . $message;
        }

        $messageTr = $this->translateError($code);
        parent::__construct($messageTr, $code, $previous);
    }

    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }

}

?>