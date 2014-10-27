<?php

/**
 * ApiRelationProvider
 *
 * Example:
 * <pre>
 * $relationProvider = new ApiRelationProvider(
 *    array(
 *      "config"=>array(
 *        "comments"=>array( //HAS_MANY relation
 *          "columnName"=>"comments",
 *          "relationName"=>"comments",
 *          "return"=>"array" //string ("object"|"array") return CActiveRecord object or array
 *        ),
 *        "profile"=>array( //HAS_ONE relation
 *          "columnName"=>"profile",
 *          "relationName"=>"profile",
 *          "return"=>"array" //string ("object"|"array") return CActiveRecord object or array
 *        ),
 *     ),
 *     "model"=>$model //CActiveRecord $model for fetching relation data
 * ));
 * $model = User::model()->findByPk(1);
 * $relations = $relationProvider->getData($model);
 * </pre>
 * The above example returns the following array:
 * <pre>
 *   array(
 *     'comments'=>array(
 *        array(
 *          'id'=>'42',
 *          'content'=>'comment 42 content',
 *          ...
 *        ),
 *        array(
 *          'id'=>'81',
 *          'content'=>'comment 81 content',
 *          ...
 *        ),
 *        ...
 *     ),
 *     'profile'=>array(
 *        'userid'=>'1',
 *        'name'=>'username',
 *        'login'=>'userlogin',
 *        'email'=>'user@email.com',
 *        ...
 *     )
 *   )
 * </pre>
 * @author Oleg Gutnikov <goodnickoff@gmail.com>
 * @package api
 */
class ApiRelationProvider 
{
    
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
     * @var array relations config
     * <ul>
     *   <li>'columnName' - string name of array key with relation data</li>
     *   <li>'return' - 'object'|'array' return CActiveRecord object or array</li>
     *   <li>'relationName' - string name of relation in model</li>
     *   <li>
     *     'keyField' - string he name of the key field. This is a field that uniquely identifies a data record. 
     *     Data in response will be indexed by this key value. If it's not set data will be indexed in order. 
     *   </li>
     *   <li>
     *     'safeAttributes' - string comma separated list of relation attributes which will be represented in response.
     *   </li>
     * </ul>
     */
    public $relationsConfig = array();
    
    public $model;
    
    public $relations = array();
    
    public function __construct( array $relationsConfig = array())
    {
        $this->relationsConfig = $relationsConfig['config'];
        if (isset($relationsConfig['model'])) {
            $this->model = $relationsConfig['model'];
        }
        $this->with = Yii::app()->request->getParam($this->requestParamName, "");
        $this->relations = $this->normalizeRelations();
    }
    
    /**
     * Function return relation data
     * 
     * @param CActiveRecord $model
     * @return array relations data
     */
    public function getData(CActiveRecord $model)
    {
        $this->model = $model;
        $result = array();
        $relationData = array();
        $keys = array();
        
        foreach ($this->relations as $relationKey => $relationConfig) {
            $columnName = isset($relationConfig['columnName']) ? $relationConfig['columnName'] : $relationKey;
            $keys[$columnName] = isset($relationConfig['keyField']) ? $relationConfig['keyField'] : false;
            $relationData[$columnName] = $this->getRelationData($relationKey);
        }
        
        foreach ($relationData as $relName => $relData) {
            if (isset($model->relations()[$relName]) && count($relData) !== count($relData, COUNT_RECURSIVE)) {//multidimensional array
                $result[$relName] = array();
                foreach ($relData as $data) {
                    if ($keys[$relName] && isset($data[$keys[$relName]])) {
                        $result[$relName][$data[$keys[$relName]]] = $data;
                    } else {
                        $result[$relName][] = $data;
                    }
                }
            } else {
                $result[$relName] = $relData;
            }
        }
        
        return $result;
    }
    
    /**
     * Function returns relation data array. 
     * Attributes will be filtered by {@link getSafeAttributes} method
     * @param string $relationName
     * @return array Array of relation datas
     */
    protected function getRelationData($relationName)
    {
        $relationConfig = $this->relations[$relationName];
        $relationName = isset($relationConfig['relationName']) ? $relationConfig['relationName'] : $relationName;
        $return = isset($relationConfig['return'])? $relationConfig['return'] : 'object';
        $relationResult = $this->model->$relationName;
        $result = array();
            
        if ($relationResult === null) {
            $relationResult = array();
        }
        
        if ($relationResult instanceof CActiveRecord) {
            if ($return == 'array') {
                $result = $this->getSafeAttributes($relationName, $relationResult->attributes);
            } elseif ($return == 'object') {
                $result = $relationResult;
            }
        } else {
            $arrayOfModels = true;
            array_walk($relationResult, function($val) use(&$arrayOfModels){
                    $arrayOfModels = $arrayOfModels && $val instanceof CActiveRecord;
                }
            );
            if ($arrayOfModels) {
                foreach ($relationResult as $model) {
                    $result[] = $this->getSafeAttributes($relationName, $model->attributes);
                }
            } else {
                if (count($relationResult) !== count($relationResult, COUNT_RECURSIVE)) {//multidimensional array
                    foreach ($relationResult as $data) {
                        $result[] = $this->getSafeAttributes($relationName, $data);
                    }
                } else {
                    $result = $this->getSafeAttributes($relationName, $relationResult);
                }
            }
        }
        return $result;
    }


    /**
     * Function returns relation attribures that lists in 
     * @param type $attributes
     */
    protected function getSafeAttributes($relationName, $attributes)
    {
        if (
            isset($this->relations[$relationName]['safeAttributes']) 
            && is_array($this->relations[$relationName]['safeAttributes'])
        ) {
            $attributes = array_intersect_key($attributes, array_flip($this->relations[$relationName]['safeAttributes']));
        }
        return $attributes;
    }
    
    /**
     * Function returns array with list of relations names
     * @return array
     */
    public function getRelationsList() 
    {
        $list = array();
        $relations = array();
        if ($this->model) {
            $relations = $this->model->relations();
        }
        foreach ($this->relations as $relation) {
            if (isset($relations[$relation['relationName']])) {
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
    protected function normalizeRelations()
    {
        $result = array();
        if (is_string($this->with)) {
            $with = explode(',', $this->with);
        }
        if (is_array($with)) {
            foreach ($with as $relationName) {
                if (!$relationName)
                    continue;
                $relationConfig = array();
                $relationName = trim($relationName);
                if (isset($this->relationsConfig[$relationName])) {
                    $relationConfig = $this->relationsConfig[$relationName];
                    if (isset($relationConfig['safeAttributes'])) {
                        $relationConfig['safeAttributes'] = array_map('trim', explode(',', $relationConfig['safeAttributes']));
                    }
                } else {
                    if (Yii::app()->controller instanceof ApiController) {
                        Yii::app()->controller->sendJson(
                            array('error'=>array('relation'=>"relation $relationName does not exists.")),
                            400
                        );
                    } else {
                        throw new CHttpException(400, "relation $relationName does not exists.");
                    }
                }
                if ($relationName && !empty($relationConfig))
                    $result[$relationName] = $relationConfig;
            }
        }
        return $result;
    }
}