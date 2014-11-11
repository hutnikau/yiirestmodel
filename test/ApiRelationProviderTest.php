<?php

/**
 * Description of TestApiRelationProvider
 *
 * @author Oleg Gutnikov <goodnickoff@gmail.com>
 */
class ApiRelationProviderTest extends CDbTestCase {
     public $fixtures = array(
        'authitems'=>'AuthItem',
        'users'=>'User',
        'authitemchilds'=>'AuthItemChild',
    );
     
    public function testGetData(){
        $record = $this->authitemchilds('authitemchild_1');
        $admin = $this->authitems('admin');
        
        $_GET['with'] = 'parent, child';
        
        $relationProvider = new ApiRelationProvider(
            array(
                "config"=>array(
                    'parent'=>array(
                        'relationName'=>'parent0',
                        'columnName'=>'parentColumn',
                    ),
                    'child'=>array(
                        'relationName'=>'child0',
                        'return'=>'object',
                    )
                )    
            )
        );
        
        $relationData = $relationProvider->getData( $record );
        
        $this->assertTrue(is_array($relationData));
        
        $this->assertArrayHasKey('parentColumn', $relationData);
        $this->assertArrayHasKey('name', $relationData['parentColumn']);
        $this->assertArrayHasKey('description', $relationData['parentColumn']);
        $this->assertArrayHasKey('bizrule', $relationData['parentColumn']);
        $this->assertArrayHasKey('child', $relationData);
        $this->assertInstanceOf('CActiveRecord', $relationData['child']);
        
        $_GET['with'] = 'chidrens';
        
        $relationProvider = new ApiRelationProvider(
            array(
                "config"=>array(
                    'chidrens'=>array(
                        'relationName'=>'authItemChildren',
                        'columnName'=>'chidrens',
                        'return'=>'array',
                    )
                )    
            )    
        );
        
        $relationData = $relationProvider->getData( $admin );
        $this->assertTrue(is_array($relationData));
        $this->assertArrayHasKey('chidrens', $relationData);
        $this->assertEquals(count($relationData['chidrens']), count($admin->authItemChildren));
    }
}
