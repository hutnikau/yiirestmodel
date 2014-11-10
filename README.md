# Extension for implementation RESTful API

## Installation 

Extract files under protected/extensions.

Add to config:
```
    array('api/<controller>/list', 'pattern'=>'api/<controller:\w+>', 'verb'=>'GET'),
    array('api/<controller>/view', 'pattern'=>'api/<controller:\w+>/<id:\d+>', 'verb'=>'GET'),

    array('api/<controller>/create', 'pattern'=>'api/<controller:\w+>', 'verb'=>'POST'),

    array('api/<controller>/update', 'pattern'=>'api/<controller:\w+>/<id:\d+>', 'verb'=>'PUT'),
    array('api/<controller>/update', 'pattern'=>'api/<controller:\w+>', 'verb'=>'PUT'),

    array('api/<controller>/delete', 'pattern'=>'api/<controller:\w+>/<id:\d+>', 'verb'=>'DELETE'),
    array('api/<controller>/delete', 'pattern'=>'api/<controller:\w+>', 'verb'=>'DELETE'),
```

## Documentation 

http://goodnickoff.github.io/

## Usage 

Create module and controllers in it.

Controller example:
```php
class UsersController extends ApiController
{
    public $safeAttributes = array(
        'id', 'first_name',  'middle_name', 'last_name', 'email', 
    );

    public function __construct($id, $module = null) 
    {
        $this->model = new User('read');
        parent::__construct($id, $module);
    }
    
    /**
     * Function returns user data
     * @method GET
     */
    public function actionView()
    {
        if (!Yii::app()->user->checkAccess('getUser')) {
            $this->accessDenied();
        }
        $this->getView();
    }
    
    /**
     * Function returns user list
     * @method GET
     */
    public function actionList()
    {
        if (!Yii::app()->user->checkAccess('getUser')) {
            $this->accessDenied();
        }
        
        $this->getList();
    }
    
    /**
     * Function creates new user
     * @method POST
     */
    public function actionCreate()
    {
        if (!Yii::app()->user->checkAccess('createUser')) {
            $this->accessDenied();
        }
        
        $this->model->setScenario('create'); 
        
        $this->create();
    }
    
    /**
     * Function updates user.
     * @method PUT
     */
    public function actionUpdate()
    {
        if (!Yii::app()->user->checkAccess('updateUser')) {
            $this->accessDenied();
        }
        
        $this->model->setScenario('update'); 

        $this->update();
    }
    
    /**
     * Function deletes user.
     * @method DELETE
     */
    public function actionDelete()
    {
        if (!Yii::app()->user->checkAccess('deleteUser')) {
            $this->accessDenied();
        }
        
        $this->model->setScenario('delete'); 

        $this->delete();
    }
    
    public function getRelations() 
    {
        return array(
            'comments'=>array(          // relation GET parameter name (...?with=comments)
                'relationName'=>'comments',  //model relation name
                'columnName'=>'comments',    //column name in response
                'return'=>'array'            //return array of arrays or array of models
            )
        );
    }
}
```

### Get records
```
GET: /user - all users
GET: /user/2 - user with id=42
```

#### search and filtering
```
{"name":"alex", "age":"25"} — WHERE name='alex' AND age=25
[{"name":"alex"}, {"age":"25"}]  WHERE name='alex' OR age=25
```
The comparison operator is intelligently determined based on the first few characters in the given value. In particular, it recognizes the following operators if they appear as the leading characters in the given value:
* <: the column must be less than the given value.
* >: the column must be greater than the given value.
* <=: the column must be less than or equal to the given value.
* >=: the column must be greater than or equal to the given value.
* <>: the column must not be the same as the given value.
* =: the column must be equal to the given value.

Examples:
```
GET: /users?filter={"name":"alex"} — user with name alex
GET: /users?filter={"name":"alex", "age":">25"} — user with name alex AND age greater than 25
GET: /users?filter=[{"name":"alex"}, {"name":"dmitry"}] — user with name alex OR dmitry
GET: /users?search={"name":"alex"} — user with name contains the substring alex (alexey, alexander, alex)
```
#### relations

```
GET: /user/1?with=comments,posts — get user data with comments and posts array (comma separated list of relations in `with` GET parameter)
{
    "id":"1",
    "first_name":"Alex",
    "comments":[{"id":"1","text":"..."}, {"id":"2","text":"..."}],
    "posts":[{"id":"1","content":"..."}, {"id":"2","content":"..."}],
    ...
}
```

### Deleting

```
DELETE: /user/42 - delete user with id = 42
DELETE: /user  - delete all users
DELETE: /user?filter={"first_name":"Alex"} - delete users with name 'Alex'
```

### Create

```
POST: /user - create new user
```
### Create collection
```
POST: /user - create new users
```
pass POST parameters:
```
[
    {"name":"admin"},
    {"name":"guest"}
]
```
Creating two users 'admin' and 'guest'

### Update
```
PUT: /user/42 - update user with id = 42
```
#### Update collection
```
PUT: /user
```
pass POST parameters:
```
[
    {"id":"1","name":"admin"},
    {"id":"2","name":"guest"}
]
```
update users with id 1 and 2

### limit, offset, order
```
GET: /users/?offset=10&limit=10
GET: /users/?order=id DESC
GET: /users/?order=id ASC
GET: /users/?order=parent_id ASC,ordering ASC
GET: /users/?order=comment.id&with=comment
```

### Response format
By default response is sent in the format of JSON. To change the format of response pass `format` GET parameter with value `xml`
```
GET: /users?format=xml
```