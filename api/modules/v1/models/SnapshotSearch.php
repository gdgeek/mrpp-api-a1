<?php

namespace app\modules\v1\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use app\modules\v1\models\Snapshot;

/**
 * SnapshotSearch represents the model behind the search form of `app\modules\v1\models\Snapshot`.
 */
class SnapshotSearch extends Snapshot
{
    /**
     * {@inheritdoc}
     */
    public $tags;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'verse_id', 'created_by'], 'integer'],
            [['name', 'description', 'uuid', 'code', 'data', 'image', 'metas', 'resources', 'created_at', 'type', 'tags'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios()
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search($params, $pageSize = 15)
    {
        $query = Snapshot::find();

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => $pageSize,
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'id' => $this->id,
            'verse_id' => $this->verse_id,
            'created_at' => $this->created_at,
            'created_by' => $this->created_by,
        ]);

        $query->andFilterWhere(['like', 'uuid', $this->uuid])
            ->andFilterWhere(['like', 'code', $this->code])
            ->andFilterWhere(['like', 'data', $this->data])
            ->andFilterWhere(['like', 'metas', $this->metas])
            ->andFilterWhere(['like', 'resources', $this->resources]);

        return $dataProvider;
    }

    public function searchCheckin($params)
    {
        $dataProvider = $this->search($params);
        $query = $dataProvider->query;

        // 链接tag表进行过滤。tag表的key为checkin,通过verse_tags链接
        $query->innerJoin('verse_property AS vp', 'vp.verse_id  = snapshot.verse_id')
            ->innerJoin('property', 'property.id = vp.property_id')
            ->andWhere(['property.key' => 'checkin'])
            ->groupBy(['snapshot.id']);

        return $dataProvider;
    }

    public function searchPublic($params, $pageSize = 15)
    {
        $dataProvider = $this->search($params, $pageSize);
        $query = $dataProvider->query;

        $query->innerJoin('verse_property AS vp1', 'vp1.verse_id = snapshot.verse_id')
            ->innerJoin('property', 'property.id = vp1.property_id')
            ->andWhere(['property.key' => 'public']);
            
        $this->applyTagFilter($query);
        
        // 读取结果缓存 30 秒
        $query->cache(30);

        return $dataProvider;
    }

    public function searchPrivate($params, $userId, $pageSize = 15)
    {
        $dataProvider = $this->search($params, $pageSize);
        $query = $dataProvider->query;

        $query->innerJoin('verse AS v1', 'v1.id = snapshot.verse_id')
            ->andWhere(['in', 'v1.author_id', $userId]);

        $this->applyTagFilter($query);

        // 读取结果缓存 30 秒
        $query->cache(30);

        return $dataProvider;
    }

    public function searchGroup($params, $userId, $pageSize = 15)
    {
        // 使用子查询合并为一次查询
        // group_user -> group_verse -> snapshot
        $verseIdsSubQuery = (new \yii\db\Query())
            ->select('gv.verse_id')
            ->from('group_verse gv')
            ->innerJoin('group_user gu', 'gu.group_id = gv.group_id')
            ->where(['gu.user_id' => $userId]);

        // 直接复用 search 方法的基础逻辑，但这里 query 需要特殊处理，或者直接构建
        // 原逻辑是 Snapshot::find()->where(['verse_id' => $subQuery])
        // 这里我们可以先调用 search 获取 DataProvider，然后修改 query
        
        $dataProvider = $this->search($params, $pageSize);
        // 重置 where 条件或合并? 原逻辑似乎不依赖 search 的各种 filterWhere，或者说 searchGroup 本身也应该支持 search 的过滤?
        // 假设 searchGroup 也应该支持 id/name 等过滤，那么我们在 search 的 query 上追加条件
        
        $dataProvider->query->andWhere(['verse_id' => $verseIdsSubQuery]);
        
        $dataProvider->query->cache(30);
        
        return $dataProvider;
    }

    protected function applyTagFilter($query)
    {
        if ($this->tags) {
            $tagsArray = array_map('intval', explode(',', $this->tags));
            if (!empty($tagsArray)) {
                // 假设有一个 verse_tags 表，包含 verse_id 和 tag_id 字段
                $query->innerJoin('verse_tags AS vt2', 'vt2.verse_id = snapshot.verse_id')
                    ->andWhere(['in', 'vt2.tags_id', $tagsArray])
                    ->groupBy('snapshot.id');
            }
        }
    }
}
