<?php

namespace App;

use App\DAO\LoginUserDAO;

/**
 * Clase base para Business Logic Layer
 * Incluye inyección de dependencias para DBConnectorPDO
 *
 * @author Dey Gordillo <dey.gordillo@simpledatacorp.com>
 * @version 2.0
 */
class BaseClass extends BaseComponent
{
    /**
     * @var string Operación SOAP actual
     */
    protected string $operation = '';

    /**
     * @var array Estructura de respuesta SOAP
     */
    protected array $response = [];

    // Métodos específicos de BLL
    protected function setOperation(string $operation): void
    {
        $this->operation = $operation;
    }
    public function getOperation(): string
    {
        return $this->operation;
    }

    protected function setBuildResponse(array $xmlResponse = []): array
    {
        $this->response = [
            'Header' => [
                'DateTime' => date('c'),
                'Operation' => $this->getOperation()
            ],
            'Return' => [
                'Code' => $this->getError(),
                'Description' => $this->getErrorDescription()
            ]
        ];

        if (!empty($xmlResponse)) {
            $this->response['Data'] = $xmlResponse;
        }

        return $this->response;
    }

    protected function setErrorResponse(string $errorDescription = "", string $error = "500", array $xmlResponse = []): array
    {
        $this->setError($error);
        $this->setErrorDescription($errorDescription);
        return $this->setBuildResponse($xmlResponse);
    }

    /**
     * Request a Endpoint 1
     * Estructura Base de Properties -> Values
     * @param array $headerProperties
     * - Operation
     * @param array $propertiesValues
     * [
     *    'Name'  => 'Value'
     * ]
     */
    public function requestEndpoint1($soapUrl = '', $headerProperties = [], $propertiesValues = [])
    {
        $operation = $headerProperties['Operation'] ?? 'unknown';

        $return = new \stdClass();
        $return->errno = 1;
        $return->error = 'Not executed';
        $return->httpcode = 500;
        $return->xml_post_string = '';
        $return->response = '';
        $return->time = 0;
        try {
            $this->log->writeLog("{$this->tx} " . __FUNCTION__ . " url: {$soapUrl} header: " . json_encode($headerProperties) . " properties: " . json_encode($propertiesValues) . "\n");
            $idUser     = $headerProperties['idUser'] ?? '';
            $latitude   = $headerProperties['Latitude'] ?? '';
            $longitude  = $headerProperties['Longitude'] ?? '';

            $requestData = '';
            foreach ($propertiesValues as $field => $value) {
                $requestData .= '<Property xsi:type="urn:Property">
                    <Name xsi:type="xsd:string">' . $field . '</Name>
                    <Value xsi:type="xsd:string">' . $value . '</Value>
                </Property>';
            }

            $xml_post_string = '<soapenv:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:Demo">
            <soapenv:Header/>
                <soapenv:Body>
                    <urn:OutputRequest soapenv:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
                        <OperationRequest xsi:type="urn:OperationRequest" xmlns:urn="urn:Configurationwsdl">
                            <Header xsi:type="urn:HeaderRequest">
                                <Company xsi:type="xsd:string"></Company>
                                <Login xsi:type="xsd:string"></Login>
                                <PasswordHash xsi:type="xsd:string"></PasswordHash>
                                <DateTime xsi:type="xsd:string">' . date('c') . '</DateTime>
                                <Operation xsi:type="xsd:string">' . $operation . '</Operation>
                                <Destination xsi:type="xsd:string"></Destination>
                                <Id xsi:type="xsd:int"></Id>
                                <idUser xsi:type="xsd:int">' . $idUser . '</idUser>
                                <Latitude xsi:type="xsd:string">' . $latitude . '</Latitude>
                                <Longitude xsi:type="xsd:string">' . $longitude . '</Longitude>
                            </Header>
                            <Data xsi:type="urn:DataRequest">
                                ' . $requestData . '
                            </Data>
                        </OperationRequest>
                    </urn:OutputRequest>
                </soapenv:Body>
            </soapenv:Envelope>';

            $headers = array(
                "Content-type: text/xml;charset=\"utf-8\"",
                "Accept: text/xml",
                "Cache-Control: no-cache",
                "SOAPAction: \"urn:Demo#OutputRequest\"",
                "Content-length: " . strlen($xml_post_string),
            );

            // $this->log->writeLog("{$this->tx} SOAP ".$operation." request: " . print_r($xml_post_string, true) . "\n");
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
            curl_setopt($ch, CURLOPT_URL, $soapUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_post_string);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $initm    = microtime(true);
            $response = curl_exec($ch);
            $endtm    = microtime(true);
            $this->log->writeLog("{$this->tx} SOAP " . $operation . " time: " . print_r($endtm - $initm, true) . "\n");
            $return = new \stdClass();
            $return->errno = curl_errno($ch);
            $return->error = curl_error($ch);
            $return->httpcode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $return->xml_post_string = $xml_post_string;
            $return->response = $response;
            $return->time = $endtm - $initm;

            if ($return->error) {
                $this->log->writeLog("{$this->tx} Error curl: " . print_r($return->error, true) . "\n");
            } else {
                if ((int) $return->httpcode != 200) {
                    $this->log->writeLog("{$this->tx} " . __FUNCTION__ . " error http_code: " . print_r($return->httpcode, true) . "\n");
                    $this->log->writeLog("{$this->tx} " . __FUNCTION__ . " soap response: " . print_r($return->response, true) . "\n");
                }
            }
            unset($response, $xml_post_string);
            curl_close($ch);
        } catch (\Throwable $th) {
            $this->log->writeLog("{$this->tx} SOAP " . $operation . " Error: " . print_r($th->getMessage(), true) . "\n");
        }
        return $return;
    }
}
