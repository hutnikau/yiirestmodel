<?php
/**
 * ApiXmlResponseProvider class file.
 * @author Oleg Gutnikov <goodnickoff@gmail.com>
 * @package api
 */ 

/**  
 * ApiXmlResponseProvider implements a response provider based on ApiResponseProvider
 * 
 * ApiXmlResponseProvider provides end user data in xml format.
 */
class ApiXmlResponseProvider extends ApiResponseProvider
{
    /**
     * Function converts $data array to xml format, sends it to client and terminates the application.
     * Usage example:
     * <pre>
     *   $this->sendData(
     *       array(...),
     *       200,
     *       array(
     *           "Content-Range: items $offset-$limit/$total",
     *           ...
     *       )
     *   );
     * </pre>
     * @param array $data 
     * @param int $status code.
     * @param array $headers http headers array.
     */
    public function sendData($data, $status = 200, array $headers=array())
    {
        if($status === null) {
            $status = $this->statusCode;
        }

        $xml = new SimpleXMLElement("<?xml version=\"1.0\"?><response></response>");
        $this->arrayToXml($data, $xml);
        
        $this->layout = false;
        $status_header = 'HTTP/1.1 ' . $status . ' ' . $this->_getStatusCodeMessage($status);
        header($status_header);
        header('Content-type: text/xml');

        foreach($headers as $header){
            header($header);
        }

        echo $xml->asXML();
        Yii::app()->end();
    }
    
    private function arrayToXml($data, &$xml) {
        foreach($data as $key => $value) {
            if(is_array($value)) {
                if(!is_numeric($key)){
                    $subnode = $xml->addChild("$key");
                    $this->arrayToXml($value, $subnode);
                }
                else{
                    $subnode = $xml->addChild("item$key");
                    $this->arrayToXml($value, $subnode);
                }
            }
            else {
                $xml->addChild("$key",htmlspecialchars("$value"));
            }
        }
    }
    
    /**
     * Function displays "access denied" message to end users 
     * with 403 http status code and terminates the application.
     * @return null
     */
    public function accessDenied()
    {
        $this->sendData( 
            array( 
                'error'=>array('access'=>Yii::t('yii', 'You do not have sufficient permissions to access.')) 
            ), 
            403
        );
    }
}