<?php

/**
 * Description of ApiRelationProvider
 *
 * Example:
 * 
 * <pre>
 * $relationProvider = new ApiRelationProwider('parent, child', array(
 *      "config"=>array(
 *          "parent"=>array(
 *              "columnName"=>"userChild",
 *              "return"=>"array" //string ("object", "array") return CActiveRecord object or array
 *          ),
 *      ),
 *      "model"=>$model //CActiveRecord $model for fetching relation data
 * ));
 * $model = User::model()->findByPk(1);
 * $relations = $relationProvider->getData($model); 
 * 
 * return:
 * array(
 *      "first_name"=>"john",
 *      "last_name"=>"doe"
 *      ...
 *      "userChild"=>array(
 *          ... //relation data
 *      )
 *      ...
 * )
 * </pre>
 * 
 * @author Oleg Gutnikov <goodnickoff@gmail.com>
 * @package api
 */
class ApiRelationProvider {
    
    /**
     *
     * @var string $_GET parameter name containing a list of relations.
     */
    public $requestParamName = 'with';
    
    /** 
     * @var mixed list of relations which to be returned.
     * 
     * Example:
     * 
     * string:
     * <pre>
     * "parent, child"
     * </pre>
     * array:
     * <pre>
     * array('parent', 'child')
     * </pre>
     */
    public $with = "";
    
    /**
     *
     * @var array relations config
     * <ul>
     * <li>
     * 'columnName' - string name of array key with relation data
     * </li>
     * <li>
     * 'return' - 'object'|'array' return CActiveRecord object or array
     * </li>
     * <li>
     * 'relationName' - string name of relation in model
     * </li>
     * </ul>
     */
    public $relationsConfig = array();
    
    public $model;
    
    public $relations = array();
    
    public function __construct( array $relationsConfig = array()){
        $this->relationsConfig = $relationsConfig['config'];
        if(isset($relationsConfig['model'])){
            $this->model = $relationsConfig['model'];
        }
        $this->with = Yii::app()->request->getParam( $this->requestParamName , "" );
        $this->relations = $this->normalizeRelations();
    }
    
    /**
     * Function return relation data
     * 
     * @param CActiveRecord $model
     * @return array relations data
     */
    public function getData( CActiveRecord $model ){
        $this->model = $model;
        $result = array();
        foreach($this->relations as $relationKey=>$relationConfig){
            $relationName = isset($relationConfig['relationName'])?$relationConfig['relationName']:$relationKey;
            $columnName = isset($relationConfig['columnName'])?$relationConfig['columnName']:$relationKey;
            $return = isset($relationConfig['return'])? $relationConfig['return'] : 'object';
            
            $relation = $model->$relationName;
            
            if(is_null( $relation ))
                $relation = array();
            
            if(is_array($relation) && !empty($relation) && $return == 'array'){
                foreach($relation as $relationData){
                    $result[$columnName][] = $relationData->attributes;
                }
            }
            elseif($return == 'array' && is_object($relation)){
                $result[$columnName] = $relation->attributes;
            }
            else{
                $result[$columnName] = $relation;
            }
        }
        
        return $result;
    }
    
    /**
     * Function returns array with list of relations names
     * @return array
     */
    public function getRalationsList(){
        $list = array();
        $relations = array();
        if($this->model){
            $relations = $this->model->relations();
        }
        foreach($this->relations as $relation){
            if(isset($relations[$relation['relationName']])){
                $list[] = $relation['relationName'];
            }
        }
        return $list;
    }
    
    /**
     * Function normalizes $with property
     * 
     * @return array where key is relation name and value is relation config array.
     */
    protected function normalizeRelations( ){
        $result = array();
        if(is_string($this->with)){
            $with = explode(',', $this->with);
        }
        if(is_array($with)){
            foreach($with as $relationName){
                if(!$relationName)
                    continue;
                $relationConfig = array();
                $relationName = trim($relationName);
                if(isset($this->relationsConfig[$relationName])){
                    $relationConfig = $this->relationsConfig[$relationName];
                }
                else{
                    throw new CHttpException(400, "relation $relationName does not exists.");
                }
                if($relationName && !empty($relationConfig) )
                    $result[$relationName] = $relationConfig;
            }
        }
        return $result;
    }
}
