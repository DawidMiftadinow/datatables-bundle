<?php

/*
 * Symfony DataTables Bundle
 * (c) Omines Internetbureau B.V. - https://omines.nl/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Omines\DataTablesBundle\Adapter;

use Omines\DataTablesBundle\DataTableState;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * ArrayAdapter.
 *
 * @author Niels Keurentjes <niels.keurentjes@omines.com>
 */
class ArrayAdapter implements AdapterInterface
{
    /** @var array */
    private $data = [];

    /** @var PropertyAccessor */
    private $accessor;

    /**
     * {@inheritdoc}
     */
    public function configure(array $options)
    {
        $this->data = $options;
        $this->accessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * {@inheritdoc}
     */
    public function getData(DataTableState $state): ResultSetInterface
    {
        $length = $state->getLength();
        $map = [];

        $filteredData = $this->filterData($this->data, $state);

        $this->sortData($filteredData, $state);

        $page = $length > 0 ? array_slice($filteredData, $state->getStart(), $state->getLength()) : $this->data;

        foreach ($state->getDataTable()->getColumns() as $column) {
            unset($propertyPath);
            if (empty($propertyPath = $column->getPropertyPath()) && !empty($field = $column->getField() ?? $column->getName())) {
                $propertyPath = "[$field]";
            }
            if (null !== $propertyPath) {
                $map[$column->getName()] = $propertyPath;
            }
        }

        $data = iterator_to_array($this->processData($state, $page, $map));

        return new ArrayResultSet($data, count($this->data), count($filteredData));
    }

    protected function filterData($data, $state)
    {
        $filteredData = [];
        $searchColumns = $state->getSearchColumns();
        foreach ($this->data as $key => $row) {
            $ok = true;
            foreach ($row as $colName => $colValue) {
                if (isset($searchColumns[$colName]['search']) && ! empty($filterString = $searchColumns[$colName]['search'])) {
                    if (! preg_match("/$filterString/", (string)$colValue)) {
                        $ok = false;
                        break;
                    }
                }
            }
            if ($ok) {
                $filteredData[] = $row;
            }
        }
        return $filteredData;
    }

    protected function sortData(&$data, $state)
    {
        foreach ($state->getOrderBy() as $item) {
            $column = $item[0];
            $colName = $column->getName();
            $order = $item[1];
            usort($data, function($row1, $row2) use ($colName, $order) {
                $val1 = $row1[$colName];
                $val2 = $row2[$colName];
                if (is_numeric($val1) && is_numeric($val2)) {
                    if ($order == 'desc') {
                        return $val1 < $val2;
                    } else {
                        return $val1 > $val2;
                    }
                } else if (is_string($val1) && is_string($val2)) {
                    if ($order == 'desc') {
                        $val1 = strtolower($val1);
                        $val2 = strtolower($val2);
                        return strcmp($val1, $val2) > 0;
                    } else {
                        return strcmp($val1, $val2) < 0;
                    }
                }
            });
        }
    }

    /**
     * @param DataTableState $state
     * @param array $data
     * @param array $map
     * @return \Generator
     */
    protected function processData(DataTableState $state, array $data, array $map)
    {
        $transformer = $state->getDataTable()->getTransformer();
        $search = $state->getGlobalSearch() ?: '';
        foreach ($data as $result) {
            if ($row = $this->processRow($state, $result, $map, $search)) {
                if (null !== $transformer) {
                    $row = call_user_func($transformer, $row, $result);
                }
                yield $row;
            }
        }
    }

    /**
     * @param DataTableState $state
     * @param array $result
     * @param array $map
     * @param string $search
     * @return array|null
     */
    protected function processRow(DataTableState $state, array $result, array $map, string $search)
    {
        $row = [];
        $match = empty($search);
        foreach ($state->getDataTable()->getColumns() as $column) {
            $value = (!empty($propertyPath = $map[$column->getName()]) && $this->accessor->isReadable($result, $propertyPath)) ? $this->accessor->getValue($result, $propertyPath) : null;
            $value = $column->transform($value, $result);
            if (!$match) {
                $match = (false !== mb_stripos($value, $search));
            }
            $row[$column->getName()] = $value;
        }

        return $match ? $row : null;
    }
}
