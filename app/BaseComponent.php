<?php

namespace App;

use Libraries\DBConnectorPDO;
use Libraries\DayLog;

/**
 * Clase base común para BLL y DAO
 * Evita duplicación de código (DRY principle)
 */
abstract class BaseComponent
{
    /**
     * @var DBConnectorPDO Conexión a la base de datos inyectada
     */
    protected DBConnectorPDO $db;

    /**
     * @var string Código de error actual
     */
    protected string $error = '0';

    /**
     * @var string Descripción del error actual
     */
    protected string $errorDescription = '';

    /**
     * @var DayLog|null Instancia del registro de día
     */
    protected ?DayLog $log = null;

    /**
     * @var string|null Identificador de transacción
     */
    protected ?string $tx = null;

    /**
     * @var int|null ID de empresa actual
     */
    protected ?int $idEmpresa = null;

    /**
     * Constructor base con inyección de dependencias
     */
    public function __construct(DBConnectorPDO $db)
    {
        $this->db = $db;
        $this->tx = substr(uniqid(), 3);
        // Usar solo el nombre de la clase sin namespace para el log
        $className = (new \ReflectionClass(static::class))->getShortName();
        $this->log = new DayLog(BASE_HOME_PATH, $className);
    }

    // Métodos comunes de error
    public function setError(string $error): void
    {
        $this->error = $error;
    }
    public function getError(): string
    {
        return $this->error;
    }

    public function setErrorDescription(string $description): void
    {
        $this->errorDescription = $description;
    }
    public function getErrorDescription(): string
    {
        return $this->errorDescription;
    }

    // Métodos comunes de logging
    public function setLog(DayLog $log): void
    {
        $this->log = $log;
    }
    public function getLog(): ?DayLog
    {
        return $this->log;
    }

    public function setTx(string $tx): void
    {
        $this->tx = $tx;
        $this->db->setTx($tx);
    }
    public function getTx(): ?string
    {
        return $this->tx;
    }

    // Métodos comunes de empresa
    public function setIdEmpresa(int $idEmpresa): void
    {
        $this->idEmpresa = $idEmpresa;
    }
    public function getIdEmpresa(): ?int
    {
        return $this->idEmpresa;
    }

    /**
     * Realiza una petición SOAP a un endpoint específico
     * @param string $soapUrl URL del servicio SOAP
     * @param array $headerProperties Propiedades del header SOAP
     * @param array $propertiesValues Valores de propiedades para la petición
     * @return \stdClass Respuesta del servicio
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
