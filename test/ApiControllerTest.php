<?php

/**
 * ApiControllerTest
 *
 * @author Oleg Gutnikov <goodnickoff@gmail.com>
 * @package test\unit
 */
class ApiControllerTest extends CDbTestCase{
    
    public $fixtures = array(
        'users'=>'User'
    );
    
    public function testGetModel(){
        $controller = new ApiController('api');
        
        $user = $this->users('user1');
        
        $testEmptyUser = $controller->getModel(User::model());
        $testUser1 = $controller->getModel(User::model(), $user->id);
        
        $this->assertTrue( $testEmptyUser->isNewRecord );
        $this->assertTrue( $testUser1->first_name == $user->first_name );
    }
    
    public function testValidate()
    {
        $user1 = $user = $this->users('user1');
        $user2 = $user = $this->users('user2');
        
        
        // Update collection with wrong email
        $controller = new ApiController('api');
        $controller->method = 'PUT';
        $controller->model = new User('update');
        $controller->data = array(
            array(
                'id'=>$user1->id,
                'login'=>$user1->login,
                'email'=>'wrong_email'
            ),
            array(
                'id'=>$user2->id,
                'login'=>$user2->login,
            ),
        );
        
        $result = $controller->validate(false);
        $this->assertFalse($result);
        $errors = $controller->getModelErrors();
        $this->assertEquals(1, count($errors));
        $this->assertArrayHasKey('email', $errors[0]);
        
        
        
        // Update collection with right email
        $controller = new ApiController('api');
        $controller->method = 'PUT';
        $controller->model = new User('update');
        $controller->data = array(
            array(
                'id'=>$user1->id,
                'login'=>$user1->login,
                'email'=>'right@email.com'
            ),
            array(
                'id'=>$user2->id,
                'login'=>$user2->login,
            ),
        );
        $result = $controller->validate(false);
        $this->assertTrue($result);
        $this->assertNull($controller->getModelErrors());
        
        
        // Create collection with wrong email
        $controller = new ApiController('api');
        $controller->method = 'POST';
        $controller->model = new User('create');
        $controller->data = array(
            array(
                'login'=>'new_user_1',
                'email'=>'wrong_email.com',
                'password'=>'1234567',
                'password_repeat'=>'1234567',
            ),
            array(
                'login'=>'new_user_2',
                'email'=>'new2@email.com',
                'password'=>'1234567',
                'password_repeat'=>'1234567',
            ),
        );
        $result = $controller->validate(false);
        $errors = $controller->getModelErrors();
        $this->assertEquals(1, count($errors));
        $this->assertArrayHasKey('email', $errors[0]);
        
        
        // Create single record with wrong email
        $controller = new ApiController('api');
        $controller->method = 'POST';
        $controller->model = new User('create');
        $controller->data = array(
            'login'=>'new_user_1',
            'email'=>'wrong_email.com',
            'password'=>'1234567',
            'password_repeat'=>'1234567',
        );
        $result = $controller->validate(false);
        $errors = $controller->getModelErrors();
        $this->assertFalse($result);
        $this->assertEquals(1, count($errors));
        $this->assertArrayHasKey('email', $errors);
        
        // Create single record with right email
        $controller = new ApiController('api');
        $controller->method = 'POST';
        $controller->model = new User('create');
        $controller->data = array(
            'login'=>'new_user_1',
            'email'=>'right1@email.com',
            'password'=>'1234567',
            'password_repeat'=>'1234567',
        );
        $result = $controller->validate(false);
        $this->assertTrue($result);
        $this->assertNull($controller->getModelErrors());
        
    }
    
    public function testIsCollection(){
        $controller = new ApiController('api');
        
        $method = new ReflectionMethod('ApiController', 'isCollection');
        $method->setAccessible(true);
        
        $controller->data = array(
            array(
                'id'=>'1',
                'name'=>'role_1',
                'description'=>'role_1 desc',
                'bizrule'=>'role_1 bizrule',
            ),
            array(
                'id'=>'2',
                'name'=>'role_2',
                'description'=>'role_2 desc',
                'bizrule'=>'role_2 bizrule',
            ),
            array(
                'id'=>'3',
                'name'=>'role_3',
                'description'=>'role_3 desc',
                'bizrule'=>'role_3 bizrule',
            ),
        );
        
        $result = $method->invoke($controller);
        $this->assertTrue($result);
        
        $controller->data = array(
            "stringKey"=>array(
                'name'=>'role_1',
                'description'=>'role_1 desc',
                'bizrule'=>'role_1 bizrule',
            ),
            array(
                'name'=>'role_2',
                'description'=>'role_2 desc',
                'bizrule'=>'role_2 bizrule',
            ),
            array(
                'name'=>'role_3',
                'description'=>'role_3 desc',
                'bizrule'=>'role_3 bizrule',
            ),
        );
        $result = $method->invoke($controller);
        $this->assertFalse($result);
        
        
        $controller->data = array(
            array(
                'name'=>'role_2',
                'description'=>'role_2 desc',
                'bizrule'=>'role_2 bizrule',
            ),
            'string'
        );
        $result = $method->invoke($controller);
        $this->assertFalse($result);
    }
    
    public function testGetModelsForAffect()
    {
        // test update one model
        $controller = new ApiController('api');
        $controller->method = 'PUT';
        $controller->model = new User('update');
        $user2 = $this->users('user2');
        $user3 = $this->users('user3');
        
        $singleUserData = array(
            $user2->attributes
        );
        $singleUserData['first_name'] = 'affected first name';
        $_GET['id'] = $user2->id;
        $_POST = $singleUserData;
        
        $models = $controller->getModelsForAffect(true);
        $this->assertTrue(is_array($models));
        $this->assertEquals(count($models), 1);
        $this->assertEquals($models[0]->id, $user2->id);
        
        
        
        
        // test create one model
        $_POST = array();
        unset($_GET['id']);
        $this->assertTrue(!isset($_GET['id']));
        $this->assertTrue(empty($_POST));
        
        $controller = new ApiController('api');
        $controller->method = 'POST';
        $controller->model = new User('create');
        
        $singleUserData = array(
            'id'=>'',
            'first_name'=>'new first_name 1',
            'middle_name'=>'new middle_name 1',
            'last_name'=>'new last_name 1',
            'email'=>'newuser1@email.com',
            'login'=>'user1',
            'password'=>'test password',
            'role'=>'test role',
        );
        
        $controller->data = $singleUserData;
        
        $models = $controller->getModelsForAffect(true);
        $this->assertTrue(is_array($models));
        $this->assertEquals(count($models), 1);
        $this->assertTrue($models[0]->isNewRecord);
        
        
        
        // test update collection
        $_POST = array();
        unset($_GET['id']);
        $this->assertTrue(!isset($_GET['id']));
        $this->assertTrue(empty($_POST));
        
        $collectionUsersData = array(
            $user2->attributes,
            $user3->attributes
        );
        $_POST = $collectionUsersData;
        $controller = new ApiController('api');
        $controller->method = 'PUT';
        $controller->model = new User('update');
        $models = $controller->getModelsForAffect(true);
        
        $this->assertTrue(is_array($models));
        $this->assertEquals(2, count($models));
        $this->assertTrue($models[0] instanceof CActiveRecord && $models[1] instanceof CActiveRecord);
        $this->assertTrue($models[0]->id == $user2->id);
        $this->assertTrue($models[1]->id == $user3->id);
        
        
        
        // test create collection
        $_POST = array();
        unset($_GET['id']);
        $this->assertTrue(!isset($_GET['id']));
        $this->assertTrue(empty($_POST));
        $collectionUsersData = array(
            array(
                'id'=>'',
                'first_name'=>'new first_name 1',
                'middle_name'=>'new middle_name 1',
                'last_name'=>'new last_name 1',
                'email'=>'newuser1@email.com',
                'login'=>'user1',
                'password'=>'test password',
                'role'=>'test role',
            ),
            array(
                'first_name'=>'new first_name 2',
                'middle_name'=>'new middle_name 2',
                'last_name'=>'new last_name 2',
                'email'=>'newuser2@email.com',
                'login'=>'user2',
                'password'=>'test password',
                'role'=>'test role',
            ),
        );
        $_POST = $collectionUsersData;
        $controller = new ApiController('api');
        $controller->method = 'POST';
        $controller->model = new User('create');
        $models = $controller->getModelsForAffect(true);
        
        $this->assertTrue(is_array($models));
        $this->assertEquals(count($models), 2);
        $this->assertTrue($models[0] instanceof CActiveRecord && $models[1] instanceof CActiveRecord);
        $this->assertTrue($models[0]->id === null);
        $this->assertTrue($models[1]->id === null);
        $this->assertTrue($models[0]->isNewRecord);
        $this->assertTrue($models[1]->isNewRecord);
    }
}
