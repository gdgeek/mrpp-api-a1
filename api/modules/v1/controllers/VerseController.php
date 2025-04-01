<?php
namespace app\modules\v1\controllers;
use app\modules\v1\models\VerseSearch;
use yii\web\BadRequestHttpException;

//use app\modules\v1\models\VerseReleaseSearch;
use yii\helpers\HtmlPurifier;
use yii\rest\ActiveController;
use Yii;
class VerseController extends ActiveController
{

    public $modelClass = 'app\modules\v1\models\Verse';
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        return $behaviors;
    }
    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index']);
        unset($actions['create']);
        unset($actions['update']);
        unset($actions['delete']);
        unset($actions['options']);
        //  unset($actions['view']);
        return $actions;
    }


    public function actionIndex()
    {




        $searchModel = new VerseSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $dataProvider->query->andWhere(['author_id' => Yii::$app->user->id]);


        $tags = Yii::$app->request->get('tags');

        // 如果tags参数存在，将其转换为数字数组
        if ($tags) {
            $tagsArray = array_map('intval', explode(',', $tags));
            if (isset($tagsArray) && !empty($tagsArray)) {
                // 假设有一个 verse_tags 表，包含 verse_id 和 tag_id 字段
                $dataProvider->query->innerJoin('verse_tags', 'verse_tags.verse_id = verse.id')
                    ->andWhere(['in', 'verse_tags.tags_id', $tagsArray])
                    ->groupBy('verse.id'); // 避免重复结果
            }
        }

        return $dataProvider;
    }
    public function actionPublic()
    {
        $searchModel = new VerseSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        // 合并查询：直接在主查询中添加标签条件
        $dataProvider->query->innerJoin('verse_tags AS vt_public', 'vt_public.verse_id = verse.id')
            ->innerJoin('tags AS t_public', 't_public.id = vt_public.tags_id')
            ->andWhere(['t_public.key' => 'public']);

        // 处理额外的标签过滤
        $tags = Yii::$app->request->get('tags');
        // 如果tags参数存在，将其转换为数字数组
        if ($tags) {
            $tagsArray = array_map('intval', explode(',', $tags));
            if (isset($tagsArray) && !empty($tagsArray)) {
                // 假设有一个 verse_tags 表，包含 verse_id 和 tag_id 字段
                $dataProvider->query->innerJoin('verse_tags', 'verse_tags.verse_id = verse.id')
                    ->andWhere(['in', 'verse_tags.tags_id', $tagsArray])
                    ->groupBy('verse.id'); // 避免重复结果
            }
        }

        return $dataProvider;
    }

    public function actionOpen()
    {
        $searchModel = new VerseSearch();
        $papeSize = Yii::$app->request->get('pageSize', 15);
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams, $papeSize);
        $query = $dataProvider->query;
        $query->select('verse.*')->leftJoin('verse_open', '`verse_open`.`verse_id` = `verse`.`id`')->andWhere(['NOT', ['verse_open.id' => null]]);
        return $dataProvider;
    }
    public function actionRelease()
    {
        if (!isset(Yii::$app->request->queryParams['code'])) {
            throw new BadRequestHttpException('缺乏 code 数据');
        }
        $code = Yii::$app->request->queryParams['code'];
        $searchModel = new VerseSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $query = $dataProvider->query;

        $query->select('verse.*')->leftJoin('verse_release', '`verse_release`.`verse_id` = `verse`.`id`')->andWhere(['verse_release.code' => $code]);
        return $query->one();
    }
}
