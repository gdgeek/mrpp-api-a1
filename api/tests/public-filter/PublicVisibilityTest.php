<?php

namespace tests\publicFilter;

use app\components\policies\VersePolicy;
use app\components\SceneQueryFilters;
use app\modules\v1\controllers\PrivateController;
use app\modules\v1\controllers\PublicController;
use app\modules\v1\controllers\ServerController;
use app\modules\v1\controllers\SnapshotController;
use app\modules\v1\controllers\VerseController;
use app\modules\v1\models\SnapshotSearch;
use app\modules\v1\models\TagsSearch;
use app\modules\v1\models\User;
use app\modules\v1\models\Verse;
use app\modules\v1\models\VerseSearch;
use app\modules\v2\controllers\SnapshotController as V2SnapshotController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\Application;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

final class PublicVisibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        new Application([
            'id' => 'a1-public-filter-test',
            'basePath' => dirname(__DIR__, 2),
            'components' => [
                'db' => ['class' => 'yii\db\Connection', 'dsn' => 'sqlite::memory:'],
                'cache' => ['class' => 'yii\caching\ArrayCache'],
                'request' => [
                    'cookieValidationKey' => 'test-only',
                    'scriptFile' => dirname(__DIR__, 2) . '/web/index.php',
                    'scriptUrl' => '/index.php',
                ],
                'user' => [
                    'identityClass' => User::class,
                    'enableSession' => false,
                    'enableAutoLogin' => false,
                ],
                'response' => ['class' => BufferedResponse::class, 'format' => Response::FORMAT_JSON],
                'errorHandler' => ['class' => TestErrorHandler::class],
            ],
        ]);
        Yii::$app->request->setQueryParams([]);
        $this->seedDatabase();
    }

    protected function tearDown(): void
    {
        Yii::$app->db->close();
        Yii::$app = null;
        parent::tearDown();
    }

    private function seedDatabase(): void
    {
        $schemas = [
            'verse' => [
                'id' => 'pk', 'author_id' => 'integer', 'updater_id' => 'integer',
                'image_id' => 'integer', 'name' => 'string', 'uuid' => 'string',
                'created_at' => 'string', 'updated_at' => 'string', 'info' => 'text',
                'data' => 'text', 'description' => 'text', 'version' => 'integer', 'status' => 'integer',
            ],
            'snapshot' => [
                'id' => 'pk', 'verse_id' => 'integer', 'uuid' => 'string', 'created_by' => 'integer',
                'created_at' => 'string', 'code' => 'text', 'data' => 'text', 'metas' => 'text',
                'resources' => 'text', 'type' => 'integer', 'name' => 'string',
                'description' => 'text', 'image' => 'text',
            ],
            'tags' => ['id' => 'pk', 'name' => 'string', 'key' => 'string', 'type' => 'string'],
            'verse_tags' => ['verse_id' => 'integer', 'tags_id' => 'integer'],
            'property' => ['id' => 'pk', 'key' => 'string'],
            'verse_property' => ['verse_id' => 'integer', 'property_id' => 'integer'],
            'group_verse' => ['group_id' => 'integer', 'verse_id' => 'integer'],
            'group_user' => ['group_id' => 'integer', 'user_id' => 'integer'],
            'user' => ['id' => 'pk', 'username' => 'string', 'auth_key' => 'string'],
        ];
        foreach ($schemas as $table => $columns) {
            Yii::$app->db->createCommand()->createTable($table, $columns)->execute();
        }
        foreach ([1, 2, 3, 4, 5] as $id) {
            Yii::$app->db->createCommand()->insert('verse', [
                'id' => $id, 'name' => 'Scene ' . $id, 'uuid' => 'verse-' . $id,
                'author_id' => $id === 3 ? 20 : 10, 'status' => $id === 2 ? 1 : 0,
            ])->execute();
            Yii::$app->db->createCommand()->insert('snapshot', [
                'id' => $id, 'verse_id' => $id, 'uuid' => 'snapshot-' . $id,
            ])->execute();
        }
        $rows = [
            'tags' => [
                ['id' => 1, 'name' => 'Old public', 'key' => 'public', 'type' => 'Status'],
                ['id' => 2, 'name' => 'Category A', 'key' => 'a', 'type' => 'Classify'],
                ['id' => 3, 'name' => 'Category B', 'key' => 'b', 'type' => 'Classify'],
                ['id' => 4, 'name' => 'Legacy category', 'key' => null, 'type' => 'Classify'],
            ],
            'property' => [['id' => 1, 'key' => 'public'], ['id' => 2, 'key' => 'checkin']],
            'verse_property' => [
                ['verse_id' => 1, 'property_id' => 1], ['verse_id' => 1, 'property_id' => 2],
                ['verse_id' => 2, 'property_id' => 2],
                ['verse_id' => 3, 'property_id' => 1], ['verse_id' => 3, 'property_id' => 2],
                ['verse_id' => 4, 'property_id' => 1], ['verse_id' => 4, 'property_id' => 2],
            ],
            'verse_tags' => [
                ['verse_id' => 1, 'tags_id' => 2],
                ['verse_id' => 2, 'tags_id' => 1], ['verse_id' => 2, 'tags_id' => 2],
                ['verse_id' => 3, 'tags_id' => 2],
                ['verse_id' => 4, 'tags_id' => 2], ['verse_id' => 4, 'tags_id' => 3],
                ['verse_id' => 5, 'tags_id' => 4],
            ],
            'group_verse' => [
                ['group_id' => 7, 'verse_id' => 1], ['group_id' => 7, 'verse_id' => 2],
                ['group_id' => 7, 'verse_id' => 4], ['group_id' => 8, 'verse_id' => 3],
            ],
            'group_user' => [['group_id' => 7, 'user_id' => 10], ['group_id' => 8, 'user_id' => 20]],
            'user' => [
                ['id' => 10, 'username' => 'owner', 'auth_key' => 'test'],
                ['id' => 20, 'username' => 'other', 'auth_key' => 'test'],
            ],
        ];
        foreach ($rows as $table => $items) {
            foreach ($items as $item) {
                Yii::$app->db->createCommand()->insert($table, $item)->execute();
            }
        }
    }

    private function ids(ActiveDataProvider $provider): array
    {
        $provider->pagination = false;
        $ids = array_map(static fn ($model) => (int) $model->id, $provider->getModels());
        sort($ids);
        return $ids;
    }

    private function snapshots(string $scope, array $params): ActiveDataProvider
    {
        $search = new SnapshotSearch();
        return match ($scope) {
            'public' => $search->searchPublic($params),
            'checkin' => $search->searchCheckin($params),
            'private' => $search->searchPrivate($params, 10),
            'group' => $search->searchGroup($params, 10),
            default => $search->search($params),
        };
    }

    public static function scopeCases(): iterable
    {
        yield 'public property without legacy tag' => ['public', [], [1, 3, 4]];
        yield 'public cannot be widened' => ['public', ['public' => 0], []];
        yield 'checkin publicly visible' => ['checkin', ['public' => 1], [1, 3, 4]];
        yield 'checkin not public' => ['checkin', ['public' => 0], [2]];
        yield 'owner publicly visible' => ['private', ['public' => 'true'], [1, 4]];
        yield 'owner not public' => ['private', ['public' => 'false'], [2, 5]];
        yield 'group publicly visible' => ['group', ['public' => true], [1, 4]];
        yield 'group not public' => ['group', ['public' => false], [2]];
        yield 'old public tag only' => ['private', ['tags' => '1'], []];
        yield 'old tag and category' => ['private', ['tags' => '1,2'], [1, 2, 4]];
        yield 'category OR without duplicate scenes' => ['private', ['tags' => '2,3'], [1, 2, 4]];
        yield 'property AND category' => ['private', ['public' => 1, 'tags' => '2'], [1, 4]];
        yield 'not property AND category' => ['private', ['public' => 0, 'tags' => '2'], [2]];
        yield 'nullable category key remains usable' => ['private', ['tags' => [4]], [5]];
        yield 'malformed category cannot widen scope' => ['private', ['tags' => 'bad'], []];
        yield 'base search property filter' => ['all', ['public' => '1'], [1, 3, 4]];
    }

    #[DataProvider('scopeCases')]
    public function testActualSnapshotResults(string $scope, array $params, array $expected): void
    {
        self::assertSame($expected, $this->ids($this->snapshots($scope, $params)));
    }

    public static function normalizationCases(): iterable
    {
        foreach ([1, '1', true, 'true'] as $value) {
            yield [ $value, true ];
        }
        foreach ([0, '0', false, 'false'] as $value) {
            yield [ $value, false ];
        }
    }

    #[DataProvider('normalizationCases')]
    public function testExactPublicNormalization($value, bool $expected): void
    {
        self::assertSame($expected, SceneQueryFilters::normalizePublic(['public' => $value]));
        self::assertNull(SceneQueryFilters::normalizePublic([]));
    }

    public static function invalidPublicCases(): iterable
    {
        foreach (['', 'foo', '2', 2, null, [], ['1']] as $value) {
            foreach (['public', 'checkin', 'private', 'group'] as $scope) {
                yield [$scope, $value];
            }
        }
    }

    #[DataProvider('invalidPublicCases')]
    public function testInvalidPublicRejectedBeforeCachedQuery(string $scope, $value): void
    {
        $this->ids($this->snapshots($scope, []));
        $this->expectException(BadRequestHttpException::class);
        $this->snapshots($scope, ['public' => $value]);
    }

    public function testQueryCacheSeparatesAbsentTrueAndFalse(): void
    {
        // Repeat in both orders while the Yii2 SQL result cache is enabled.
        foreach ([[], ['public' => 1], ['public' => 0], ['public' => 1], [], ['public' => 0]] as $params) {
            $expected = !array_key_exists('public', $params) ? [1, 2, 4, 5] : ($params['public'] ? [1, 4] : [2, 5]);
            self::assertSame($expected, $this->ids($this->snapshots('private', $params)));
        }
    }

    public function testPublicPropertyNeedsNoClassificationAssociation(): void
    {
        Yii::$app->db->createCommand()->delete('verse_tags', ['verse_id' => 1])->execute();
        self::assertSame([1, 3, 4], $this->ids($this->snapshots('public', [])));
        $controller = new PublicController('public', Yii::$app);
        self::assertSame(1, (int) $controller->actionById(1)->id);
        self::assertSame(1, (int) $controller->actionByUuid('snapshot-1')->id);
        self::assertSame(1, (int) $controller->actionByVerseId(1)->id);
    }

    public function testTagsDirectoryNeverReturnsPublic(): void
    {
        // Exclusion follows the key even if the obsolete record was Classify.
        Yii::$app->db->createCommand()->update('tags', ['type' => 'Classify'], ['id' => 1])->execute();
        self::assertSame([2, 3, 4], $this->ids((new TagsSearch())->search([])));
        self::assertSame([], $this->ids((new TagsSearch())->search(['TagsSearch' => ['type' => 'Status']])));
        self::assertSame([], $this->ids((new TagsSearch())->search(['TagsSearch' => ['key' => 'public']])));
    }

    public function testVerseQueriesUsePropertiesAndClassifications(): void
    {
        self::assertSame([1, 3, 4], $this->ids((new VerseSearch())->search(['public' => 1])));
        self::assertSame([], $this->ids((new VerseSearch())->search(['tags' => '1'])));
        self::assertSame([2], $this->ids((new VerseSearch())->search(['public' => 0, 'tags' => '1,2'])));
    }

    public function testVersePolicyUsesPropertyAndRetainsOwnerPermissions(): void
    {
        $policy = new VersePolicy();
        $propertyOnly = Verse::findOne(1);
        $oldTagAndStatusOnly = Verse::findOne(2);
        $owner = User::findOne(10);
        $other = User::findOne(20);
        self::assertTrue($policy->canView(null, $propertyOnly));
        self::assertFalse($policy->canView(null, $oldTagAndStatusOnly));
        self::assertTrue($policy->canView($owner, $oldTagAndStatusOnly));
        self::assertFalse($policy->canView($other, $oldTagAndStatusOnly));
        self::assertTrue($policy->canUpdate($owner, $oldTagAndStatusOnly));
        self::assertTrue($policy->canDelete($owner, $oldTagAndStatusOnly));
        self::assertFalse($policy->canUpdate($other, $propertyOnly));
        self::assertFalse($policy->canDelete($other, $propertyOnly));
    }

    public static function publicDetailCases(): iterable
    {
        foreach (['actionById', 'actionByUuid', 'actionByVerseId'] as $action) {
            yield $action . ' property only' => [$action, $action === 'actionByUuid' ? 'snapshot-1' : 1, true];
            yield $action . ' old tag only' => [$action, $action === 'actionByUuid' ? 'snapshot-2' : 2, false];
            yield $action . ' absent identifier' => [$action, $action === 'actionByUuid' ? 'absent' : 99, false];
        }
    }

    #[DataProvider('publicDetailCases')]
    public function testPublicDetailsRequireProperty(string $action, $identifier, bool $allowed): void
    {
        $controller = new PublicController('public', Yii::$app);
        if (!$allowed) {
            $this->expectException(BadRequestHttpException::class);
        }
        $model = $controller->$action($identifier);
        self::assertSame(1, (int) $model->id);
    }

    public static function controllerScopeCases(): iterable
    {
        foreach (['public', 'checkin', 'private', 'group'] as $scope) {
            yield [$scope];
        }
    }

    #[DataProvider('controllerScopeCases')]
    public function testServerAndV2ScopesApplyPublicAndTags(string $scope): void
    {
        Yii::$app->user->setIdentity(User::findOne(10));
        Yii::$app->request->setQueryParams(['scope' => $scope, 'public' => '1', 'tags' => '1,2']);
        $expected = in_array($scope, ['private', 'group'], true) ? [1, 4] : [1, 3, 4];
        $server = new ServerController('server', Yii::$app, new SnapshotSearch(), new TagsSearch());
        $action = 'action' . ucfirst($scope);
        self::assertSame($expected, $this->ids($server->$action()));
        $v2 = new V2SnapshotController('snapshot', Yii::$app, new SnapshotSearch());
        self::assertSame($expected, $this->ids($v2->actionIndex()));
    }

    #[DataProvider('controllerScopeCases')]
    public function testServerAndV2InvalidPublicMapsToHttp400(string $scope): void
    {
        Yii::$app->user->setIdentity(User::findOne(10));
        Yii::$app->request->setQueryParams(['scope' => $scope, 'public' => ['1']]);
        $server = new ServerController('server', Yii::$app, new SnapshotSearch(), new TagsSearch());
        $v2 = new V2SnapshotController('snapshot', Yii::$app, new SnapshotSearch());
        $action = 'action' . ucfirst($scope);
        foreach ([fn () => $server->$action(), fn () => $v2->actionIndex()] as $call) {
            try {
                $call();
                self::fail('Invalid public must throw HTTP 400.');
            } catch (BadRequestHttpException $error) {
                Yii::$app->errorHandler->renderForTest($error);
                self::assertSame(400, Yii::$app->response->statusCode);
                self::assertSame(400, Yii::$app->response->data['status']);
            }
        }
    }

    public function testV2GuestCannotUsePrivateOrGroup(): void
    {
        $v2 = new V2SnapshotController('snapshot', Yii::$app, new SnapshotSearch());
        foreach (['private', 'group'] as $scope) {
            Yii::$app->request->setQueryParams(['scope' => $scope, 'public' => 1]);
            try {
                $v2->actionIndex();
                self::fail('Guest scope must remain forbidden.');
            } catch (ForbiddenHttpException $error) {
                self::assertSame(403, $error->statusCode);
            }
        }
    }

    public function testLegacyListControllersSharePropertyFiltering(): void
    {
        Yii::$app->user->setIdentity(User::findOne(10));
        Yii::$app->request->setQueryParams(['public' => 0, 'tags' => '1,2']);
        self::assertSame([], $this->ids((new PublicController('public', Yii::$app))->actionList()));
        self::assertSame([], $this->ids((new SnapshotController('snapshot', Yii::$app))->actionPublic()));
        self::assertSame([2], $this->ids((new PrivateController('private', Yii::$app))->actionList()));
        self::assertSame([2], $this->ids((new SnapshotController('snapshot', Yii::$app))->actionIndex()));
        $verse = new VerseController('verse', Yii::$app, new VersePolicy(), new VerseSearch());
        self::assertSame([2], $this->ids($verse->actionIndex()));
        self::assertSame([], $this->ids($verse->actionPublic()));
    }
}

/** Keep the real Yii error renderer/status mapping while suppressing HTTP output. */
final class BufferedResponse extends Response
{
    public function send()
    {
        $this->isSent = true;
    }
}

final class TestErrorHandler extends \yii\web\ErrorHandler
{
    public function renderForTest(\Throwable $error): void
    {
        $this->renderException($error);
    }
}
