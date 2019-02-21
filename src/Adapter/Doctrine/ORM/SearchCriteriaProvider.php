<?php

/*
 * Symfony DataTables Bundle
 * (c) Omines Internetbureau B.V. - https://omines.nl/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Omines\DataTablesBundle\Adapter\Doctrine\ORM;

use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\QueryBuilder;
use Omines\DataTablesBundle\Column\AbstractColumn;
use Omines\DataTablesBundle\DataTableState;
use Omines\DataTablesBundle\Adapter\Doctrine\ORM\QueryBuilderProcessorInterface;

/**
 * SearchCriteriaProvider.
 *
 * @author Niels Keurentjes <niels.keurentjes@omines.com>
 */
class SearchCriteriaProvider implements QueryBuilderProcessorInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(QueryBuilder $queryBuilder, DataTableState $state)
    {
        $this->processSearchColumns($queryBuilder, $state);
        $this->processGlobalSearch($queryBuilder, $state);
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param DataTableState $state
     */
    private function processSearchColumns(QueryBuilder $queryBuilder, DataTableState $state)
    {
        foreach ($state->getSearchColumns() as $searchInfo) {
            /** @var AbstractColumn $column */
            $column = $searchInfo['column'];
            $search = $searchInfo['search'];

            if (null != $search && null !== ($filter = $column->getFilter()) && $filter->getOperator()) {
                $search = $filter->getValue($search);

                if (strtoupper($filter->getOperator()) === 'LIKE') {
                    $expr = $queryBuilder->expr();
                    $queryBuilder->andWhere($expr->like($column->getField(), $expr->literal("%{$search}%")));
                } else if (strtoupper($filter->getOperator()) === 'BETWEEN') {
                    $field = $column->getField();
                    $queryBuilder->andWhere("$field BETWEEN :left AND :right")
                        ->setParameter('left', $search[0])
                        ->setParameter('right', $search[1])
                    ;
                } else {
                    $queryBuilder->andWhere(new Comparison($column->getField(), $filter->getOperator(), "'$search'"));
                }
            }
        }
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param DataTableState $state
     */
    private function processGlobalSearch(QueryBuilder $queryBuilder, DataTableState $state)
    {
        if (null != ($globalSearch = $state->getGlobalSearch())) {
            $expr = $queryBuilder->expr();
            $comparisons = $expr->orX();
            foreach ($state->getDataTable()->getColumns() as $column) {
                if ($column->isGlobalSearchable() && !empty($column->getField()) && $column->isValidForSearch($globalSearch)) {
                    $comparisons->add(new Comparison($column->getLeftExpr(), $column->getOperator(),
                        $expr->literal($column->getRightExpr($globalSearch))));
                }
            }
            $queryBuilder->andWhere($comparisons);
        }
    }
}
