<?php

namespace RadioSolution\ProgramBundle\Entity;

use Doctrine\ORM\EntityRepository;

/**
 * MusicRepository.
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class MusicRepository extends EntityRepository
{
    /**
     * @param string $orderBy ASC/DESC
     *
     * @return array
     */
    public function findAllFeatured($limit = 20, $direction = 'DESC')
    {
        return $this->queryPublishedOrderedByFeaturedFrom($limit, $direction)
            ->getQuery()
            ->getResult()
        ;
    }

    public function queryPublishedOrderedByFeaturedFrom($limit = 20, $direction = 'DESC')
    {
        return $this->createQueryBuilder('e')
            ->where('e.published = true')
            ->andWhere('e.featuredFrom is not NULL and e.featuredTo is not NULL')
            ->addOrderBy('e.featuredFrom', $direction)
            //->setMaxResults($limit)
        ;
    }

    public function findPublished($id)
    {
        return $this->queryByIdAndPublished($id)
            ->getQuery()
            ->getSingleResult()
        ;
    }

    public function queryByIdAndPublished($id)
    {
        return $this->createQueryBuilder('e')
            ->where('e.published = true')
            ->andWhere('e.id = :id')
            ->setParameter('id', $id);
        ;
    }

    public function findPublishedBySlug($slug)
    {
        return $this->queryBySlugAndPublished($slug)
            ->getQuery()
            ->getSingleResult()
        ;
    }

    public function queryBySlugAndPublished($slug)
    {
        return $this->createQueryBuilder('e')
            ->where('e.published = true')
            ->andWhere('e.slug = :slug')
            ->setParameter('slug', $slug);
        ;
    }
}
