<?php

namespace app\components;

use yii\db\Query;
use yii\web\BadRequestHttpException;

/** Shared property and classification filters for scene/snapshot queries. */
final class SceneQueryFilters
{
    public static function normalizePublic(array $params): ?bool
    {
        if (!array_key_exists('public', $params)) {
            return null;
        }

        return match ($params['public']) {
            1, '1', true, 'true' => true,
            0, '0', false, 'false' => false,
            default => throw new BadRequestHttpException('public must be 0, 1, false, or true.'),
        };
    }

    public static function applyPublic($query, string $verseIdColumn, array $params): void
    {
        $public = self::normalizePublic($params);
        if ($public === null) {
            return;
        }

        $property = (new Query())
            ->select('public_filter_vp.verse_id')
            ->from(['public_filter_vp' => 'verse_property'])
            ->innerJoin(['public_filter_p' => 'property'], 'public_filter_p.id = public_filter_vp.property_id')
            ->where('public_filter_vp.verse_id = ' . $verseIdColumn)
            ->andWhere(['public_filter_p.key' => 'public']);
        $query->andWhere([$public ? 'exists' : 'not exists', $property]);
    }

    public static function applyTags($query, string $verseIdColumn, $tags): void
    {
        if ($tags === null || $tags === '' || $tags === []) {
            return;
        }
        if (!is_string($tags) && !is_array($tags)) {
            throw new BadRequestHttpException('tags must be a comma-separated ID list or an array of IDs.');
        }

        $ids = [];
        foreach (is_array($tags) ? $tags : explode(',', $tags) as $id) {
            if ((is_int($id) || (is_string($id) && ctype_digit(trim($id)))) && (int) $id > 0) {
                $ids[] = (int) $id;
            }
        }
        if ($ids === []) {
            $query->andWhere('0=1');
            return;
        }

        $matchingTag = (new Query())
            ->select('classification_vt.verse_id')
            ->from(['classification_vt' => 'verse_tags'])
            ->innerJoin(['classification_t' => 'tags'], 'classification_t.id = classification_vt.tags_id')
            ->where('classification_vt.verse_id = ' . $verseIdColumn)
            ->andWhere(['classification_t.id' => array_values(array_unique($ids))])
            ->andWhere(['or', ['<>', 'classification_t.key', 'public'], ['classification_t.key' => null]]);
        $query->andWhere(['exists', $matchingTag]);
    }
}
