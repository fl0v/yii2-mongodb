<?php

namespace yiiunit\extensions\mongodb;

use MongoDB\BSON\ObjectID;
use yii\mongodb\Query;
use yii\caching\ArrayCache;

class QueryRunTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->setUpTestRows();
    }

    protected function tearDown()
    {
        $this->dropCollection('customer');
        parent::tearDown();
    }

    /**
     * Sets up test rows.
     */
    protected function setUpTestRows()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $rows = [];
        for ($i = 1; $i <= 10; $i++) {
            $rows[] = [
                'name' => 'name' . $i,
                'status' => $i,
                'address' => 'address' . $i,
                'group' => ($i % 2 === 0) ? 'even' : 'odd',
                'avatar' => [
                    'width' => 50 + $i,
                    'height' => 100 + $i,
                    'url' => 'http://some.url/' . $i,
                ],
            ];
        }
        $collection->batchInsert($rows);
    }

    // Tests :

    public function testAll()
    {
        $connection = $this->getConnection();
        $query = new Query();
        $rows = $query->from('customer')->all($connection);
        $this->assertEquals(10, count($rows));
    }

    public function testDirectMatch()
    {
        $connection = $this->getConnection();
        $query = new Query();
        $rows = $query->from('customer')
            ->where(['name' => 'name1'])
            ->all($connection);
        $this->assertEquals(1, count($rows));
        $this->assertEquals('name1', $rows[0]['name']);
    }

    public function testIndexBy()
    {
        $connection = $this->getConnection();
        $query = new Query();
        $rows = $query->from('customer')
            ->indexBy('name')
            ->all($connection);
        $this->assertEquals(10, count($rows));
        $this->assertNotEmpty($rows['name1']);
    }

    public function testInCondition()
    {
        $connection = $this->getConnection();
        $query = new Query();
        $rows = $query->from('customer')
            ->where([
                'name' => ['name1', 'name5']
            ])
            ->all($connection);
        $this->assertEquals(2, count($rows));
        $this->assertEquals('name1', $rows[0]['name']);
        $this->assertEquals('name5', $rows[1]['name']);
    }

    public function testNotInCondition()
    {
        $connection = $this->getConnection();

        $query = new Query();
        $rows = $query->from('customer')
            ->where(['not in', 'name', ['name1', 'name5']])
            ->all($connection);
        $this->assertEquals(8, count($rows));

        $query = new Query();
        $rows = $query->from('customer')
            ->where(['not in', 'name', ['name1']])
            ->all($connection);
        $this->assertEquals(9, count($rows));
    }

    /**
     * @depends testInCondition
     */
    public function testCompositeInCondition()
    {
        $connection = $this->getConnection();
        $query = new Query();
        $rows = $query->from('customer')
            ->where([
                'in',
                ['status', 'name'],
                [
                    ['status' => 1, 'name' => 'name1'],
                    ['status' => 3, 'name' => 'name3'],
                    ['status' => 5, 'name' => 'name7'],
                ]
            ])
            ->all($connection);
        $this->assertEquals(2, count($rows));
        $this->assertEquals('name1', $rows[0]['name']);
        $this->assertEquals('name3', $rows[1]['name']);
    }

    public function testOrCondition()
    {
        $connection = $this->getConnection();
        $query = new Query();
        $rows = $query->from('customer')
            ->where(['name' => 'name1'])
            ->orWhere(['address' => 'address5'])
            ->all($connection);
        $this->assertEquals(2, count($rows));
        $this->assertEquals('name1', $rows[0]['name']);
        $this->assertEquals('address5', $rows[1]['address']);
    }

    public function testCombinedInAndCondition()
    {
        $connection = $this->getConnection();
        $query = new Query();
        $rows = $query->from('customer')
            ->where([
                'name' => ['name1', 'name5']
            ])
            ->andWhere(['name' => 'name1'])
            ->all($connection);
        $this->assertEquals(1, count($rows));
        $this->assertEquals('name1', $rows[0]['name']);
    }

    public function testCombinedInLikeAndCondition()
    {
        $connection = $this->getConnection();
        $query = new Query();
        $rows = $query->from('customer')
            ->where([
                'name' => ['name1', 'name5', 'name10']
            ])
            ->andWhere(['LIKE', 'name', 'me1'])
            ->andWhere(['name' => 'name10'])
            ->all($connection);
        $this->assertEquals(1, count($rows));
        $this->assertEquals('name10', $rows[0]['name']);
    }

    public function testNestedCombinedInAndCondition()
    {
        $connection = $this->getConnection();
        $query = new Query();
        $rows = $query->from('customer')
            ->where([
                'and',
                ['name' => ['name1', 'name2', 'name3']],
                ['name' => 'name1']
            ])
            ->orWhere([
                'and',
                ['name' => ['name4', 'name5', 'name6']],
                ['name' => 'name6']
            ])
            ->all($connection);
        $this->assertEquals(2, count($rows));
        $this->assertEquals('name1', $rows[0]['name']);
        $this->assertEquals('name6', $rows[1]['name']);
    }

    public function testOrder()
    {
        $connection = $this->getConnection();

        $query = new Query();
        $rows = $query->from('customer')
            ->orderBy(['name' => SORT_DESC])
            ->all($connection);
        $this->assertEquals('name9', $rows[0]['name']);

        $query = new Query();
        $rows = $query->from('customer')
            ->orderBy(['avatar.height' => SORT_DESC])
            ->all($connection);
        $this->assertEquals('name10', $rows[0]['name']);
    }

    public function testMatchPlainId()
    {
        $connection = $this->getConnection();
        $query = new Query();
        $row = $query->from('customer')->one($connection);
        $query = new Query();
        $rows = $query->from('customer')
            ->where(['_id' => $row['_id']->__toString()])
            ->all($connection);
        $this->assertEquals(1, count($rows));
    }

    public function testRegex()
    {
        $connection = $this->getConnection();
        $query = new Query();
        $rows = $query->from('customer')
            ->where(['REGEX', 'name', '/me1/'])
            ->all($connection);
        $this->assertEquals(2, count($rows));
        $this->assertEquals('name1', $rows[0]['name']);
        $this->assertEquals('name10', $rows[1]['name']);
    }

    public function testLike()
    {
        $connection = $this->getConnection();

        $query = new Query();
        $rows = $query->from('customer')
            ->where(['LIKE', 'name', 'me1'])
            ->all($connection);
        $this->assertEquals(2, count($rows));
        $this->assertEquals('name1', $rows[0]['name']);
        $this->assertEquals('name10', $rows[1]['name']);

        $query = new Query();
        $rowsUppercase = $query->from('customer')
            ->where(['LIKE', 'name', 'ME1'])
            ->all($connection);
        $this->assertEquals($rows, $rowsUppercase);
    }

    public function testCompare()
    {
        $connection = $this->getConnection();

        $query = new Query();
        $rows = $query->from('customer')
            ->where(['$gt', 'status', 8])
            ->all($connection);
        $this->assertEquals(2, count($rows));

        $query = new Query();
        $rows = $query->from('customer')
            ->where(['>', 'status', 8])
            ->all($connection);
        $this->assertEquals(2, count($rows));

        $query = new Query();
        $rows = $query->from('customer')
            ->where(['<=', 'status', 3])
            ->all($connection);
        $this->assertEquals(3, count($rows));
    }

    public function testNot()
    {
        $connection = $this->getConnection();

        $query = new Query();
        $rows = $query->from('customer')
            ->where(['not', 'status', ['$gte' => 10]])
            ->all($connection);
        $this->assertEquals(9, count($rows));

        $query = new Query();
        $rows = $query->from('customer')
            ->where(['not', 'name', 'name1'])
            ->all($connection);
        $this->assertEquals(9, count($rows));

        $query = new Query();
        $rows = $query->from('customer')
            ->where(['not', 'name', null])
            ->all($connection);
        $this->assertEquals(10, count($rows));
    }

    public function testExists()
    {
        $connection = $this->getConnection();

        $query = new Query();
        $exists = $query->from('customer')
            ->where(['name' => 'name1'])
            ->exists($connection);
        $this->assertTrue($exists);

        $query = new Query();
        $exists = $query->from('customer')
            ->where(['name' => 'un-existing-name'])
            ->exists($connection);
        $this->assertFalse($exists);
    }

    public function testModify()
    {
        $connection = $this->getConnection();

        $query = new Query();

        $searchName = 'name5';
        $newName = 'new name';
        $row = $query->from('customer')
            ->where(['name' => $searchName])
            ->modify(['$set' => ['name' => $newName]], ['new' => false], $connection);
        $this->assertEquals($searchName, $row['name']);

        $searchName = 'name7';
        $newName = 'new name';
        $row = $query->from('customer')
            ->where(['name' => $searchName])
            ->modify(['$set' => ['name' => $newName]], ['new' => true], $connection);
        $this->assertEquals($newName, $row['name']);

        $row = $query->from('customer')
            ->where(['name' => 'not existing name'])
            ->modify(['$set' => ['name' => 'new name']], ['new' => false], $connection);
        $this->assertNull($row);
    }

    /**
     * @see https://github.com/yiisoft/yii2/issues/4879
     * @see https://github.com/yiisoft/yii2-mongodb/issues/101
     *
     * @depends testInCondition
     */
    public function testInConditionIgnoreKeys()
    {
        $connection = $this->getConnection();
        $query = new Query();
        $rows = $query->from('customer')
            /*->where([
                'name' => [
                    0 => 'name1',
                    15 => 'name5'
                ]
            ])*/
            ->where(['in', 'name', [
                10 => 'name1',
                15 => 'name5'
            ]])
            ->all($connection);
        $this->assertEquals(2, count($rows));
        $this->assertEquals('name1', $rows[0]['name']);
        $this->assertEquals('name5', $rows[1]['name']);

        // @see https://github.com/yiisoft/yii2-mongodb/issues/101
        $query = new Query();
        $rows = $query->from('customer')
            ->where(['_id' => [
                10 => $rows[0]['_id'],
                15 => $rows[1]['_id']
            ]])
            ->all($connection);
        $this->assertEquals(2, count($rows));
    }

    /**
     * @see https://github.com/yiisoft/yii2/issues/7010
     */
    public function testSelect()
    {
        $connection = $this->getConnection();
        $query = new Query();
        $rows = $query->from('customer')
            ->select(['name' => true, '_id' => false])
            ->limit(1)
            ->all($connection);
        $row = array_pop($rows);
        $this->assertArrayHasKey('name', $row);
        $this->assertArrayNotHasKey('address', $row);
        $this->assertArrayNotHasKey('_id', $row);
    }

    public function testScalar()
    {
        $connection = $this->getConnection();

        $result = (new Query())
            ->select(['name' => true, '_id' => false])
            ->from('customer')
            ->orderBy(['name' => SORT_ASC])
            ->limit(1)
            ->scalar($connection);
        $this->assertSame('name1', $result);

        $result = (new Query())
            ->select(['name' => true, '_id' => false])
            ->from('customer')
            ->andWhere(['status' => -1])
            ->scalar($connection);
        $this->assertSame(false, $result);

        $result = (new Query())
            ->select(['name'])
            ->from('customer')
            ->orderBy(['name' => SORT_ASC])
            ->limit(1)
            ->scalar($connection);
        $this->assertSame('name1', $result);

        $result = (new Query())
            ->select(['_id'])
            ->from('customer')
            ->limit(1)
            ->scalar($connection);
        $this->assertTrue($result instanceof ObjectID);
    }

    public function testColumn()
    {
        $connection = $this->getConnection();

        $result = (new Query())->from('customer')
            ->select(['name' => true, '_id' => false])
            ->orderBy(['name' => SORT_ASC])
            ->limit(2)
            ->column($connection);
        $this->assertEquals(['name1', 'name10'], $result);

        $result = (new Query())->from('customer')
            ->select(['name' => true, '_id' => false])
            ->andWhere(['status' => -1])
            ->orderBy(['name' => SORT_ASC])
            ->limit(2)
            ->column($connection);
        $this->assertEquals([], $result);

        $result = (new Query())->from('customer')
            ->select(['name'])
            ->orderBy(['name' => SORT_ASC])
            ->limit(2)
            ->column($connection);
        $this->assertEquals(['name1', 'name10'], $result);

        $result = (new Query())->from('customer')
            ->select(['_id'])
            ->orderBy(['name' => SORT_ASC])
            ->limit(2)
            ->column($connection);
        $this->assertTrue($result[0] instanceof ObjectID);
        $this->assertTrue($result[1] instanceof ObjectID);
    }

    /**
     * @depends testColumn
     */
    public function testColumnIndexBy()
    {
        $connection = $this->getConnection();

        $result = (new Query())->from('customer')
            ->select(['name'])
            ->orderBy(['name' => SORT_ASC])
            ->limit(2)
            ->indexBy('status')
            ->column($connection);
        $this->assertEquals([1 => 'name1', 10 => 'name10'], $result);

        $result = (new Query())->from('customer')
            ->select(['name', 'status'])
            ->orderBy(['name' => SORT_ASC])
            ->limit(2)
            ->indexBy(function ($row) {
                return $row['status'] * 2;
            })
            ->column($connection);
        $this->assertEquals([2 => 'name1', 20 => 'name10'], $result);

        $result = (new Query())->from('customer')
            ->select(['name'])
            ->orderBy(['name' => SORT_ASC])
            ->limit(2)
            ->indexBy('name')
            ->column($connection);
        $this->assertEquals(['name1' => 'name1', 'name10' => 'name10'], $result);
    }

    public function testEmulateExecution()
    {
        $query = new Query();
        if (!$query->hasMethod('emulateExecution')) {
            $this->markTestSkipped('"yii2" version 2.0.11 or higher required');
        }

        $db = $this->getConnection();

        $this->assertGreaterThan(0, $query->from('customer')->count('*', $db));

        $rows = (new Query())
            ->from('customer')
            ->emulateExecution()
            ->all($db);
        $this->assertSame([], $rows);

        $row = (new Query())
            ->from('customer')
            ->emulateExecution()
            ->one($db);
        $this->assertSame(false, $row);

        $exists = (new Query())
            ->from('customer')
            ->emulateExecution()
            ->exists($db);
        $this->assertSame(false, $exists);

        $count = (new Query())
            ->from('customer')
            ->emulateExecution()
            ->count('*', $db);
        $this->assertSame(0, $count);

        $sum = (new Query())
            ->from('customer')
            ->emulateExecution()
            ->sum('id', $db);
        $this->assertSame(0, $sum);

        $sum = (new Query())
            ->from('customer')
            ->emulateExecution()
            ->average('id', $db);
        $this->assertSame(0, $sum);

        $max = (new Query())
            ->from('customer')
            ->emulateExecution()
            ->max('id', $db);
        $this->assertSame(null, $max);

        $min = (new Query())
            ->from('customer')
            ->emulateExecution()
            ->min('id', $db);
        $this->assertSame(null, $min);

        $scalar = (new Query())
            ->select(['id'])
            ->from('customer')
            ->emulateExecution()
            ->scalar($db);
        $this->assertSame(null, $scalar);

        $column = (new Query())
            ->select(['id'])
            ->from('customer')
            ->emulateExecution()
            ->column($db);
        $this->assertSame([], $column);

        $row = (new Query())
            ->select(['id'])
            ->from('customer')
            ->emulateExecution()
            ->modify(['name' => 'new name'], [], $db);
        $this->assertSame(null, $row);

        $values = (new Query())
            ->select(['id'])
            ->from('customer')
            ->emulateExecution()
            ->distinct('name', $db);
        $this->assertSame([], $values);
    }

    /**
     * @depends testAll
     *
     * @see https://github.com/yiisoft/yii2-mongodb/issues/205
     */
    public function testOffsetLimit()
    {
        $db = $this->getConnection();

        $rows = (new Query())
            ->from('customer')
            ->limit(2)
            ->all($db);
        $this->assertCount(2, $rows);

        $rows = (new Query())
            ->from('customer')
            ->limit(-1)
            ->all($db);
        $this->assertCount(10, $rows);

        $rows = (new Query())
            ->from('customer')
            ->orderBy(['name' => SORT_ASC])
            ->offset(2)
            ->limit(1)
            ->all($db);
        $this->assertCount(1, $rows);
        $this->assertEquals('name2', $rows[0]['name']);

        $rows = (new Query())
            ->from('customer')
            ->orderBy(['name' => SORT_ASC])
            ->offset(-1)
            ->limit(1)
            ->all($db);
        $this->assertCount(1, $rows);
        $this->assertEquals('name1', $rows[0]['name']);
    }

    public function testDistinct()
    {
        $db = $this->getConnection();

        $rows = (new Query())
            ->from('customer')
            ->distinct('group', $db);
        sort($rows);

        $this->assertSame(['even', 'odd'], $rows);
    }

    public function testAggregationShortcuts()
    {
        $db = $this->getConnection();

        $max = (new Query())
            ->from('customer')
            ->where(['group' => 'odd'])
            ->count('*', $db);
        $this->assertSame(5, $max);

        $max = (new Query())
            ->from('customer')
            ->where(['group' => 'even'])
            ->max('status', $db);
        $this->assertSame(10, $max);

        $max = (new Query())
            ->from('customer')
            ->where(['group' => 'even'])
            ->min('status', $db);
        $this->assertSame(2, $max);

        $max = (new Query())
            ->from('customer')
            ->where(['group' => 'even'])
            ->sum('status', $db);
        $this->assertSame(30, $max);

        $max = (new Query())
            ->from('customer')
            ->where(['group' => 'even'])
            ->average('status', $db);
        $this->assertEquals(6, $max);
    }

    public function testQueryCache()
    {
        $db = $this->getConnection();
        $db->enableQueryCache = true;
        $db->queryCache = new ArrayCache();
        $query = (new Query())->select(['name'])->from('customer');

        // No cache
        $newRecordId = $db->createCommand()->insert('customer', ['name' => 'John']);
        $this->assertEquals('John', $query->where(['_id' => (string) $newRecordId])->scalar($db), 'Asserting initial value');
        $db->createCommand()->update('customer', ['_id' => (string) $newRecordId], ['name' => 'Mike'], ['multi' => false, 'upsert' => false]);
        $this->assertEquals('Mike', $query->where(['_id' => (string) $newRecordId])->scalar($db), 'Query reflects DB changes when caching is disabled');

        // Connection cache
        $newRecordId = $db->createCommand()->insert('customer', ['name' => 'John']);
        $db->cache(function ($db) use ($query, $newRecordId) {
            $this->assertEquals('John', $query->where(['_id' => (string) $newRecordId])->scalar($db), 'Asserting initial value');        

            $db->createCommand()->update('customer', ['_id' => (string) $newRecordId], ['name' => 'Mike'], ['multi' => false, 'upsert' => false]);
            $this->assertEquals('John', $query->where(['_id' => (string) $newRecordId])->scalar($db), 'Query does NOT reflect DB changes when wrapped in connection caching');

            $db->noCache(function () use ($query, $db, $newRecordId) {
                $this->assertEquals('Mike', $query->where(['_id' => (string) $newRecordId])->scalar($db), 'Query reflects DB changes when wrapped in connection caching and noCache simultaneously');
            });

            $this->assertEquals('John', $query->where(['_id' => (string) $newRecordId])->scalar($db), 'Cache does not get changes after getting newer data from DB in noCache block.');
        }, 10);


        // Connection cache disabled
        $db->enableQueryCache = false;
        $db->cache(function ($db) use ($query, $newRecordId) {
            $this->assertEquals('Mike', $query->where(['_id' => (string) $newRecordId])->scalar($db), 'When cache is disabled for the whole connection, Query inside cache block does not get cached');
            $db->createCommand()->update('customer', ['_id' => (string) $newRecordId], ['name' => 'Alex'], ['multi' => false, 'upsert' => false]);
            $this->assertEquals('Alex', $query->where(['_id' => (string) $newRecordId])->scalar($db));
        }, 10);


        $db->enableQueryCache = true;
        $query->cache();
        $newRecordId = $db->createCommand()->insert('customer', ['name' => 'John']);

        $this->assertEquals('John', $query->where(['_id' => (string) $newRecordId])->scalar($db));
        $db->createCommand()->update('customer', ['_id' => (string) $newRecordId], ['name' => 'Mike'], ['multi' => false, 'upsert' => false]);
        $this->assertEquals('John', $query->where(['_id' => (string) $newRecordId])->scalar($db), 'When both Connection and Query have cache enabled, we get cached value');
        $this->assertEquals('Mike', $query->noCache()->where(['_id' => (string) $newRecordId])->scalar($db), 'When Query has disabled cache, we get actual data');

        $db->cache(function ($db) use ($query, $newRecordId) {
            $this->assertEquals('Mike', $query->noCache()->where(['_id' => (string) $newRecordId])->scalar($db));
            $this->assertEquals('John', $query->cache()->where(['_id' => (string) $newRecordId])->scalar($db));
        }, 10);
    }

}
