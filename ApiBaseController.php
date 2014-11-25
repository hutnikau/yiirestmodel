<?php
/**
 * ApiBaseController class file
 * 
 * @author Oleg Gutnikov <goodnickoff@gmail.com>
 * @package api
 */

/**
 * This is the base API controller class.
 * @property ApiResponseProvider $responseProvider 
 * 
 */
class ApiBaseController extends CController
{
	public $layout = false;
        public $statusCode = 200;
        
        /**
         * @var string The format in which data is sent to the user.
         */
        public $format = 'json';
        
        private $_responseProvider;

        public function __construct($id, $module=null)
        {
            parent::__construct($id, $module);
            switch (mb_strtolower(Yii::app()->request->getQuery('format', $this->format))) {
                case 'xml':
                    $this->responseProvider = new ApiXmlResponseProvider();
                    break;
                case 'json':
                    $this->responseProvider = new ApiJsonResponseProvider();
                    break;
                default:
                    $this->responseProvider = new ApiJsonResponseProvider();
                    break;
            }
        }
        
        /**
         * Function fetch params from php://input.
         * @return array HTTP params
         */
        public function getInputParams()
        {
            $result = array();
            $rawBody = Yii::app()->request->rawBody;

            if (is_null($result = json_decode($rawBody, true))) {
               if(function_exists('mb_parse_str')) {
                    mb_parse_str(Yii::app()->request->rawBody, $result);
                } else {
                    parse_str(Yii::app()->request->rawBody, $result);
                } 
            }
            if(!is_array($result) || (empty($result) && !empty($_POST))){
                $result = $_POST;
            }

            return $result;
        }
        
        public function getResponseProvider()
        {
            return $this->_responseProvider;
        }
        
        public function setResponseProvider(ApiResponseProvider $responseProvider)
        {
            return $this->_responseProvider = $responseProvider;
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
         * @deprecated use {@link sendData()} method instead.
         * @param array $data 
         * @param int $status code.
         * @param array $headers http headers array.
         * @return null
         */
        public function sendJson($data, $status = null, array $headers=array())
        {
            if( $status === null ) {
                $status = $this->statusCode;
            }
            $this->sendData($data, $status,  $headers);
        }
        
        /**
         * Function send data to end user and terminate the application.
         * Format of data defined based on {@link responseProvider} 
         * @param array $data Data to be sent
         * @param integer $status Status code (e.g. 200 or 403)
         * @param array $headers List of additional headers to use when sending an response
         */
        public function sendData($data, $status = null, array $headers=array())
        {
            if ($status === null) {
                $status = $this->statusCode;
            }
            $this->responseProvider->sendData($data, $status, $headers);
        }
        
        /**
         * Function displays "access denied" message to end users 
         * with 403 http status code and terminates the application.
         * @return null
         */
        protected function accessDenied()
        {
            $this->sendJson( 
                array( 
                    'error'=>array('access'=>Yii::t('yii', 'You do not have sufficient permissions to access.')) 
                ), 
                403
            );
        }
}