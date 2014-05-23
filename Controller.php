<?php
/**
 * Controller is the customized base controller class.
 * All controller classes for this application should extend from this base class.
 */
class Controller extends CController
{
	/**
	 * @var string the default layout for the controller view. Defaults to '//layouts/column1',
	 * meaning using a single column layout. See 'protected/views/layouts/column1.php'.
	 */
	public $layout = 'false';
	/**
	 * @var array the breadcrumbs of the current page. The value of this property will
	 * be assigned to {@link CBreadcrumbs::links}. Please refer to {@link CBreadcrumbs::links}
	 * for more details on how to specify this property.
	 */
        
        public function __construct($id,$module=null){
            parent::__construct($id,$module);
        }
        
        
        /**
         * Function parse headers and fetch params from php://input
         * @return array HTTP params
         */
        public function getJson(){
            $result = array();
            $rawBody = Yii::app()->request->rawBody;

            if(is_null( $result = json_decode($rawBody, true))){
               if(function_exists('mb_parse_str')) {
                    mb_parse_str(Yii::app()->request->rawBody, $result);
                } else {
                    parse_str(Yii::app()->request->rawBody, $result);
                } 
            }
            //is_array($result)? array_merge($result, $_POST) : $result = $_POST;
            if(!is_array($result)){
                $result = $_POST;
            }

            return $result;
        }
        
        /**
         * Function convert $data array to json string, sends it to client and terminates the application.
         * Usage example:
         * <pre>
         *   $this->sendJson(
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
        public function sendJson($data, $status = 200, array $headers=array()){
            $this->layout = false;
            $status_header = 'HTTP/1.1 ' . $status . ' ' . $this->_getStatusCodeMessage($status);
            header($status_header);
            header('Content-type: application/json');

            foreach($headers as $header){
                header($header);
            }

            echo json_encode($data);
            Yii::app()->end(); 
        }
        
        /**
         * Function displays "access denied" message to end users. And terminate the application
         * @return null
         */
        protected function accessDenied(){
            $this->sendJson( 
                array( 
                    'error'=>array('access'=>Yii::t('yii', 'You do not have sufficient permissions to access.')) 
                ), 
                403
            );
        }
        
        /**
         * Funcion return HTTP status code message
         * @param int $status status code
         * @return string code message
         */
        private function _getStatusCodeMessage($status)
        {
            $codes = Array(
                200 => 'OK',
                400 => 'Bad Request',
                401 => 'Unauthorized',
                402 => 'Payment Required',
                403 => 'Forbidden',
                404 => 'Not Found',
                500 => 'Internal Server Error',
                501 => 'Not Implemented',
            );
            return (isset($codes[$status])) ? $codes[$status] : '';
        }
        
        
}