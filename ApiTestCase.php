<?php
/**
 * This file contains the ApiTestCase class.
 *

 */

/**
 * ApiTestCase is the base class for testing all RESTful API controller methods.
 * 
 * Usage:
 * <pre>
 * $response = $this->post(
 *      'api/login',
 *      array(
 *          'username'=>'userName',
 *          'password'=>'password'
 *      ),
 *      array(
 *          CURLOPT_CRLF=>true,
 *          'cookies'=>array(
 *              'giud'=>'value',
 *              ...
 *          )
 *      )
 * );
 * </pre>
 * 

 * @package test
 * 
 */
class ApiTestCase extends CDbTestCase {
    
    /** 
     * @var array authenticated cookies 
     */
    public $authCookies;
    /** 
     * @var string authentication url 
     */
    public $loginUrl = 'login';
    /** 
     * @var array data which will be sended for authentication.
     */
    public $loginData = array('username'=>'admin', 'password'=>'admin');
    
    /**
     * Function authenticates in application and set $authCookies from response;
     * 
     * @param boolean $reload need to reload cookies and not return from the cache.
     */
    public function getAuthCookies($reload = false){
        $this->authCookies = unserialize(Yii::app()->testdb->createCommand(
            "SELECT PHPSESSID from auth_data"
        )->queryScalar());
        if($reload || empty($this->authCookies)){
            $response = $this->post($this->loginUrl, $this->loginData);
            $cookies = array();
            foreach($response['cookies'] as $cookieName=>$cookieData){
                $cookies[$cookieName] = $cookieData['value'];
            }
            $this->authCookies = $cookies;
            Yii::app()->testdb->createCommand()->delete('auth_data');
            Yii::app()->testdb->createCommand()->insert('auth_data', array('PHPSESSID'=>serialize($this->authCookies)));
        }
        return $this->authCookies;
    }
    
    /**
     * Function creates report and save it in report folder
     * @param mixed $data data to report
     * @return void
     */
    public function createReport($data){
        ob_start();
        if(is_string($data)){
            echo($data);
        }
        else{
            var_dump($data);
        }
        $data = ob_get_clean();
        file_put_contents(dirname(__FILE__).'/../../assets/report.php', $data);
    }
    
    /**
     * Function executes curl request with GET method.
     * 
     * @param string $url URL
     * @param array $params request GET params
     * @param array $options additional request options
     * @return array response
     */
    public function get( $url, array $params = array(), array $options = array()){
        $options[CURLOPT_CUSTOMREQUEST] = 'GET';
        
        if(!empty($params)){
            $url .= "?".$this->_encode($params);
        }
        return $this->request($url, $params, $options);  
    }
    
    /**
     * Function executes curl request with POST method.
     * 
     * @param string $url URL
     * @param array $params request parameters
     * @param array $options additional request options
     * @return array response
     */
    public function post($url, array $params = array(), array $options = array())
    {
        $options[CURLOPT_CUSTOMREQUEST] = 'POST';
        
        $options[CURLOPT_POSTFIELDS] = $params;
        return $this->request($url, $params, $options);  
    }
    
    /**
     * Function executes curl request with PUT method.
     * 
     * @param string $url URL
     * @param array $params request parameters
     * @param array $options additional request options
     * @return array response
     */
    public function put($url, array $params = array(), array $options = array())
    {
        $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
        
        $options[CURLOPT_POSTFIELDS] = $params;
        return $this->request($url, $params, $options);  
    }
    
    /**
     * Function executes curl request with DELETE method.
     * 
     * @param string $url URL
     * @param array $params request parameters
     * @param array $options additional request options
     * @return array response
     */
    public function delete($url, array $params = array(), array $options = array())
    {
        $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
        
        $options[CURLOPT_POSTFIELDS] = $params;
        return $this->request($url, $params, $options);  
    }
    
    /**
     * Function executes curl request
     * 
     * @param string $url URL
     * @param array $params request parameters
     * @param array $options additional request options
     * @return array response
     * <pre>
     * array{
     *   'body' => "response content",
     *   'code' => 200,
     *   'location' => 'http://www.example.org/index.php',
     *   'cookies' => array(...)   
     *   'headers' => array(...)   
     * }
     * </pre>
     */
    private function request($url, array $params=array(), array $options=array()){
        $url = rtrim(TEST_BASE_URL,'/').'/'.trim($url, '/');
        $ch = curl_init($url);

        $defaultOptions = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => 1,
        );
        
        if (isset($options[CURLOPT_POSTFIELDS])) {
            $options[CURLOPT_POSTFIELDS] = $this->_encode($options[CURLOPT_POSTFIELDS]);
        }
        
        if(isset($options['cookies'])){
            $cookiesStringToPass = "";
            foreach ($options['cookies'] as $name=>$value) {
                if ($cookiesStringToPass) {
                    $cookiesStringToPass  .= ';';
                }
                $cookiesStringToPass .= $name . '=' . addslashes($value);
            }
            $options[CURLOPT_COOKIE] = $cookiesStringToPass;
            unset($options['cookies']);
        }
        
        $options = $options + $defaultOptions;
        
        curl_setopt_array($ch, $options);
        
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);

        $header = substr($response, 0, $info['header_size']);
        $body = substr($response, $info['header_size']);

        preg_match("/Location: (.*?)\n/", $header, $matches);
        $location = isset($matches[1]) ? $matches[1] : null;
        
        $headersArray = ($this->http_parse_headers( $header ));
        $cookies = isset($headersArray['Set-Cookie'])?$headersArray['Set-Cookie'] : array();
        
        curl_close($ch);
        
        return array(
            'body' => $body, 
            'code' => $info['http_code'], 
            'location' => $location, 
            'cookies' => $cookies, 
            'headers' => $headersArray,
            'decoded' => json_decode($body, true),
        );
    }
    
    /**
     * Function generate URL-encoded query string from array
     * 
     * @param array $params query parameters
     * @return string
     */
    private function _encode(array $params)
    {
        return http_build_query($params, null, '&');
    }
    
    /**
     * Function convert HTTP headers to array
     * @param string $header
     * @return array HTTP headers
     * <br>
     * Example:
     * <pre>
     * array(
     *   'Date' =>" Tue, 18 Mar 2014 11:50:12 GMT",
     *   'Server' => "Apache/2.4.7 (Win32) OpenSSL/1.0.1e PHP/5.5.9",
     *   'X-Powered-By' =>"PHP/5.5.9",
     *   'Set-Cookie' => array(
     *         'PHPSESSID' =>array(
     *           'value' => "ou85gd41ad8rlgme12dps41ki4",
     *           'path' => "/"
     *         )
     *         'guid' => array(
     *           'value' => "53283b69df9cc8.91971156",
     *           'expires' => 1397737577,
     *           'Max-Age' => "2592000",
     *           'path' => "/",
     *         )
     *       ),
     *   'Expires' => "Thu, 19 Nov 1981 08:52:00 GMT",
     *   'Cache-Control' =>"no-store, no-cache, must-revalidate, post-check=0, pre-check=0",
     *   'Pragma' =>"no-cache",
     *   'Content-Length' =>"204",
     *   'Content-Type' =>"application/json",
     * )
     * </pre> 
     */
    private function http_parse_headers( $header )
    {
        $retVal = array();
        $fields = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $header));
        
        foreach( $fields as $i => $field ) {
            if( preg_match('/([^:]+): (.+)/m', $field, $match) ) {
                $match[1] = preg_replace_callback('/(?<=^|[\x09\x20\x2D])./', function($m){return strtoupper($m[0]);}, strtolower(trim($match[1])));
                if( isset($retVal[$match[1]]) ) {
                    if(!is_array($retVal[$match[1]])){
                        $retVal[$match[1]] = array($retVal[$match[1]], $match[2]);
                    }
                    else{
                        $retVal[$match[1]][] = trim($match[2]);
                    }
                } else {
                    $retVal[$match[1]] = trim($match[2]);
                }
            }
        }
        $cookies = array();
        if(isset($retVal['Set-Cookie'])){
            if(is_string($retVal['Set-Cookie'])){
                $retVal['Set-Cookie'] = array($retVal['Set-Cookie']);
            }
            foreach($retVal['Set-Cookie'] as $cookie){
                $cookiesParts = explode(';', trim($cookie));
                foreach($cookiesParts as $cookiesPartNum=>$cookiesPart){
                    $cookiesValues = explode('=', trim($cookiesPart));
                    if($cookiesPartNum == 0){
                        $cookieName = $cookiesValues[0];
                        $cookies[$cookieName] = array();
                    }
                    $aTmp = array();
                    if (count($cookiesValues) == 2)
                    {  
                        switch ($cookiesValues[0])  
                        {  
                            case 'path':  
                            case 'domain':  
                                $aTmp['name'] = trim($cookiesValues[0]);
                                $aTmp['value'] =  urldecode(trim($cookiesValues[1]));  
                                break;  
                            case 'expires':  
                                $aTmp['name'] = trim($cookiesValues[0]);
                                $aTmp['value'] = strtotime(urldecode(trim($cookiesValues[1])));  
                                break;  
                            default:  
                                $aTmp['name'] = trim($cookiesValues[0]);  
                                $aTmp['value'] = trim($cookiesValues[1]);  
                                break;  
                        }  
                    }
                    if($cookiesPartNum == 0){
                        $cookies[$cookieName]['value'] = $aTmp['value'];
                    }
                    else{
                        $cookies[$cookieName][$aTmp['name']] = $aTmp['value'];
                    }
                }
            }
            $retVal['Set-Cookie'] = $cookies;
        }
        return $retVal;
    }
    
}
