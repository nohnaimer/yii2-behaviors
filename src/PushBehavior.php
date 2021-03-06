<?php

namespace lav45\behaviors;

use yii\base\Behavior;
use yii\db\ActiveRecord;
use yii\db\ActiveRecordInterface;
use yii\db\AfterSaveEvent;
use lav45\behaviors\traits\WatchAttributesTrait;

/**
 * Class PushBehavior
 *
 * ================ Example usage ================
 * public function behaviors()
 * {
 *      return [
 *          [
 *              'class' => PushBehavior::class,
 *              'relation' => 'apiUser',
 *              'attributes' => [
 *                  // Observe the change in the `status` attribute
 *                  // Writes the "value" in field `status` the relation model
 *                  'status',
 *
 *                  // Observe the change in the `status` attribute
 *                  // Writes the "value" in field `statusName` the relation model
 *                  'status' => 'statusName',
 *
 *                  // or
 *                  'status' => [  // Watch for changed attribute `status`
 *                      'field' => 'statusName', // and set this value in to the relation attribute `statusName`
 *                      'value' => 'status_name', // then get value from the attribute `status_name`
 *                  ],
 *
 *                  // Observe the change in the `status` attribute
 *                  [
 *                      'watch' => 'status', // if changed attribute `status`
 *                      // 'watch' => ['status', 'username'], // Watch for changes in a few fields
 *
 *                      'field' => 'statusName', // and set value in this relation attribute `statusName`
 *                      'value' => 'array.key', // then get value from the $this->array['key']
 *                      // 'value' => ['array', 'key'],
 *                      // 'value' => function($owner) {
 *                      //     return $owner->array['key'];
 *                      // },
 *                  ],
 *              ]
 *          ]
 *      ];
 * }
 *
 * @package lav45\behaviors
 * @property ActiveRecord $owner
 */
class PushBehavior extends Behavior
{
    use WatchAttributesTrait;

    /**
     * @var string target relation name
     */
    public $relation;
    /**
     * @var bool|\Closure whether to create related models
     * Can be passed to \Closure, then the user can instantiate the associated model
     * function (ActiveRecord $model) {
     *     $model->save();
     * }
     */
    public $updateRelation = true;
    /**
     * @var bool|\Closure whether to delete related models
     * Can be passed to \Closure then the user will be able to decide how to unlink the link to the linked model
     * function (ActiveRecord $model) {
     *      // performed necessary actions related model
     *      $model->delete();
     * }
     */
    public $deleteRelation = true;
    /**
     * @var bool|\Closure whether to create related models
     * Can be passed to \Closure, then the user can instantiate the associated model
     * function () {
     *      return new Group(); // ActiveRecord extension
     * }
     */
    public $createRelation = true;

    /**
     * @inheritdoc
     */
    public function events()
    {
        $events = [ActiveRecord::EVENT_AFTER_INSERT => 'afterInsert'];

        if (false !== $this->updateRelation) {
            $events[ActiveRecord::EVENT_AFTER_UPDATE] = 'afterUpdate';
        }
        if (false !== $this->deleteRelation) {
            $events[ActiveRecord::EVENT_BEFORE_DELETE] = 'beforeDelete';
        }

        return $events;
    }

    /**
     * Insert related model
     */
    final public function afterInsert()
    {
        foreach ($this->getRelationIterator() as $model) {
            if (null === $model) {
                if (false === $this->createRelation) {
                    continue;
                }
                if (true === $this->createRelation) {
                    $model = $this->createRelationModel();
                } elseif (is_callable($this->createRelation)) {
                    $model = call_user_func($this->createRelation);
                }
            }
            $this->updateModel($model, $this->attributes);
            if ($model->getIsNewRecord()) {
                $this->owner->link($this->relation, $model);
            } else {
                $model->save(false);
            }
        }
    }

    /**
     * Update fields in related model
     * @param AfterSaveEvent $event
     */
    final public function afterUpdate(AfterSaveEvent $event)
    {
        if ($changedAttributes = $this->getChangedAttributes($event->changedAttributes)) {
            foreach ($this->getRelationIterator(true) as $model) {
                $this->updateModel($model, $changedAttributes);
                if (true === $this->updateRelation) {
                    $model->save(false);
                } elseif (is_callable($this->updateRelation)) {
                    call_user_func($this->updateRelation, $model);
                }
            }
        }
    }

    /**
     * @throws \Exception
     * @throws \Throwable
     */
    final public function beforeDelete()
    {
        foreach ($this->getRelationIterator(true) as $model) {
            if (true === $this->deleteRelation) {
                $model->delete();
            } elseif (is_callable($this->deleteRelation)) {
                call_user_func($this->deleteRelation, $model);
            }
        }
    }

    /**
     * @return ActiveRecordInterface
     */
    protected function createRelationModel()
    {
        $class = $this->owner->getRelation($this->relation)->modelClass;
        return new $class;
    }

    /**
     * @param bool $skip_empty
     * @return \Generator|ActiveRecordInterface[]
     */
    private function getRelationIterator($skip_empty = false)
    {
        $relation = $this->owner->getRelation($this->relation);

        if (true === $relation->multiple) {
            foreach ($relation->each() as $item) {
                yield $item;
            }
        } else {
            $item = $relation->one();
            if (true === $skip_empty) {
                if ($item) {
                    yield $item;
                }
            } else {
                yield $item;
            }
        }
    }
}