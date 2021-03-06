<?php

namespace rynpsc\reviews\elements\db;

use rynpsc\reviews\Plugin;
use rynpsc\reviews\elements\Review;
use rynpsc\reviews\models\Summary;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use craft\helpers\StringHelper;

class ReviewQuery extends ElementQuery
{
    public $submissionDate;
    public $element;
    public $elementId;
    public $email;
    public $fullName;
    public $rating;
    public $review;
    public $typeId;
    public $moderationStatus;
    public $userId;

    /**
     * @inheritdoc
     */
    public function __construct($elementType, array $config = [])
    {
        // Default status
        if (!isset($config['status'])) {
            $config['status'] = 'live';
        }

        parent::__construct($elementType, $config);
    }

    /**
     * @inheritdoc
     */
    protected $defaultOrderBy = [
        'reviews_reviews.submissionDate' => SORT_DESC,
    ];

    public function fullName($value): self
    {
        $this->fullName = $value;
        return $this;
    }

    public function rating($value): self
    {
        $this->rating = $value;
        return $this;
    }

    public function submissionDate($value): self
    {
        $this->submissionDate = $value;
        return $this;
    }

    public function email($value): self
    {
        $this->email = $value;
        return $this;
    }

    public function typeId($value): self
    {
        $this->typeId = $value;
        return $this;
    }

    public function userId($value): self
    {
        $this->userId = $value;
        return $this;
    }

    public function element($value): self
    {
        $this->elementId = $value->id;
        return $this;
    }

    public function elementId($value): self
    {
        $this->elementId = $value;
        return $this;
    }

    public function moderationStatus($value): self
    {
        $this->moderationStatus = $value;
        return $this;
    }

    public function summary($db = null)
    {
        $typesService = Plugin::getInstance()->getReviewTypes();

        if ($this->typeId === null) {
            return null;
        }

        $type = $typesService->getReviewTypeById($this->typeId);

        if ($type === null) {
            return null;
        }

        $this->select([
            'count' => 'COUNT(*)',
            'average' => 'AVG([[rating]])',
            'lowest' => 'MIN([[rating]])',
            'highest' => 'MAX([[rating]])',
        ]);

        for ($i = 1; $i <= $type->maxRating; $i++) {
            $param = ':rating' . $i;
            $this->addParams([$param => $i]);
            $this->addSelect(['countRating' . $i => "COUNT(CASE WHEN [[rating]] = {$param} THEN 1 END)"]);
        }

        $result = $this->createCommand($db)->queryOne();

        $model = new Summary();
        $model->setAttributes($result);

        foreach ($result as $key => $value) {
            if (StringHelper::startsWith($key, 'countRating')) {
                $model->ratingCounts[] = $value;
            }
        }

        return $model;
    }

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        $this->joinElementTable('reviews_reviews');

        $this->query->select([
            'reviews_reviews.submissionDate',
            'reviews_reviews.elementId',
            'reviews_reviews.email',
            'reviews_reviews.id',
            'reviews_reviews.fullName',
            'reviews_reviews.rating',
            'reviews_reviews.review',
            'reviews_reviews.siteId',
            'reviews_reviews.typeId',
            'reviews_reviews.userId',
            'reviews_reviews.moderationStatus',
        ]);

        if ($this->elementId) {
            $this->subQuery->andWhere(Db::parseParam('reviews_reviews.elementId', $this->elementId));
        }

        if ($this->email) {
            $this->subQuery->andWhere(Db::parseParam('reviews_reviews.email', $this->email));
        }

        if ($this->moderationStatus) {
            $this->subQuery->andWhere(Db::parseParam('reviews_reviews.moderationStatus', $this->moderationStatus));
        }

        if ($this->fullName) {
            $this->subQuery->andWhere(Db::parseParam('reviews_reviews.fullName', $this->fullName));
        }

        if ($this->submissionDate) {
            $this->subQuery->andWhere(Db::parseDateParam('reviews_reviews.submissionDate', $this->submissionDate));
        }

        if ($this->rating) {
            $this->subQuery->andWhere(Db::parseParam('reviews_reviews.rating', $this->rating));
        }

        if ($this->review) {
            $this->subQuery->andWhere(Db::parseParam('reviews_reviews.review', $this->review));
        }

        if ($this->typeId) {
            $this->subQuery->andWhere(Db::parseParam('reviews_reviews.typeId', $this->typeId));
        }

        if ($this->userId) {
            $this->subQuery->andWhere(Db::parseParam('reviews_reviews.userId', $this->userId));
        }

        return parent::beforePrepare();
    }

    /**
     * @inheritdoc
     */
    protected function statusCondition(string $status)
    {
        switch ($status) {
            case Review::STATUS_LIVE:
                return [
                    'elements.enabled' => true,
                    'reviews_reviews.moderationStatus' => Review::STATUS_APPROVED,
                ];
            case Review::STATUS_APPROVED:
                return [
                    'reviews_reviews.moderationStatus' => Review::STATUS_APPROVED,
                ];
            case Review::STATUS_PENDING:
                return [
                    'reviews_reviews.moderationStatus' => Review::STATUS_PENDING,
                ];
            case Review::STATUS_REJECTED:
                return [
                    'reviews_reviews.moderationStatus' => Review::STATUS_REJECTED,
                ];
            default:
                return parent::statusCondition($status);
        }
    }
}
