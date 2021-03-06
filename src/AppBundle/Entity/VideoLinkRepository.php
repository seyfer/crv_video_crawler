<?php

namespace AppBundle\Entity;

use Doctrine\ORM\EntityRepository;

/**
 * VideoLinkRepository
 *
 * This class was generated by the PhpStorm "Php Annotations" Plugin. Add your own custom
 * repository methods below.
 */
class VideoLinkRepository extends EntityRepository
{
    /**
     * @return array
     */
    public function removeAll()
    {
        $qb = $this->getEntityManager()->createQueryBuilder();

        $qb->delete($this->getEntityName(), 'e');

        return $qb->getQuery()->getResult();
    }

    /**
     * @param bool $downloaded
     * @return array
     */
    public function markAll($downloaded = false)
    {
        $qb = $this->getEntityManager()->createQueryBuilder();

        $qb->update($this->getEntityName(), 'e')
           ->set('e.downloaded', '?1')
           ->setParameter(1, $downloaded);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param array $params
     * @return array
     */
    public function getLinksBy(array $params = [])
    {
        $qb = $this->createQueryBuilder('e');

        if (isset($params['downloaded'])) {
            $qb->andWhere('e.downloaded = :downloaded')
               ->setParameter('downloaded', (boolean)$params['downloaded']);
        }

        if (isset($params['fromId'])) {
            $qb->andWhere('e.id >= :fromId')
               ->setParameter('fromId', (int)$params['fromId']);
        }

        if (isset($params['id'])) {
            $qb->andWhere('e.id >= :id')
               ->setParameter('id', (int)$params['id']);
        }

        $qb->orderBy('e.id', 'ASC');

        $query = $qb->getQuery();

        return $query->getResult();
    }
}
