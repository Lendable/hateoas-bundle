<?php
/**
 * @copyright 2014 Integ S.A.
 * @license http://opensource.org/licenses/MIT The MIT License (MIT)
 * @author Javier Lorenzana <javier.lorenzana@gointegro.com>
 */

namespace GoIntegro\Bundle\HateoasBundle\Util;

// ORM.
use Doctrine\ORM\EntityManagerInterface;
// Paginadores
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
// Colecciones
use GoIntegro\Bundle\HateoasBundle\Collections\PaginatedCollection;
// Request.
use GoIntegro\Bundle\HateoasBundle\JsonApi\Request;
use GoIntegro\Bundle\HateoasBundle\JsonApi\Request\Params;

class RepositoryHelper
{
    const RESOURCE_ENTITY_INTERFACE = 'GoIntegro\\Bundle\\HateoasBundle\\JsonApi\\ResourceEntityInterface';

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var array
     */
    private $filters = [];

    /**
     * @param EntityManagerInterface
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Helper method to paginate a query using the HATEOAS request parameters.
     * @param Params $request
     * @return PaginatedCollection
     */
    public function findByRequestParams(Params $params)
    {
        return $this->findPaginated(
            $params->primaryClass,
            $params->filters,
            $params->sorting,
            $params->getPageOffset(),
            $params->getPageSize()
        );
    }

    /**
     * Helper method to paginate "find by" queries.
     * @param string $entityClass
     * @param array $criteria
     * @param integer $offset
     * @param integer $limit
     * @return PaginatedCollection
     */
    public function findPaginated(
        $entityClass,
        array $criteria,
        array $sorting,
        $offset = Params::DEFAULT_PAGE_OFFSET,
        $limit = Params::DEFAULT_PAGE_SIZE
    )
    {
        $qb = $this->entityManager
            ->getRepository($entityClass)
            ->createQueryBuilder('e')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $this->applySorting($qb, $sorting, 'e');

        foreach ($this->filters as $filter) {
            if ($filter->supportsClass($entityClass)) {
                $qb = $filter->filter($qb, $criteria, 'e');
            }
        }

        $query = $qb->getQuery();
        $paginator = new Paginator($query);
        $collection = new PaginatedCollection($paginator);

        return $collection;
    }

    /**
     * @param Request\FilterInterface $filter
     * @return self
     */
    public function addFilter(Request\FilterInterface $filter)
    {
        $this->filters[] = $filter;
    }

    private function applySorting(QueryBuilder $qb, array $sorts, $alias = 'e')
    {
        foreach($sorts as $type => $sorting) {
            foreach($sorting as $resourceName => $sort) {
                foreach($sort as $field => $order) {
                    if('association' == $type) {
                        $namespace = $alias . '.' . $resourceName;
                        $qb->leftJoin($namespace, $resourceName);
                        $field = $resourceName . '.' . $field;
                    } elseif ('field' == $type) {
                        $field = $alias . '.' . $field;
                    } else {
                        break;
                    }
                    $qb->addOrderBy($field, $order);
                }
            }
        }
    }
}
