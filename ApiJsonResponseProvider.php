<?php
/**
 * ApiJsonResponseProvider class file.
 * @author Oleg Gutnikov <goodnickoff@gmail.com>
 * @package api
 */ 

/**  
 * ApiJsonResponseProvider implements a response provider based on ApiResponseProvider
 * 
 * ApiJsonResponseProvider provides end user data in json format.
 */
class ApiJsonResponseProvider extends ApiResponseProvider
{
    /**
     * Function converts $data array to json format, sends it to client and terminates the application.
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
        $this->layout = false;
        $status_header = 'HTTP/1.1 ' . $status . ' ' . $this->_getStatusCodeMessage($status);
        header($status_header);
        header('Content-type: application/json');
        //header('Content-type: text/html');

        foreach($headers as $header){
            header($header);
        }

        echo json_encode($data);
        Yii::app()->end(); 
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