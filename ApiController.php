<?php
/**
 * This is the base class for all API controller classes.
 * 
 * Each application REST API controller class extends this class for inheriting 
 * common for API controller methods and properties.
 * 
 * @property CDbCriteria $baseCriteria The precedence criteria.
 * @property string $method Request type, such as GET, POST, PUT, DELETE.
 * 
 * @author Oleg Gutnikov <goodnickoff@gmail.com>
 * @package api
 */
class ApiController extends ApiBaseController
{
    public $idParamName = 'id';
    
    /** 
     * @var array params from php://input. 
     */
    public $data = array();
    
    /** 
     * @var array priority model data which will override data from input. 
     */
    public $priorityData = array();
    
    /**
     * @var array relations rules for API entity
     */
    public $relations = array();
    
    /** 
     * @var CActiveRecord controller model. 
     */
    public $model;
    
    /** 
     * @var int Http Status-Code 
     */
    public $statusCode = 200;
    
    /** 
     * @var array List of safe attributes which will be sent to end user. If array is empty then all attributes will be sent.
     */
    public $safeAttributes = array();
    
    /*
     * @var boolean Whether the response should be sent to the end user or should be returned as an array.
     */
    public $sendToEndUser = true;
    
    /** 
     * @var array contains information about the range of selected data.
     * <ul>
     * <li>
     * total - Number of records found, excluding the $limit
     * </li>
     * <li>
     * start - number of first selected record. Note start is 0 based, so starting at 5 means the 6th item.
     * </li>
     * <li>
     * end - number of last selected record. Note start is 0 based, so starting at 5 means the 6th item.
     * </li>
     * </ul>
     */
    public $contentRange = array(
        'total'=>0,
        'start'=>0,
        'end'=>0,
    );
    
    /**
     * @var CDbCriteria criteria for data selection
     */
    public $criteria;
    
    /**
     * @var array default criteria params 
     */
    public $criteriaParams;
    
    /**
     * Response to the user when no record found.
     * @var array  
     */
    public $notFoundErrorResponse = array(
        'error'=>array('Not found') 
    );
    
    /**
     * Whether to quote the alias name
     * @var boolean 
     */
    public $tableAliasQuotes = false;
    
    /**
     * Array of models which was affected during last operation (create, update or delete)
     * @var CActiveRecord[] 
     */
    private $_affectedModels = array();
    private $_modelsForAffect = null;
    
    /** @var CDbCriteria Precedence criteria. */
    private $_baseCriteria;
    
    /** @var array model errors */
    private $_modelErrors;
    
    /** @var string request type, such as GET, POST, PUT, DELETE.  */
    private $_method;
    
    
    public function __construct($id, $module = null)
    {
        parent::__construct($id, $module);
        $this->data = $this->getInputParams();
    }
    
    /**
     * Function send response with record attributes or 404 error if no record found.
     * @param boolean $sendToEndUser Whether the response should be sent to the end user or should be returned as an array.
     * @param integer $recordId priority record id.
     * @return array result. Null if the result sended to end user.
     */
    public function getView($sendToEndUser = null, $recordId = null)
    {
        if (is_bool($sendToEndUser)) {
            $this->sendToEndUser = $sendToEndUser;
        }
        $id = $this->getRecordId($recordId);
        $model = $this->getModel($this->model, $id, false);
        if (is_null($model)) {
            $result = $this->notFoundErrorResponse;
            $this->statusCode = 404;
        } else {
            try {
                $relationData = new ApiRelationProvider(
                    array(
                        'config'=>$this->getRelations(),
                        'model'=>$this->model
                    )
                );
                $result = array_merge($model->attributes, $relationData->getData($model));
            } catch (CException $e) {
                $result = array('error'=>array($e->getMessage()));
                $this->statusCode = 400;
            }
        }        
        return $this->returnResult($result);
    }
    
    /**
     * Function send response with list of record attributes or empty array if no record found.
     * 
     * @param boolean $sendToEndUser Whether the response should be sent to the end user or should be returned as an array.
     * @return array result. Null if the result sended to end user.
     */
    public function getList($sendToEndUser = null)
    {
        if (is_bool($sendToEndUser)) {
            $this->sendToEndUser = $sendToEndUser;
        }
        $this->checkModel();
        $this->criteria = new CDbCriteria($this->getFinalCriteriaParams());
        try {
            $relationData = new ApiRelationProvider(
                array(
                    'config'=>$this->getRelations(),
                    'model'=>$this->model
                )
            );
        } catch (CException $e) {
            $result = array('error'=>array($e->getMessage()));
            $this->statusCode = 400;
            return $this->returnResult($result);
        }
        
        if (is_array($this->criteria->with) && !empty($this->criteria->with)) {
            $this->criteria->together = true;
        }
        $this->criteria->with = $relationData->getRelationsList();
        $this->criteria->mergeWith($this->getFilterCriteria() , 'OR');
        $this->criteria->mergeWith($this->getSearchCriteria() , 'OR');
        $this->criteria->mergeWith($this->baseCriteria, 'AND');
        
        try {
            $records = $this->model->findAll($this->criteria);
            $result = array();
            foreach ($records as $record) {
                $result[] = array_merge($record->attributes, $relationData->getData($record));
            }
        } catch (Exception $ex) {
            $message = property_exists($ex, 'errorInfo')? $ex->errorInfo : $ex->getMessage();
            $result = array("error"=>$message);
            $this->statusCode = 400;
        }
        
        return $this->returnResult($result, $this->statusCode==200?array($this->getContentRangeHeader()) : array());
    }
    
    /**
     * Function crates new record or collection of records.
     * 
     * @param boolean $sendToEndUser Whether the response should be sent to the end user or should be returned as an array. 
     * @return array with new record attributes. Null if the result sended to end user.
     */
    public function create($sendToEndUser = null)
    {
        if (is_bool($sendToEndUser)) {
            $this->sendToEndUser = $sendToEndUser;
        }

        if (!empty($this->data)) {
            $result = array();
            $valid = $this->validate(false);
            if ($valid) {
                try{
                    $models = $this->getModelsForAffect();
                    foreach ($models as $model) {
                        if(!$model->save()){
                           throw new CDbException($this->getModelErrors());
                        }
                        $this->_affectedModels[] = $model;
                        $result[] = $model->attributes;
                    }

                    $result = count($this->_affectedModels)===1? $result[0] : $result;
                } catch (Exception $ex) {
                    $message = property_exists($ex, 'errorInfo')? $ex->errorInfo : $ex->getMessage();
                    $result = array("error"=>$message);
                    $this->statusCode = 400;
                }
            } else {
                $this->statusCode = 400;
                $result = array('error'=>$this->getModelErrors());
            }
        } else {
            $this->statusCode = 400;
            $result = array('error'=>'Data is not received.');
        }
        
        return $this->returnResult($result);
    }
    
    /**
     * Function updates existing record or collection of records.
     * 
     * @param boolean $sendToEndUser Whether the response should be sent to the end user or should be returned as an array. 
     * @param int $id record id.
     * @return array with updted record attributes. Null if the result sended to end user.
     */
    public function update($sendToEndUser = null, $id = null)
    {
        if (is_bool($sendToEndUser)) {
            $this->sendToEndUser = $sendToEndUser;
        }
        if (!empty($this->data)) {
            $result = array();
            $models = $this->getModelsForAffect();
            if (count($models) === 0) {
                $result = $this->notFoundErrorResponse;
                $this->statusCode = 404;
                $valid = false;
            }
            $valid = $this->validate(false);
            if ($valid) {
                 try{
                    foreach ($models as $model) {
                        if(!$model->save()){
                           throw new CDbException($this->getModelErrors());
                        }
                        $this->_affectedModels[] = $model;
                        $result[] = $model->attributes;
                    }
                    $this->statusCode = 200;
                    $result = (count($models)===1)? $result[0] : $result;
                } catch (Exception $ex) {
                    $message = property_exists($ex, 'errorInfo')? $ex->errorInfo : $ex->getMessage();
                    $result = array("error"=>$message);
                    $this->statusCode = 400;
                }
            } else {
                $this->statusCode = 400;
                $result = array('error'=>$this->getModelErrors());
            }
        } else {
            $result = array('error'=>'Data is not received.');
        }
        
        return $this->returnResult($result);
    }
    
    /**
     * Function deletes record. If $id is null then delete all records.
     * Deleted records can be filtered by 'filter' and 'search' params.
     * @param boolean $sendToEndUser Whether the response should be sent to the end user or should be returned as an array. 
     * @return array result. Null if the result sended to end user.
     */
    public function delete($sendToEndUser = null)
    {
        if (is_bool($sendToEndUser)) {
            $this->sendToEndUser = $sendToEndUser;
        }
        $this->checkModel();
        $result = array();
        
        $this->_affectedModels = $this->getModelsForAffect();
        if (count($this->_affectedModels) === 0) {
            $result = $this->notFoundErrorResponse;
            $this->statusCode = 404;
        }
        
        foreach ($this->_affectedModels as $model) {
            if ($model->delete()) {
                $result[] = $model->attributes;
            } else {
                $result = array('error'=>$model->errors);
                $this->statusCode = 400;
                break;
            }
        }
        
        if (count($this->notFoundErrorResponse) == 1 && !isset($result['error'])) {
            $result = $result[0];
        }        
        
        return $this->returnResult($result);
    }
    
    /**
     * Function returns CActiveRecord model.
     * If param $id is not passed than return new model.
     * If model not found by id then send response with 404 error code and terminate app if $this->sendToEndUser is true.
     * 
     * @param CActiveRecord $model Model class instance
     * @param int $id id attribute for which you want to find a record.
     * @param boolean $newIfNull create new model in $id is null.
     * If $id is not set then return empty model
     * @return CActiveRecord model
     */
    public function getModel(CActiveRecord $model, $id = null, $newIfNull = true)
    {
        if(is_null($id) && $newIfNull)
            return new $model;
        
        $this->criteria = new CDbCriteria();
        $this->criteria->addCondition(
            $model->getTableAlias($this->tableAliasQuotes).".".$this->idParamName."=:id"
        );
        $this->criteria->params[':id'] = $id;
        $this->criteria->mergeWith($this->baseCriteria);
        
        $model = $model->find($this->criteria);
        
        if(is_null($model)){
            $this->statusCode = 404;
            if($this->sendToEndUser){
                $this->sendData(
                    $this->notFoundErrorResponse, 
                    404
                );
            }
        }
        
        return $model;
    }
    
    /**
     * Function returns array of models which will be affected (update or delete)
     * or array of new models on create request
     * @param boolean $refresh Find models or return from cache.
     * @param boolean $fillModels Whether to fill the model attributes.
     * @return array Models that have to be affected.
     */
    public function getModelsForAffect($refresh = false, $fillModels = true)
    {
        if ($this->_modelsForAffect == null || $refresh) {
            $this->_modelsForAffect = array();
            $input = $this->data;
            if(!$this->isCollection()){
                $input = array($this->data);
            } 
            
            if (empty($input)) {
                $input = array($this->idParamName=>Yii::app()->request->getParam($this->idParamName, null));
            }
            
            foreach ($input as $data) {
                $models = array();
                $id = ($this->isCollection() && isset($data[$this->idParamName]) && $data[$this->idParamName]!=='')? 
                    $data[$this->idParamName] : 
                    Yii::app()->request->getQuery('id', null);
                
                if ($id === null && !$this->isCollection() && $this->getMethod() !== 'POST') {
                    $this->criteria = new CDbCriteria();
                    $this->criteria->mergeWith($this->getFilterCriteria(), 'OR');
                    $this->criteria->mergeWith($this->getSearchCriteria(), 'OR');
                    $this->criteria->mergeWith($this->baseCriteria, 'AND');
                    $models = $this->model->findAll($this->criteria);
                    foreach ($models as $model) {
                        $model->setScenario($this->model->getScenario());
                    }
                } elseif($id !== null && $this->isCollection() && $this->getMethod() === 'POST') {
                    $models = array(new $this->model($this->model->getScenario()));
                } else {
                    $model = $this->getModel($this->model, $id);
                    if ($model !== null) {
                        $model->setScenario($this->model->getScenario());
                        $models[] = $model;
                    }
                }
                
                if ($fillModels) {
                    foreach ($models as $model) {
                        $model->attributes = $this->priorityData + $data;
                    }
                }
                $this->_modelsForAffect = array_merge($this->_modelsForAffect, $models);
            }
        }
        return $this->_modelsForAffect;
    }
    
    /**
     * Performs the validation.
     *
     * This method executes the validation rules as declared in model rules.
     * Only the rules applicable to the current scenario will be executed.
     *
     * Errors found during the validation can be retrieved via {@link getModelErrors}.
     *
     * @param boolean $sendToEndUser Whether the response should be sent to the end user or should be returned as an array. 
     * @return boolean whether the validation is successful without any error.
     */
    public function validate($sendToEndUser = null)
    {
        if (!empty($this->data)) {
            $valid = true;
            $result = array();
            $models = $this->getModelsForAffect();
            foreach ($models as $ndx => $model) {
                if (!$model->validate()) {
                    $valid = false;
                    $this->statusCode = 400;
                    $result[$ndx] = $model->errors;
                }
            }
        } else {
            if (!$sendToEndUser) {
                $result = array('error'=>'Data is not received.');
            } else {
                return false;
            }
        }
        
        $this->_modelErrors = $valid ? null : $result; 
        
        if (!$sendToEndUser) {
            return $valid;
        }
        return $this->returnResult($result);
    }
    
    /**
     * Returns the errors for all models.
     * @return array errors for all models. Empty array is returned if no error.
     */
    public function getModelErrors()
    {
        //if(is_array($this->_modelErrors) && count($this->_modelErrors)===1 && isset($this->_modelErrors[0])){
        if(!$this->isCollection() && isset($this->_modelErrors[0])){
            return $this->_modelErrors[0];
        }
        return $this->_modelErrors;
    }
    
    /**
     * Function returns relations rules for API entity
     * @return array array of relations
     */
    public function getRelations()
    {
        return $this->relations;
    }
    
    /**
     * Returns the named GET or POST parameter value.
     * If the GET or POST parameter does not exist, the second parameter to this method will be returned.
     * If both GET and POST contains such a named parameter, the GET parameter takes precedence.
     * @param string $name the GET parameter name
     * @param mixed $defaultValue the default parameter value if the GET parameter does not exist.
     * @return mixed the parameter value
     */
    public function getParam($name, $defaultValue=null)
    {
        return isset($this->data[$name]) ?$this->data[$name] : $defaultValue;
    }
    
    /**
     * Function returns array of model which was affected during last operation (create or update).
     * If no models was affected function returns empty array.
     * @return CActiveRecord[] Affexted models
     */
    public function getAffectedModels()
    {
        return $this->_affectedModels;
    }
    
    /**
     * Function returns base criteria wich will be used for searching models.
     * Parameters of this criteria will be used for records when searching, updating and deleting data.
     * @param boolean $refresh whether to reload cached _baseCriteria prop. 
     * @return CDbCriteria
     */
    public function getBaseCriteria($refresh = false)
    {
        if ($this->_baseCriteria === null || $refresh) {
            $this->_baseCriteria = new CDbCriteria();
        } 
        return $this->_baseCriteria;
    }
    
    /**
     * Function sets base criteria.
     * @see getBaseCriteria
     * @param CDbCriteria $criteria
     * @return CDbCriteria
     */
    public function setBaseCriteria(CDbCriteria $criteria)
    {
        $this->_baseCriteria = $criteria;
    }
    
    /**
     * Function returns the request type, such as GET, POST, HEAD, PUT, DELETE. 
     * @return string
     */
    public function getMethod()
    {
        if ($this->_method === null) {
            $this->_method = Yii::app()->request->requestType;
        }
            
        return $this->_method;
    }
    
    /**
     * Function sets the request type, such as GET, POST, HEAD, PUT, DELETE. 
     * @param string $type Request type
     * @return string
     */
    public function setMethod($type)
    {
        $this->_method = $type;
    }
    
    /**
     * Function return Content-Range header value
     * @return string Content-Range
     */
    protected function getContentRangeHeader()
    {
        $total = $this->model->count( $this->criteria );
        $start = ($this->criteria->offset < 0)? 0 : $this->criteria->offset;
        $end = ($this->criteria->limit > $total)? $total-1 : $this->criteria->limit+$start-1;
        
        $this->contentRange = array(
            'total' => $total,
            'end' => $end,
            'start' => $start,
        );
        
        return "Content-Range: items ".$this->contentRange['start']."-".$this->contentRange['end']."/".$this->contentRange['total'];
    }

    
    /**
     * Functions returns array of CDbCriteria params. 
     * The array contains the values ​​of a $this->_criteriaParams array that can be overwritten with the values ​​from $_GET parameters.
     * @return array CDbCriteria params.
     */
    protected function getFinalCriteriaParams()
    {
        if ($this->criteriaParams === null) {
            $this->criteriaParams = array(
                'limit' => 100, 
                'offset' => 0
            );
            $this->criteriaParams['order'] = $this->model!==null ? 
                $this->model->getTableAlias($this->tableAliasQuotes).".$this->idParamName ASC": 
                "t.$this->idParamName ASC"; 
        }

        $criteriaParams = array_intersect_key(
            $this->actionParams, 
            $this->criteriaParams
        )+$this->criteriaParams;
        
        return $criteriaParams;
    }
    
    /**
     * Function checks collection array.
     * Collection must be an <b>indexed</b> array containing arrays with a set of attribute names and their values. 
     * 
     * @return boolean if $this->data is collection of models attributes
     */
    protected function isCollection($data = null)
    {
        if (is_null($data)) {
            $data = $this->data;
        }
        if (array_values($data) !== $data || empty($data)) {
            return false;
        }
        foreach ($data as $attributes) {
            if (!is_array($attributes)) {
                return false;
            }
        }
        return true;
    }
    
    
    /**
     * Function processes filter data from request to array.
     * 
     * @param string $data json decoded data
     * <pre>
     * [{"name":"admin","description":"administrator"},{"name":"guest","description":"Гость"}]
     * </pre>
     * @return array
     * <pre>
     * array(
     *     array{
     *       'name' => 'admin',
     *       'description' => 'administrator'
     *     ),
     *     array(
     *       'name' => 'guest',
     *       'description' => 'Гость'
     *     )
     * )
     * </pre>
     */
    protected function processFilter($data)
    {
        $data = json_decode($data);
        $filterData = array();
        if (is_object($data)) {
            $filterData[] = $this->parseFilterObject($data);
        } elseif (is_array($data)) {
            foreach($data as $filterOrCondition){
                if(is_object($filterOrCondition)){
                    $filterData[] = $this->parseFilterObject($filterOrCondition);
                }
            }
        }
        return $filterData;
    }
    
    /**
     * Function creates criteria from filter data in request GET paramseters
     * 
     * @param boolean $partialMatch 
     * Whether the value should consider partial text match (using LIKE and NOT LIKE operators). 
     * Defaults to false, meaning exact comparison.
     * @param array $filterData
     * Example:
     * <pre>
     * array(
     *       array(
     *         'name' => 'admin',
     *         'description' => 'administrator'
     *       ),
     *       array(
     *         'name' => 'guest',
     *         'description' => 'Гость'
     *       )
     *   )           
     * </pre>
     * From this array will be created follow condition:
     * If $partialMatch = false
     * <pre>
     * string '((name='admin') AND (description='administrator')) OR ((name='guest') AND (description='Гость'))'
     * </pre> 
     * If $partialMatch = true
     * <pre>
     * string '((name LIKE 'admin') AND (description LIKE 'administrator')) OR ((name LIKE 'guest') AND (description LIKE 'Гость'))'
     * </pre> 
     * 
     * @return CDbCriteria 
     */
    protected function getFilterCriteria($partialMatch = false, $filterData = null)
    {
        if (is_null($filterData)) {
            $filter = Yii::app()->request->getParam('filter' , "");
            $filterData = $this->processFilter($filter);
        }
        $criteria = new CDbCriteria;
        foreach ($filterData as $filterCondition) {
            $filterCriteria = new CDbCriteria;
            foreach ($filterCondition as $attribute=>$condition) {
                if (strpos($attribute, '.')===false){ //append table alias to column name
                    $attribute = $this->model->getTableAlias($this->tableAliasQuotes).'.'.$attribute;
                }
                if ($condition == "" && !$partialMatch) {
                    $filterCriteria->addCondition($attribute."=''");
                } else {
                    $filterCriteria->compare($attribute, $condition, $partialMatch);
                }
            }
            $criteria->mergeWith( $filterCriteria, 'OR');
        }
        return $criteria;
    }
    
    /**
     * Function creates criteria from search data in request GET paramseters
     * @return CDbCriteria 
     */
    protected function getSearchCriteria()
    {
        $search = Yii::app()->request->getParam('search' , "");
        $searchData = $this->processFilter($search);
        
        return $this->getFilterCriteria(true, $searchData);
    }


    /**
     * Function convert json decoded object to array
     * @param object $filter json decoded object
     * <pre>
     * object(stdClass)
     *     public 'name' => string 'guest'
     *     public 'description' => string 'Гость'
     * </pre>
     * @return array 
     * <pre>
     * array(
     *    'name' => 'guest',
     *    'description' => 'Гость' 
     * )
     * </pre>
     */
    protected function parseFilterObject($filter)
    {
        $result = array();
        if(is_object($filter)){
            foreach($filter as $attribute=>$value){
                preg_match('/^(<>|>|<)?(.*)$/i', $value, $parsedValue);
                $result[$attribute] = $value;
            }
        } 
        return $result;
    }
    
    /**
     * Function checks if $this->model property is not null and its instance of ActiveRecord class
     */
    protected function checkModel()
    {
        if(is_null($this->model) || !$this->model instanceof CActiveRecord){
            throw new CDbException(Yii::t('yii','ApiController::model property must be an instance of CActiveRecord'));
        }
    }
    
    /**
     * Function returns $id record attribure.
     * If $id parameter is passed and it is nuneric then it will be returned.
     * Otherwise $id will be obtained from $_GET['id'].
     * @param integer $id
     */
    protected function getRecordId($id = null)
    {
        if (!is_numeric($id)) {
            $id = Yii::app()->request->getParam('id', null);
        }
        return $id;
    }
     
    /**
     * Function returns result of operation depends on $this->sendToEndUser property.
     * If <code>$this->sendToEndUser</code> is true, then result will be sended to end user and application will be terminated.
     * If <code>$this->sendToEndUser</code> is false, then result will be returned as array.
     * @param array $result
     * @param array $headers http headers array.
     * @return array
     */
    private function returnResult($result, $headers = array())
    {
        if (!empty($this->safeAttributes) && $this->statusCode == 200) {
            $safeAttrinutes = array_flip($this->safeAttributes);
            if (array_values($result) === $result) {//indexed array
                foreach ($result as &$row) {
                    $row = array_intersect_key($row, $safeAttrinutes);
                }
            } else {
                $result = array_intersect_key($result, $safeAttrinutes);
            }
        }
        
        if (!$this->sendToEndUser) {
            return $result;
        }
        
        $this->sendData($result, $this->statusCode, $headers);
    }
}