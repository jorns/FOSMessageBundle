<?php

namespace Ornicar\MessageBundle\DocumentManager;

use Doctrine\ODM\MongoDB\DocumentManager;
use Ornicar\MessageBundle\Model\MessageInterface;
use Ornicar\MessageBundle\ModelManager\MessageManager as BaseMessageManager;
use Ornicar\MessageBundle\Model\ReadableInterface;
use FOS\UserBundle\Model\UserInterface;
use Ornicar\MessageBundle\Model\ThreadInterface;
use Doctrine\ODM\MongoDB\Query\Builder;

/**
 * Default MongoDB MessageManager.
 *
 * @author Thibault Duplessis <thibault.duplessis@gmail.com>
 */
class MessageManager extends BaseMessageManager
{
    /**
     * @var DocumentManager
     */
    protected $dm;

    /**
     * @var DocumentRepository
     */
    protected $repository;

    /**
     * @var string
     */
    protected $class;

    /**
     * Constructor.
     *
     * @param DocumentManager         $dm
     * @param string                  $class
     */
    public function __construct(DocumentManager $dm, $class)
    {
        $this->dm         = $dm;
        $this->repository = $dm->getRepository($class);
        $this->class      = $dm->getClassMetadata($class)->name;
    }

    /**
     * Marks the readable as read by this participant
     * Must be applied directly to the storage,
     * without modifying the readable state.
     * We want to show the unread readables on the page,
     * as well as marking the as read.
     *
     * @param ReadableInterface $readable
     * @param UserInterface $user
     */
    public function markAsReadByParticipant(ReadableInterface $readable, UserInterface $user)
    {
        return $this->markIsReadByParticipant($readable, $user, true);
    }

    /**
     * Marks the readable as unread by this participant
     *
     * @param ReadableInterface $readable
     * @param UserInterface $user
     */
    public function markAsUnreadByParticipant(ReadableInterface $readable, UserInterface $user)
    {
        return $this->markIsReadByParticipant($readable, $user, false);
    }

    /**
     * Marks all messages of this thread as read by this participant
     *
     * @param ThreadInterface $thread
     * @param UserInterface $user
     * @param boolean $isRead
     */
    public function markIsReadByThreadAndParticipant(ThreadInterface $thread, UserInterface $user, $isRead)
    {
        $this->markIsReadByCondition($user, $isRead, function(Builder $queryBuilder) use ($thread) {
            $queryBuilder->field('thread.$id')->equals(new \MongoId($thread->getId()));
        });
    }

    /**
     * Marks the message as read or unread by this participant
     *
     * @param MessageInterface $message
     * @param UserInterface $user
     * @param boolean $isRead
     */
    protected function markIsReadByParticipant(MessageInterface $message, UserInterface $user, $isRead)
    {
        $this->markIsReadByCondition($user, $isRead, function(Builder $queryBuilder) use ($message) {
            $queryBuilder->field('id')->equals($message->getId());
        });
    }

    /**
     * Marks messages as read/unread
     * by updating directly the storage
     *
     * @param UserInterface $user
     * @param boolean $isRead
     * @param \Closure $condition
     */
    protected function markIsReadByCondition(UserInterface $user, $isRead, \Closure $condition)
    {
        $isReadByParticipantFieldName = sprintf('isReadByParticipant.%s', $user->getId());
        $queryBuilder = $this->repository->createQueryBuilder();
        $condition($queryBuilder);
        $queryBuilder->update()
            ->field($isReadByParticipantFieldName)->set((boolean) $isRead)
            ->getQuery(array('multiple' => true))
            ->execute();
    }

    /**
     * Saves a message
     *
     * @param MessageInterface $message
     * @param Boolean $andFlush Whether to flush the changes (default true)
     */
    public function updateMessage(MessageInterface $message, $andFlush = true)
    {
        $this->dm->persist($message);
        if ($andFlush) {
            $this->dm->flush();
        }
    }

    /**
     * Returns the fully qualified comment thread class name
     *
     * @return string
     **/
    public function getClass()
    {
        return $this->class;
    }
}