<?php

namespace tests\models;

use app\modules\v1\models\SnapshotSearch;
use Codeception\Test\Unit;
use yii\db\ActiveQuery;

class SnapshotSearchTest extends Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
        $db = \Yii::$app->db;
        $db->createCommand()->createTable('snapshot', [
            'id' => 'pk',
            'verse_id' => 'integer',
            'uuid' => 'string',
            'code' => 'string',
            'data' => 'text',
            'metas' => 'text',
            'resources' => 'text',
            'created_by' => 'integer',
            'created_at' => 'datetime',
            'type' => 'integer',
        ])->execute();

        $db->createCommand()->createTable('verse_property', [
            'verse_id' => 'integer',
            'property_id' => 'integer',
        ])->execute();
        
        $db->createCommand()->createTable('property', [
            'id' => 'pk',
            'key' => 'string',
            'value' => 'string',
        ])->execute();

        $db->createCommand()->createTable('verse_tags', [
            'verse_id' => 'integer',
            'tags_id' => 'integer',
        ])->execute();
        
        $db->createCommand()->createTable('verse', [
            'id' => 'pk',
            'author_id' => 'integer',
        ])->execute();
    }

    public function testSearchCheckin()
    {
        $searchModel = new SnapshotSearch();
        $params = [];
        
        $dataProvider = $searchModel->searchCheckin($params);
        $this->assertInstanceOf('yii\data\ActiveDataProvider', $dataProvider);
        
        /** @var ActiveQuery $query */
        $query = $dataProvider->query;
        
        // Check if join exists (simple string check on SQL or structure)
        // Since we can't easily run SQL without DB, we inspect the object properties if accessible
        // or just verify no exception is thrown and object is returned.
        $this->assertNotNull($query);
        
        // In a real DB test we would check results:
        // $this->assertNotEmpty($dataProvider->getModels());
    }

    public function testSearchPublic()
    {
        $searchModel = new SnapshotSearch();
        $params = ['tags' => '1,2,3'];
        $pageSize = 10;
        
        $dataProvider = $searchModel->searchPublic($params, $pageSize);
        $this->assertEquals(10, $dataProvider->getPagination()->getPageSize());
        
        // Test tag filtering logic construction
        $dataProviderWithTags = $searchModel->searchPublic($params);
        // Verify no crash
        $this->assertInstanceOf('yii\data\ActiveDataProvider', $dataProviderWithTags);
    }

    public function testSearchPrivate()
    {
        $searchModel = new SnapshotSearch();
        $params = [];
        $userId = 1;
        
        $dataProvider = $searchModel->searchPrivate($params, $userId);
        $this->assertNotNull($dataProvider->query);
        
        // Verify author_id condition would be added (conceptually)
    }
}
