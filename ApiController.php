<?php
/**
 * This is the base class for all API controller classes.
 * 
 * Each application REST API controller class extends this class for inheriting 
 * common for API controller methods and properties.
 * 
 * @author Oleg Gutnikov <goodnickoff@gmail.com>
 * @package api
 */
class ApiController extends Controller
{
    /** @var array params from php://input. */
    public $data = array();
    
    /** @var array priority model data which will override data from input. */
    public $priorityData = array();
    
    /** @var CActiveRecord controller model. */
    public $model;
    
    /** @var int Http Status-Code */
    public $statusCode = 200;
    
    /** @var CDbCriteria Precedence criteria. */
    public $baseCriteria;
    
    /**
     * Whether the response should be sent to the end user or should be returned as an array.
     * @var type 
     */
    public $sendToEndUser;
    
    /** 
     * @var array default criteria params 
     */
    public $criteriaParams = array(
        'limit' => 100, 
        'offset' => 0, 
        'order' => 'id ASC'
    );
    
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
     * Response to the user when no record found.
     * @var array  
     */
    public $notFoundErrorResponse = array( 'error'=>array('Not found') );
    
    public function __construct($id, $module=null){
        parent::__construct($id, $module);
        $this->data = $this->getJson();
    }
    
    /**
     * Function send response with record attributes or 404 error if no record found.
     * @param boolean $sendToEndUser Whether the response should be sent to the end user or should be returned as an array.
     * @param integer $id priority record id.
     * @return array result. Null if the result sended to end user.
     */
    public function getView( $sendToEndUser = true, $id = null ){
        $this->sendToEndUser = $sendToEndUser;
        $this->criteria = $this->baseCriteria;
        if(is_null($id)){
            $id = isset($this->actionParams['id'])?$this->actionParams['id'] : null;
        }
        $model = $this->getModel($this->model, $id, $this->baseCriteria, false);
        if(is_null($model)){
            $result = $this->notFoundErrorResponse;
        }
        else{
            $relationData = new ApiRelationProvider( $this->getRelations() );
            $result = array_merge($model->attributes, $relationData->getData($model));
        }        
        
        if( !$this->sendToEndUser ){
            return $result;
        }
        
        $this->sendJson( $result, $this->statusCode );
    }
    
    /**
     * Function send response with list of record attributes or empty array if no record found.
     * 
     * @param boolean $sendToEndUser Whether the response should be sent to the end user or should be returned as an array.
     * @return array result. Null if the result sended to end user.
     */
    public function getList( $sendToEndUser = true ){
        $this->sendToEndUser = $sendToEndUser;
        $this->checkModel();
        $this->criteria = new CDbCriteria($this->getCriteriaParams());
        $relationData = new ApiRelationProvider( $this->getRelations() );
        $this->criteria->with = $relationData->getRalationsList();
        if(is_array($this->criteria->with) && !empty($this->criteria->with)){
            $this->criteria->together = true;
        }
        $this->criteria->mergeWith($this->getFilterCriteria() , 'OR');
        $this->criteria->mergeWith($this->getSearchCriteria() , 'OR');
        
        if(!is_null($this->baseCriteria)){
            $this->criteria->mergeWith($this->baseCriteria, 'AND');
        }
        
        try{
            $records = $this->model->findAll( $this->criteria );
            $result = array();
            foreach($records as $record){
                $result[] = array_merge($record->attributes, $relationData->getData($record));
            }
            
        }
        catch(Exception $ex){
            $message = property_exists($ex, 'errorInfo')? $ex->errorInfo : $ex->getMessage();
            $result = array("error"=>$message);
            $this->statusCode = 400;
        }

        if( !$this->sendToEndUser ) { 
            return $result;
        }
        $this->sendJson( 
            $result, 
            $this->statusCode,
            $this->statusCode==200?array($this->getContentRangeHeader()) : array()
        );
    }
    
    /**
     * Function crates new record or collection of records.
     * 
     * @param boolean $sendToEndUser Whether the response should be sent to the end user or should be returned as an array. 
     * @return array with new record attributes. Null if the result sended to end user.
     */
    public function create( $sendToEndUser = true ){
        $this->sendToEndUser = $sendToEndUser;

        if(!empty($this->data)){
            $this->checkModel();
            $input = $this->data;
            $result = array();
            $models = array();
            $valid = true;
            if(!$this->isCollection()){
                $input = array($this->data);
            }
            foreach($input as $data){
                $model = new $this->model;
                $model->attributes = $this->priorityData + $data;
                $model->setScenario($this->model->scenario);
                if($model->validate()){
                    $models[] = $model;
                }
                else{
                    $valid = false;
                    $this->statusCode = 400;
                    $result = array('error'=>$model->errors);
                    break;
                }
            }
            if($valid){
                try{
                    foreach($models as $model){
                        $model->save();
                        $result[] = $model->attributes;
                    }
                    $result = $this->isCollection()? $result : $result[0];
                }
                catch(Exception $ex){
                    $message = property_exists($ex, 'errorInfo')? $ex->errorInfo : $ex->getMessage();
                    $result = array("error"=>$message);
                    $this->statusCode = 400;
                }
            }
        }
        else{
            $this->statusCode = 400;
            $result = array('error'=>'Data is not received.');
        }
        
        if( !$this->sendToEndUser ){
            return $result;
        }
        $this->sendJson($result, $this->statusCode);
    }
    
    /**
     * Function updates existing record or collection of records.
     * 
     * @param boolean $sendToEndUser Whether the response should be sent to the end user or should be returned as an array. 
     * @param int $id record id.
     * @return array with updted record attributes. Null if the result sended to end user.
     */
    public function update($sendToEndUser = true, $id = null ){
        $this->sendToEndUser = $sendToEndUser;
        if(!empty($this->data)){
            if(is_null($id)){
                $id = isset($this->actionParams['id'])?$this->actionParams['id'] : null;
            }
            $input = $this->data;
            $result = array();
            $models = array();
            $valid = true;
            if(!$this->isCollection()){
                $input = array($this->data);
            }
            foreach($input as $data){
                $id = ($this->isCollection() && isset($data['id']))? $data['id'] : Yii::app()->request->getParam('id', null);
                if(is_null($id)){
                    $this->criteria = new CDbCriteria();
                    $this->criteria->mergeWith($this->getFilterCriteria(), 'OR');
                    $this->criteria->mergeWith($this->getSearchCriteria(), 'OR');

                    if(!is_null($this->baseCriteria)){
                        $this->criteria->mergeWith($this->baseCriteria, 'AND');
                    }
                    $updatedModel = $this->model->findAll($this->criteria);
                }
                else{
                    $updatedModel = array($this->getModel($this->model, $id, $this->baseCriteria, false));
                }
                foreach($updatedModel as $model){
                    $model->attributes = $this->priorityData + $data;
                    $model->setScenario($this->model->scenario);
                    if($model->validate()){
                        $models[] = $model;
                    }
                    else{
                        $valid = false;
                        $this->statusCode = 400;
                        $result = array('error'=>$model->errors);
                        break;
                    }
                }
            }
            if($valid){
                foreach($models as $model){
                    $model->save();
                    $result[] = $model->attributes;
                }
                $this->statusCode = 200;
                $result = ($this->isCollection() || is_null($id))? $result : $result[0];
            }
        }
        else{
            $result = array('error'=>'Data is not received.');
        }
        if( !$this->sendToEndUser ){
            return $result;
        }
        $this->sendJson($result, $this->statusCode);
    }
    
    /**
     * Function deletes record. If $id is null then delete all records.
     * Deleted records can be filtered by 'filter' and 'search' params.
     * @param boolean $sendToEndUser Whether the response should be sent to the end user or should be returned as an array. 
     * @param int $id record id
     * @return array result. Null if the result sended to end user.
     */
    public function delete( $sendToEndUser = true, $id = null){
        $this->sendToEndUser = $sendToEndUser;
        $this->checkModel();
        $result = array();
        if(isset($this->actionParams['id'])){
            $id = $this->actionParams['id'];
        }
        if(is_null($id)){
            $this->criteria = new CDbCriteria();
            $this->criteria->mergeWith($this->getFilterCriteria(), 'OR');
            $this->criteria->mergeWith($this->getSearchCriteria(), 'OR');

            if(!is_null($this->baseCriteria)){
                $this->criteria->mergeWith($this->baseCriteria, 'AND');
            }
            $this->model = $this->model->findAll($this->criteria);
            foreach($this->model as $model){
                if($model->delete()){
                    $result[] = $model->attributes;
                }
                else{
                    $result = array('error'=>$model->errors);
                    $this->statusCode = 400;
                    break;
                }
            }
        }
        else{
            $this->model = $this->getModel($this->model, $id, $this->baseCriteria);
            if(!is_null($this->model)){
                $this->model->delete();
                $result = $this->model->attributes;
                $this->statusCode = 200;
            }
            else{
                $result = $this->notFoundErrorResponse;
                $this->statusCode = 404;
            }
        }
                
        if( !$this->sendToEndUser ){
            return $result;
        }
        
        $this->sendJson( $result, $this->statusCode );
    }
    
    /**
     * Function returns CActiveRecord model.
     * If param $id is not passed than return new model.
     * If model not found by id then send response with 404 error code and terminate app if $this->sendToEndUser is true.
     * 
     * @param CActiveRecord $model Model class instance
     * @param int $id id attribute for which you want to find a record.
     * @param CDbCriteria $baseCriteria precedence criteria.
     * @param boolean $newIfNull create new model in $id is null.
     * If $id is not set then return empty model
     * @return CActiveRecord model
     */
    public function getModel( CActiveRecord $model, $id = null, $baseCriteria = null, $newIfNull = true ){
        if(is_null($id) && $newIfNull)
            return new $model;
        
        $this->criteria = new CDbCriteria();
        $this->criteria->addCondition('id=:id');
        $this->criteria->params[':id'] = $id;
        
        if(!is_null($baseCriteria)){
            $this->criteria->mergeWith($baseCriteria);
        }
        
        $model = $model->find($this->criteria);
        
        if(is_null($model)){
            $this->statusCode = 404;
            if($this->sendToEndUser){
                $this->sendJson(
                    $this->notFoundErrorResponse, 
                    404
                );
            }
        }
        
        return $model;
    }
    
    /**
     * Function returns relations rules for API entity
     * @return array array of relations
     */
    public function getRelations(){
        return array();
    }
    
    
    /**
     * Function return Content-Range header value
     * @return string Content-Range
     */
    protected function getContentRangeHeader(){
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
     * The array contains the values ​​of a $this->criteriaParams array that can be overwritten with the values ​​from $_GET parameters.
     * @return array CDbCriteria params.
     */
    protected function getCriteriaParams(){

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
    protected function isCollection($data = null){
        if(is_null($data)){
            $data = $this->data;
        }
        if(array_values($data) !== $data){
            return false;
        }
        foreach($data as $attributes){
            if(!is_array($attributes)){
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
    protected function processFilter($data){
        $data = json_decode($data);
        $filterData = array();
        if(is_object($data)){
            $filterData[] = $this->parseFilterObject($data);
        }
        elseif(is_array($data)){
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
    protected function getFilterCriteria($partialMatch = false, $filterData = null){
        if(is_null($filterData)){
            $filter = Yii::app()->request->getParam( 'filter' , "" );
            $filterData = $this->processFilter($filter);
        }
        $criteria = new CDbCriteria;
        foreach($filterData as $filterCondition){
            $filterCriteria = new CDbCriteria;
            foreach($filterCondition as $attribute=>$condition){
                if($condition == "" && !$partialMatch){
                    $filterCriteria->addCondition($attribute."=''");
                }
                else{
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
    protected function getSearchCriteria(){
        $search = Yii::app()->request->getParam( 'search' , "" );
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
    protected function parseFilterObject($filter){
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
    protected function checkModel(){
        if(is_null($this->model) || !$this->model instanceof CActiveRecord){
            $this->sendJson(
                array( 'error'=>array('Wrong collection model.') ), 
                500
            );
        }
    }
}
